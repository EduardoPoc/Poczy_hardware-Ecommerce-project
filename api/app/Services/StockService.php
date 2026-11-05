<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function listMovements(int $productId, int $perPage = 20): LengthAwarePaginator
    {
        return Product::findOrFail($productId)->stockMovements()->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /** @param array{type: string, quantity: int, reason?: ?string} $data */
    public function create(int $productId, array $data): StockMovement
    {
        return DB::transaction(fn (): StockMovement => $this->move(
            Product::lockForUpdate()->findOrFail($productId),
            $data['type'], (int) $data['quantity'], $data['reason'] ?? null,
        ), 3);
    }

    /** The caller must hold the product row lock inside a transaction. */
    public function move(Product $product, string $type, int $quantity, ?string $reason = null): StockMovement
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Stock changes require a transaction and a product row lock.');
        }
        if (! in_array($type, ['INBOUND', 'OUTBOUND', 'ADJUSTMENT'], true) || $quantity === 0 ||
            $quantity < -2147483647 || $quantity > 2147483647 ||
            ($type !== 'ADJUSTMENT' && $quantity < 0)) {
            throw ValidationException::withMessages(['quantity' => 'Informe uma quantidade positiva para entradas e saídas, ou uma variação diferente de zero para ajustes.']);
        }
        $stock = $product->stock + ($type === 'OUTBOUND' ? -$quantity : $quantity);
        if ($stock < 0 || $stock > 2147483647) {
            throw ValidationException::withMessages(['stock' => 'Estoque insuficiente ou saldo acima do limite permitido.']);
        }
        $product->update(['stock' => $stock, 'is_active' => $product->is_active && $stock >= 1]);

        return $product->stockMovements()->create([
            'type' => $type, 'quantity' => $quantity, 'reason' => $reason,
        ]);
    }
}
