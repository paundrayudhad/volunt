<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Sertifikat Saya') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if ($certificates->isEmpty())
                        <p class="text-sm">Belum ada sertifikat. Sertifikat terbit setelah event selesai dan syarat kehadiran terpenuhi.</p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($certificates as $sertifikat)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $sertifikat->event?->name ?? 'Event' }}</p>
                                        <p class="text-sm text-gray-500">Nomor: {{ $sertifikat->certificate_no }} — Diterbitkan: {{ $sertifikat->issued_at?->format('d M Y') ?? '—' }}</p>
                                        <p class="text-sm text-gray-500">Status: {{ $sertifikat->isRevoked() ? 'Dicabut' : 'Aktif' }}</p>
                                    </div>
                                    @if (! $sertifikat->isRevoked())
                                        <a href="{{ route('my.certificates.download', $sertifikat->id) }}" class="underline text-sm">Unduh PDF</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $certificates->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
