<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer' => ['sometimes', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'max:255'],
            'customer.email' => ['required_with:customer', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.address' => ['nullable', 'string', 'max:2000'],

            'delivery' => ['required', 'array'],
            'delivery.name' => ['required', 'string', 'max:255'],
            'delivery.email' => ['required', 'email', 'max:255'],
            'delivery.phone' => ['nullable', 'string', 'max:50'],
            'delivery.address' => ['required', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'lines.*.vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],

            'expected_total' => ['nullable', 'string', 'max:20'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
