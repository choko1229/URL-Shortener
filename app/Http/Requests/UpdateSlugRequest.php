<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ShortenerSettings;
use Illuminate\Foundation\Http\FormRequest;

/** カスタムスラッグの編集（requirements.md 2-4）。重複・予約語は SlugEditor で確認する */
final class UpdateSlugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $settings = $this->container->make(ShortenerSettings::class);

        return [
            'custom_slug' => [
                'required',
                'string',
                'min:'.$settings->customSlugMinLengthFor($this->user()),
                'max:'.$settings->customSlugMaxLength(),
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'custom_slug.required' => '新しいカスタムスラッグを入力してください。',
            'custom_slug.min' => 'カスタムスラッグは:min文字以上で入力してください。',
            'custom_slug.max' => 'カスタムスラッグは:max文字以内で入力してください。',
            'custom_slug.regex' => 'カスタムスラッグには半角英数字・ハイフン・アンダースコアのみ使えます。',
        ];
    }
}
