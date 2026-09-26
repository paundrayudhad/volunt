<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Analitik Event') }} — {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 p-6 shadow sm:rounded-lg">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-700 pb-4">
                    <div>
                        <a href="{{ route('organizer.events.show', [$organization->slug, $event->slug]) }}" class="text-sm underline">← Kembali ke Detail Event</a>
                        <h3 class="text-lg font-bold mt-1">Ringkasan Kinerja & Metrik Operasional</h3>
                    </div>

                    @can('exportEventData', [\App\Policies\AnalyticsPolicy::class, $event])
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-medium">Export Data:</span>
                            <div class="inline-flex rounded-md shadow-sm">
                                <a href="{{ route('organizer.events.export', [$organization->slug, $event->slug, 'registrations', 'format' => 'csv']) }}" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-l border border-gray-300 dark:border-gray-600">CSV Pendaftaran</a>
                                <a href="{{ route('organizer.events.export', [$organization->slug, $event->slug, 'registrations', 'format' => 'xlsx']) }}" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 border-t border-b border-r border-gray-300 dark:border-gray-600">XLSX Pendaftaran</a>
                                <a href="{{ route('organizer.events.export', [$organization->slug, $event->slug, 'attendances', 'format' => 'xlsx']) }}" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-r border-t border-b border-r border-gray-300 dark:border-gray-600">XLSX Presensi</a>
                            </div>
                        </div>
                    @endcan
                </div>

                <!-- KPI Tiles -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                    <div class="bg-blue-50 dark:bg-blue-900/40 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                        <span class="text-xs text-blue-600 dark:text-blue-300 font-semibold uppercase tracking-wider">Total Pendaftar</span>
                        <p class="text-2xl font-bold mt-1 text-blue-900 dark:text-blue-100">{{ $summary['registration_funnel']['total'] }}</p>
                        <span class="text-xs text-gray-500">{{ $summary['registration_funnel']['accepted'] }} Relawan Diterima</span>
                    </div>

                    <div class="bg-green-50 dark:bg-green-900/40 p-4 rounded-lg border border-green-200 dark:border-green-800">
                        <span class="text-xs text-green-600 dark:text-green-300 font-semibold uppercase tracking-wider">Attendance Rate</span>
                        <p class="text-2xl font-bold mt-1 text-green-900 dark:text-green-100">{{ $summary['attendance_summary']['attendance_rate_pct'] }}%</p>
                        <span class="text-xs text-gray-500">{{ $summary['attendance_summary']['present'] + $summary['attendance_summary']['late'] }} / {{ $summary['attendance_summary']['total_assignments'] }} Shift Terisi</span>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900/40 p-4 rounded-lg border border-yellow-200 dark:border-yellow-800">
                        <span class="text-xs text-yellow-600 dark:text-yellow-300 font-semibold uppercase tracking-wider">Insiden Terbuka</span>
                        <p class="text-2xl font-bold mt-1 text-yellow-900 dark:text-yellow-100">{{ $summary['incident_summary']['open'] + $summary['incident_summary']['in_progress'] }}</p>
                        <span class="text-xs text-red-500 font-medium">{{ $summary['incident_summary']['critical_count'] }} Kritis</span>
                    </div>

                    <div class="bg-purple-50 dark:bg-purple-900/40 p-4 rounded-lg border border-purple-200 dark:border-purple-800">
                        <span class="text-xs text-purple-600 dark:text-purple-300 font-semibold uppercase tracking-wider">Sertifikat Terbit</span>
                        <p class="text-2xl font-bold mt-1 text-purple-900 dark:text-purple-100">{{ $summary['certificate_summary']['issued'] }}</p>
                        <span class="text-xs text-gray-500">{{ $summary['certificate_summary']['revoked'] }} Dicabut</span>
                    </div>
                </div>

                <!-- Registration Funnel -->
                <div class="mt-8">
                    <h4 class="font-semibold text-sm uppercase tracking-wider text-gray-500">Tahapan Konversi Relawan (Registration Funnel)</h4>
                    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
                        <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded">
                            <span class="text-xs text-gray-500">Pending Review</span>
                            <p class="text-lg font-bold">{{ $summary['registration_funnel']['pending'] }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded">
                            <span class="text-xs text-gray-500">Under Review</span>
                            <p class="text-lg font-bold">{{ $summary['registration_funnel']['under_review'] }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded">
                            <span class="text-xs text-gray-500">Accepted</span>
                            <p class="text-lg font-bold text-green-600">{{ $summary['registration_funnel']['accepted'] }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded">
                            <span class="text-xs text-gray-500">Rejected / Withdrawn</span>
                            <p class="text-lg font-bold text-gray-400">{{ $summary['registration_funnel']['rejected'] + $summary['registration_funnel']['withdrawn'] }}</p>
                        </div>
                    </div>
                </div>

                <!-- Role & Shift Utilization Tables -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                    <div>
                        <h4 class="font-semibold text-sm uppercase tracking-wider text-gray-500 mb-3">Utilisasi Kuota Peran (Roles)</h4>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Role</th>
                                        <th class="px-3 py-2 text-right">Kuota</th>
                                        <th class="px-3 py-2 text-right">Diterima</th>
                                        <th class="px-3 py-2 text-right">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse ($summary['role_utilization'] as $r)
                                        <tr>
                                            <td class="px-3 py-2 font-medium">{{ $r['role_name'] }}</td>
                                            <td class="px-3 py-2 text-right">{{ $r['quota'] }}</td>
                                            <td class="px-3 py-2 text-right">{{ $r['accepted_count'] }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ $r['utilization_pct'] }}%</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-3 py-2 text-center text-gray-500">Belum ada data role.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-semibold text-sm uppercase tracking-wider text-gray-500 mb-3">Utilisasi Jadwal Shift</h4>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Shift</th>
                                        <th class="px-3 py-2 text-right">Kapasitas</th>
                                        <th class="px-3 py-2 text-right">Terisi</th>
                                        <th class="px-3 py-2 text-right">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse ($summary['shift_utilization'] as $s)
                                        <tr>
                                            <td class="px-3 py-2 font-medium">{{ $s['shift_name'] }}</td>
                                            <td class="px-3 py-2 text-right">{{ $s['capacity'] }}</td>
                                            <td class="px-3 py-2 text-right">{{ $s['filled_count'] }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ $s['utilization_pct'] }}%</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-3 py-2 text-center text-gray-500">Belum ada data shift.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
