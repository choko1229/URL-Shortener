<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\SiteIcon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** 管理画面「サイト設定」のサービスアイコン（ロゴマーク・ファビコン） */
final class SiteIconRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'icon' => ['required', Rule::in([...array_keys(SiteIcon::BUILT_IN), SiteIcon::UPLOADED])],
            'file' => [
                Rule::requiredIf(fn (): bool => $this->string('icon')->toString() === SiteIcon::UPLOADED && ! $this->hasStoredImage()),
                'nullable',
                'image',
                'mimes:'.implode(',', SiteIcon::ALLOWED_EXTENSIONS),
                'max:'.SiteIcon::MAX_KILOBYTES,
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'icon.required' => 'アイコンを選んでください。',
            'icon.in' => 'アイコンを選んでください。',
            'file.required' => '画像を選んでください。',
            'file.image' => '画像ファイルを選んでください。',
            'file.mimes' => '画像は '.implode(' / ', SiteIcon::ALLOWED_EXTENSIONS).' のいずれかで選んでください。',
            'file.max' => '画像は :max KB 以内にしてください。',
        ];
    }

    /** すでにアップロード済みなら、選び直さなくても「画像」のままにできる */
    private function hasStoredImage(): bool
    {
        return app(SiteIcon::class)->path() !== null;
    }
}
