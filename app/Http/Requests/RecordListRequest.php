<?php

namespace App\Http\Requests;

use App\Http\Controllers\TrackingController;
use App\Models\BookingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordListRequest extends FormRequest
{
    public const PAGE_SIZES = [10, 25, 50, 100];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['per_page' => ['sometimes', 'integer', Rule::in(self::PAGE_SIZES)], 'page' => 'sometimes|integer|min:1', 'q' => 'nullable|string|max:100', 'type' => ['sometimes', Rule::in(array_keys(BookingOption::TYPES))], 'status' => ['nullable', Rule::in(array_merge(TrackingController::STATUSES, ['all', 'enabled', 'disabled']))]];
        foreach (['number', 'customer', 'vendor', 'origin', 'destination', 'service', 'email', 'phone', 'location'] as $field) {
            $rules[$field] = 'nullable|string|max:100';
        }
        foreach (['date', 'delivered'] as $prefix) {
            $rules[$prefix.'_from'] = ['nullable', 'date_format:Y-m-d'];
            $rules[$prefix.'_to'] = ['nullable', 'date_format:Y-m-d', Rule::when($this->filled($prefix.'_from'), 'after_or_equal:'.$prefix.'_from')];
        }

        return $rules;
    }

    public function pageSize(): int
    {
        return (int) ($this->validated()['per_page'] ?? 10);
    }
}
