<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Tambah Field') }} — {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('organizer.events.fields.store', [$org->slug, $event->slug]) }}" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="label" value="Label field" />
                        <x-text-input id="label" name="label" type="text" class="mt-1 block w-full"
                            :value="old('label')" required />
                        <x-input-error :messages="$errors->get('label')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="type" value="Tipe" />
                        <select id="type" name="type" required
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('type') == $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="placeholder" value="Placeholder (opsional)" />
                            <x-text-input id="placeholder" name="placeholder" type="text" class="mt-1 block w-full"
                                :value="old('placeholder')" />
                            <x-input-error :messages="$errors->get('placeholder')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="sort_order" value="Urutan" />
                            <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full"
                                :value="old('sort_order', 0)" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="required" value="1" @checked(old('required')) />
                            Wajib diisi
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) />
                            Aktif
                        </label>
                    </div>

                    <div>
                        <x-input-label value="Opsi (khusus: {{ implode(', ', $optionTypes) }})" />
                        <p class="text-xs text-gray-500">Format satu baris: label|value — contoh: Kecil|S</p>
                        <textarea id="options" name="options_text" rows="3"
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">{{ old('options_text') }}</textarea>
                        <x-input-error :messages="$errors->get('options')" class="mt-2" />
                    </div>

                    <x-primary-button>Simpan field</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
