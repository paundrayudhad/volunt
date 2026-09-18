<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Pendaftaran Saya') }}
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
                    @if ($list->isEmpty())
                        <p class="text-sm">Belum ada pendaftaran. Jelajahi katalog event untuk mulai mendaftar.</p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($list as $item)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <a href="{{ route('registrations.show', $item->id) }}" class="underline font-medium">{{ $item->event->name }}</a>
                                        <p class="text-sm text-gray-500">Peran: {{ $item->role->name }} — Status: {{ $item->status }}</p>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ $item->created_at?->format('d M Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $list->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
