{{-- 管理者向けの画面。共通のタブを上に置き、1 つの画面として切り替える --}}
@props(['viewer', 'title', 'description' => null])

<x-dashboard.page :viewer="$viewer" :title="$title" :description="$description">
    <x-slot:tabs>
        @include('partials.admin-tabs', ['items' => \App\Support\AdminNavigation::tabs(request())])
    </x-slot:tabs>

    {{ $slot }}
</x-dashboard.page>
