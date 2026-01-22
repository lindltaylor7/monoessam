<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubdealershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:20|unique:subdealerships,ruc,' . $this->route('subdealership')->id,
            'fiscal_address' => 'nullable|string|max:500',
            'legal_address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:subdealerships,email,' . $this->route('subdealership')->id,
            'dealership_id' => 'required|exists:dealerships,id',
        ];
    }
}