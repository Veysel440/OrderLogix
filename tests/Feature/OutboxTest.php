<?php


use App\Models\Product;

it('creates outbox on order', function () {
    Product::create(['sku'=>'T1','name'=>'Test','price'=>10,'stock_qty'=>10]);
    $order = app(\App\Services\Orders\OrderService::class)->create([
        'items'=>[['sku'=>'T1','qty'=>1]],
    ]);
    expect(\DB::table('outbox_events')->where('aggregate_id',$order->id)->count())->toBeGreaterThan(0);
});
