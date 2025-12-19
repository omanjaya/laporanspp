<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RekonGetValueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'field' => [
                'required',
                'string',
                'in:nama_siswa,dana_masyarakat,jum_tagihan,no_bukti'
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
            'field.required' => 'Field name is required.',
            'field.in' => 'Invalid field specified. Allowed fields: nama_siswa, dana_masyarakat, jum_tagihan, no_bukti',
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
            'field' => 'field name',
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
            'field' => strtolower(trim($this->field)),
        ]);
    }
}