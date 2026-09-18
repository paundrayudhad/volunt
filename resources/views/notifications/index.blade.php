<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Notifikasi') }}
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
                    @if ($notifications->isEmpty())
                        <p class="text-sm">Belum ada notifikasi.</p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($notifications as $notif)
                                <li class="py-3 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium">{{ $notif->data['title'] ?? 'Notifikasi' }}</p>
                                        <p class="text-sm text-gray-500">{{ $notif->read_at ? 'Sudah dibaca' : 'Belum dibaca' }} — {{ $notif->created_at?->format('d M Y H:i') ?? '-' }}</p>
                                    </div>
                                    @if (! $notif->read_at)
                                        <form method="POST" action="{{ route('notifications.read', $notif->id) }}">
                                            @csrf
                                            <x-secondary-button>Tandai dibaca</x-secondary-button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-4">
                            {{ $notifications->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
