<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Kelola Organisasi') }}
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
                    <p class="text-sm">Tangguhkan, arsipkan, atau aktifkan kembali organisasi. Tindakan sensitif memerlukan konfirmasi password dan alasan.</p>

                    <ul class="mt-4 space-y-4">
                        @forelse ($organisasi as $org)
                            <li class="border-b pb-4">
                                <p class="font-semibold">{{ $org->name }} ({{ $org->slug }})</p>
                                <p class="text-sm">Status: {{ $org->status }}</p>

                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <form method="POST" action="{{ route('admin.organizations.suspend', $org->id) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <label for="suspend-{{ $org->id }}" class="text-sm">Alasan penangguhan</label>
                                        <input id="suspend-{{ $org->id }}" name="reason" type="text" required
                                            class="rounded border-gray-300 dark:bg-gray-700 text-sm"
                                            placeholder="Tulis alasan" />
                                        <x-danger-button>Tangguhkan</x-danger-button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.organizations.archive', $org->id) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <label for="archive-{{ $org->id }}" class="text-sm">Alasan pengarsipan</label>
                                        <input id="archive-{{ $org->id }}" name="reason" type="text" required
                                            class="rounded border-gray-300 dark:bg-gray-700 text-sm"
                                            placeholder="Tulis alasan" />
                                        <x-danger-button>Arsipkan</x-danger-button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.organizations.activate', $org->id) }}">
                                        @csrf
                                        <x-primary-button>Aktifkan</x-primary-button>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li>Belum ada organisasi.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
