<?php

namespace App\Http\Controllers;

use App\Services\Orders\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $r, OrderService $svc)
    {
        $data = $r->validate([
            'user_id' => ['nullable','integer'],
            'currency'=> ['nullable','string','size:3'],
            'items'   => ['required','array','min:1'],
            'items.*.sku' => ['required','string'],
            'items.*.qty' => ['required','integer','min:1'],
        ]);
        $order = $svc->create($data);
        return response()->json($order, 201);
    }
}
