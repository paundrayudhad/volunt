<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Daftar Seluruh Sertifikat') }} — Superadmin
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="GET" action="{{ route('admin.certificates.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div>
                            <x-input-label for="q" value="Cari Nomor / Nama / Email" />
                            <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$searchQuery" placeholder="WV-2026-..." />
                        </div>

                        <div>
                            <x-input-label for="org" value="Organisasi" />
                            <select id="org" name="org" class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm">
                                <option value="">Semua Organisasi</option>
                                @foreach ($orgOptions as $org)
                                    <option value="{{ $org->id }}" {{ $selectedOrg === $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm">
                                <option value="" {{ $selectedStatus === '' ? 'selected' : '' }}>Semua Status</option>
                                <option value="valid" {{ $selectedStatus === 'valid' ? 'selected' : '' }}>Valid / Aktif</option>
                                <option value="revoked" {{ $selectedStatus === 'revoked' ? 'selected' : '' }}>Dicabut</option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <x-primary-button class="h-10">Filter</x-primary-button>
                            <a href="{{ route('admin.certificates.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded text-xs font-semibold tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 h-10">Reset</a>
                        </div>
                    </form>

                    @if ($certificates->isEmpty())
                        <p class="text-sm text-gray-500">Tidak ada data sertifikat yang cocok dengan filter.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Nomor</th>
                                        <th class="px-3 py-2 text-left">Penerima</th>
                                        <th class="px-3 py-2 text-left">Event</th>
                                        <th class="px-3 py-2 text-left">Organisasi</th>
                                        <th class="px-3 py-2 text-left">Terbit</th>
                                        <th class="px-3 py-2 text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($certificates as $cert)
                                        <tr>
                                            <td class="px-3 py-2 font-mono font-bold">{{ $cert->certificate_no }}</td>
                                            <td class="px-3 py-2">
                                                <span class="font-medium">{{ $cert->user?->name ?? '-' }}</span>
                                                <span class="block text-xs text-gray-500">{{ $cert->user?->email ?? '-' }}</span>
                                            </td>
                                            <td class="px-3 py-2">{{ $cert->event?->name ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $cert->event?->organization?->name ?? '-' }}</td>
                                            <td class="px-3 py-2 text-xs text-gray-500">{{ $cert->issued_at?->format('d M Y H:i') ?? '-' }}</td>
                                            <td class="px-3 py-2">
                                                @if ($cert->isRevoked())
                                                    <span class="px-2 py-0.5 text-xs rounded bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 font-semibold">Dicabut</span>
                                                @else
                                                    <span class="px-2 py-0.5 text-xs rounded bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300 font-semibold">Valid</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $certificates->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
