<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Penugasan Relawan') }} — {{ $event->name }}
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

                    <form method="GET" action="{{ route('organizer.events.assignments.index', [$org->slug, $event->slug]) }}" class="mt-4 flex flex-wrap items-center gap-2">
                        <label for="status" class="text-sm">Status</label>
                        <select id="status" name="status" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                            <option value="">Semua status</option>
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <label for="shift" class="text-sm">Shift</label>
                        <select id="shift" name="shift" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                            <option value="">Semua shift</option>
                            @foreach ($shifts as $shift)
                                <option value="{{ $shift->id }}" @selected($selectedShift === (string) $shift->id)>
                                    {{ $shift->start_at?->format('d M Y H:i') ?? $shift->id }} — {{ $shift->location ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                        <x-primary-button>Filter</x-primary-button>
                    </form>

                    @can('manage', [\App\Models\Assignment::class, $event])
                        <form method="POST" action="{{ route('organizer.events.assignments.assign', [$org->slug, $event->slug]) }}" class="mt-6 space-y-3">
                            @csrf
                            <h3 class="font-semibold">Tugaskan relawan</h3>
                            <div class="flex flex-wrap items-center gap-2">
                                <label for="registration_id" class="text-sm">Pendaftar diterima</label>
                                <select id="registration_id" name="registration_id" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    @foreach ($candidates as $candidate)
                                        <option value="{{ $candidate->id }}">{{ $candidate->user?->name ?? 'Pendaftar' }} #{{ $candidate->id }}</option>
                                    @endforeach
                                </select>
                                <label for="shift_id" class="text-sm">Shift</label>
                                <select id="shift_id" name="shift_id" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    @foreach ($shifts as $shift)
                                        <option value="{{ $shift->id }}">
                                            {{ $shift->start_at?->format('d M Y H:i') ?? $shift->id }} — {{ $shift->location ?? '-' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="location" class="text-sm">Lokasi khusus (opsional)</label>
                                <input id="location" name="location" type="text" value="{{ old('location') }}"
                                    class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm"
                                    placeholder="Kosongkan untuk memakai lokasi shift" />
                            </div>
                            <x-primary-button>Tugaskan</x-primary-button>
                        </form>
                    @endcan

                    @if ($assignments->isEmpty())
                        <p class="mt-6 text-sm">Belum ada penugasan pada event ini.</p>
                    @else
                        <ul class="mt-6 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($assignments as $item)
                                <li class="py-3">
                                    <a href="{{ route('organizer.events.assignments.show', [$org->slug, $event->slug, $item->id]) }}" class="underline font-medium">{{ $item->user?->name ?? 'Relawan' }}</a>
                                    <p class="text-sm text-gray-500">Peran: {{ $item->role?->name ?? '-' }} — Shift: {{ $item->shift?->start_at?->format('d M Y H:i') ?? '-' }} — Status: {{ $item->status }}</p>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-4">
                            {{ $assignments->links() }}
                        </div>
                    @endif

                    @can('manage', [\App\Models\Assignment::class, $event])
                        <form method="POST" action="{{ route('organizer.events.assignments.bulk', [$org->slug, $event->slug]) }}" class="mt-6 space-y-3">
                            @csrf
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($candidates as $candidate)
                                    <li class="py-3 flex items-start gap-3">
                                        <input type="checkbox" name="ids[]" value="{{ $candidate->id }}" class="mt-1" />
                                        <div>
                                            <p class="font-medium">{{ $candidate->user?->name ?? 'Pendaftar' }}</p>
                                            <p class="text-sm text-gray-500">Peran: {{ $candidate->role?->name ?? '-' }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="flex flex-wrap items-center gap-2">
                                <label for="bulk-shift" class="text-sm">Shift tujuan</label>
                                <select id="bulk-shift" name="shift_id" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    @foreach ($shifts as $shift)
                                        <option value="{{ $shift->id }}">
                                            {{ $shift->start_at?->format('d M Y H:i') ?? $shift->id }} — {{ $shift->location ?? '-' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-primary-button>Terapkan penugasan massal</x-primary-button>
                            </div>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
