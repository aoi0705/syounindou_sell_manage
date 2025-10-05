<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderImportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'raw_body' => ['required', 'string', 'min:50'],
            'note'     => ['nullable','string','max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'raw_body.required' => 'メール本文を貼り付けてください。',
            'raw_body.min' => '本文が短すぎます。正しい受注通知を貼り付けてください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'raw_body' => 'メール本文',
            'note'     => '備考', // ★追加
        ];
    }
}
