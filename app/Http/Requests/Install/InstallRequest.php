<?php

declare(strict_types=1);

namespace App\Http\Requests\Install;

use App\Installer\DatabaseCredentials;
use App\Installer\SiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** セットアップ画面の入力（DB 接続情報と、自動入力されたドメイン） */
final class InstallRequest extends FormRequest
{
    // 制御文字（改行など）を含まないこと。.env への書き込みを壊さないため
    private const NO_CONTROL_CHARACTERS = '/\A[^\x00-\x1F\x7F]*\z/';

    /** ホスト名（ポート・スキームなし）。localhost などの単一ラベルも許可 */
    private const HOST_PATTERN = '/\A(?=.{1,253}\z)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/';

    /** @var array<string, string> */
    public const DOMAIN_FIELDS = [
        'main_domain' => 'メインドメイン',
        'dashboard_domain' => 'ダッシュボードのドメイン',
        'api_domain' => 'API のドメイン',
        'redirect_domain' => 'リダイレクト確認のドメイン',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (array_keys(self::DOMAIN_FIELDS) as $field) {
            $value = $this->input($field);
            $normalized[$field] = is_string($value) ? strtolower(trim($value)) : $value;
        }

        $this->merge($normalized);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [
            'db_host' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9.\-]+\z/'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            // DSN の区切り文字（; など）を含められないよう文字種を限定する
            'db_database' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z0-9_\-]+\z/'],
            'db_username' => ['required', 'string', 'max:32', 'regex:'.self::NO_CONTROL_CHARACTERS],
            'db_password' => ['nullable', 'string', 'max:255', 'regex:'.self::NO_CONTROL_CHARACTERS],
            'confirmed' => ['nullable', 'boolean'],
        ];

        foreach (array_keys(self::DOMAIN_FIELDS) as $field) {
            $rules[$field] = ['required', 'string', 'regex:'.self::HOST_PATTERN];
        }

        return $rules;
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [
            static function (Validator $validator): void {
                $domains = array_filter(
                    array_intersect_key($validator->getData(), self::DOMAIN_FIELDS),
                    'is_string',
                );

                foreach (array_count_values($domains) as $domain => $count) {
                    if ($count > 1) {
                        $field = (string) array_search((string) $domain, $domains, true);
                        $validator->errors()->add($field, '4つのドメインにはそれぞれ異なる値を指定してください。');
                    }
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $messages = [
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

        foreach (self::DOMAIN_FIELDS as $field => $label) {
            $messages["{$field}.required"] = "{$label}を入力してください。";
            $messages["{$field}.regex"] = "{$label}は「example.com」の形式で入力してください（https:// やポート番号は不要です）。";
        }

        return $messages;
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

    public function siteSettings(bool $secure): SiteSettings
    {
        return new SiteSettings(
            mainDomain: $this->string('main_domain')->toString(),
            dashboardDomain: $this->string('dashboard_domain')->toString(),
            apiDomain: $this->string('api_domain')->toString(),
            redirectDomain: $this->string('redirect_domain')->toString(),
            secure: $secure,
        );
    }
}
