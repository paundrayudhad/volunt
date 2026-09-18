<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Seleksi Pendaftar') }} — {{ $event->name }}
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
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <a href="{{ route('organizer.events.show', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke event</a>

                    <form method="GET" action="{{ route('organizer.events.registrations.index', [$org->slug, $event->slug]) }}" class="mt-4 flex flex-wrap items-center gap-2">
                        <label for="status" class="text-sm">Status</label>
                        <select id="status" name="status" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                            <option value="">Semua status</option>
                            @foreach ($daftarStatus as $status)
                                <option value="{{ $status }}" @selected($statusDipilih === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <x-primary-button>Filter</x-primary-button>
                    </form>

                    @if ($pendaftaran->isEmpty())
                        <p class="mt-4 text-sm">Belum ada pendaftaran pada event ini.</p>
                    @else
                        <form method="POST" action="{{ route('organizer.events.registrations.bulk', [$org->slug, $event->slug]) }}" class="mt-4 space-y-3">
                            @csrf
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($pendaftaran as $satu)
                                    <li class="py-3 flex items-start gap-3">
                                        <input type="checkbox" name="ids[]" value="{{ $satu->id }}" class="mt-1" />
                                        <div>
                                            <a href="{{ route('organizer.events.registrations.show', [$org->slug, $event->slug, $satu->id]) }}" class="underline font-medium">{{ $satu->user?->name ?? 'Pendaftar' }}</a>
                                            <p class="text-sm text-gray-500">Peran: {{ $satu->role?->name ?? '-' }} — Status: {{ $satu->status }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="flex flex-wrap items-center gap-2">
                                <label for="bulk-action" class="text-sm">Aksi massal</label>
                                <select id="bulk-action" name="action" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    <option value="accepted">Terima</option>
                                    <option value="rejected">Tolak</option>
                                    <option value="waitlisted">Waitlist</option>
                                    <option value="cancelled">Batalkan</option>
                                </select>
                                <input name="reason" type="text" placeholder="Alasan (wajib saat menolak)"
                                    class="rounded border-gray-300 dark:bg-gray-700 text-sm" />
                                <x-primary-button>Terapkan seleksi massal</x-primary-button>
                            </div>
                        </form>

                        <div class="mt-4">
                            {{ $pendaftaran->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
