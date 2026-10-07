<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PlaceOrderRequest extends CheckoutPreviewRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return parent::rules() + [
            'idempotency_key' => ['required', 'uuid'], 'customer_name' => ['required', 'string', 'max:120'], 'customer_email' => ['required', 'email:rfc', 'max:254'],
            'customer_phone' => ['required', 'string', 'regex:/^\\+?[0-9 ()-]{7,25}$/'], 'shipping_address' => ['required', 'string', 'max:240'], 'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_postal_code' => ['required', 'string', 'regex:/^[0-9]{5}$/'], 'shipping_country' => ['required', Rule::in(['ME'])],
            'payment_method' => ['required', Rule::in(['cash_on_delivery', 'bank_transfer'])], 'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
