<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Buat Pengumuman') }} — {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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
                    <a href="{{ route('organizer.events.announcements.index', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke daftar pengumuman</a>

                    <form method="POST" action="{{ route('organizer.events.announcements.store', [$org->slug, $event->slug]) }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="title" value="Judul" />
                            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required maxlength="255" />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="body" value="Isi pengumuman" />
                            <textarea id="body" name="body" rows="5" required class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('body') }}</textarea>
                            <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="target_type" value="Target penerima" />
                            <select id="target_type" name="target_type" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">
                                <option value="event" @selected(old('target_type') === 'event')>Seluruh event</option>
                                <option value="division" @selected(old('target_type') === 'division')>Per divisi</option>
                                <option value="role" @selected(old('target_type') === 'role')>Per peran</option>
                                <option value="shift" @selected(old('target_type') === 'shift')>Per shift</option>
                                <option value="individual" @selected(old('target_type') === 'individual')>Individu</option>
                            </select>
                            <x-input-error :messages="$errors->get('target_type')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="target_id" value="ID target (opsional untuk seluruh event)" />
                            <x-text-input id="target_id" name="target_id" type="number" class="mt-1 block w-full" :value="old('target_id')" />
                            <x-input-error :messages="$errors->get('target_id')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">Isi dengan ID divisi, peran, shift, atau pengguna sesuai target yang dipilih.</p>
                        </div>
                        <div>
                            <x-input-label for="expires_at" value="Kedaluwarsa (opsional)" />
                            <x-text-input id="expires_at" name="expires_at" type="datetime-local" class="mt-1 block w-full" :value="old('expires_at')" />
                            <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
                        </div>
                        <x-primary-button>Simpan sebagai draf</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
