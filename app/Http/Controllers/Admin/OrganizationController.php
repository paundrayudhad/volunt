<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(private OrganizationService $organizations) {}

    public function index(): View
    {
        $organisasi = Organization::orderBy('name')->get();

        return view('admin.organizations.index', ['organisasi' => $organisasi]);
    }

    public function suspend(Request $request, int $org): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $organisasi = Organization::findOrFail($org);
        $this->organizations->suspend($organisasi, $request->user(), $data['reason']);

        return redirect()->route('admin.organizations.index')
            ->with('status', 'Organisasi ditangguhkan.');
    }

    public function archive(Request $request, int $org): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $organisasi = Organization::findOrFail($org);
        $this->organizations->archive($organisasi, $request->user(), $data['reason']);

        return redirect()->route('admin.organizations.index')
            ->with('status', 'Organisasi diarsipkan.');
    }

    public function activate(Request $request, int $org): RedirectResponse
    {
        $organisasi = Organization::findOrFail($org);
        $this->organizations->activate($organisasi, $request->user());

        return redirect()->route('admin.organizations.index')
            ->with('status', 'Organisasi diaktifkan kembali.');
    }
}
