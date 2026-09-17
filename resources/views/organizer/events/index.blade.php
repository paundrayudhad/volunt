<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Daftar Event') }}
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
                        <h3 class="text-lg font-semibold">{{ $org->name }}</h3>
                        @can('create', [App\Models\Event::class, $org])
                            <a href="{{ route('organizer.events.create', $org->slug) }}" class="underline">Buat event</a>
                        @endcan
                    </div>

                    @if ($acara->isEmpty())
                        <p class="mt-4 text-sm">Belum ada event. Buat event pertama untuk organisasimu.</p>
                    @else
                        <ul class="mt-4 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($acara as $event)
                                <li class="py-3 flex items-center justify-between">
                                    <div>
                                        <a href="{{ route('organizer.events.show', [$org->slug, $event->slug]) }}" class="underline font-medium">{{ $event->name }}</a>
                                        <p class="text-sm text-gray-500">Status: {{ $event->status }}</p>
                                    </div>
                                    <span class="text-sm text-gray-500">{{ $event->start_at?->format('d M Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $acara->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
