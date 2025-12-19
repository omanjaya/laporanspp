<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RekonSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // This request should only be used for validated API requests
        return $this->input('api_key_validated', false) || session()->has('authenticated');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'sekolah' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z0-9_\-\s]+$/'
            ],
            'tahun' => [
                'required',
                'integer',
                'min:2000',
                'max:' . (date('Y') + 5)
            ],
            'bulan' => [
                'required',
                'integer',
                'min:1',
                'max:12'
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'sekolah.required' => 'School name is required.',
            'sekolah.regex' => 'School name contains invalid characters.',
            'sekolah.max' => 'School name must not exceed 100 characters.',
            'tahun.required' => 'Year is required.',
            'tahun.min' => 'Year must be 2000 or later.',
            'tahun.max' => 'Year cannot be more than 5 years in the future.',
            'bulan.required' => 'Month is required.',
            'bulan.min' => 'Month must be between 1 and 12.',
            'bulan.max' => 'Month must be between 1 and 12.',
            'per_page.max' => 'Maximum 100 records per page.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'sekolah' => 'school',
            'tahun' => 'year',
            'bulan' => 'month',
            'per_page' => 'per page',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sekolah' => trim($this->sekolah),
            'tahun' => (int) $this->tahun,
            'bulan' => (int) $this->bulan,
        ]);
    }
}