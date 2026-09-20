<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Insiden') }} — {{ $event->name }}
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

                    <form method="GET" action="{{ route('organizer.events.incidents.index', [$org->slug, $event->slug]) }}" class="mt-4 flex flex-wrap items-end gap-3 text-sm">
                        <div>
                            <x-input-label for="filter_category" value="Kategori" />
                            <select id="filter_category" name="category" class="mt-1 block rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="">Semua</option>
                                @foreach (\App\Models\Incident::CATEGORIES as $kategori)
                                    <option value="{{ $kategori }}" @selected(request('category') === $kategori)>{{ $kategori }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="filter_status" value="Status" />
                            <select id="filter_status" name="status" class="mt-1 block rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="">Semua</option>
                                @foreach (\App\Models\Incident::STATUSES as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="filter_priority" value="Prioritas" />
                            <select id="filter_priority" name="priority" class="mt-1 block rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="">Semua</option>
                                @foreach (\App\Models\Incident::PRIORITIES as $prioritas)
                                    <option value="{{ $prioritas }}" @selected(request('priority') === $prioritas)>{{ $prioritas }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>Saring</x-primary-button>
                    </form>

                    @can('manage', [\App\Models\Incident::class, $event])
                        @php $bolehLapor = true; @endphp
                    @elsecan('viewAny', [\App\Models\Incident::class, $event])
                        @php $bolehLapor = auth()->user()->can('incident.report'); @endphp
                    @endcan
                    @if (! empty($bolehLapor))
                        <form method="POST" action="{{ route('organizer.events.incidents.store', [$org->slug, $event->slug]) }}" class="mt-6 space-y-4">
                            @csrf
                            <h3 class="font-medium">Lapor insiden baru</h3>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="category" value="Kategori" />
                                    <select id="category" name="category" required class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">
                                        @foreach (\App\Models\Incident::CATEGORIES as $kategori)
                                            <option value="{{ $kategori }}" @selected(old('category') === $kategori)>{{ $kategori }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('category')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="priority" value="Prioritas" />
                                    <select id="priority" name="priority" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">
                                        <option value="">Sedang (bawaan)</option>
                                        @foreach (\App\Models\Incident::PRIORITIES as $prioritas)
                                            <option value="{{ $prioritas }}" @selected(old('priority') === $prioritas)>{{ $prioritas }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="location" value="Lokasi" />
                                    <x-text-input id="location" name="location" type="text" required maxlength="255" class="mt-1 block w-full" :value="old('location')" />
                                    <x-input-error :messages="$errors->get('location')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="description" value="Deskripsi (minimal 10 karakter)" />
                                    <textarea id="description" name="description" rows="3" required class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('description') }}</textarea>
                                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                </div>
                            </div>
                            <x-primary-button>Lapor insiden</x-primary-button>
                        </form>
                    @endif

                    @if ($incidents->isEmpty())
                        <p class="mt-6 text-sm">Belum ada insiden pada event ini.</p>
                    @else
                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Kategori</th>
                                        <th class="px-3 py-2 text-left font-medium">Prioritas</th>
                                        <th class="px-3 py-2 text-left font-medium">Status</th>
                                        <th class="px-3 py-2 text-left font-medium">Lokasi</th>
                                        <th class="px-3 py-2 text-left font-medium">Pelapor</th>
                                        <th class="px-3 py-2 text-left font-medium">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($incidents as $insiden)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <a href="{{ route('organizer.events.incidents.show', [$org->slug, $event->slug, $insiden->id]) }}" class="underline font-medium">{{ $insiden->category }}</a>
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ $insiden->priority }}
                                                @if ($insiden->isCritical())
                                                    <span class="ml-1 inline-block rounded bg-red-600 px-2 py-0.5 text-xs font-bold text-white">KRITIS</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">{{ $insiden->status }}</td>
                                            <td class="px-3 py-2">{{ $insiden->location }}</td>
                                            <td class="px-3 py-2">{{ $insiden->reporter?->name ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $insiden->created_at?->format('d M Y H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $incidents->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
