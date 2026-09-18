<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Pengumuman') }} — {{ $event->name }}
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
                    <a href="{{ route('organizer.events.show', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke event</a>

                    @can('publish', [\App\Models\Announcement::class, $event])
                        <div class="mt-4">
                            <a href="{{ route('organizer.events.announcements.create', [$org->slug, $event->slug]) }}" class="underline text-sm font-medium">Buat pengumuman</a>
                        </div>
                    @endcan

                    @if ($announcements->isEmpty())
                        <p class="mt-6 text-sm">Belum ada pengumuman pada event ini.</p>
                    @else
                        <ul class="mt-6 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($announcements as $item)
                                <li class="py-3">
                                    <a href="{{ route('organizer.events.announcements.show', [$org->slug, $event->slug, $item->id]) }}" class="underline font-medium">{{ $item->title }}</a>
                                    <p class="text-sm text-gray-500">Target: {{ $item->target_type }} — Status: {{ $item->published_at ? 'Terbit' : 'Draf' }}</p>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-4">
                            {{ $announcements->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
