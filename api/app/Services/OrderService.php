<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    /** @return list<string> */
    public static function allowedTransitions(Order $order): array
    {
        return self::TRANSITIONS[$order->status] ?? [];
    }

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

    /**
     * Reserva o estoque e salva cópias do preço e endereço na mesma transação.
     * Se uma etapa falhar, pedido e estoque voltam ao estado anterior.
     *
     * @param  array{address_id: int, payment_method: string, items: list<array{product_id: int, quantity: int}>}  $data
     */
    public function create(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            User::lockForUpdate()->findOrFail($user->id);
            $cart = Cart::where('user_id', $user->id)->lockForUpdate()->first();
            $address = $user->addresses()->lockForUpdate()->findOrFail($data['address_id']);
            [$categories, $products] = $this->lockCatalog(array_column($data['items'], 'product_id'));
            $total = $this->calculateProductsTotal($data['items'], $products, $categories);
            $shipping = $this->validatedShippingTotal($total);
            $order = $user->orders()->create([
                'status' => 'PENDING',
                'payment_method' => $data['payment_method'],
                'products_total' => (string) $total,
                'shipping_total' => (string) $shipping,
                'total' => (string) $total->plus($shipping),
            ]);
            $order->shippingAddress()->create($address->only(['postal_code', 'street', 'number', 'complement', 'city', 'state']));
            $this->reserveItems($order, $data['items'], $products, $categories);
            $order->statusHistory()->create(['status' => 'PENDING', 'changed_at' => now()]);
            $cart?->items()->delete();

            return $this->details($order);
        }, 3);
    }

    /**
     * Bloqueia categorias antes de produtos, ordenados por ID, como nas escritas do catálogo.
     * Manter essa ordem reduz impasses entre pedidos e atualizações do catálogo.
     *
     * @param  list<int>  $productIds
     * @return array{Collection<int, Category>, Collection<int, Product>}
     */
    private function lockCatalog(array $productIds): array
    {
        $categoryIds = Product::whereIn('id', $productIds)->pluck('category_id')->unique();
        $categories = Category::whereIn('id', $categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        return [$categories, $products];
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Category>  $categories
     */
    private function calculateProductsTotal(array $items, Collection $products, Collection $categories): BigDecimal
    {
        $total = BigDecimal::zero()->toScale(2);
        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);
            $category = $product === null ? null : $categories->get($product->category_id);
            if ($product === null || ! $product->is_active || ! $category?->is_active) {
                throw ValidationException::withMessages(['items.'.$index.'.product_id' => 'The product is not available.']);
            }
            if ($product->stock < $item['quantity']) {
                throw ValidationException::withMessages(['items.'.$index.'.quantity' => 'Insufficient stock.']);
            }
            $total = $total->plus(BigDecimal::of($product->price)->multipliedBy($item['quantity']));
        }

        return $total;
    }

    private function validatedShippingTotal(BigDecimal $productsTotal): BigDecimal
    {
        $shipping = BigDecimal::of((string) config('shop.shipping_total'))->toScale(2);
        if ($shipping->isNegative() || $productsTotal->plus($shipping)->isGreaterThan('99999999.99')) {
            throw ValidationException::withMessages(['items' => 'The order total or shipping amount is outside the allowed limits.']);
        }

        return $shipping;
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Category>  $categories
     */
    private function reserveItems(Order $order, array $items, Collection $products, Collection $categories): void
    {
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $order->items()->create([
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'product_name_snapshot' => $product->name,
                'category_name_snapshot' => $categories->get($product->category_id)->name,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
                'unit_cost' => $product->cost,
            ]);
            $this->stock->move($product, 'OUTBOUND', $item['quantity'], 'Pedido #'.$order->id);
        }
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
                throw new ConflictHttpException('Only orders awaiting payment can be cancelled.');
            }
            $this->transition($order, 'CANCELLED');
        }, 3);
    }

    public function pay(User $user, int $id): Order
    {
        return DB::transaction(function () use ($user, $id): Order {
            $order = Order::lockForUpdate()->findOrFail($id);
            abort_unless($order->user_id === $user->id, 403);
            if ($order->status !== 'PENDING') {
                throw new ConflictHttpException('Only orders awaiting payment can be paid.');
            }
            $this->transition($order, 'PAID');

            return $this->details($order);
        }, 3);
    }

    private function transition(Order $order, string $status): void
    {
        if (! array_key_exists($status, self::TRANSITIONS)) {
            throw new ConflictHttpException('Status transition is not allowed.');
        }
        if ($order->status === $status) {
            return;
        }
        if (! in_array($status, self::allowedTransitions($order), true)) {
            throw new ConflictHttpException('Status transition is not allowed.');
        }
        if ($status === 'CANCELLED') {
            $this->restoreStock($order);
        }
        $order->update(['status' => $status]);
        $order->statusHistory()->create(['status' => $status, 'changed_at' => now()]);
    }

    private function restoreStock(Order $order): void
    {
        $items = $order->items()->orderBy('product_id')->get();
        $products = Product::whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($items as $item) {
            $product = $products->get($item->product_id);
            if ($product === null) {
                throw new ConflictHttpException('The product was removed; stock must be reconciled before cancellation.');
            }
            $this->stock->move($product, 'INBOUND', $item->quantity, 'Cancelamento do pedido #'.$order->id);
        }
    }
}
