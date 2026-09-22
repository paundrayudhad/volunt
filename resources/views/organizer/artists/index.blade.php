<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Artis — {{ $event->name }}
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

                    <form method="GET" action="{{ route('organizer.events.artists.index', [$org->slug, $event->slug]) }}" class="mt-4 flex flex-wrap items-end gap-3 text-sm">
                        <div>
                            <x-input-label for="filter_status" value="Status" />
                            <select id="filter_status" name="status" class="mt-1 block rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="">Semua</option>
                                @foreach (\App\Models\Artist::STATUSES as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="filter_attendance" value="Kehadiran" />
                            <select id="filter_attendance" name="attendance" class="mt-1 block rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="">Semua</option>
                                @foreach (\App\Models\Artist::ATTENDANCES as $hadir)
                                    <option value="{{ $hadir }}" @selected(request('attendance') === $hadir)>{{ $hadir }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>Saring</x-primary-button>
                    </form>

                    @can('manage', [\App\Models\Artist::class, $event])
                        <form method="POST" action="{{ route('organizer.events.artists.store', [$org->slug, $event->slug]) }}" class="mt-6 space-y-4">
                            @csrf
                            <h3 class="font-medium">Tambah artis baru</h3>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="name" value="Nama artis" />
                                    <x-text-input id="name" name="name" type="text" required maxlength="255" class="mt-1 block w-full" :value="old('name')" />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="genre" value="Genre" />
                                    <x-text-input id="genre" name="genre" type="text" maxlength="100" class="mt-1 block w-full" :value="old('genre')" />
                                    <x-input-error :messages="$errors->get('genre')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="stage" value="Panggung" />
                                    <x-text-input id="stage" name="stage" type="text" maxlength="100" class="mt-1 block w-full" :value="old('stage')" />
                                    <x-input-error :messages="$errors->get('stage')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="scheduled_at" value="Jadwal tampil" />
                                    <x-text-input id="scheduled_at" name="scheduled_at" type="datetime-local" class="mt-1 block w-full" :value="old('scheduled_at')" />
                                    <x-input-error :messages="$errors->get('scheduled_at')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="duration_minutes" value="Durasi (menit, 15–240)" />
                                    <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="15" max="240" class="mt-1 block w-full" :value="old('duration_minutes')" />
                                    <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="performance_order" value="Urutan tampil" />
                                    <x-text-input id="performance_order" name="performance_order" type="number" min="1" class="mt-1 block w-full" :value="old('performance_order')" />
                                    <x-input-error :messages="$errors->get('performance_order')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="contact_name" value="Nama kontak" />
                                    <x-text-input id="contact_name" name="contact_name" type="text" maxlength="255" class="mt-1 block w-full" :value="old('contact_name')" />
                                    <x-input-error :messages="$errors->get('contact_name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="contact_phone" value="Nomor kontak" />
                                    <x-text-input id="contact_phone" name="contact_phone" type="text" maxlength="50" class="mt-1 block w-full" :value="old('contact_phone')" />
                                    <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label for="rider_text" value="Rider" />
                                    <textarea id="rider_text" name="rider_text" rows="3" maxlength="2000" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('rider_text') }}</textarea>
                                    <x-input-error :messages="$errors->get('rider_text')" class="mt-2" />
                                </div>
                            </div>
                            <x-primary-button>Tambah artis</x-primary-button>
                        </form>
                    @endcan

                    @if ($artists->isEmpty())
                        <p class="mt-6 text-sm">Belum ada artis pada event ini.</p>
                    @else
                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Nama</th>
                                        <th class="px-3 py-2 text-left font-medium">Status</th>
                                        <th class="px-3 py-2 text-left font-medium">Kehadiran</th>
                                        <th class="px-3 py-2 text-left font-medium">Jadwal</th>
                                        <th class="px-3 py-2 text-left font-medium">Panggung</th>
                                        <th class="px-3 py-2 text-left font-medium">Rider</th>
                                        <th class="px-3 py-2 text-left font-medium">LO aktif</th>
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
                                            $namaLo = $artis->liaisons->map(fn ($row) => $row->user?->name)->filter()->implode(', ');
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2">
                                                <a href="{{ route('organizer.events.artists.show', [$org->slug, $event->slug, $artis->id]) }}" class="underline font-medium">{{ $artis->name }}</a>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="inline-block rounded px-2 py-0.5 text-xs font-bold {{ $warnaStatus }}">{{ $artis->status }}</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="inline-block rounded px-2 py-0.5 text-xs font-bold {{ $warnaHadir }}">{{ $artis->attendance }}</span>
                                            </td>
                                            <td class="px-3 py-2">{{ $artis->scheduled_at?->format('d M Y H:i') ?? 'Jadwal menyusul' }}</td>
                                            <td class="px-3 py-2">{{ $artis->stage ?? '—' }}</td>
                                            <td class="px-3 py-2">
                                                @if ($artis->rider_fulfilled)
                                                    <span class="inline-block rounded bg-green-200 px-2 py-0.5 text-xs font-bold text-green-900">Rider terpenuhi</span>
                                                @else
                                                    <span class="text-gray-500">Rider belum terpenuhi</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">{{ $namaLo !== '' ? $namaLo : 'Belum ada LO' }}</td>
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
