<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Daftar sebagai Relawan') }} — {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('registrations.store', $event->slug) }}" enctype="multipart/form-data" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}" />

                    <div>
                        <x-input-label for="role_id" value="Pilih peran" />
                        <select id="role_id" name="role_id" required
                            class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>
                                    {{ $role->name }} (sisa {{ $role->remainingQuota() }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                    </div>

                    @foreach ($fields as $field)
                        <div>
                            <x-input-label :for="'answers-'.$field->id" :value="$field->label.($field->required ? ' *' : '')" />
                            @if (in_array($field->type, ['select', 'radio']))
                                <select id="answers-{{ $field->id }}" name="answers[{{ $field->id }}]"
                                    class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">
                                    <option value="">— Pilih —</option>
                                    @foreach ($field->options as $option)
                                        <option value="{{ $option->value }}" @selected(old("answers.{$field->id}") == $option->value)>
                                            {{ $option->label }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif (in_array($field->type, ['multi_select', 'checkbox']))
                                @foreach ($field->options as $option)
                                    <label class="flex items-center gap-2 text-sm mt-1">
                                        <input type="checkbox" name="answers[{{ $field->id }}][]" value="{{ $option->value }}"
                                            @checked(in_array($option->value, (array) old("answers.{$field->id}", []))) />
                                        {{ $option->label }}
                                    </label>
                                @endforeach
                            @elseif ($field->type === 'file')
                                <input id="answers-{{ $field->id }}" type="file" name="answers[{{ $field->id }}]"
                                    class="mt-1 block w-full text-sm" accept=".pdf,.jpg,.jpeg,.png" />
                            @elseif ($field->type === 'textarea')
                                <textarea id="answers-{{ $field->id }}" name="answers[{{ $field->id }}]" rows="3"
                                    class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700">{{ old("answers.{$field->id}") }}</textarea>
                            @else
                                <x-text-input id="answers-{{ $field->id }}" name="answers[{{ $field->id }}]"
                                    type="{{ $field->type === 'date' ? 'date' : ($field->type === 'time' ? 'time' : 'text') }}"
                                    class="mt-1 block w-full" :value="old('answers.'.$field->id)" />
                            @endif
                            <x-input-error :messages="$errors->get('answers.'.$field->id)" class="mt-2" />
                        </div>
                    @endforeach

                    <x-primary-button>Kirim pendaftaran</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
