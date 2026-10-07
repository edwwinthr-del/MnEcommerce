<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutPreviewRequest;
use App\Http\Requests\PlaceOrderRequest;
use App\Services\CheckoutService;
use App\Services\PricingService;

class CheckoutController extends Controller
{
    public function preview(CheckoutPreviewRequest $request, PricingService $pricing)
    {
        return response()->json(['data' => $pricing->publicQuote($pricing->quote($request->validated()))]);
    }

    public function store(PlaceOrderRequest $request, CheckoutService $checkout)
    {
        $input = $request->safe()->except('idempotency_key');
        [$order,$created] = $checkout->create($input, $request->validated('idempotency_key'), $request->user('sanctum')?->id);

        return response()->json(['data' => $order->only(['order_number', 'order_status', 'payment_status', 'total', 'currency', 'customer_email'])], $created ? 201 : 200);
    }
}
