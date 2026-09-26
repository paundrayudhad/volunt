<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Portofolio Analitik') }} — {{ $organization->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 p-6 shadow sm:rounded-lg">
                <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                    <a href="{{ route('organizer.show', $organization->slug) }}" class="text-sm underline">← Kembali ke Organisasi</a>
                    <h3 class="text-lg font-bold mt-1">Ringkasan Portofolio & Dampak Relawan</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                    <div class="bg-indigo-50 dark:bg-indigo-900/40 p-4 rounded-lg border border-indigo-200 dark:border-indigo-800">
                        <span class="text-xs text-indigo-600 dark:text-indigo-300 font-semibold uppercase tracking-wider">Total Event</span>
                        <p class="text-2xl font-bold mt-1 text-indigo-900 dark:text-indigo-100">{{ $summary['total_events'] }}</p>
                        <span class="text-xs text-gray-500">{{ $summary['completed_events'] }} Selesai, {{ $summary['active_events'] }} Aktif</span>
                    </div>

                    <div class="bg-emerald-50 dark:bg-emerald-900/40 p-4 rounded-lg border border-emerald-200 dark:border-emerald-800">
                        <span class="text-xs text-emerald-600 dark:text-emerald-300 font-semibold uppercase tracking-wider">Relawan Unik</span>
                        <p class="text-2xl font-bold mt-1 text-emerald-900 dark:text-emerald-100">{{ $summary['unique_volunteers_count'] }}</p>
                        <span class="text-xs text-gray-500">Pernah berpartisipasi</span>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/40 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                        <span class="text-xs text-blue-600 dark:text-blue-300 font-semibold uppercase tracking-wider">Total Penugasan Lolos</span>
                        <p class="text-2xl font-bold mt-1 text-blue-900 dark:text-blue-100">{{ $summary['total_accepted_registrations'] }}</p>
                        <span class="text-xs text-gray-500">Pendaftaran diterima</span>
                    </div>

                    <div class="bg-amber-50 dark:bg-amber-900/40 p-4 rounded-lg border border-amber-200 dark:border-amber-800">
                        <span class="text-xs text-amber-600 dark:text-amber-300 font-semibold uppercase tracking-wider">Event Selesai</span>
                        <p class="text-2xl font-bold mt-1 text-amber-900 dark:text-amber-100">{{ $summary['completed_events'] }}</p>
                        <span class="text-xs text-gray-500">Terselenggara sukses</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
