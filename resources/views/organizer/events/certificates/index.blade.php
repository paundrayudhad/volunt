<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Sertifikat') }} — {{ $event->name }}
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

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                        <p class="text-sm text-gray-500">Ambang kehadiran efektif: {{ $ambang }}% — Diterbitkan: {{ $diterbitkan }}</p>

                        <form method="GET" action="{{ route('organizer.events.certificates.index', [$org->slug, $event->slug]) }}" class="flex items-center gap-2">
                            <label for="filter-status" class="text-xs text-gray-500">Status:</label>
                            <select id="filter-status" name="status" onchange="this.form.submit()" class="text-sm rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                <option value="" {{ $selectedStatus === '' ? 'selected' : '' }}>Semua</option>
                                <option value="valid" {{ $selectedStatus === 'valid' ? 'selected' : '' }}>Valid / Aktif</option>
                                <option value="revoked" {{ $selectedStatus === 'revoked' ? 'selected' : '' }}>Dicabut</option>
                            </select>
                        </form>
                    </div>

                    @can('issue', [\App\Models\Certificate::class, $event])
                        <form method="POST" action="{{ route('organizer.events.certificates.issue', [$org->slug, $event->slug]) }}" class="mt-4 flex items-end gap-3">
                            @csrf
                            <div>
                                <x-input-label for="min_attendance_pct" value="Ambang kehadiran (opsional, 1–100)" />
                                <x-text-input id="min_attendance_pct" name="min_attendance_pct" type="number" min="1" max="100" class="mt-1 block w-40" :value="old('min_attendance_pct')" />
                                <x-input-error :messages="$errors->get('min_attendance_pct')" class="mt-2" />
                                <x-input-error :messages="$errors->get('threshold')" class="mt-2" />
                            </div>
                            <x-primary-button>Terbitkan sertifikat</x-primary-button>
                        </form>
                    @endcan

                    @if ($certificates->isEmpty())
                        <p class="mt-6 text-sm">Belum ada sertifikat yang diterbitkan pada event ini.</p>
                    @else
                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Nomor</th>
                                        <th class="px-3 py-2 text-left font-medium">Relawan</th>
                                        <th class="px-3 py-2 text-left font-medium">Diterbitkan</th>
                                        <th class="px-3 py-2 text-left font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($certificates as $item)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <a href="{{ route('organizer.events.certificates.show', [$org->slug, $event->slug, $item->id]) }}" class="underline font-medium">{{ $item->certificate_no }}</a>
                                            </td>
                                            <td class="px-3 py-2">{{ $item->user?->name ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $item->issued_at?->format('d M Y H:i') ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $item->isRevoked() ? 'Dicabut' : 'Aktif' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $certificates->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
