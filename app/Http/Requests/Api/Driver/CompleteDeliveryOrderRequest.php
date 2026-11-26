<?php

namespace App\Http\Requests\Api\Driver;

use App\Models\DeliveryOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteDeliveryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDriver() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proof_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'received_by' => ['nullable', Rule::in(array_keys(DeliveryOrder::RECEIVED_LABELS))],
            'received_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
