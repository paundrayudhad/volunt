<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Detail Pendaftar') }} — {{ $pendaftaran->user?->name ?? 'Pendaftar' }}
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
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="text-sm">Event: <span class="font-medium">{{ $event->name }}</span></p>
                    <p class="mt-1 text-sm">Peran: <span class="font-medium">{{ $pendaftaran->role?->name ?? '-' }}</span></p>
                    <p class="mt-1 text-sm">Status: <span class="font-medium">{{ $pendaftaran->status }}</span></p>
                    @if ($pendaftaran->rejection_reason)
                        <p class="mt-1 text-sm">Alasan penolakan: {{ $pendaftaran->rejection_reason }}</p>
                    @endif

                    @if ($pendaftaran->answers->isNotEmpty())
                        <h3 class="mt-4 font-semibold">Jawaban</h3>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($pendaftaran->answers as $answer)
                                <li>
                                    <span class="font-medium">{{ $answer->field?->label ?? 'Field' }}:</span>
                                    {{ $answer->value_text ?? (is_array($answer->value_jsonb) ? implode(', ', $answer->value_jsonb) : $answer->file_path) }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($pendaftaran->histories->isNotEmpty())
                        <h3 class="mt-4 font-semibold">Riwayat status</h3>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($pendaftaran->histories as $history)
                                <li>{{ $history->from_status ?? '-' }} → {{ $history->to_status }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('organizer.events.registrations.review', [$org->slug, $event->slug, $pendaftaran->id]) }}" class="mt-6 space-y-3">
                        @csrf
                        <div class="flex flex-wrap items-center gap-2">
                            <label for="action" class="text-sm">Keputusan</label>
                            <select id="action" name="action" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="accepted">Terima</option>
                                <option value="rejected">Tolak</option>
                                <option value="waitlisted">Waitlist</option>
                                <option value="cancelled">Batalkan</option>
                            </select>
                        </div>
                        <div>
                            <label for="reason" class="text-sm">Alasan (wajib saat menolak)</label>
                            <input id="reason" name="reason" type="text" value="{{ old('reason') }}"
                                class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm"
                                placeholder="Tulis alasan keputusan" />
                        </div>
                        <x-primary-button>Simpan keputusan</x-primary-button>
                    </form>

                    <div class="mt-4">
                        <a href="{{ route('organizer.events.registrations.index', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke daftar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
