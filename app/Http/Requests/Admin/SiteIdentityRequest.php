<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** 管理画面「サイト設定」のサイト名など */
final class SiteIdentityRequest extends FormRequest
{
    private const NO_CONTROL_CHARACTERS = '/\A[^\x00-\x1F\x7F]*\z/';

    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', 'regex:'.self::NO_CONTROL_CHARACTERS],
            'tagline' => ['required', 'string', 'max:120', 'regex:'.self::NO_CONTROL_CHARACTERS],
            'operator' => ['required', 'string', 'max:60', 'regex:'.self::NO_CONTROL_CHARACTERS],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'サイト名を入力してください。',
            'name.max' => 'サイト名は:max文字以内で入力してください。',
            'tagline.required' => 'キャッチコピーを入力してください。',
            'tagline.max' => 'キャッチコピーは:max文字以内で入力してください。',
            'operator.required' => '運営者名を入力してください。',
            'operator.max' => '運営者名は:max文字以内で入力してください。',
            'name.regex' => 'サイト名に改行などの制御文字は使えません。',
            'tagline.regex' => 'キャッチコピーに改行などの制御文字は使えません。',
            'operator.regex' => '運営者名に改行などの制御文字は使えません。',
        ];
    }

    /** @return array{name: string, tagline: string, operator: string} */
    public function identity(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'tagline' => $this->string('tagline')->trim()->toString(),
            'operator' => $this->string('operator')->trim()->toString(),
        ];
    }
}
