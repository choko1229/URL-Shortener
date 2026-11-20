<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ShortDomainMode;
use App\Http\Controllers\Controller;
use App\Models\ShortDomain;
use App\Models\ShortUrl;
use App\Support\ShortDomains;
use App\ViewModels\ViewerData;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * 短縮URLに使う追加ドメインの管理（go.example.com など）。
 * ドメインごとに「ページと API」か「API のみ」かを選べる。DNS と Web サーバーの設定は別に必要。
 */
final class ShortDomainController extends Controller
{
    /** ホスト名（小文字の英数字・ハイフンのラベルをドットでつなぐ。ポートは含めない） */
    private const HOST_PATTERN = '/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\z/';

    public function index(Request $request, ShortDomains $domains): View
    {
        $extra = $domains->extra();
        $hosts = array_map(static fn (ShortDomain $domain): string => $domain->host, $extra);

        $counts = ShortUrl::withTrashed()
            ->whereIn('domain', [ShortDomains::PRIMARY, ...$hosts])
            ->selectRaw('domain, count(*) as aggregate')
            ->groupBy('domain')
            ->pluck('aggregate', 'domain')
            ->all();

        return view('dashboard.admin.domains', [
            'viewer' => ViewerData::fromUser($request->user()),
            'primaryHost' => $domains->primaryHost(),
            'domains' => $extra,
            'counts' => $counts,
            'modes' => ShortDomainMode::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['host' => ShortDomains::normalize((string) $request->input('host'))]);

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:253', 'regex:'.self::HOST_PATTERN, Rule::unique('short_domains', 'host'), $this->notReservedHost()],
            'mode' => ['required', Rule::enum(ShortDomainMode::class)],
        ], [
            'host.required' => 'ドメインを入力してください。',
            'host.max' => 'ドメインが長すぎます。',
            'host.regex' => 'ドメインは go.example.com のように入力してください（https:// やパス、ポート番号は付けません）。',
            'host.unique' => 'このドメインはすでに追加されています。',
        ]);

        $domain = ShortDomain::query()->create(['host' => $validated['host'], 'mode' => $validated['mode']]);

        Log::info('短縮URL用のドメインを追加しました。', ['host' => $domain->host, 'mode' => $domain->mode->value, 'user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', "{$domain->host} を追加しました。DNS と Web サーバーで、このドメインをこのアプリに向けてください。");
    }

    public function update(Request $request, ShortDomain $shortDomain): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::enum(ShortDomainMode::class)],
        ]);

        $shortDomain->mode = ShortDomainMode::from((string) $validated['mode']);
        $shortDomain->save();

        Log::info('短縮URL用ドメインの発行方法を変更しました。', ['host' => $shortDomain->host, 'mode' => $shortDomain->mode->value, 'user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', "{$shortDomain->host} を「{$shortDomain->mode->label()}」にしました。発行済みの短縮URLはそのまま使えます。");
    }

    /** 短縮URLが 1 件でもあるドメインは削除できない（削除すると開けなくなり、欠番の記録も意味を失うため） */
    public function destroy(Request $request, ShortDomain $shortDomain): RedirectResponse
    {
        $count = ShortUrl::withTrashed()->where('domain', $shortDomain->host)->count();

        if ($count > 0) {
            return back()->with('error', "{$shortDomain->host} には短縮URLが{$count}件（削除済みを含む）あるため削除できません。新しく発行させたくない場合は「API のみ」にしてください。");
        }

        $shortDomain->delete();

        Log::info('短縮URL用のドメインを削除しました。', ['host' => $shortDomain->host, 'user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', "{$shortDomain->host} を削除しました。");
    }

    /** このアプリがすでに使っているドメイン（メイン・ダッシュボード・API・リダイレクト確認）は追加できない */
    private function notReservedHost(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $used = array_map(
                static fn (mixed $host): string => ShortDomains::normalize((string) $host),
                [...array_values((array) config('shortener.domains')), app(ShortDomains::class)->primaryHost()],
            );

            if (in_array($value, $used, true)) {
                $fail('このドメインはサイトの画面（メイン・ダッシュボード・API・リダイレクト確認）ですでに使っています。別のドメインを入力してください。');
            }
        };
    }
}
