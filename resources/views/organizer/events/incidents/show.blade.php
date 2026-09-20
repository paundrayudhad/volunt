<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Insiden #{{ $incident->id }} — {{ $event->name }}
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
                    <a href="{{ route('organizer.events.incidents.index', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke daftar insiden</a>

                    <dl class="mt-4 text-sm space-y-2">
                        <div><dt class="inline font-medium">Kategori:</dt> <dd class="inline">{{ $incident->category }}</dd></div>
                        <div>
                            <dt class="inline font-medium">Prioritas:</dt>
                            <dd class="inline">{{ $incident->priority }}
                                @if ($incident->isCritical())
                                    <span class="ml-1 inline-block rounded bg-red-600 px-2 py-0.5 text-xs font-bold text-white">KRITIS</span>
                                @endif
                            </dd>
                        </div>
                        <div><dt class="inline font-medium">Status:</dt> <dd class="inline">{{ $incident->status }}</dd></div>
                        <div><dt class="inline font-medium">Lokasi:</dt> <dd class="inline">{{ $incident->location }}</dd></div>
                        <div><dt class="inline font-medium">Deskripsi:</dt> <dd class="inline">{{ $incident->description }}</dd></div>
                        <div><dt class="inline font-medium">Pelapor:</dt> <dd class="inline">{{ $incident->reporter?->name ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">Petugas:</dt> <dd class="inline">{{ $incident->assignee?->name ?? '—' }}</dd></div>
                    </dl>

                    @if ($incident->lostFoundItem)
                        <div class="mt-4 text-sm">
                            <span class="font-medium">Barang tertaut:</span>
                            <a href="{{ route('organizer.events.lost_found.show', [$org->slug, $event->slug, $incident->lostFoundItem->id]) }}" class="underline">{{ $incident->lostFoundItem->item_name }}</a>
                            ({{ $incident->lostFoundItem->status }})
                        </div>
                    @endif

                    <h3 class="mt-6 font-medium">Riwayat status</h3>
                    @if ($incident->histories->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">Belum ada perpindahan status.</p>
                    @else
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Dari</th>
                                        <th class="px-3 py-2 text-left font-medium">Ke</th>
                                        <th class="px-3 py-2 text-left font-medium">Pelaku</th>
                                        <th class="px-3 py-2 text-left font-medium">Catatan</th>
                                        <th class="px-3 py-2 text-left font-medium">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($incident->histories as $riwayat)
                                        <tr>
                                            <td class="px-3 py-2">{{ $riwayat->from_status }}</td>
                                            <td class="px-3 py-2">{{ $riwayat->to_status }}</td>
                                            <td class="px-3 py-2">{{ $riwayat->actor?->name ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $riwayat->note ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $riwayat->created_at?->format('d M Y H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @can('manage', $incident)
                        @if ($incident->status === 'open')
                            <form method="POST" action="{{ route('organizer.events.incidents.assign', [$org->slug, $event->slug, $incident->id]) }}" class="mt-6 space-y-4">
                                @csrf
                                <div>
                                    <x-input-label for="assignee_id" value="ID petugas (member organisasi)" />
                                    <x-text-input id="assignee_id" name="assignee_id" type="number" required class="mt-1 block w-60" :value="old('assignee_id')" />
                                    <x-input-error :messages="$errors->get('assignee_id')" class="mt-2" />
                                </div>
                                <x-primary-button>Tugaskan</x-primary-button>
                            </form>
                        @endif

                        @if (($berikut = \App\Models\Incident::NEXT[$incident->status] ?? []) !== [])
                            <form method="POST" action="{{ route('organizer.events.incidents.transition', [$org->slug, $event->slug, $incident->id]) }}" class="mt-6 space-y-4">
                                @csrf
                                <div>
                                    <x-input-label for="to" value="Tahap berikutnya" />
                                    <select id="to" name="to" required class="mt-1 block w-60 rounded border-gray-300 dark:bg-gray-700 text-sm">
                                        @foreach ($berikut as $tujuan)
                                            <option value="{{ $tujuan }}">{{ $tujuan }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('to')" class="mt-2" />
                                </div>
                                <x-primary-button>Lanjut tahap</x-primary-button>
                            </form>
                        @endif

                        @if (in_array($incident->status, ['resolved', 'closed'], true))
                            <form method="POST" action="{{ route('organizer.events.incidents.reopen', [$org->slug, $event->slug, $incident->id]) }}" class="mt-6 space-y-4">
                                @csrf
                                <div>
                                    <x-input-label for="reason" value="Alasan pembukaan ulang (minimal 10 karakter)" />
                                    <textarea id="reason" name="reason" rows="3" required maxlength="2000" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('reason') }}</textarea>
                                    <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                                </div>
                                <x-primary-button>Buka ulang</x-primary-button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
