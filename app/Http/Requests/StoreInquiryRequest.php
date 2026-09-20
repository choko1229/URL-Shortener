<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** お問い合わせフォームの入力 */
final class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:64'],
            'reply_to' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            // reCAPTCHA v3 のトークン（検証はコントローラで行う）
            'recaptcha_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.max' => 'お名前は:max文字以内で入力してください。',
            'reply_to.max' => '返信先は:max文字以内で入力してください。',
            'message.required' => 'お問い合わせの内容を入力してください。',
            'message.min' => 'お問い合わせの内容は:min文字以上で入力してください。',
            'message.max' => 'お問い合わせの内容は:max文字以内で入力してください。',
        ];
    }
}
