<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Log Audit dan Keamanan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="text-sm">Halaman ini baca-saja, tanpa tombol ubah atau hapus. Gunakan filter untuk menelusuri kejadian.</p>

                    <div class="mt-4 flex gap-4">
                        <a href="{{ route('admin.logs.index', ['tab' => 'audit']) }}" class="underline">Audit</a>
                        <a href="{{ route('admin.logs.index', ['tab' => 'keamanan']) }}" class="underline">Keamanan</a>
                    </div>

                    @if ($tab === 'keamanan')
                        <form method="GET" action="{{ route('admin.logs.index') }}" class="mt-4 flex flex-wrap items-center gap-2">
                            <input type="hidden" name="tab" value="keamanan" />
                            <label for="type" class="text-sm">Jenis kejadian</label>
                            <input id="type" name="type" type="text" value="{{ request('type') }}"
                                class="rounded border-gray-300 dark:bg-gray-700 text-sm"
                                placeholder="mis. failed_login" />
                            <x-primary-button>Saring</x-primary-button>
                        </form>

                        <ul class="mt-4 space-y-3">
                            @forelse ($keamanan as $log)
                                <li class="border-b pb-3">
                                    <p class="font-semibold">{{ $log->type }}</p>
                                    <p class="text-sm">Aktor: {{ $log->actor?->email ?? 'Sistem' }} | IP: {{ $log->ip ?? '-' }}</p>
                                    <p class="text-sm">Waktu: {{ $log->created_at }}</p>
                                </li>
                            @empty
                                <li>Tidak ada log keamanan.</li>
                            @endforelse
                        </ul>

                        <div class="mt-4">{{ $keamanan->appends(request()->query())->links() }}</div>
                    @else
                        <form method="GET" action="{{ route('admin.logs.index') }}" class="mt-4 flex flex-wrap items-center gap-2">
                            <input type="hidden" name="tab" value="audit" />
                            <label for="action" class="text-sm">Aksi audit</label>
                            <input id="action" name="action" type="text" value="{{ request('action') }}"
                                class="rounded border-gray-300 dark:bg-gray-700 text-sm"
                                placeholder="mis. organization.approved" />
                            <x-primary-button>Saring</x-primary-button>
                        </form>

                        <ul class="mt-4 space-y-3">
                            @forelse ($audit as $log)
                                <li class="border-b pb-3">
                                    <p class="font-semibold">Aksi: {{ $log->action }}</p>
                                    <p class="text-sm">Sumber: {{ $log->resource_type }} #{{ $log->resource_id }} | Aktor: {{ $log->actor?->email ?? 'Sistem' }}</p>
                                    <p class="text-sm">Waktu: {{ $log->created_at }}</p>
                                </li>
                            @empty
                                <li>Tidak ada log audit.</li>
                            @endforelse
                        </ul>

                        <div class="mt-4">{{ $audit->appends(request()->query())->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
