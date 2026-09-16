<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Models\OrganizationRequest;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganizationRequestController extends Controller
{
    public function __construct(private OrganizationService $organizations) {}

    public function index(): View
    {
        $riwayat = OrganizationRequest::where('user_id', request()->user()->id)
            ->orderByDesc('id')->get();

        return view('organizations.requests.index', ['riwayat' => $riwayat]);
    }

    public function create(): View
    {
        return view('organizations.requests.create');
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $this->organizations->request($request->validated(), $request->user());

        return redirect()->route('organizations.requests.index')
            ->with('status', 'Pengajuan dikirim dan menunggu persetujuan admin.');
    }
}
