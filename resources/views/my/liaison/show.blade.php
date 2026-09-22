<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $artist->name }}
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
                    <a href="{{ route('my.liaison.index') }}" class="underline text-sm">Kembali ke dampingan saya</a>

                    @php
                        $warnaStatus = [
                            'scheduled' => 'bg-gray-200 text-gray-800',
                            'soundcheck' => 'bg-yellow-200 text-yellow-900',
                            'performing' => 'bg-blue-200 text-blue-900',
                            'done' => 'bg-green-200 text-green-900',
                            'cancelled' => 'bg-red-200 text-red-900',
                        ][$artist->status] ?? 'bg-gray-200 text-gray-800';
                        $warnaHadir = [
                            'expected' => 'bg-gray-200 text-gray-800',
                            'arrived' => 'bg-green-200 text-green-900',
                            'no_show' => 'bg-red-200 text-red-900',
                        ][$artist->attendance] ?? 'bg-gray-200 text-gray-800';
                        $berikut = array_merge(\App\Models\Artist::NEXT[$artist->status] ?? [], in_array($artist->status, ['scheduled', 'soundcheck', 'performing'], true) ? ['cancelled'] : []);
                    @endphp

                    <dl class="mt-4 text-sm space-y-2">
                        <div><dt class="inline font-medium">Event:</dt> <dd class="inline">{{ $artist->event?->name ?? '—' }}</dd></div>
                        <div>
                            <dt class="inline font-medium">Status:</dt>
                            <dd class="inline"><span class="inline-block rounded px-2 py-0.5 text-xs font-bold {{ $warnaStatus }}">{{ $artist->status }}</span></dd>
                        </div>
                        <div>
                            <dt class="inline font-medium">Kehadiran:</dt>
                            <dd class="inline"><span class="inline-block rounded px-2 py-0.5 text-xs font-bold {{ $warnaHadir }}">{{ $artist->attendance }}</span></dd>
                        </div>
                        <div><dt class="inline font-medium">Genre:</dt> <dd class="inline">{{ $artist->genre ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">Panggung:</dt> <dd class="inline">{{ $artist->stage ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">Jadwal tampil:</dt> <dd class="inline">{{ $artist->scheduled_at?->format('d M Y H:i') ?? 'Jadwal menyusul' }}</dd></div>
                        @if ($artist->scheduled_at)
                            <div><dt class="inline font-medium">Selesai perkiraan:</dt> <dd class="inline">{{ $artist->endsAt()?->format('d M Y H:i') ?? '—' }}</dd></div>
                        @endif
                        <div><dt class="inline font-medium">Urutan tampil:</dt> <dd class="inline">{{ $artist->performance_order ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">Kontak:</dt> <dd class="inline">{{ $artist->contact_name ?? '—' }}{{ $artist->contact_phone ? ' ('.$artist->contact_phone.')' : '' }}</dd></div>
                        <div>
                            <dt class="inline font-medium">Rider:</dt>
                            <dd class="inline">
                                @if ($artist->rider_fulfilled)
                                    <span class="inline-block rounded bg-green-200 px-2 py-0.5 text-xs font-bold text-green-900">Rider terpenuhi</span>
                                @else
                                    <span>Rider belum terpenuhi</span>
                                @endif
                            </dd>
                        </div>
                        @if ($artist->rider_text)
                            <div><dt class="inline font-medium">Isi rider:</dt> <dd class="inline">{{ $artist->rider_text }}</dd></div>
                        @endif
                        <div>
                            <dt class="inline font-medium">LO aktif:</dt>
                            <dd class="inline">
                                @if ($artist->liaisons->isEmpty())
                                    Belum ada LO
                                @else
                                    {{ $artist->liaisons->map(fn ($row) => $row->user?->name ?? '—')->implode(', ') }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <form method="POST" action="{{ route('my.liaison.status', [$artist->id]) }}" class="mt-6 space-y-4">
                        @csrf
                        <h3 class="font-medium">Perbarui status dampingan</h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="to" value="Tahap berikutnya" />
                                <select id="to" name="to" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    <option value="">Tidak berubah</option>
                                    @foreach ($berikut as $tujuan)
                                        <option value="{{ $tujuan }}" @selected(old('to') === $tujuan)>{{ $tujuan }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('to')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="attendance" value="Kehadiran" />
                                <select id="attendance" name="attendance" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    <option value="">Tidak berubah</option>
                                    @foreach (\App\Models\Artist::ATTENDANCES as $hadir)
                                        <option value="{{ $hadir }}" @selected(old('attendance') === $hadir)>{{ $hadir }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('attendance')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="note" value="Catatan (wajib bila membatalkan, minimal 10 karakter)" />
                            <textarea id="note" name="note" rows="3" maxlength="500" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('note') }}</textarea>
                            <x-input-error :messages="$errors->get('note')" class="mt-2" />
                        </div>
                        <x-primary-button>Simpan status</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('my.liaison.note', [$artist->id]) }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="body" value="Catatan baru" />
                            <textarea id="body" name="body" rows="3" required maxlength="2000" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('body') }}</textarea>
                            <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        </div>
                        <x-primary-button>Simpan catatan</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('my.liaison.rider', [$artist->id]) }}" class="mt-6 space-y-4">
                        @csrf
                        <div class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="fulfilled" value="0" />
                            <input id="rider_fulfilled" type="checkbox" name="fulfilled" value="1" @checked((bool) old('fulfilled', $artist->rider_fulfilled)) class="rounded border-gray-300" />
                            <x-input-label for="rider_fulfilled" value="Rider sudah terpenuhi" />
                        </div>
                        <x-input-error :messages="$errors->get('fulfilled')" class="mt-2" />
                        <x-primary-button>Simpan status rider</x-primary-button>
                    </form>

                    <h3 class="mt-6 font-medium">Catatan</h3>
                    @if ($artist->notes->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">Belum ada catatan.</p>
                    @else
                        <ul class="mt-2 text-sm space-y-2">
                            @foreach ($artist->notes as $catatan)
                                <li class="rounded border border-gray-200 dark:border-gray-700 p-3">
                                    <p>{{ $catatan->body }}</p>
                                    <p class="mt-1 text-xs text-gray-500">Oleh {{ $catatan->author?->name ?? '—' }} · {{ $catatan->created_at?->format('d M Y H:i') ?? '—' }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <h3 class="mt-6 font-medium">Riwayat status</h3>
                    @if ($artist->histories->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">Belum ada perpindahan status.</p>
                    @else
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Dari</th>
                                        <th class="px-3 py-2 text-left font-medium">Ke</th>
                                        <th class="px-3 py-2 text-left font-medium">Kehadiran</th>
                                        <th class="px-3 py-2 text-left font-medium">Pelaku</th>
                                        <th class="px-3 py-2 text-left font-medium">Catatan</th>
                                        <th class="px-3 py-2 text-left font-medium">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($artist->histories as $riwayat)
                                        <tr>
                                            <td class="px-3 py-2">{{ $riwayat->from_status ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $riwayat->to_status ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $riwayat->from_attendance || $riwayat->to_attendance ? ($riwayat->from_attendance ?? '—').' → '.($riwayat->to_attendance ?? '—') : '—' }}</td>
                                            <td class="px-3 py-2">{{ $riwayat->actor?->name ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $riwayat->note ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $riwayat->created_at?->format('d M Y H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
