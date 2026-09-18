<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Detail Pendaftaran') }} — {{ $registration->event->name }}
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
                    <p class="text-sm">Peran: <span class="font-medium">{{ $registration->role->name }}</span></p>
                    <p class="mt-1 text-sm">Status: <span class="font-medium">{{ $registration->status }}</span></p>
                    @if ($registration->rejection_reason)
                        <p class="mt-1 text-sm">Alasan penolakan: {{ $registration->rejection_reason }}</p>
                    @endif

                    @if ($registration->answers->isNotEmpty())
                        <h3 class="mt-4 font-semibold">Jawaban</h3>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($registration->answers as $answer)
                                <li>
                                    <span class="font-medium">{{ $answer->field?->label ?? 'Field' }}:</span>
                                    {{ $answer->value_text ?? (is_array($answer->value_jsonb) ? implode(', ', $answer->value_jsonb) : $answer->file_path) }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-4 flex items-center gap-4">
                        <a href="{{ route('registrations.index') }}" class="underline text-sm">Kembali ke daftar</a>
                        @if (! $registration->isTerminal())
                            <form method="POST" action="{{ route('registrations.withdraw', $registration->id) }}">
                                @csrf
                                <x-primary-button>Tarik pendaftaran</x-primary-button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
