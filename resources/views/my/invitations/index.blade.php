<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Undangan Event Saya
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
                    <h3 class="text-lg font-bold mb-4">Daftar Undangan Relawan</h3>

                    @if ($invitations->isEmpty())
                        <div class="text-center py-8 text-sm text-gray-500">
                            Belum ada undangan event untuk Anda saat ini.
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($invitations as $inv)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 px-2 py-0.5 rounded font-semibold">
                                                {{ $inv->event?->organization?->name }}
                                            </span>
                                            @php
                                                $badgeClass = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    'accepted' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'declined' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                    'expired' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                    'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                ][$inv->status] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span class="text-xs px-2 py-0.5 rounded font-semibold {{ $badgeClass }}">
                                                {{ ucfirst($inv->status) }}
                                            </span>
                                        </div>

                                        <h4 class="font-bold text-base text-gray-900 dark:text-white">
                                            {{ $inv->event?->name }}
                                        </h4>

                                        @if ($inv->role)
                                            <p class="text-xs text-gray-600 dark:text-gray-300">
                                                <strong>Posisi yang Ditawarkan:</strong> {{ $inv->role->name }}
                                            </p>
                                        @endif

                                        @if ($inv->message)
                                            <p class="text-xs italic text-gray-500 bg-gray-50 dark:bg-gray-900/50 p-2 rounded mt-1">
                                                "{{ $inv->message }}"
                                            </p>
                                        @endif

                                        <p class="text-xs text-gray-400">
                                            Berlaku hingga: {{ $inv->expires_at->format('d M Y H:i') }}
                                        </p>
                                    </div>

                                    @if ($inv->status === 'pending' && ! $inv->isExpired())
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('my.invitations.respond', $inv->id) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="accepted">
                                                <x-primary-button class="bg-green-600 hover:bg-green-500">
                                                    Terima Undangan
                                                </x-primary-button>
                                            </form>
                                            <form method="POST" action="{{ route('my.invitations.respond', $inv->id) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="declined">
                                                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded text-xs font-semibold uppercase tracking-widest transition">
                                                    Tolak
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $invitations->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
