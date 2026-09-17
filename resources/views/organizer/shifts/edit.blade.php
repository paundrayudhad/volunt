<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Ubah Shift') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('organizer.events.shifts.update', [$org->slug, $event->slug, $shift->id]) }}" class="p-6 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="division_id" value="Divisi" />
                        <select id="division_id" name="division_id"
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">
                            @foreach ($divisi as $satu)
                                <option value="{{ $satu->id }}" @selected(old('division_id', $shift->division_id) == $satu->id)>{{ $satu->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('division_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role_id" value="Role (opsional, harus satu event)" />
                        <select id="role_id" name="role_id"
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">
                            <option value="">Tanpa role</option>
                            @foreach ($peran as $satu)
                                <option value="{{ $satu->id }}" @selected(old('role_id', $shift->role_id) == $satu->id)>{{ $satu->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="start_at" value="Waktu mulai" />
                            <x-text-input id="start_at" name="start_at" type="datetime-local" class="mt-1 block w-full"
                                :value="old('start_at', $shift->start_at?->format('Y-m-d\TH:i'))" required />
                            <x-input-error :messages="$errors->get('start_at')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="end_at" value="Waktu selesai" />
                            <x-text-input id="end_at" name="end_at" type="datetime-local" class="mt-1 block w-full"
                                :value="old('end_at', $shift->end_at?->format('Y-m-d\TH:i'))" required />
                            <x-input-error :messages="$errors->get('end_at')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="location" value="Lokasi (opsional)" />
                        <x-text-input id="location" name="location" type="text" class="mt-1 block w-full"
                            :value="old('location', $shift->location)" />
                        <x-input-error :messages="$errors->get('location')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="capacity" value="Kapasitas (opsional)" />
                        <x-text-input id="capacity" name="capacity" type="number" min="0" class="mt-1 block w-full"
                            :value="old('capacity', $shift->capacity)" />
                        <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                    </div>

                    <x-primary-button>Simpan perubahan</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
