<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Detail Penugasan') }} — {{ $assignment->user?->name ?? 'Relawan' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
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
                    <p class="text-sm">Event: <span class="font-medium">{{ $event->name }}</span></p>
                    <p class="mt-1 text-sm">Peran: <span class="font-medium">{{ $assignment->role?->name ?? '-' }}</span></p>
                    <p class="mt-1 text-sm">Shift: <span class="font-medium">{{ $assignment->shift?->start_at?->format('d M Y H:i') ?? '-' }} — {{ $assignment->shift?->location ?? '-' }}</span></p>
                    <p class="mt-1 text-sm">Status: <span class="font-medium">{{ $assignment->status }}</span></p>
                    @if ($assignment->location)
                        <p class="mt-1 text-sm">Lokasi penugasan: {{ $assignment->location }}</p>
                    @endif

                    @if ($assignment->histories->isNotEmpty())
                        <h3 class="mt-4 font-semibold">Riwayat penugasan</h3>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($assignment->histories as $history)
                                <li>{{ $history->from_status ?? '-' }} → {{ $history->to_status }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @can('manage', $assignment)
                        <form method="POST" action="{{ route('organizer.events.assignments.reassign', [$org->slug, $event->slug, $assignment->id]) }}" class="mt-6 space-y-3">
                            @csrf
                            <div class="flex flex-wrap items-center gap-2">
                                <label for="shift_id" class="text-sm">Pindah ke shift</label>
                                <select id="shift_id" name="shift_id" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    @foreach ($shifts as $shift)
                                        <option value="{{ $shift->id }}" @selected((int) $assignment->shift_id === (int) $shift->id)>
                                            {{ $shift->start_at?->format('d M Y H:i') ?? $shift->id }} — {{ $shift->location ?? '-' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-primary-button>Pindahkan</x-primary-button>
                            </div>
                        </form>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('organizer.events.assignments.confirm', [$org->slug, $event->slug, $assignment->id]) }}">
                                @csrf
                                <x-primary-button>Konfirmasi</x-primary-button>
                            </form>
                            <form method="POST" action="{{ route('organizer.events.assignments.cancel', [$org->slug, $event->slug, $assignment->id]) }}" class="flex items-center gap-2">
                                @csrf
                                <input name="reason" type="text" placeholder="Alasan pembatalan (opsional)"
                                    class="rounded border-gray-300 dark:bg-gray-700 text-sm" />
                                <x-danger-button>Batalkan</x-danger-button>
                            </form>
                        </div>
                    @endcan

                    <div class="mt-4">
                        <a href="{{ route('organizer.events.assignments.index', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke daftar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
