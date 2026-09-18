<?php

declare(strict_types=1);

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ShortUrl\GuestLinkDeleter;
use App\ViewModels\ViewerData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** 未ログインで発行した短縮URLを、削除用シークレットトークンで削除する（requirements.md 2-4） */
final class GuestDeletionController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('main.delete', [
            'viewer' => $user instanceof User ? ViewerData::fromUser($user) : ViewerData::guest(),
        ]);
    }

    public function destroy(Request $request, GuestLinkDeleter $deleter): RedirectResponse
    {
        $validated = $request->validate([
            'short_url' => ['required', 'string', 'max:2048'],
            'deletion_token' => ['required', 'string', 'max:255'],
        ], [
            'short_url.required' => '削除する短縮URLを入力してください。',
            'deletion_token.required' => '削除用トークンを入力してください。',
        ]);

        if (! $deleter->delete($validated['short_url'], trim($validated['deletion_token']))) {
            return back()
                ->withInput($request->only('short_url'))
                ->with('error', '短縮URLまたは削除用トークンが正しくありません。すでに削除されている可能性もあります。');
        }

        return redirect()->route('main.delete')->with('notice', '短縮URLを削除しました。このコードは今後使われません。');
    }
}
