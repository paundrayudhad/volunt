<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewOrganizationRequest;
use App\Models\OrganizationRequest;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationRequestController extends Controller
{
    public function __construct(private OrganizationService $organizations) {}

    public function index(): View
    {
        $antrean = OrganizationRequest::orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get();

        return view('admin.requests.index', ['antrean' => $antrean]);
    }

    public function approve(Request $request, OrganizationRequest $organizationRequest): RedirectResponse
    {
        abort_unless($organizationRequest->isPending(), 422, 'Pengajuan sudah diproses.');

        $this->organizations->approve($organizationRequest, $request->user());

        return redirect()->route('admin.requests.index')
            ->with('status', 'Pengajuan disetujui. Organisasi aktif dan pengaju menjadi owner.');
    }

    public function reject(ReviewOrganizationRequest $request, OrganizationRequest $organizationRequest): RedirectResponse
    {
        abort_unless($organizationRequest->isPending(), 422, 'Pengajuan sudah diproses.');

        $this->organizations->reject($organizationRequest, $request->user(), $request->validated()['reason']);

        return redirect()->route('admin.requests.index')
            ->with('status', 'Pengajuan ditolak dengan alasan tercatat.');
    }
}
