<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Kelola Pendaftaran') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="text-sm">Pantau semua pendaftaran lintas organisasi. Panel ini read-only; keputusan seleksi dilakukan oleh organizer masing-masing event.</p>

                    <form method="GET" action="{{ route('admin.registrations.index') }}" class="mt-4 flex flex-wrap items-center gap-2">
                        <label for="status" class="text-sm">Status</label>
                        <select id="status" name="status" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                            <option value="">Semua status</option>
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <label for="org" class="text-sm">Organisasi</label>
                        <select id="org" name="org" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                            <option value="">Semua organisasi</option>
                            @foreach ($orgOptions as $orgFilter)
                                <option value="{{ $orgFilter->id }}" @selected($selectedOrg === $orgFilter->id)>{{ $orgFilter->name }}</option>
                            @endforeach
                        </select>
                        <x-primary-button>Filter</x-primary-button>
                    </form>

                    <ul class="mt-4 space-y-4">
                        @forelse ($registrations as $item)
                            <li class="border-b pb-4">
                                <a href="{{ route('admin.registrations.show', $item->id) }}" class="underline font-medium">{{ $item->user?->name ?? 'Pendaftar' }}</a>
                                <p class="text-sm">Event: {{ $item->event?->name ?? '-' }} &middot; Organisasi: {{ $item->event?->organization?->name ?? '-' }} &middot; Status: {{ $item->status }}</p>
                            </li>
                        @empty
                            <li>Belum ada pendaftaran.</li>
                        @endforelse
                    </ul>

                    <div class="mt-4">
                        {{ $registrations->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
