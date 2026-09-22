<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Dampingan Saya
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
                    @if ($artists->isEmpty())
                        <p class="text-sm">Belum ada artis yang Anda dampingi.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Nama</th>
                                        <th class="px-3 py-2 text-left font-medium">Event</th>
                                        <th class="px-3 py-2 text-left font-medium">Status</th>
                                        <th class="px-3 py-2 text-left font-medium">Kehadiran</th>
                                        <th class="px-3 py-2 text-left font-medium">Jadwal</th>
                                        <th class="px-3 py-2 text-left font-medium">Rider</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($artists as $artis)
                                        @php
                                            $warnaStatus = [
                                                'scheduled' => 'bg-gray-200 text-gray-800',
                                                'soundcheck' => 'bg-yellow-200 text-yellow-900',
                                                'performing' => 'bg-blue-200 text-blue-900',
                                                'done' => 'bg-green-200 text-green-900',
                                                'cancelled' => 'bg-red-200 text-red-900',
                                            ][$artis->status] ?? 'bg-gray-200 text-gray-800';
                                            $warnaHadir = [
                                                'expected' => 'bg-gray-200 text-gray-800',
                                                'arrived' => 'bg-green-200 text-green-900',
                                                'no_show' => 'bg-red-200 text-red-900',
                                            ][$artis->attendance] ?? 'bg-gray-200 text-gray-800';
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2">
                                                <a href="{{ route('my.liaison.show', [$artis->id]) }}" class="underline font-medium">{{ $artis->name }}</a>
                                            </td>
                                            <td class="px-3 py-2">{{ $artis->event?->name ?? '—' }}</td>
                                            <td class="px-3 py-2">
                                                <span class="inline-block rounded px-2 py-0.5 text-xs font-bold {{ $warnaStatus }}">{{ $artis->status }}</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="inline-block rounded px-2 py-0.5 text-xs font-bold {{ $warnaHadir }}">{{ $artis->attendance }}</span>
                                            </td>
                                            <td class="px-3 py-2">{{ $artis->scheduled_at?->format('d M Y H:i') ?? 'Jadwal menyusul' }}</td>
                                            <td class="px-3 py-2">
                                                @if ($artis->rider_fulfilled)
                                                    <span class="inline-block rounded bg-green-200 px-2 py-0.5 text-xs font-bold text-green-900">Rider terpenuhi</span>
                                                @else
                                                    <span class="text-gray-500">Rider belum terpenuhi</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $artists->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
