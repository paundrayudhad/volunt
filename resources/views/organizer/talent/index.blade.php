<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Talent Pool — {{ $org->name }}
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
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-bold">Database Relawan Internal</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Cari relawan yang pernah berpartisipasi pada event {{ $org->name }}.
                            </p>
                        </div>
                        <a href="{{ route('organizer.show', $org->slug) }}" class="underline text-sm">Kembali ke organisasi</a>
                    </div>

                    <form method="GET" action="{{ route('organizer.talent.index', $org->slug) }}" class="grid sm:grid-cols-3 gap-4 mb-6">
                        <div>
                            <x-input-label for="q" value="Kata Kunci (Nama / Email)" />
                            <x-text-input id="q" name="q" type="text" class="mt-1 block w-full text-sm" :value="request('q')" placeholder="Cari nama atau email..." />
                        </div>
                        <div>
                            <x-input-label for="city" value="Domisili / Kota" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full text-sm" :value="request('city')" placeholder="Jakarta, Bandung, dll" />
                        </div>
                        <div>
                            <x-input-label for="skills" value="Keahlian / Skill" />
                            <x-text-input id="skills" name="skills" type="text" class="mt-1 block w-full text-sm" :value="request('skills')" placeholder="Design, Medis, dll" />
                        </div>
                        <div class="sm:col-span-3 flex justify-end gap-2">
                            <a href="{{ route('organizer.talent.index', $org->slug) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition">Reset</a>
                            <x-primary-button>Cari Relawan</x-primary-button>
                        </div>
                    </form>

                    @if ($talents->isEmpty())
                        <div class="text-center py-8 text-sm text-gray-500">
                            Tidak ada relawan yang sesuai dengan kriteria pencarian.
                        </div>
                    @else
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach ($talents as $relawan)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5 flex flex-col justify-between hover:shadow-md transition">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-bold text-base">
                                                <a href="{{ route('organizer.talent.show', [$org->slug, $relawan->id]) }}" class="hover:underline">
                                                    {{ $relawan->name }}
                                                </a>
                                            </h4>
                                            @if ($relawan->volunteerProfile?->city)
                                                <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded text-gray-600 dark:text-gray-300">
                                                    {{ $relawan->volunteerProfile->city }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">{{ $relawan->email }}</p>

                                        @if ($relawan->volunteerProfile?->skills)
                                            <div class="mt-3 flex flex-wrap gap-1">
                                                @foreach (explode(',', $relawan->volunteerProfile->skills) as $skill)
                                                    <span class="text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-100 px-2 py-0.5 rounded">
                                                        {{ trim($skill) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($relawan->volunteerProfile?->bio)
                                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-2 line-clamp-2">
                                                {{ $relawan->volunteerProfile->bio }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                        <a href="{{ route('organizer.talent.show', [$org->slug, $relawan->id]) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                            Lihat Portofolio &rarr;
                                        </a>

                                        @can('inviteTalent', $org)
                                            @if ($activeEvents->isNotEmpty())
                                                <details class="relative">
                                                    <summary class="cursor-pointer inline-flex items-center px-3 py-1 bg-indigo-600 text-white rounded text-xs font-semibold hover:bg-indigo-500">
                                                        Undang
                                                    </summary>
                                                    <div class="absolute right-0 bottom-full mb-2 w-72 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded shadow-xl p-4 z-20">
                                                        <h5 class="text-xs font-bold mb-2">Undang ke Event</h5>
                                                        <form method="POST" action="" onsubmit="this.action = '/organizer/{{ $org->slug }}/events/' + this.event_slug.value + '/invitations';">
                                                            @csrf
                                                            <input type="hidden" name="user_id" value="{{ $relawan->id }}">
                                                            <div class="mb-2">
                                                                <x-input-label for="event_{{ $relawan->id }}" value="Pilih Event" class="text-xs" />
                                                                <select id="event_{{ $relawan->id }}" name="event_slug" class="mt-1 block w-full text-xs rounded border-gray-300 dark:bg-gray-700" required>
                                                                    <option value="">-- Pilih Event --</option>
                                                                    @foreach ($activeEvents as $evt)
                                                                        <option value="{{ $evt->slug }}">{{ $evt->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="mb-2">
                                                                <x-input-label for="msg_{{ $relawan->id }}" value="Pesan (opsional)" class="text-xs" />
                                                                <textarea id="msg_{{ $relawan->id }}" name="message" rows="2" maxlength="1000" class="mt-1 block w-full text-xs rounded border-gray-300 dark:bg-gray-700" placeholder="Pesan ajakan..."></textarea>
                                                            </div>
                                                            <button type="submit" class="w-full py-1 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded">
                                                                Kirim Undangan
                                                            </button>
                                                        </form>
                                                    </div>
                                                </details>
                                            @endif
                                        @endcan
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $talents->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
