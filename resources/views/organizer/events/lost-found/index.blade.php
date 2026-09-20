<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Barang hilang & temuan') }} — {{ $event->name }}
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

                    <form method="GET" action="{{ route('organizer.events.lost_found.index', [$org->slug, $event->slug]) }}" class="mt-4 flex flex-wrap items-end gap-3 text-sm">
                        <div>
                            <x-input-label for="filter_kind" value="Jenis" />
                            <select id="filter_kind" name="kind" class="mt-1 block rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="">Semua</option>
                                <option value="lost" @selected(request('kind') === 'lost')>HILANG</option>
                                <option value="found" @selected(request('kind') === 'found')>TEMU</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="filter_status" value="Status" />
                            <select id="filter_status" name="status" class="mt-1 block rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="">Semua</option>
                                @foreach (\App\Models\LostFoundItem::STATUSES as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>Saring</x-primary-button>
                    </form>

                    @if (auth()->user()->can('incident.report') && auth()->user()->belongsToOrganization($event->organization_id))
                        <form method="POST" action="{{ route('organizer.events.lost_found.store', [$org->slug, $event->slug]) }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                            @csrf
                            <h3 class="font-medium">Lapor barang baru</h3>
                            <div class="grid sm:grid-cols-2 gap-4">
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
                                    <x-input-label for="photo" value="Foto (jpg/png/webp, maks 5MB)" />
                                    <input id="photo" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm" />
                                    <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                                </div>
                            </div>
                            <div>
                                <x-input-label for="description" value="Deskripsi" />
                                <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                            <x-primary-button>Lapor barang</x-primary-button>
                        </form>
                    @endif

                    @if ($items->isEmpty())
                        <p class="mt-6 text-sm">Belum ada laporan barang pada event ini.</p>
                    @else
                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Barang</th>
                                        <th class="px-3 py-2 text-left font-medium">Jenis</th>
                                        <th class="px-3 py-2 text-left font-medium">Status</th>
                                        <th class="px-3 py-2 text-left font-medium">Lokasi</th>
                                        <th class="px-3 py-2 text-left font-medium">Foto</th>
                                        <th class="px-3 py-2 text-left font-medium">Pengklaim</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($items as $barang)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <a href="{{ route('organizer.events.lost_found.show', [$org->slug, $event->slug, $barang->id]) }}" class="underline font-medium">{{ $barang->item_name }}</a>
                                            </td>
                                            <td class="px-3 py-2">{{ $barang->kind === 'lost' ? 'HILANG' : 'TEMU' }}</td>
                                            <td class="px-3 py-2">{{ $barang->status }}</td>
                                            <td class="px-3 py-2">{{ $barang->location ?? '—' }}</td>
                                            <td class="px-3 py-2">
                                                @if ($barang->photo_path && Route::has('lostfound.photo'))
                                                    <img src="{{ URL::signedRoute('lostfound.photo', ['lostFoundItem' => $barang->id], now()->addMinutes(30)) }}" alt="Foto {{ $barang->item_name }}" class="h-12 w-12 object-cover rounded" />
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">{{ $barang->claimant?->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $items->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
