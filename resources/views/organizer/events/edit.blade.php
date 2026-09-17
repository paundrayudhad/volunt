<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Ubah Event') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('organizer.events.update', [$org->slug, $event->slug]) }}" class="p-6 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="name" value="Nama event" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            :value="old('name', $event->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="slug" value="Slug (huruf kecil, angka, tanda hubung)" />
                        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full"
                            :value="old('slug', $event->slug)" required />
                        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="category" value="Kategori" />
                            <x-text-input id="category" name="category" type="text" class="mt-1 block w-full"
                                :value="old('category', $event->category)" />
                            <x-input-error :messages="$errors->get('category')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="venue" value="Tempat" />
                            <x-text-input id="venue" name="venue" type="text" class="mt-1 block w-full"
                                :value="old('venue', $event->venue)" />
                            <x-input-error :messages="$errors->get('venue')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="description" value="Deskripsi" />
                        <textarea id="description" name="description" rows="4"
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">{{ old('description', $event->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="capacity" value="Kapasitas (opsional)" />
                        <x-text-input id="capacity" name="capacity" type="number" min="0" class="mt-1 block w-full"
                            :value="old('capacity', $event->capacity)" />
                        <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                    </div>

                    <x-primary-button>Simpan perubahan</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
