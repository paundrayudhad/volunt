<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Anggota Organisasi') }}
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
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Daftar anggota {{ $org->name }}</h3>
                        <a href="{{ route('organizer.members.create', $org->slug) }}" class="underline">Undang anggota</a>
                    </div>

                    <table class="mt-4 w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2">Nama</th>
                                <th class="py-2">Email</th>
                                <th class="py-2">Role</th>
                                <th class="py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($anggota as $member)
                                <tr class="border-b">
                                    <td class="py-2">{{ $member->user->name }}</td>
                                    <td class="py-2">{{ $member->user->email }}</td>
                                    <td class="py-2">{{ $member->role }}</td>
                                    <td class="py-2 flex gap-2">
                                        <form method="POST" action="{{ route('organizer.members.update', [$org->slug, $member->id]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="role" value="{{ $member->role === 'owner' ? 'staff' : 'owner' }}" />
                                            <button type="submit" class="underline">Jadikan {{ $member->role === 'owner' ? 'staff' : 'owner' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('organizer.members.destroy', [$org->slug, $member->id]) }}"
                                            onsubmit="return confirm('Keluarkan anggota ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="underline text-red-600">Keluarkan</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <h3 class="mt-8 text-lg font-semibold">Undangan terkirim</h3>
                    <ul class="mt-2 text-sm list-disc pl-5">
                        @forelse ($undangan as $inv)
                            <li>{{ $inv->email }} — {{ $inv->accepted_at ? 'diterima' : ($inv->declined_at ? 'ditolak' : 'menunggu') }}</li>
                        @empty
                            <li>Belum ada undangan.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
