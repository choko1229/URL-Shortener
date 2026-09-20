<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PreviewMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** 発行済みリンクの「共有時のカード」の変更 */
final class UpdatePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'preview_mode' => ['required', Rule::enum(PreviewMode::class)],
            'preview_title' => ['exclude_unless:preview_mode,'.PreviewMode::Custom->value, 'required', 'string', 'max:120'],
            'preview_description' => ['exclude_unless:preview_mode,'.PreviewMode::Custom->value, 'nullable', 'string', 'max:300'],
            'preview_image_url' => ['exclude_unless:preview_mode,'.PreviewMode::Custom->value, 'nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'preview_mode.required' => '表示のしかたを選んでください。',
            'preview_title.required' => 'カードのタイトルを入力してください。',
            'preview_title.max' => 'カードのタイトルは:max文字以内で入力してください。',
            'preview_description.max' => 'カードの説明は:max文字以内で入力してください。',
            'preview_image_url.url' => 'カードの画像URLは http:// または https:// から始まるURLを入力してください。',
        ];
    }

    /**
     * モデルに保存する値（「内容を指定する」以外では入力を残さない）。
     * attributes() は検証メッセージ用に予約されているため別名にしている
     *
     * @return array<string, mixed>
     */
    public function previewAttributes(): array
    {
        $mode = PreviewMode::from((string) $this->validated('preview_mode'));
        $custom = $mode === PreviewMode::Custom;

        return [
            'preview_mode' => $mode,
            'preview_title' => $custom ? $this->stringOrNull('preview_title') : null,
            'preview_description' => $custom ? $this->stringOrNull('preview_description') : null,
            'preview_image_url' => $custom ? $this->stringOrNull('preview_image_url') : null,
        ];
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
