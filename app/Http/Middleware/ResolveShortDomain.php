<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ShortDomains;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 追加ドメイン（go.example.com など）で開かれた短縮URLのドメインを確かめる。
 * 管理画面で登録していないホストは 404 にする。
 * コントローラの引数がずれないよう、ルートのホスト名パラメータは取り除き、リクエストの属性に入れて渡す。
 */
final class ResolveShortDomain
{
    public const ATTRIBUTE = 'short_domain';

    public const PARAMETER = 'shortHost';

    public function __construct(private readonly ShortDomains $domains) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $host = $route?->parameter(self::PARAMETER);

        $domain = is_string($host) ? $this->domains->find($host) : null;
        abort_if($domain === null, Response::HTTP_NOT_FOUND);

        $route->forgetParameter(self::PARAMETER);
        $request->attributes->set(self::ATTRIBUTE, $domain->host);

        return $next($request);
    }

    /** 短縮URLを開いたドメイン（メインドメインなら空文字） */
    public static function domainOf(Request $request): string
    {
        $domain = $request->attributes->get(self::ATTRIBUTE);

        return is_string($domain) ? $domain : ShortDomains::PRIMARY;
    }
}
