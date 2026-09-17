<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $divisi->name }}
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
                    <p class="text-sm">{{ $divisi->description ?? 'Tanpa deskripsi.' }}</p>

                    <div class="mt-4 flex flex-wrap gap-4">
                        <a href="{{ route('organizer.events.divisions.index', [$org->slug, $event->slug]) }}" class="underline">Kembali ke daftar divisi</a>
                        @can('manage', $divisi)
                            <a href="{{ route('organizer.events.divisions.edit', [$org->slug, $event->slug, $divisi->id]) }}" class="underline">Ubah divisi</a>
                        @endcan
                    </div>

                    <h3 class="mt-6 font-semibold">Role ({{ $divisi->roles->count() }})</h3>
                    <ul class="mt-2 text-sm space-y-1">
                        @foreach ($divisi->roles as $peran)
                            <li>
                                <a href="{{ route('organizer.events.roles.show', [$org->slug, $event->slug, $peran->id]) }}" class="underline">{{ $peran->name }}</a>
                            </li>
                        @endforeach
                    </ul>

                    <h3 class="mt-6 font-semibold">Shift ({{ $divisi->shifts->count() }})</h3>
                    <ul class="mt-2 text-sm space-y-1">
                        @foreach ($divisi->shifts as $shift)
                            <li>
                                <a href="{{ route('organizer.events.shifts.show', [$org->slug, $event->slug, $shift->id]) }}" class="underline">{{ $shift->start_at?->format('d M Y H:i') }}</a>
                            </li>
                        @endforeach
                    </ul>

                    @can('manage', $divisi)
                        <form method="POST" action="{{ route('organizer.events.divisions.destroy', [$org->slug, $event->slug, $divisi->id]) }}" class="mt-6">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>Hapus divisi</x-danger-button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
