{{--
    未ログインで「無期限」を選んだときに表示するログイン案内（requirements.md 2-3）
    @var int $maxExpiryDays
--}}
<dialog id="login-dialog" class="dialog" aria-labelledby="login-dialog-title" aria-describedby="login-dialog-description">
    <div class="p-6 sm:p-8">
        <div class="mb-4 flex size-11 items-center justify-center rounded-control bg-primary-tint text-primary-dark">
            <x-icon name="lock" :size="22" />
        </div>
        <h2 id="login-dialog-title" class="font-rounded text-section font-bold">無期限のリンクにはログインが必要です</h2>
        <p id="login-dialog-description" class="mt-2 text-sm leading-relaxed text-text-secondary">
            ログインしていない場合、有効期限は最大{{ $maxExpiryDays }}日までです。Discordでログインすると、無期限のリンクやカスタムスラッグを使えます。
        </p>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <form method="dialog">
                <x-button type="submit" variant="secondary" size="sm" class="w-full sm:w-auto">期限を選び直す</x-button>
            </form>
            <x-button size="sm" :href="route('main.login')">
                <x-icon name="log-in" :size="16" />
                Discordでログイン
            </x-button>
        </div>
    </div>
</dialog>
