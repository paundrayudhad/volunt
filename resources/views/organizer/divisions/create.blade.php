<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Tambah Divisi') }} — {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('organizer.events.divisions.store', [$org->slug, $event->slug]) }}" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nama divisi" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Deskripsi" />
                        <textarea id="description" name="description" rows="3"
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="supervisor_id" value="ID supervisor (opsional, harus anggota aktif)" />
                        <x-text-input id="supervisor_id" name="supervisor_id" type="number" class="mt-1 block w-full"
                            :value="old('supervisor_id')" />
                        <x-input-error :messages="$errors->get('supervisor_id')" class="mt-2" />
                    </div>

                    <x-primary-button>Simpan divisi</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
