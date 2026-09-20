<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** 管理画面「サイト設定」の固定ページ（Markdown） */
final class SitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100', 'regex:/\A[^\x00-\x1F\x7F]*\z/'],
            'body' => ['required', 'string', 'max:50000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'ページのタイトルを入力してください。',
            'title.max' => 'ページのタイトルは:max文字以内で入力してください。',
            'title.regex' => 'ページのタイトルに改行などの制御文字は使えません。',
            'body.required' => '本文を入力してください（「テンプレートを入力」から下書きを読み込めます）。',
            'body.max' => '本文は:max文字以内で入力してください。',
        ];
    }
}
