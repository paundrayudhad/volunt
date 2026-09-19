<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Pengumuman Saya') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if ($announcements->isEmpty())
                        <p class="text-sm">Belum ada pengumuman untuk Anda.</p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($announcements as $item)
                                <li class="py-3">
                                    <p class="font-medium">{{ $item->title }}</p>
                                    <p class="text-sm text-gray-500">{{ $item->event?->name ?? 'Event' }} — {{ $item->published_at?->format('d M Y H:i') ?? '-' }}</p>
                                    <div class="mt-1 text-sm whitespace-pre-line">{{ $item->body }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
