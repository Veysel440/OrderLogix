<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\{Order, OrderItem, OutboxEvent, Product};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrderService
{
    /**
     * @param array{user_id?:int, currency?:string, items: array<int, array{sku:string, qty:int}>} $data
     */
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new \InvalidArgumentException('items required');
            }

            $bySku = collect($items)->keyBy('sku');
            $products = Product::query()->whereIn('sku', array_keys($bySku->all()))->get();
            if ($products->count() !== $bySku->count()) {
                throw new \RuntimeException('unknown sku detected');
            }

            $order = new Order([
                'user_id' => $data['user_id'] ?? null,
                'status'  => OrderStatus::PENDING,
                'currency'=> $data['currency'] ?? 'TRY',
                'total'   => 0,
            ]);
            $order->save();

            $total = 0;
            foreach ($products as $p) {
                $qty = (int) $bySku[$p->sku]['qty'];
                $line = $p->price * $qty;
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $p->id,
                    'qty'        => $qty,
                    'unit_price' => $p->price,
                    'line_total' => $line,
                ]);
                $total += $line;
            }
            $order->update(['total' => $total]);

            $eventId = (string) Str::uuid();
            OutboxEvent::create([
                'id' => $eventId,
                'type' => 'order.placed',
                'payload' => [
                    'order_id' => $order->id,
                    'user_id'  => $order->user_id,
                    'total'    => (float) $order->total,
                    'currency' => $order->currency,
                    'items'    => $order->items()->with('product:id,sku,name')->get()
                        ->map(fn($i) => [
                            'sku' => $i->product->sku,
                            'qty' => (int) $i->qty,
                            'unit_price' => (float) $i->unit_price,
                            'line_total' => (float) $i->line_total,
                        ])->all(),
                ],
                'occurred_at' => now(),
                'aggregate_type' => 'order',
                'aggregate_id' => (string) $order->id,
            ]);

            return $order->fresh(['items.product']);
        });
    }
}
