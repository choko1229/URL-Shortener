<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** 管理画面「全URL」の CSV インポート */
final class ImportLinksRequest extends FormRequest
{
    public const MAX_KILOBYTES = 2048;

    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Excel から保存した CSV は text/plain などで届くことがあるため、拡張子で判定する
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'CSV ファイルを選んでください。',
            'file.extensions' => 'CSV ファイル（.csv）を選んでください。',
            'file.max' => 'ファイルは :max KB 以内にしてください。',
        ];
    }

    public function csvPath(): string
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file->getRealPath() : '';
    }
}
