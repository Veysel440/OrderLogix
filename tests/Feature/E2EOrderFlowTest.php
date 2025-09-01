<?php

use App\Models\Product;

uses()->group('e2e');

it('processes order to inventory.reserve and reserves stock', function () {
    declareTestTopology();

    Product::create(['sku'=>'ABC-1','name'=>'Kupa','price'=>100,'stock_qty'=>100]);
    $order = app(\App\Services\Orders\OrderService::class)->create([
        'items'=>[['sku'=>'ABC-1','qty'=>2]],
    ]);

    artisan('outbox:publish');

    artisan('mq:consume:orchestrator', ['--once' => true]);

    artisan('mq:consume:inventory', ['--once' => true]);

    $p = \App\Models\Product::where('sku','ABC-1')->firstOrFail();
    expect($p->reserved_qty)->toBe(2);

    $r = \App\Models\Reservation::where('order_id',$order->id)->first();
    expect($r)->not()->toBeNull();
    expect($r->status)->toBe('RESERVED');
});
