<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Tambah Role') }} — {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('organizer.events.roles.store', [$org->slug, $event->slug]) }}" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="division_id" value="Divisi" />
                        <select id="division_id" name="division_id" required
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">
                            @foreach ($divisi as $satu)
                                <option value="{{ $satu->id }}" @selected(old('division_id') == $satu->id)>{{ $satu->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('division_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" value="Nama role" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="quota" value="Kuota" />
                        <x-text-input id="quota" name="quota" type="number" min="0" class="mt-1 block w-full"
                            :value="old('quota', 0)" />
                        <x-input-error :messages="$errors->get('quota')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="location" value="Lokasi (opsional)" />
                        <x-text-input id="location" name="location" type="text" class="mt-1 block w-full"
                            :value="old('location')" />
                        <x-input-error :messages="$errors->get('location')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Deskripsi" />
                        <textarea id="description" name="description" rows="3"
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <x-primary-button>Simpan role</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
