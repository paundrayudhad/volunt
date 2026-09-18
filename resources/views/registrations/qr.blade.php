<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Kode Kehadiran Saya') }} — {{ $assignment->event?->name ?? 'Event' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 p-4 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="text-sm">Shift: <span class="font-medium">{{ $assignment->shift?->start_at?->format('d M Y H:i') ?? '-' }} — {{ $assignment->shift?->location ?? $assignment->location ?? '-' }}</span></p>
                    <p class="mt-1 text-sm">Status penugasan: <span class="font-medium">{{ $assignment->status }}</span></p>

                    <h3 class="mt-4 font-semibold">Kode kehadiran</h3>
                    <p class="mt-1 text-sm">Tunjukkan kode berikut kepada petugas pemindai. Kode hanya tampil sekali dan kedaluwarsa dalam 5 menit.</p>
                    <p class="mt-2 rounded border border-dashed border-gray-400 p-3 font-mono text-sm break-all">{{ $qrToken }}</p>

                    <svg class="mt-4 h-40 w-40 border border-gray-300 bg-white p-2" viewBox="0 0 100 100" role="img" aria-label="Kode QR kehadiran">
                        <rect x="4" y="4" width="28" height="28" fill="none" stroke="black" stroke-width="3" />
                        <rect x="12" y="12" width="12" height="12" fill="black" />
                        <rect x="68" y="4" width="28" height="28" fill="none" stroke="black" stroke-width="3" />
                        <rect x="76" y="12" width="12" height="12" fill="black" />
                        <rect x="4" y="68" width="28" height="28" fill="none" stroke="black" stroke-width="3" />
                        <rect x="12" y="76" width="12" height="12" fill="black" />
                        <rect x="44" y="44" width="12" height="12" fill="black" />
                        <rect x="60" y="60" width="16" height="16" fill="black" />
                        <rect x="44" y="68" width="8" height="8" fill="black" />
                        <rect x="68" y="44" width="8" height="8" fill="black" />
                    </svg>
                    <p class="mt-1 text-xs text-gray-500">Ilustrasi bingkai pindai; kode teks di atas yang dipindai aplikasi.</p>

                    <form method="POST" action="{{ route('my.qr.rotate', $assignment->id) }}" class="mt-6">
                        @csrf
                        <x-primary-button>Buat kode baru</x-primary-button>
                    </form>

                    <div class="mt-4">
                        <a href="{{ route('registrations.index') }}" class="underline text-sm">Kembali ke pendaftaran</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
