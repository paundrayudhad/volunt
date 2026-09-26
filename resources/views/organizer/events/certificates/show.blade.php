<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Sertifikat {{ $certificate->certificate_no }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 p-4 rounded">
                    {{ session('status') }}
                </div>
            @endif
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
                    <a href="{{ route('organizer.events.certificates.index', [$org->slug, $event->slug]) }}" class="underline text-sm">Kembali ke daftar sertifikat</a>

                    <dl class="mt-4 text-sm space-y-2">
                        <div><dt class="inline font-medium">Nomor:</dt> <dd class="inline">{{ $certificate->certificate_no }}</dd></div>
                        <div><dt class="inline font-medium">Relawan:</dt> <dd class="inline">{{ $certificate->user?->name ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">Diterbitkan:</dt> <dd class="inline">{{ $certificate->issued_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                        <div><dt class="inline font-medium">Status:</dt> <dd class="inline">{{ $certificate->isRevoked() ? 'Dicabut' : 'Aktif' }}</dd></div>
                        @if ($certificate->isRevoked())
                            <div><dt class="inline font-medium">Alasan pencabutan:</dt> <dd class="inline">{{ $certificate->revoke_reason }}</dd></div>
                        @endif
                    </dl>

                    <h3 class="mt-6 font-medium">Riwayat verifikasi</h3>
                    @if ($verifications->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">Belum ada verifikasi untuk sertifikat ini.</p>
                    @else
                        <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            @foreach ($verifications as $riwayat)
                                <li class="py-2">{{ $riwayat->verified_at?->format('d M Y H:i') ?? '—' }}{{ $riwayat->ip ? ' — '.$riwayat->ip : '' }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-4">
                            {{ $verifications->links() }}
                        </div>
                    @endif

                    @if (! $certificate->isRevoked())
                        @can('revoke', $certificate)
                            <form method="POST" action="{{ route('organizer.events.certificates.revoke', [$org->slug, $event->slug, $certificate->id]) }}" class="mt-6 space-y-4">
                                @csrf
                                <div>
                                    <x-input-label for="reason" value="Alasan pencabutan (minimal 10 karakter)" />
                                    <textarea id="reason" name="reason" rows="3" required maxlength="2000" class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 text-sm">{{ old('reason') }}</textarea>
                                    <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                                </div>
                                <x-primary-button>Cabut sertifikat</x-primary-button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
