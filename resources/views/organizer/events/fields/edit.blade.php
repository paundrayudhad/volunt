<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Ubah Field') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('organizer.events.fields.update', [$org->slug, $event->slug, $field->id]) }}" class="p-6 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="label" value="Label field" />
                        <x-text-input id="label" name="label" type="text" class="mt-1 block w-full"
                            :value="old('label', $field->label)" required />
                        <x-input-error :messages="$errors->get('label')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="type" value="Tipe" />
                        <select id="type" name="type" required
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">
                            @foreach ($types as $tipe)
                                <option value="{{ $tipe }}" @selected(old('type', $field->type) == $tipe)>{{ $tipe }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="placeholder" value="Placeholder (opsional)" />
                            <x-text-input id="placeholder" name="placeholder" type="text" class="mt-1 block w-full"
                                :value="old('placeholder', $field->placeholder)" />
                            <x-input-error :messages="$errors->get('placeholder')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="sort_order" value="Urutan" />
                            <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full"
                                :value="old('sort_order', $field->sort_order)" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="validation_rule" value="Aturan validasi tambahan (opsional)" />
                        <x-text-input id="validation_rule" name="validation_rule" type="text" class="mt-1 block w-full"
                            :value="old('validation_rule', $field->validation_rule)" />
                        <x-input-error :messages="$errors->get('validation_rule')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="required" value="1" @checked(old('required', $field->required)) />
                            Wajib diisi
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $field->is_active)) />
                            Aktif
                        </label>
                    </div>

                    <x-primary-button>Simpan perubahan</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
