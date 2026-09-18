<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $field->label }}
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
                    <p class="text-sm">Tipe: {{ $field->type }} — {{ $field->required ? 'Wajib' : 'Opsional' }} — {{ $field->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                    @if ($field->placeholder)
                        <p class="mt-1 text-sm">Placeholder: {{ $field->placeholder }}</p>
                    @endif
                    @if ($field->options->isNotEmpty())
                        <ul class="mt-3 list-disc pl-5 text-sm">
                            @foreach ($field->options as $opsi)
                                <li>{{ $opsi->label }} ({{ $opsi->value }})</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-4">
                        <a href="{{ route('organizer.events.fields.index', [$org->slug, $event->slug]) }}" class="underline">Kembali ke daftar field</a>
                        @can('manage', $field)
                            <a href="{{ route('organizer.events.fields.edit', [$org->slug, $event->slug, $field->id]) }}" class="underline">Ubah field</a>
                        @endcan
                    </div>

                    @can('manage', $field)
                        <form method="POST" action="{{ route('organizer.events.fields.destroy', [$org->slug, $event->slug, $field->id]) }}" class="mt-6">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>Hapus field</x-danger-button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
