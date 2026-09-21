<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\ShortUrl\SlugAvailability;
use App\Support\SitePaths;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** 管理画面「サイト設定」の固定ページの URL */
final class SitePathsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [];
        foreach (array_keys(SitePaths::PAGES) as $page) {
            $rules["paths.{$page}"] = ['bail', 'required', 'string', 'regex:'.SitePaths::PATTERN];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $messages = [];
        foreach (SitePaths::PAGES as $page => $definition) {
            $messages["paths.{$page}.required"] = "{$definition['label']}の URL を入力してください。";
            $messages["paths.{$page}.regex"] = "{$definition['label']}の URL は、半角英数字・ハイフン・アンダースコアで 1〜20 文字にしてください（/ は使えません）。";
        }

        return $messages;
    }

    /**
     * 形式が正しい場合だけ、ほかのページ・短縮URL・画面と重ならないかを確かめる
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $availability = $this->container->make(SlugAvailability::class);
            // 入れ替え（terms と privacy を交換するなど）もできるよう、ページ同士の現在のルートは重なってよい
            $pageRoutes = array_map(static fn (string $page): string => "main.{$page}", array_keys(SitePaths::PAGES));
            $seen = [];

            foreach ($this->paths() as $page => $path) {
                $label = SitePaths::PAGES[$page]['label'];
                $key = mb_strtolower($path);

                if (isset($seen[$key])) {
                    $validator->errors()->add("paths.{$page}", "{$label}の URL（/{$path}）が{$seen[$key]}と同じです。");

                    continue;
                }
                $seen[$key] = $label;

                if ($availability->isUsedByActiveLink($path)) {
                    $validator->errors()->add("paths.{$page}", "/{$path} はすでに短縮URLとして使われています。別の語にするか、先にその短縮URLを削除・変更してください。");

                    continue;
                }

                $route = $availability->routeAt($path);
                if ($route !== null && $route !== 'main.short-link.show' && ! in_array($route, $pageRoutes, true)) {
                    $validator->errors()->add("paths.{$page}", "/{$path} はサイトのほかの機能で使っているため指定できません。");
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function paths(): array
    {
        $paths = [];
        foreach (array_keys(SitePaths::PAGES) as $page) {
            $paths[$page] = trim((string) $this->input("paths.{$page}", ''));
        }

        return $paths;
    }
}
