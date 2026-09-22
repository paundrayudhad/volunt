<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 p-4 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="text-sm">Status: <strong>{{ $event->status }}</strong></p>
                    <p class="mt-2 text-sm">{{ $event->description ?? 'Belum ada deskripsi.' }}</p>
                    <p class="mt-2 text-sm">Jadwal: {{ $event->start_at?->format('d M Y H:i') }} — {{ $event->end_at?->format('d M Y H:i') }}</p>
                    @if ($event->venue)
                        <p class="mt-1 text-sm">Tempat: {{ $event->venue }}</p>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-4">
                        <a href="{{ route('organizer.events.index', $org->slug) }}" class="underline">Kembali ke daftar</a>
                        @can('update', $event)
                            <a href="{{ route('organizer.events.edit', [$org->slug, $event->slug]) }}" class="underline">Ubah event</a>
                        @endcan
                        @can('viewAny', [\App\Models\Artist::class, $event])
                            <a href="{{ route('organizer.events.artists.index', [$org->slug, $event->slug]) }}" class="underline">Kelola Artis</a>
                        @endcan
                    </div>

                    @can('publish', $event)
                        <form method="POST" action="{{ route('organizer.events.transition', [$org->slug, $event->slug]) }}" class="mt-6 max-w-md">
                            @csrf
                            <x-input-label for="status" value="Ubah status event (perlu konfirmasi kata sandi)" />
                            <select id="status" name="status" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">
                                @foreach (\App\Services\EventService::TRANSITIONS[$event->status] ?? [] as $berikutnya)
                                    <option value="{{ $berikutnya }}">{{ $berikutnya }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            <div class="mt-3">
                                <x-input-label for="reason" value="Alasan (wajib bila membatalkan)" />
                                <textarea id="reason" name="reason" rows="2"
                                    class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">{{ old('reason') }}</textarea>
                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                            </div>
                            <x-primary-button class="mt-3">Ubah status</x-primary-button>
                        </form>
                    @endcan

                    @can('delete', $event)
                        <form method="POST" action="{{ route('organizer.events.destroy', [$org->slug, $event->slug]) }}" class="mt-6">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>Hapus event</x-danger-button>
                        </form>
                    @endcan
                </div>
            </div>

            @can('viewAny', [\App\Models\Artist::class, $event])
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="font-semibold">Artis ({{ $event->artists_count }})</h3>
                        @if ($event->artists->isEmpty())
                            <p class="mt-2 text-sm text-gray-500">Belum ada artis pada event ini.</p>
                        @else
                            <ul class="mt-2 text-sm space-y-1">
                                @foreach ($event->artists as $artis)
                                    <li>
                                        <a href="{{ route('organizer.events.artists.show', [$org->slug, $event->slug, $artis->id]) }}" class="underline">{{ $artis->name }}</a>
                                        <span class="text-gray-500">— {{ $artis->scheduled_at?->format('d M Y H:i') ?? 'Jadwal menyusul' }} ({{ $artis->status }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <a href="{{ route('organizer.events.artists.index', [$org->slug, $event->slug]) }}" class="underline text-sm mt-3 inline-block">Kelola Artis</a>
                    </div>
                </div>
            @endcan

            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="font-semibold">Divisi ({{ $event->divisions->count() }})</h3>
                        <ul class="mt-2 text-sm space-y-1">
                            @foreach ($event->divisions as $divisi)
                                <li>
                                    <a href="{{ route('organizer.events.divisions.show', [$org->slug, $event->slug, $divisi->id]) }}" class="underline">{{ $divisi->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                        @can('manage', [\App\Models\EventDivision::class, $event])
                            <a href="{{ route('organizer.events.divisions.create', [$org->slug, $event->slug]) }}" class="underline text-sm mt-3 inline-block">Tambah divisi</a>
                        @endcan
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="font-semibold">Role ({{ $event->roles->count() }})</h3>
                        <ul class="mt-2 text-sm space-y-1">
                            @foreach ($event->roles as $peran)
                                <li>
                                    <a href="{{ route('organizer.events.roles.show', [$org->slug, $event->slug, $peran->id]) }}" class="underline">{{ $peran->name }}</a>
                                    <span class="text-gray-500">— Sisa {{ $peran->remainingQuota() }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @can('manage', [\App\Models\EventRole::class, $event])
                            <a href="{{ route('organizer.events.roles.create', [$org->slug, $event->slug]) }}" class="underline text-sm mt-3 inline-block">Tambah role</a>
                        @endcan
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="font-semibold">Shift ({{ $event->shifts->count() }})</h3>
                        <ul class="mt-2 text-sm space-y-1">
                            @foreach ($event->shifts as $shift)
                                <li>
                                    <a href="{{ route('organizer.events.shifts.show', [$org->slug, $event->slug, $shift->id]) }}" class="underline">{{ $shift->start_at?->format('d M Y H:i') }}</a>
                                    <span class="text-gray-500">{{ $shift->location ?? '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @can('manage', [\App\Models\EventShift::class, $event])
                            <a href="{{ route('organizer.events.shifts.create', [$org->slug, $event->slug]) }}" class="underline text-sm mt-3 inline-block">Tambah shift</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
