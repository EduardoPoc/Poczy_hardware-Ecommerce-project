<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderService
{
    private const TRANSITIONS = [
        'PENDING' => ['PAID', 'CANCELLED'],
        'PAID' => ['SHIPPED'],
        'SHIPPED' => ['DELIVERED'],
        'DELIVERED' => [],
        'CANCELLED' => [],
    ];

    public function __construct(private StockService $stock) {}

    private function userOrders(User $user): HasMany
    {
        return $user->orders()->withCount('items')->withSum('items as items_quantity', 'quantity');
    }

    public function listUserOrders(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->userOrders($user)->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();
    }

    public function getUserOrder(User $user, string $id): Order
    {
        return $this->details($this->accessibleOrders($user)->findOrFail($id));
    }

    private function accessibleOrders(User $user): Builder
    {
        return Order::query()->when($user->role !== 'ADMIN', fn (Builder $query): Builder => $query->where('user_id', $user->id));
    }

    public function listAll(int $perPage = 20): LengthAwarePaginator
    {
        return Order::withCount('items')->withSum('items as items_quantity', 'quantity')
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): Order
    {
        return $this->details(Order::findOrFail($id));
    }

    private function details(Order $order): Order
    {
        $order->loadCount('items')->loadSum('items as items_quantity', 'quantity')->load([
            'items' => fn (HasMany $query): HasMany => $query->orderBy('id'),
            'shippingAddress',
            'statusHistory' => fn (HasMany $query): HasMany => $query->orderBy('changed_at')->orderBy('id'),
        ]);
        $order->items_quantity = (int) ($order->items_quantity ?? 0);
        foreach ($order->items as $item) {
            $item->setAttribute('line_total', (string) BigDecimal::of($item->unit_price)->multipliedBy($item->quantity)->toScale(2));
        }

        return $order;
    }

    /** @param array{address_id: int, payment_method: string, items: list<array{product_id: int, quantity: int}>} $data */
    public function create(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $address = $user->addresses()->lockForUpdate()->findOrFail($data['address_id']);
            $ids = array_column($data['items'], 'product_id');
            $categoryIds = Product::whereIn('id', $ids)->pluck('category_id')->unique();
            $categories = Category::whereIn('id', $categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = BigDecimal::zero()->toScale(2);
            foreach ($data['items'] as $index => $item) {
                $product = $products->get($item['product_id']);
                $category = $product === null ? null : $categories->get($product->category_id);
                if ($product === null || ! $product->is_active || ! $category?->is_active) {
                    throw ValidationException::withMessages(['items.'.$index.'.product_id' => 'O produto não está disponível.']);
                }
                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items.'.$index.'.quantity' => 'Estoque insuficiente.']);
                }
                $total = $total->plus(BigDecimal::of($product->price)->multipliedBy($item['quantity']));
            }
            $shipping = BigDecimal::of((string) config('shop.shipping_total'))->toScale(2);
            if ($shipping->isNegative() || $total->plus($shipping)->isGreaterThan('99999999.99')) {
                throw ValidationException::withMessages(['items' => 'O total do pedido ou frete está fora dos limites permitidos.']);
            }
            $order = $user->orders()->create([
                'status' => 'PENDING', 'payment_method' => $data['payment_method'],
                'products_total' => (string) $total, 'shipping_total' => (string) $shipping,
                'total' => (string) $total->plus($shipping),
            ]);
            $order->shippingAddress()->create($address->only(['postal_code', 'street', 'number', 'complement', 'city', 'state']));
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $order->items()->create([
                    'product_id' => $product->id, 'category_id' => $product->category_id,
                    'product_name_snapshot' => $product->name,
                    'category_name_snapshot' => $categories->get($product->category_id)->name,
                    'quantity' => $item['quantity'], 'unit_price' => $product->price, 'unit_cost' => $product->cost,
                ]);
                $this->stock->move($product, 'OUTBOUND', $item['quantity'], 'Pedido #'.$order->id);
            }
            $order->statusHistory()->create(['status' => 'PENDING', 'changed_at' => now()]);

            return $this->details($order);
        }, 3);
    }

    public function updateStatus(int $id, string $status): Order
    {
        return DB::transaction(function () use ($id, $status): Order {
            $order = Order::lockForUpdate()->findOrFail($id);
            $this->transition($order, $status);

            return $this->details($order);
        }, 3);
    }

    public function cancel(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id): void {
            $order = $this->accessibleOrders($user)->lockForUpdate()->findOrFail($id);
            if ($order->status === 'CANCELLED') {
                return;
            }
            if ($order->status !== 'PENDING') {
                throw new ConflictHttpException('Somente pedidos aguardando pagamento podem ser cancelados.');
            }
            $this->transition($order, 'CANCELLED');
        }, 3);
    }

    public function pay(User $user, int $id): Order
    {
        return DB::transaction(function () use ($user, $id): Order {
            $order = $this->accessibleOrders($user)->lockForUpdate()->findOrFail($id);
            $this->transition($order, 'PAID');

            return $this->details($order);
        }, 3);
    }

    private function transition(Order $order, string $status): void
    {
        if (! array_key_exists($status, self::TRANSITIONS)) {
            throw new ConflictHttpException('Transição de status não permitida.');
        }
        if ($order->status === $status) {
            return;
        }
        if (! in_array($status, self::TRANSITIONS[$order->status] ?? [], true)) {
            throw new ConflictHttpException('Transição de status não permitida.');
        }
        if ($status === 'CANCELLED') {
            $items = $order->items()->orderBy('product_id')->get();
            $products = Product::whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                if ($product === null) {
                    throw new ConflictHttpException('O produto foi removido; o estoque exige conciliação antes do cancelamento.');
                }
                $this->stock->move($product, 'INBOUND', $item->quantity, 'Cancelamento do pedido #'.$order->id);
            }
        }
        $order->update(['status' => $status]);
        $order->statusHistory()->create(['status' => $status, 'changed_at' => now()]);
    }
}
