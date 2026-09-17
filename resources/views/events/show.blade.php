<x-public-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="text-sm">Penyelenggara: {{ $event->organization->name }}</p>
                    <p class="mt-2 text-sm">{{ $event->description ?? 'Belum ada deskripsi untuk event ini.' }}</p>
                    <p class="mt-2 text-sm">Jadwal: {{ $event->start_at?->format('d M Y H:i') }} — {{ $event->end_at?->format('d M Y H:i') }}</p>
                    @if ($event->venue)
                        <p class="mt-1 text-sm">Tempat: {{ $event->venue }}</p>
                    @endif
                    @if ($event->address)
                        <p class="mt-1 text-sm">Alamat: {{ $event->address }}</p>
                    @endif
                    @if ($event->category)
                        <p class="mt-1 text-sm">Kategori: {{ $event->category }}</p>
                    @endif

                    <div class="mt-4">
                        <a href="{{ route('events.index') }}" class="underline text-sm">Kembali ke katalog</a>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-semibold">Posisi Relawan ({{ $event->roles->count() }})</h3>
                    @if ($event->roles->isEmpty())
                        <p class="mt-2 text-sm">Belum ada posisi relawan yang dibuka.</p>
                    @else
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($event->roles as $peran)
                                <li class="flex items-center justify-between gap-4">
                                    <div>
                                        <span class="font-medium">{{ $peran->name }}</span>
                                        @if ($peran->division)
                                            <span class="text-gray-500">— {{ $peran->division->name }}</span>
                                        @endif
                                    </div>
                                    <span class="text-gray-500">Sisa {{ $peran->remainingQuota() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-semibold">Jadwal Shift ({{ $event->shifts->count() }})</h3>
                    @if ($event->shifts->isEmpty())
                        <p class="mt-2 text-sm">Jadwal shift akan diumumkan kemudian.</p>
                    @else
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($event->shifts as $shift)
                                <li class="flex items-center justify-between gap-4">
                                    <span>{{ $shift->start_at?->format('d M Y H:i') }} — {{ $shift->end_at?->format('H:i') }}</span>
                                    <span class="text-gray-500">{{ $shift->location ?? 'Lokasi menyusul' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
