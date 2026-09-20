<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Barang hilang & temuan') }}
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
                    <h3 class="font-medium">Lapor barang baru</h3>
                    <form method="POST" action="{{ route('my.lost_found.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="event_id" value="Event" />
                                <select id="event_id" name="event_id" required class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    @foreach ($events as $event)
                                        <option value="{{ $event->id }}" @selected((int) old('event_id') === $event->id)>{{ $event->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('event_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="kind" value="Jenis" />
                                <select id="kind" name="kind" required class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    <option value="lost" @selected(old('kind') === 'lost')>HILANG</option>
                                    <option value="found" @selected(old('kind') === 'found')>TEMU</option>
                                </select>
                                <x-input-error :messages="$errors->get('kind')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="item_name" value="Nama barang" />
                                <x-text-input id="item_name" name="item_name" type="text" required maxlength="255" class="mt-1 block w-full" :value="old('item_name')" />
                                <x-input-error :messages="$errors->get('item_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="location" value="Lokasi" />
                                <x-text-input id="location" name="location" type="text" maxlength="255" class="mt-1 block w-full" :value="old('location')" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="photo" value="Foto (opsional, maks 5MB)" />
                                <input id="photo" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm" />
                                <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="description" value="Deskripsi" />
                            <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                        <x-primary-button>Kirim laporan</x-primary-button>
                    </form>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-medium">Laporanku</h3>
                    @if ($mine->isEmpty())
                        <p class="mt-2 text-sm">Belum ada laporan barang.</p>
                    @else
                        <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($mine as $barang)
                                <li class="py-3 flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $barang->item_name }} ({{ $barang->kind === 'lost' ? 'HILANG' : 'TEMU' }})</p>
                                        <p class="text-sm text-gray-500">{{ $barang->event?->name ?? 'Event' }} — Status: {{ $barang->status }} — {{ $barang->location ?? '—' }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $mine->links() }}</div>
                    @endif
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-medium">Barang temu di event yang saya ikuti</h3>
                    @if ($found->isEmpty())
                        <p class="mt-2 text-sm">Belum ada barang temu yang bisa diklaim.</p>
                    @else
                        <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($found as $barang)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $barang->item_name }}</p>
                                        <p class="text-sm text-gray-500">{{ $barang->event?->name ?? 'Event' }} — {{ $barang->location ?? '—' }}</p>
                                    </div>
                                    @if ((int) $barang->reporter_id !== (int) auth()->id())
                                        <form method="POST" action="{{ route('my.lost_found.claim', $barang->id) }}">
                                            @csrf
                                            <x-primary-button>Klaim</x-primary-button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $found->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
