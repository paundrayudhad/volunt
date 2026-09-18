<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    private const STATUS = [
        'pending',
        'under_review',
        'accepted',
        'rejected',
        'waitlisted',
        'cancelled',
        'withdrawn',
    ];

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $orgId = $request->query('org');

        $pendaftaran = Registration::with(['event.organization', 'user', 'role'])
            ->when(in_array($status, self::STATUS, true), fn ($query) => $query->where('status', $status))
            ->when(is_numeric($orgId), fn ($query) => $query->whereHas('event', fn ($event) => $event->where('organization_id', (int) $orgId)))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.registrations.index', [
            'pendaftaran' => $pendaftaran,
            'statusDipilih' => in_array($status, self::STATUS, true) ? $status : '',
            'orgDipilih' => is_numeric($orgId) ? (int) $orgId : '',
            'daftarStatus' => self::STATUS,
            'daftarOrg' => Organization::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(int $registrationAdmin): View
    {
        $pendaftaran = Registration::with(['event.organization', 'user', 'role', 'answers.field', 'histories'])
            ->findOrFail($registrationAdmin);

        return view('admin.registrations.show', ['pendaftaran' => $pendaftaran]);
    }
}
