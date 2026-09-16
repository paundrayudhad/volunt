<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Undang Anggota') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('organizer.members.store', $org->slug) }}" class="p-6 space-y-4">
                    @csrf

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Undangan hanya untuk role staff. Calon anggota menerima undangan lewat daftar undangan mereka.
                    </p>

                    <div>
                        <x-input-label for="email" value="Email calon anggota" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                            :value="old('email')" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <x-primary-button>Kirim undangan</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
