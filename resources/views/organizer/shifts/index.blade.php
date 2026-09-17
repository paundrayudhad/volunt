<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Daftar Shift') }} — {{ $event->name }}
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
                    <div class="flex items-center justify-between">
                        <a href="{{ route('organizer.events.show', [$org->slug, $event->slug]) }}" class="underline">Kembali ke event</a>
                        @can('manage', [\App\Models\EventShift::class, $event])
                            <a href="{{ route('organizer.events.shifts.create', [$org->slug, $event->slug]) }}" class="underline">Tambah shift</a>
                        @endcan
                    </div>

                    @if ($jadwal->isEmpty())
                        <p class="mt-4 text-sm">Belum ada shift pada event ini.</p>
                    @else
                        <ul class="mt-4 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($jadwal as $satu)
                                <li class="py-3">
                                    <a href="{{ route('organizer.events.shifts.show', [$org->slug, $event->slug, $satu->id]) }}" class="underline font-medium">{{ $satu->start_at?->format('d M Y H:i') }}</a>
                                    <p class="text-sm text-gray-500">Divisi: {{ $satu->division?->name }} — {{ $satu->location ?? 'Tanpa lokasi' }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
