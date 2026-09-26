<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Ringkasan Analitik Platform') }} — Admin
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 p-6 shadow sm:rounded-lg">
                <h3 class="text-lg font-bold border-b border-gray-200 dark:border-gray-700 pb-4">Statistik Global Platform</h3>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Organisasi</span>
                        <p class="text-2xl font-bold mt-1">{{ $summary['organizations']['total'] }}</p>
                        <span class="text-xs text-green-600">{{ $summary['organizations']['active'] }} Aktif, {{ $summary['organizations']['pending'] }} Pending</span>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Event Publik</span>
                        <p class="text-2xl font-bold mt-1">{{ $summary['events']['total'] }}</p>
                        <span class="text-xs text-blue-600">{{ $summary['events']['published'] }} Tayang, {{ $summary['events']['completed'] }} Selesai</span>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Pengguna Terdaftar</span>
                        <p class="text-2xl font-bold mt-1">{{ $summary['volunteers']['total_users'] }}</p>
                        <span class="text-xs text-purple-600">{{ $summary['volunteers']['completed_profiles'] }} Profil Lengkap</span>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Sertifikat Terbit</span>
                        <p class="text-2xl font-bold mt-1">{{ $summary['certificates']['total_issued'] }}</p>
                        <span class="text-xs text-emerald-600">{{ $summary['certificates']['total_verifications'] }} Verifikasi Publik</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
