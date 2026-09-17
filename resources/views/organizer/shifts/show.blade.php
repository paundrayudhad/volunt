<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Detail Shift') }} — {{ $event->name }}
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
                    <p class="text-sm">Jadwal: {{ $shift->start_at?->format('d M Y H:i') }} — {{ $shift->end_at?->format('d M Y H:i') }}</p>
                    <p class="mt-1 text-sm">Divisi: {{ $shift->division?->name }}</p>
                    <p class="mt-1 text-sm">Role: {{ $shift->role?->name ?? 'Tanpa role' }}</p>
                    <p class="mt-1 text-sm">Lokasi: {{ $shift->location ?? 'Tanpa lokasi' }}</p>

                    <div class="mt-4 flex flex-wrap gap-4">
                        <a href="{{ route('organizer.events.shifts.index', [$org->slug, $event->slug]) }}" class="underline">Kembali ke daftar shift</a>
                        @can('manage', $shift)
                            <a href="{{ route('organizer.events.shifts.edit', [$org->slug, $event->slug, $shift->id]) }}" class="underline">Ubah shift</a>
                        @endcan
                    </div>

                    @can('manage', $shift)
                        <form method="POST" action="{{ route('organizer.events.shifts.destroy', [$org->slug, $event->slug, $shift->id]) }}" class="mt-6">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>Hapus shift</x-danger-button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
