<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Profil Volunteer') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('profile.volunteer.update') }}" class="p-6 space-y-4">
                    @csrf
                    @method('PATCH')

                    @if (session('status'))
                        <div class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 p-4 rounded">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div>
                        <x-input-label for="full_name" value="Nama lengkap *" />
                        <x-text-input id="full_name" name="full_name" type="text" class="mt-1 block w-full"
                            :value="old('full_name', $profile->full_name)" required />
                        <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="phone" value="Nomor telepon" />
                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                                :value="old('phone', $profile->phone)" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="city" value="Kota" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full"
                                :value="old('city', $profile->city)" />
                            <x-input-error :messages="$errors->get('city')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="education" value="Pendidikan" />
                            <x-text-input id="education" name="education" type="text" class="mt-1 block w-full"
                                :value="old('education', $profile->education)" />
                            <x-input-error :messages="$errors->get('education')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="date_of_birth" value="Tanggal lahir" />
                            <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full"
                                :value="old('date_of_birth', $profile->date_of_birth?->format('Y-m-d'))" />
                            <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="experience" value="Pengalaman" />
                        <textarea id="experience" name="experience" rows="3"
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">{{ old('experience', $profile->experience) }}</textarea>
                        <x-input-error :messages="$errors->get('experience')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="portfolio_url" value="Tautan portofolio" />
                        <x-text-input id="portfolio_url" name="portfolio_url" type="url" class="mt-1 block w-full"
                            :value="old('portfolio_url', $profile->portfolio_url)" />
                        <x-input-error :messages="$errors->get('portfolio_url')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="emergency_contact" value="Kontak darurat" />
                        <textarea id="emergency_contact" name="emergency_contact" rows="2"
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">{{ old('emergency_contact', $profile->emergency_contact) }}</textarea>
                        <x-input-error :messages="$errors->get('emergency_contact')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="visibility" value="Visibilitas profil *" />
                        <select id="visibility" name="visibility" required
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">
                            @foreach (['public' => 'Publik', 'organizers_only' => 'Hanya penyelenggara', 'private' => 'Pribadi'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('visibility', $profile->visibility) == $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('visibility')" class="mt-2" />
                    </div>

                    <x-primary-button>Simpan profil</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
