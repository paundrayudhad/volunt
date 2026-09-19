<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Kehadiran Relawan') }} — {{ $event->name }}
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
                    <a href="{{ route('organizer.events.show', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke event</a>
                    <a href="{{ route('organizer.events.attendances.scan', [$org->slug, $event->slug]) }}" class="underline text-sm ml-4">Pindai kehadiran</a>

                    @if ($attendances->isEmpty())
                        <p class="mt-6 text-sm">Belum ada catatan kehadiran pada event ini.</p>
                    @else
                        <ul class="mt-6 space-y-2 text-sm">
                            @foreach ($attendances as $attendance)
                                <li>{{ $attendance->user?->name ?? 'Relawan' }} — {{ $attendance->status }} — {{ $attendance->method }} — {{ $attendance->checked_in_at?->format('d M Y H:i') ?? '-' }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $attendances->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
