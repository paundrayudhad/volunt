<x-public-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Katalog Event
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Temukan event kerelawanan yang sedang dibuka untuk umum.</p>
                    <form method="GET" action="{{ route('events.index') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                        <div>
                            <x-input-label for="search" value="Cari nama event" />
                            <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$pencarian" placeholder="cth. Festival" />
                        </div>
                        <div>
                            <x-input-label for="kategori" value="Kategori" />
                            <x-text-input id="kategori" name="kategori" type="text" class="mt-1 block w-full" :value="$kategori" placeholder="cth. musik" />
                        </div>
                        <div>
                            <x-input-label for="kota" value="Kota" />
                            <x-text-input id="kota" name="kota" type="text" class="mt-1 block w-full" :value="$kota" placeholder="cth. Bandung" />
                        </div>
                        <div class="flex items-end gap-2">
                            <x-primary-button>Cari</x-primary-button>
                            <a href="{{ route('events.index') }}" class="underline text-sm">Atur ulang</a>
                        </div>
                    </form>

                    @if ($acara->isEmpty())
                        <p class="mt-6 text-sm">Belum ada event yang cocok dengan pencarianmu.</p>
                    @else
                        <ul class="mt-6 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($acara as $event)
                                <li class="py-4">
                                    <a href="{{ route('events.show', $event->slug) }}" class="underline font-medium">{{ $event->name }}</a>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $event->start_at?->format('d M Y') }}
                                        @if ($event->venue)
                                            — {{ $event->venue }}
                                        @endif
                                        @if ($event->category)
                                            — {{ $event->category }}
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $acara->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
