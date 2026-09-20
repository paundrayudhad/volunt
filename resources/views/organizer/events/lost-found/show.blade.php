<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $item->item_name }} — {{ $event->name }}
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
                    <a href="{{ route('organizer.events.lost_found.index', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke daftar barang</a>

                    <dl class="mt-4 text-sm space-y-2">
                        <div><dt class="inline font-medium">Jenis:</dt> <dd class="inline">{{ $item->kind === 'lost' ? 'HILANG' : 'TEMU' }}</dd></div>
                        <div><dt class="inline font-medium">Status:</dt> <dd class="inline">{{ $item->status }}</dd></div>
                        <div><dt class="inline font-medium">Lokasi:</dt> <dd class="inline">{{ $item->location ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">Deskripsi:</dt> <dd class="inline">{{ $item->description ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">Pelapor:</dt> <dd class="inline">{{ $item->reporter?->name ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">Pengklaim:</dt> <dd class="inline">{{ $item->claimant?->name ?? '—' }}</dd></div>
                    </dl>

                    @if ($item->photo_path && Route::has('lostfound.photo'))
                        <div class="mt-4">
                            <img src="{{ URL::signedRoute('lostfound.photo', ['lostFoundItem' => $item->id], now()->addMinutes(30)) }}" alt="Foto {{ $item->item_name }}" class="h-32 w-32 object-cover rounded" />
                        </div>
                    @endif

                    @if ($item->incidents->isNotEmpty())
                        <h3 class="mt-6 font-medium">Insiden tertaut</h3>
                        <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            @foreach ($item->incidents as $insiden)
                                <li class="py-2">
                                    <a href="{{ route('organizer.events.incidents.show', [$org->slug, $event->slug, $insiden->id]) }}" class="underline">Insiden #{{ $insiden->id }}</a>
                                    — {{ $insiden->category }}, {{ $insiden->status }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @can('manage', $item)
                        @if ($item->status === 'claimed')
                            <form method="POST" action="{{ route('organizer.events.lost_found.resolve', [$org->slug, $event->slug, $item->id]) }}" class="mt-6 space-y-4">
                                @csrf
                                <div>
                                    <x-input-label for="decision" value="Keputusan klaim" />
                                    <select id="decision" name="decision" required class="mt-1 block w-60 rounded border-gray-300 dark:bg-gray-700 text-sm">
                                        <option value="returned">Setuju — kembalikan</option>
                                        <option value="rejected">Tolak — kembali ke posko</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('decision')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="note" value="Catatan (opsional, maks 500 karakter)" />
                                    <textarea id="note" name="note" rows="3" maxlength="500" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('note') }}</textarea>
                                    <x-input-error :messages="$errors->get('note')" class="mt-2" />
                                </div>
                                <x-primary-button>Simpan keputusan</x-primary-button>
                            </form>
                        @endif

                        @if (in_array($item->status, ['found', 'returned'], true))
                            <form method="POST" action="{{ route('organizer.events.lost_found.close', [$org->slug, $event->slug, $item->id]) }}" class="mt-6">
                                @csrf
                                <x-primary-button>Tutup laporan</x-primary-button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
