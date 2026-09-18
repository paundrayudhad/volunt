<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Jadwal Saya') }}
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
                    @if ($assignments->isEmpty())
                        <p class="text-sm">Belum ada jadwal. Penugasan shift Anda akan tampil di sini.</p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($assignments as $item)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $item->event?->name ?? 'Event' }}</p>
                                        <p class="text-sm text-gray-500">Shift: {{ $item->shift?->start_at?->format('d M Y H:i') ?? '-' }} — {{ $item->shift?->location ?? $item->location ?? '-' }}</p>
                                        <p class="text-sm text-gray-500">Status: {{ $item->status }} — Kehadiran: {{ $item->attendances->first()?->status ?? 'Belum dicatat' }}</p>
                                    </div>
                                    <a href="{{ route('my.qr.show', $item->id) }}" class="underline text-sm">Kode QR</a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $assignments->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
