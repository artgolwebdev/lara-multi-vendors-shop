<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'description' => ['nullable', 'string', 'max:20000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'quantity' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'offers' => ['required', 'array', 'min:1'],
            'offers.*.vendor_id' => ['required', 'integer', 'distinct', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'offers.*.price' => ['required', 'numeric', 'gt:0'],
            'offers.*.vendor_sku' => ['nullable', 'string', 'max:100'],
            'offers.*.is_active' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
