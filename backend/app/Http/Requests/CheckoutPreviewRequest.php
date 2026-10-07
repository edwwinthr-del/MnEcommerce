<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*' => ['required', 'array:product_id,variant_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'min:1'], 'items.*.variant_id' => ['nullable', 'integer', 'min:1'], 'items.*.quantity' => ['required', 'integer', 'between:1,20'],
            'coupon_code' => ['nullable', 'string', 'max:50'], 'total' => ['prohibited'], 'subtotal' => ['prohibited'], 'discount_total' => ['prohibited'], 'shipping_total' => ['prohibited'], 'selling_price' => ['prohibited'], 'purchase_price' => ['prohibited'], 'order_status' => ['prohibited'], 'payment_status' => ['prohibited'], 'user_id' => ['prohibited'], 'admin_note' => ['prohibited'],
        ];
    }
}
