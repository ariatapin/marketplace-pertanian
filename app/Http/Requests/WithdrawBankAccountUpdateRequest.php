<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawBankAccountUpdateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'bank_name' => [
                'nullable',
                'string',
                'max:120',
                'required_with:account_number,account_holder',
            ],
            'account_number' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[0-9\\s\\-]+$/',
                'required_with:bank_name,account_holder',
            ],
            'account_holder' => [
                'nullable',
                'string',
                'max:255',
                'required_with:bank_name,account_number',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_name.required_with' => 'Lengkapi semua data rekening: nama bank, nomor rekening, dan nama pemilik rekening.',
            'account_number.required_with' => 'Lengkapi semua data rekening: nama bank, nomor rekening, dan nama pemilik rekening.',
            'account_holder.required_with' => 'Lengkapi semua data rekening: nama bank, nomor rekening, dan nama pemilik rekening.',
            'account_number.regex' => 'Nomor rekening hanya boleh berisi angka, spasi, dan tanda strip (-).',
        ];
    }
}
