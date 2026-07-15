<?php

namespace App\Http\Requests\Trip;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'nullable|date',
            'from' => 'nullable|string|max:255',
            'to' => 'nullable|string|max:255',
        ];
    }
}
