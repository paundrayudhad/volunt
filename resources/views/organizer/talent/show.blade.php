<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Portofolio Relawan — {{ $talent->name }}
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
                <div class="p-6 text-gray-900 dark:text-gray-100 space-y-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-bold">{{ $talent->name }}</h3>
                            <p class="text-sm text-gray-500">{{ $talent->email }}</p>
                        </div>
                        <a href="{{ route('organizer.talent.index', $org->slug) }}" class="underline text-sm">Kembali ke Talent Pool</a>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6 bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
                        <div>
                            <h4 class="text-xs font-semibold uppercase text-gray-500 tracking-wider">Informasi Profil</h4>
                            <p class="text-sm mt-2"><strong>Domisili:</strong> {{ $talent->volunteerProfile?->city ?? '—' }}</p>
                            <p class="text-sm mt-1"><strong>Keahlian:</strong> {{ $talent->volunteerProfile?->skills ?? '—' }}</p>
                            <p class="text-sm mt-1"><strong>Bio:</strong> {{ $talent->volunteerProfile?->bio ?? '—' }}</p>
                        </div>
                        <div>
                            <h4 class="text-xs font-semibold uppercase text-gray-500 tracking-wider">Statistik Internal di {{ $org->name }}</h4>
                            <p class="text-sm mt-2"><strong>Total Partisipasi Event:</strong> {{ $participations->count() }} event</p>
                            <p class="text-sm mt-1"><strong>Sertifikat Diperoleh:</strong> {{ $certificates->count() }} sertifikat</p>
                        </div>
                    </div>

                    @can('inviteTalent', $org)
                        @if ($activeEvents->isNotEmpty())
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h4 class="text-base font-bold mb-4">Undang {{ $talent->name }} ke Event Baru</h4>
                                <form method="POST" action="" onsubmit="this.action = '/organizer/{{ $org->slug }}/events/' + this.event_slug.value + '/invitations';" class="grid sm:grid-cols-3 gap-4">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $talent->id }}">
                                    <div>
                                        <x-input-label for="event_slug" value="Pilih Event" />
                                        <select id="event_slug" name="event_slug" class="mt-1 block w-full text-sm rounded border-gray-300 dark:bg-gray-700" required>
                                            <option value="">-- Pilih Event --</option>
                                            @foreach ($activeEvents as $evt)
                                                <option value="{{ $evt->slug }}">{{ $evt->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <x-input-label for="message" value="Pesan Undangan (opsional)" />
                                        <x-text-input id="message" name="message" type="text" maxlength="1000" class="mt-1 block w-full text-sm" placeholder="Kami mengundang Anda untuk bergabung kembali..." />
                                    </div>
                                    <div class="sm:col-span-3 flex justify-end">
                                        <x-primary-button>Kirim Undangan</x-primary-button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    @endcan

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h4 class="text-base font-bold mb-4">Riwayat Event di {{ $org->name }}</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium">Nama Event</th>
                                        <th class="px-4 py-2 text-left font-medium">Role</th>
                                        <th class="px-4 py-2 text-left font-medium">Status Pendaftaran</th>
                                        <th class="px-4 py-2 text-left font-medium">Waktu Daftar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($participations as $part)
                                        <tr>
                                            <td class="px-4 py-2 font-medium">{{ $part->event?->name }}</td>
                                            <td class="px-4 py-2">{{ $part->role?->name ?? '—' }}</td>
                                            <td class="px-4 py-2">
                                                <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded font-semibold">
                                                    {{ $part->status }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-gray-500">{{ $part->created_at->format('d M Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
