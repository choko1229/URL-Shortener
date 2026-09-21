<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AccessMode;
use App\Enums\OutsiderAction;
use App\Rules\NotOwnDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** 管理画面「サイト設定」の公開範囲（限定モード） */
final class SiteAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(AccessMode::class)],
            'outsider_action' => ['required', Rule::enum(OutsiderAction::class)],
            // 自サイトへ移動させると、移動先でもまた移動して繰り返しになるため受け付けない
            'redirect_url' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->string('outsider_action')->toString() === OutsiderAction::Redirect->value),
                'string',
                'max:2048',
                'url:http,https',
                new NotOwnDomain,
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mode.required' => '使える人を選んでください。',
            'outsider_action.required' => '許可されていない人がアクセスしたときの動作を選んでください。',
            'redirect_url.required' => '移動先の URL を入力してください。',
            'redirect_url.url' => '移動先は http:// または https:// から始まる URL を入力してください。',
        ];
    }

    /** @return array{mode: string, outsider_action: string, redirect_url: string|null} */
    public function access(): array
    {
        $url = $this->string('redirect_url')->trim()->toString();

        return [
            'mode' => $this->string('mode')->toString(),
            'outsider_action' => $this->string('outsider_action')->toString(),
            'redirect_url' => $url === '' ? null : $url,
        ];
    }
}
