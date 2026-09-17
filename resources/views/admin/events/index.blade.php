<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Kelola Event') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 p-4 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="text-sm">Pantau semua event lintas organisasi. Pembatalan paksa memerlukan konfirmasi password dan alasan; tindakan tercatat di log audit.</p>

                    <form method="GET" action="{{ route('admin.events.index') }}" class="mt-4 flex flex-wrap items-center gap-2">
                        <label for="status" class="text-sm">Status</label>
                        <select id="status" name="status" class="rounded border-gray-300 dark:bg-gray-700 text-sm">
                            <option value="">Semua status</option>
                            @foreach ($daftarStatus as $status)
                                <option value="{{ $status }}" @selected($statusDipilih === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <x-primary-button>Filter</x-primary-button>
                    </form>

                    <ul class="mt-4 space-y-4">
                        @forelse ($acara as $event)
                            <li class="border-b pb-4">
                                <p class="font-semibold">{{ $event->name }} ({{ $event->slug }})</p>
                                <p class="text-sm">Organisasi: {{ $event->organization?->name ?? '-' }} &middot; Status: {{ $event->status }}</p>

                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <form method="POST" action="{{ route('admin.events.cancel', $event->id) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <label for="cancel-{{ $event->id }}" class="text-sm">Alasan pembatalan</label>
                                        <input id="cancel-{{ $event->id }}" name="reason" type="text" required
                                            class="rounded border-gray-300 dark:bg-gray-700 text-sm"
                                            placeholder="Tulis alasan" />
                                        <x-danger-button>Batalkan paksa</x-danger-button>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li>Belum ada event.</li>
                        @endforelse
                    </ul>

                    <div class="mt-4">
                        {{ $acara->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
