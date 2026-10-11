<?php

namespace App\Http\Requests\Admin;

use App\Models\DiscountRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountRuleRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(DiscountRule::types())],
            'discount_type' => ['required', Rule::in(DiscountRule::discountTypes())],
            'value' => $this->valueRules(),
            'min_quantity' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf($this->input('type') === DiscountRule::TYPE_QUANTITY),
            ],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                Rule::requiredIf($this->input('type') === DiscountRule::TYPE_CATEGORY),
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => $this->endsAtRules(),
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['min_quantity', 'vendor_id', 'product_id', 'category_id', 'starts_at', 'ends_at'] as $field) {
            if ($this->input($field) === '') {
                $normalized[$field] = null;
            }
        }

        if ($this->input('type') === DiscountRule::TYPE_CATEGORY) {
            $normalized['min_quantity'] = null;
            $normalized['product_id'] = null;
        }

        if ($this->input('type') === DiscountRule::TYPE_QUANTITY) {
            $normalized['category_id'] = null;
        }

        $this->merge($normalized);
    }

    /**
     * @return array<int, mixed>
     */
    private function valueRules(): array
    {
        if ($this->input('discount_type') === DiscountRule::DISCOUNT_TYPE_PERCENTAGE) {
            return ['required', 'numeric', 'gt:0', 'lte:100'];
        }

        return ['required', 'numeric', 'gt:0'];
    }

    /**
     * @return array<int, mixed>
     */
    private function endsAtRules(): array
    {
        return array_values(array_filter([
            'nullable',
            'date',
            $this->filled('starts_at') ? 'after:starts_at' : null,
        ]));
    }
}
