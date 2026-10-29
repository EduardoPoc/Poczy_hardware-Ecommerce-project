<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function listMovements(int $productId, int $perPage): LengthAwarePaginator
    {
        return Product::findOrFail($productId)->stockMovements()->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /** @param array{type: string, change: int, reason: string} $data */
    public function create(int $productId, array $data): StockMovement
    {
        return DB::transaction(fn (): StockMovement => $this->move(
            Product::lockForUpdate()->findOrFail($productId),
            $data['type'], (int) $data['change'], $data['reason'],
        ), 3);
    }

    /** The caller must hold the product row lock inside a transaction. */
    public function move(Product $product, string $type, int $change, string $reason): StockMovement
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Stock changes require a transaction and a product row lock.');
        }
        if (! in_array($type, ['INBOUND', 'OUTBOUND', 'ADJUSTMENT'], true) || $change === 0 ||
            ($type === 'INBOUND' && $change < 0) || ($type === 'OUTBOUND' && $change > 0)) {
            throw ValidationException::withMessages(['change' => 'A variação deve ser positiva na entrada e negativa na saída.']);
        }
        $stock = $product->stock + $change;
        if ($stock < 0 || $stock > 2147483647) {
            throw ValidationException::withMessages(['stock' => 'Estoque insuficiente ou saldo acima do limite permitido.']);
        }
        $product->update(['stock' => $stock]);

        return $product->stockMovements()->create([
            'type' => $type, 'quantity' => abs($change), 'change' => $change, 'reason' => $reason,
        ]);
    }
}
