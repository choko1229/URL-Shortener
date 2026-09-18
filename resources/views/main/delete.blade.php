{{-- 削除用トークンによる削除（requirements.md 2-4） @var \App\ViewModels\ViewerData $viewer --}}
<x-layouts.main :viewer="$viewer" title="短縮URLの削除">
    <div class="px-4 pt-12 sm:px-8 sm:pt-16">
        <div class="mx-auto max-w-[560px]">
            <h1 class="font-rounded text-section font-bold">短縮URLの削除</h1>
            <p class="mt-2 text-sm leading-relaxed text-text-secondary">
                ログインせずに発行した短縮URLは、発行時に表示された削除用トークンで削除できます。
                ログインして発行したものはダッシュボードから削除してください。
            </p>

            <x-flash-messages class="mt-5" />

            <form method="POST" action="{{ route('main.delete.destroy') }}" class="mt-6 space-y-5 rounded-card-lg bg-white p-6 shadow-card-lg sm:p-8">
                @csrf
                <div>
                    <label for="short-url" class="block text-[13px] font-medium text-text-secondary">短縮URL</label>
                    <input
                        id="short-url"
                        name="short_url"
                        type="text"
                        required
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="{{ route('main.home') }}/abc1234"
                        value="{{ old('short_url') }}"
                        class="form-control mt-2"
                        @error('short_url') aria-invalid="true" aria-describedby="short-url-error" @enderror
                    >
                    <x-field-error name="short_url" id="short-url-error" />
                </div>
                <div>
                    <label for="deletion-token" class="block text-[13px] font-medium text-text-secondary">削除用トークン</label>
                    <input
                        id="deletion-token"
                        name="deletion_token"
                        type="password"
                        required
                        autocomplete="off"
                        spellcheck="false"
                        class="form-control mt-2"
                        @error('deletion_token') aria-invalid="true" aria-describedby="deletion-token-error" @enderror
                    >
                    <x-field-error name="deletion_token" id="deletion-token-error" />
                </div>
                <p class="text-xs text-text-secondary">削除したコードは欠番となり、再利用されません。</p>
                <x-button type="submit" variant="danger-ghost" class="w-full border border-danger/40">削除する</x-button>
            </form>
        </div>
    </div>
</x-layouts.main>
