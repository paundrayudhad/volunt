<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $announcement->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 p-4 rounded">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-100 p-4 rounded">
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <a href="{{ route('organizer.events.announcements.index', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke daftar pengumuman</a>

                    <p class="mt-4 text-sm text-gray-500">Target: {{ $announcement->target_type }} — Status: {{ $announcement->published_at ? 'Terbit' : 'Draf' }} — Penerima: {{ $recipientCount }} relawan</p>
                    <div class="mt-4 whitespace-pre-line">{{ $announcement->body }}</div>

                    @if (! $announcement->published_at)
                        @can('publish', $announcement)
                            <form method="POST" action="{{ route('organizer.events.announcements.publish', [$org->slug, $event->slug, $announcement->id]) }}" class="mt-6">
                                @csrf
                                <x-primary-button>Terbitkan pengumuman</x-primary-button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
