<?php

declare(strict_types=1);

namespace App\Http\Requests\Install;

use App\Installer\DatabaseCredentials;
use Illuminate\Foundation\Http\FormRequest;

final class DatabaseSettingsRequest extends FormRequest
{
    // 制御文字（改行など）を含まないこと。.env への書き込みを壊さないため
    private const NO_CONTROL_CHARACTERS = '/\A[^\x00-\x1F\x7F]*\z/';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'db_host' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9.\-]+\z/'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            // DSN の区切り文字（; など）を含められないよう文字種を限定する
            'db_database' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z0-9_\-]+\z/'],
            'db_username' => ['required', 'string', 'max:32', 'regex:'.self::NO_CONTROL_CHARACTERS],
            'db_password' => ['nullable', 'string', 'max:255', 'regex:'.self::NO_CONTROL_CHARACTERS],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'db_host.required' => 'ホスト名を入力してください。',
            'db_host.regex' => 'ホスト名には半角英数字・ドット・ハイフンのみ使えます。',
            'db_port.required' => 'ポート番号を入力してください。',
            'db_port.integer' => 'ポート番号は数字で入力してください。',
            'db_port.between' => 'ポート番号は 1〜65535 で入力してください。',
            'db_database.required' => 'データベース名を入力してください。',
            'db_database.regex' => 'データベース名には半角英数字・アンダースコア・ハイフンのみ使えます。',
            'db_database.max' => 'データベース名は:max文字以内で入力してください。',
            'db_username.required' => 'ユーザー名を入力してください。',
            'db_username.max' => 'ユーザー名は:max文字以内で入力してください。',
            'db_username.regex' => 'ユーザー名に改行などの制御文字は使えません。',
            'db_password.max' => 'パスワードは:max文字以内で入力してください。',
            'db_password.regex' => 'パスワードに改行などの制御文字は使えません。',
        ];
    }

    public function credentials(): DatabaseCredentials
    {
        return new DatabaseCredentials(
            host: $this->string('db_host')->toString(),
            port: $this->integer('db_port'),
            database: $this->string('db_database')->toString(),
            username: $this->string('db_username')->toString(),
            password: $this->string('db_password')->toString(),
        );
    }
}
