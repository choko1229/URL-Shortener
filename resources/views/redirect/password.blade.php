{{--
    パスワード保護リンクの入力画面（requirements.md 2-5, 3: 案1）
    @var string $code
    @var string|null $error
    @var int|null $lockedMinutes
--}}
<x-layouts.redirect title="パスワードが必要です">
    <p class="text-sm leading-relaxed text-text-secondary">このリンクはパスワードで保護されています。共有された方から聞いたパスワードを入力してください。</p>

    @if ($lockedMinutes !== null)
        <div class="mt-5 flex items-start gap-2.5 rounded-control border border-danger/40 bg-white px-4 py-3 text-sm text-danger" role="alert">
            <x-icon name="lock" :size="18" class="mt-0.5" />
            <p>パスワードを続けて間違えたため、入力を一時的に停止しています。約 {{ $lockedMinutes }} 分後にもう一度お試しください。</p>
        </div>
    @else
        <form method="POST" action="{{ route('main.short-link.unlock', ['code' => $code]) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="link-password" class="block text-[13px] font-medium text-text-secondary">パスワード</label>
                <input
                    id="link-password"
                    name="password"
                    type="password"
                    autocomplete="off"
                    required
                    maxlength="72"
                    autofocus
                    class="form-control mt-2"
                    @if ($error) aria-invalid="true" aria-describedby="link-password-error" @endif
                >
                @if ($error)
                    <p id="link-password-error" class="mt-2 flex items-center gap-1.5 text-[13px] text-danger" role="alert">
                        <x-icon name="alert-circle" :size="14" />
                        <span>{{ $error }}</span>
                    </p>
                @endif
            </div>
            <x-button type="submit" class="w-full">開く</x-button>
        </form>
    @endif
</x-layouts.redirect>
