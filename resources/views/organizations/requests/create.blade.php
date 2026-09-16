<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Pengajuan Organisasi') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('organizations.requests.store') }}" class="p-6 space-y-4">
                    @csrf

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Ajukan organisasi baru. Pengajuan Anda akan ditinjau admin sebelum aktif.
                    </p>

                    <div>
                        <x-input-label for="name" value="Nama organisasi" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="slug" value="Slug (huruf kecil, angka, tanda hubung)" />
                        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full"
                            :value="old('slug')" required />
                        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Deskripsi kegiatan" />
                        <textarea id="description" name="description" rows="4"
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="contact_email" value="Email kontak" />
                            <x-text-input id="contact_email" name="contact[email]" type="email" class="mt-1 block w-full"
                                :value="old('contact.email')" />
                            <x-input-error :messages="$errors->get('contact.email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="contact_phone" value="Telepon kontak" />
                            <x-text-input id="contact_phone" name="contact[phone]" type="text" class="mt-1 block w-full"
                                :value="old('contact.phone')" />
                            <x-input-error :messages="$errors->get('contact.phone')" class="mt-2" />
                        </div>
                    </div>

                    <x-primary-button>Kirim pengajuan</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
