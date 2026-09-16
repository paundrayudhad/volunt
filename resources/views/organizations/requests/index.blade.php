<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Riwayat Pengajuan') }}
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
                    <a href="{{ route('organizations.requests.create') }}" class="underline">Buat pengajuan baru</a>

                    <ul class="mt-4 space-y-3">
                        @forelse ($riwayat as $req)
                            <li class="border-b pb-3">
                                <p class="font-semibold">{{ $req->name }} ({{ $req->slug }})</p>
                                <p class="text-sm">Status: {{ $req->status }}</p>
                                @if ($req->status === 'rejected' && $req->rejection_reason)
                                    <p class="text-sm">Alasan: {{ $req->rejection_reason }}</p>
                                @endif
                            </li>
                        @empty
                            <li>Belum ada pengajuan.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
