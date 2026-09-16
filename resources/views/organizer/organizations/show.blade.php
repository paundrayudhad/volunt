<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard Organisasi') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 p-4 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold">{{ $org->name }}</h3>
                    <p class="mt-2 text-sm">{{ $org->description ?? 'Belum ada deskripsi.' }}</p>
                    <p class="mt-2 text-sm">Status: {{ $org->status }}</p>

                    <div class="mt-4 flex gap-4">
                        <a href="{{ route('organizer.edit', $org->slug) }}" class="underline">Ubah profil</a>
                        <a href="{{ route('organizer.members.index', $org->slug) }}" class="underline">Kelola anggota</a>
                    </div>

                    @can('update', $org)
                        <form method="POST" action="{{ route('organizer.transfer', $org->slug) }}" class="mt-6 max-w-md">
                            @csrf
                            <label for="member_id" class="block text-sm font-medium">Alihkan kepemilikan ke anggota (ID anggota)</label>
                            <input id="member_id" name="member_id" type="number" required
                                class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700" />
                            @error('member_id')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                            <x-primary-button class="mt-3">Alihkan kepemilikan</x-primary-button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
