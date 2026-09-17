<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $peran->name }}
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
                    <p class="text-sm">Divisi: {{ $peran->division?->name }}</p>
                    <p class="mt-1 text-sm">Kuota: {{ $peran->quota }} — Sisa: {{ $peran->remainingQuota() }}</p>
                    <p class="mt-2 text-sm">{{ $peran->description ?? 'Tanpa deskripsi.' }}</p>

                    <div class="mt-4 flex flex-wrap gap-4">
                        <a href="{{ route('organizer.events.roles.index', [$org->slug, $event->slug]) }}" class="underline">Kembali ke daftar role</a>
                        @can('manage', $peran)
                            <a href="{{ route('organizer.events.roles.edit', [$org->slug, $event->slug, $peran->id]) }}" class="underline">Ubah role</a>
                        @endcan
                    </div>

                    @can('manage', $peran)
                        <form method="POST" action="{{ route('organizer.events.roles.destroy', [$org->slug, $event->slug, $peran->id]) }}" class="mt-6">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>Hapus role</x-danger-button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
