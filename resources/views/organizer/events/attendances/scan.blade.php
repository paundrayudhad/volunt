<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Pindai Kehadiran') }} — {{ $event->name }}
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
                    <form method="POST" action="{{ route('organizer.events.attendances.process', [$org->slug, $event->slug]) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="token" class="text-sm">Kode kehadiran</label>
                            <input id="token" name="token" type="text" value="{{ old('token') }}" required
                                class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm font-mono"
                                placeholder="Tempel kode 64 karakter dari relawan" />
                        </div>
                        <div>
                            <label for="action" class="text-sm">Aksi</label>
                            <select id="action" name="action" class="mt-1 block rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="check_in" @selected(old('action', 'check_in') === 'check_in')>Check-in</option>
                                <option value="check_out" @selected(old('action') === 'check_out')>Check-out</option>
                            </select>
                        </div>
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}" />
                        <x-primary-button>Catat kehadiran</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('organizer.events.attendances.manual', [$org->slug, $event->slug]) }}" class="mt-8 space-y-3">
                        @csrf
                        <h3 class="font-semibold">Catatan manual</h3>
                        <div>
                            <label for="assignment_id" class="text-sm">ID penugasan</label>
                            <input id="assignment_id" name="assignment_id" type="number" value="{{ old('assignment_id') }}" required
                                class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm" />
                        </div>
                        <div>
                            <label for="reason" class="text-sm">Alasan (minimal 10 karakter)</label>
                            <input id="reason" name="reason" type="text" value="{{ old('reason') }}"
                                class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm"
                                placeholder="Contoh: pemindai rusak, dicatat manual oleh koordinator" />
                        </div>
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}" />
                        <x-primary-button>Catat manual</x-primary-button>
                    </form>

                    <div class="mt-4">
                        <a href="{{ route('organizer.events.attendances.index', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke daftar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
