<?php

namespace App\Http\Requests\Booking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trip_id' => 'required|integer|exists:trips,id',
            'seats_count' => 'required|integer|min:1|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'trip_id.required' => 'Trip ID is required.',
            'trip_id.integer' => 'Trip ID must be an integer.',
            'trip_id.exists' => 'Trip ID does not exist.',
            'seats_count.required' => 'Seats count is required.',
            'seats_count.integer' => 'Seats count must be an integer.',
            'seats_count.min' => 'Seats count must be at least 1.',
            'seats_count.max' => 'Seats count cannot exceed 10.',
        ];
    }
}
