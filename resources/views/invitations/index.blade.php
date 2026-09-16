<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Undangan Saya') }}
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
                    @forelse ($undangan as $inv)
                        <div class="py-3 border-b flex items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold">{{ $inv->organization->name }}</p>
                                <p class="text-sm">Role: {{ $inv->role }}</p>
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('invitations.accept', $inv->id) }}">
                                    @csrf
                                    <x-primary-button>Terima</x-primary-button>
                                </form>
                                <form method="POST" action="{{ route('invitations.decline', $inv->id) }}">
                                    @csrf
                                    <x-secondary-button type="submit">Tolak</x-secondary-button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p>Tidak ada undangan menunggu.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
