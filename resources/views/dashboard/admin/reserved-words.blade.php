{{--
    予約語の管理（requirements.md 2-2）
    @var \App\ViewModels\ViewerData $viewer
    @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Models\ReservedWord>> $groups
    @var list<\App\Enums\ReservedWordCategory> $categories
--}}
<x-dashboard.page :viewer="$viewer" title="予約語" description="カスタムスラッグとして使えない語です。大文字小文字を区別せずに判定します。管理者は予約語でも使えます。">
    <section aria-labelledby="add-word-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
        <h2 id="add-word-heading" class="font-rounded text-[15px] font-bold">予約語を追加</h2>
        <form method="POST" action="{{ route('dashboard.admin.reserved-words.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
            @csrf
            <div class="min-w-0 flex-1">
                <label for="word" class="block text-[13px] font-medium text-text-secondary">語</label>
                <input
                    id="word"
                    name="word"
                    type="text"
                    required
                    maxlength="20"
                    pattern="[A-Za-z0-9_\-]+"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    value="{{ old('word') }}"
                    class="form-control mt-2"
                    @error('word') aria-invalid="true" aria-describedby="word-error" @enderror
                >
                <x-field-error name="word" id="word-error" />
            </div>
            <div>
                <label for="category" class="block text-[13px] font-medium text-text-secondary">分類</label>
                <select id="category" name="category" class="form-control mt-2">
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(old('category', 'custom') === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" size="sm" class="min-h-[52px] sm:mt-[30px]">
                <x-icon name="plus" :size="16" />
                追加する
            </x-button>
        </form>
    </section>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        @foreach ($categories as $category)
            @php
                $words = $groups->get($category->value, collect());
            @endphp
            <section aria-labelledby="category-{{ $category->value }}" class="rounded-card border border-border bg-white p-5 sm:p-6">
                <h2 id="category-{{ $category->value }}" class="font-rounded text-[15px] font-bold">
                    {{ $category->label() }} <span class="text-xs font-normal text-text-secondary">{{ $words->count() }}語</span>
                </h2>
                @if ($words->isEmpty())
                    <p class="mt-3 text-sm text-text-secondary">登録されていません。</p>
                @else
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($words as $word)
                            <li class="inline-flex items-center gap-1 rounded-full border border-border bg-primary-tint-soft py-1 pl-3 pr-1 text-[13px]">
                                {{ $word->word }}
                                <form method="POST" action="{{ route('dashboard.admin.reserved-words.destroy', ['reservedWord' => $word->id]) }}" data-confirm="「{{ $word->word }}」を予約語から外しますか？">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex size-7 items-center justify-center rounded-full text-text-secondary hover:bg-danger/10 hover:text-danger" aria-label="{{ $word->word }} を予約語から外す">
                                        <x-icon name="close" :size="14" />
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
</x-dashboard.page>
