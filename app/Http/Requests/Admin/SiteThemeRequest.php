<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ColorScheme;
use App\Enums\FontTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** 管理画面「サイト設定」の見た目（カラー・ダークモード・書体） */
final class SiteThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'color' => ['required', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'color_scheme' => ['required', Rule::enum(ColorScheme::class)],
            'font' => ['required', Rule::enum(FontTheme::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'color.required' => 'メインカラーを選んでください。',
            'color.regex' => 'メインカラーは #0f1a2b の形式（16進数6桁）で入力してください。',
            'color_scheme.required' => 'ダークモードの扱いを選んでください。',
            'font.required' => '書体を選んでください。',
        ];
    }

    /** @return array{color: string, color_scheme: string, font: string} */
    public function theme(): array
    {
        return [
            'color' => strtolower($this->string('color')->trim()->toString()),
            'color_scheme' => $this->string('color_scheme')->toString(),
            'font' => $this->string('font')->toString(),
        ];
    }
}
