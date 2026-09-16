<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Antrean Pengajuan') }}
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
                    <p class="text-sm">Pengajuan pending tampil paling atas. Hanya baca dan putuskan: setujui atau tolak.</p>

                    <ul class="mt-4 space-y-4">
                        @forelse ($antrean as $req)
                            <li class="border-b pb-4">
                                <p class="font-semibold">{{ $req->name }} ({{ $req->slug }})</p>
                                <p class="text-sm">Status: {{ $req->status }}</p>
                                <p class="text-sm">Pengaju: {{ $req->user?->email ?? 'Tidak diketahui' }}</p>
                                @if ($req->description)
                                    <p class="text-sm">{{ $req->description }}</p>
                                @endif

                                @if ($req->status === 'pending')
                                    <div class="mt-3 flex flex-wrap items-center gap-3">
                                        <form method="POST" action="{{ route('admin.requests.approve', $req->id) }}">
                                            @csrf
                                            <x-primary-button>Setujui</x-primary-button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.requests.reject', $req->id) }}" class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            <label for="reason-{{ $req->id }}" class="text-sm">Alasan penolakan</label>
                                            <input id="reason-{{ $req->id }}" name="reason" type="text" required
                                                class="rounded border-gray-300 dark:bg-gray-700 text-sm"
                                                placeholder="Tulis alasan penolakan" />
                                            <x-danger-button>Tolak</x-danger-button>
                                        </form>
                                    </div>
                                @elseif ($req->status === 'rejected' && $req->rejection_reason)
                                    <p class="text-sm mt-2">Alasan: {{ $req->rejection_reason }}</p>
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
