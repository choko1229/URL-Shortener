<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\DashboardPageBuilder;
use App\Support\LinkSort;
use App\ViewModels\IssuedLinkData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use LogicException;

/** ダッシュボードのトップ（発行フォーム・統計・発行履歴） */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardPageBuilder $builder): View
    {
        $user = $request->user();

        if (! $user instanceof User) {
            // auth ミドルウェア配下でのみ呼ばれる想定
            throw new LogicException('ダッシュボードには認証済みユーザーが必要です。');
        }

        return view('dashboard.index', [
            'page' => $builder->build($user, CarbonImmutable::now(), IssuedLinkData::fromSession($request->session()), LinkSort::fromRequest($request)),
        ]);
    }
}
