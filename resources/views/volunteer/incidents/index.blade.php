<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Insiden Saya') }}
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
                    <h3 class="font-medium">Lapor insiden baru</h3>
                    <form method="POST" action="{{ route('my.incidents.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="event_id" value="Event" />
                                <select id="event_id" name="event_id" required class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">
                                    @foreach ($events as $event)
                                        <option value="{{ $event->id }}" @selected((int) old('event_id') === $event->id)>{{ $event->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('event_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="location" value="Lokasi" />
                                <x-text-input id="location" name="location" type="text" required maxlength="255" class="mt-1 block w-full" :value="old('location')" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>
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
                                    <option value="">Sedang</option>
                                    @foreach (\App\Models\Incident::PRIORITIES as $prioritas)
                                        <option value="{{ $prioritas }}" @selected(old('priority') === $prioritas)>{{ $prioritas }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="description" value="Deskripsi" />
                            <textarea id="description" name="description" required rows="3" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                        <x-primary-button>Kirim laporan</x-primary-button>
                    </form>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-medium">Laporanku</h3>
                    @if ($incidents->isEmpty())
                        <p class="mt-2 text-sm">Belum ada laporan insiden.</p>
                    @else
                        <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($incidents as $insiden)
                                <li class="py-3 flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $insiden->location }}
                                            @if ($insiden->priority === 'critical')
                                                <span class="ml-2 inline-block rounded bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">KRITIS</span>
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-500">{{ $insiden->event?->name ?? 'Event' }} — {{ $insiden->category }} — Status: {{ $insiden->status }}</p>
                                        <p class="text-sm text-gray-500">{{ $insiden->created_at?->format('d M Y H:i') ?? '—' }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $incidents->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
