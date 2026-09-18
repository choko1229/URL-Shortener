<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReservedWordCategory;
use App\Http\Controllers\Controller;
use App\Models\ReservedWord;
use App\ViewModels\ViewerData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/** 予約語の管理（requirements.md 2-2: DB 管理とし、ダッシュボードから追加可能） */
final class ReservedWordController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.admin.reserved-words', [
            'viewer' => ViewerData::fromUser($request->user()),
            'groups' => ReservedWord::query()->orderBy('word')->get()->groupBy(static fn (ReservedWord $word): string => $word->category->value),
            'categories' => ReservedWordCategory::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['word' => mb_strtolower(trim((string) $request->input('word')))]);

        $validated = $request->validate([
            'word' => ['required', 'string', 'max:20', 'regex:/\A[a-z0-9_\-]+\z/', Rule::unique('reserved_words', 'word')],
            'category' => ['required', Rule::enum(ReservedWordCategory::class)],
        ], [
            'word.required' => '予約語を入力してください。',
            'word.max' => '予約語は:max文字以内で入力してください。',
            'word.regex' => '予約語には半角英数字・ハイフン・アンダースコアのみ使えます。',
            'word.unique' => 'この予約語はすでに登録されています。',
        ]);

        $word = new ReservedWord(['word' => $validated['word'], 'category' => $validated['category']]);
        $word->created_by_user_id = $request->user()?->getAuthIdentifier();
        $word->save();

        Log::info('予約語を追加しました。', ['word' => $word->word, 'user_id' => $word->created_by_user_id]);

        return back()->with('notice', "「{$word->word}」を予約語に追加しました。");
    }

    public function destroy(Request $request, ReservedWord $reservedWord): RedirectResponse
    {
        $reservedWord->delete();

        Log::info('予約語を削除しました。', ['word' => $reservedWord->word, 'user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', "「{$reservedWord->word}」を予約語から外しました。");
    }
}
