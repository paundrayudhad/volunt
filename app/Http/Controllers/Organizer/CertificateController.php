<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\IssueCertificatesRequest;
use App\Http\Requests\RevokeCertificateRequest;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\Organization;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CertificateController extends Controller
{
    public function __construct(private CertificateService $sertifikat) {}

    public function index(Request $request, Organization $organization, Event $event): View
    {
        Gate::authorize('viewAny', [Certificate::class, $event]);

        $status = $request->query('status');

        $query = Certificate::where('event_id', $event->id)
            ->with('user')
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        if ($status === 'valid') {
            $query->whereNull('revoked_at');
        } elseif ($status === 'revoked') {
            $query->whereNotNull('revoked_at');
        }

        $items = $query->paginate(15)->withQueryString();

        return view('organizer.events.certificates.index', [
            'org' => $organization,
            'event' => $event,
            'certificates' => $items,
            'selectedStatus' => (string) $status,
            'diterbitkan' => Certificate::where('event_id', $event->id)->whereNull('revoked_at')->count(),
            'ambang' => $this->sertifikat->effectiveThreshold($event),
        ]);
    }

    public function show(Organization $organization, Event $event, Certificate $sertifikat): View
    {
        Gate::authorize('view', $sertifikat);

        $sertifikat->load('user');
        $verifications = $sertifikat->verifications()
            ->orderByDesc('verified_at')
            ->paginate(15)
            ->withQueryString();

        return view('organizer.events.certificates.show', [
            'org' => $organization,
            'event' => $event,
            'certificate' => $sertifikat,
            'verifications' => $verifications,
        ]);
    }

    public function issue(IssueCertificatesRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $hasil = $this->sertifikat->issueBatch($event, $request->user(), $valid['min_attendance_pct'] ?? null);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['threshold' => $e->getMessage()]);
        }

        $diterbitkan = count($hasil['issued']);
        $dilewati = $hasil['skipped'];
        $pesan = "{$diterbitkan} sertifikat diterbitkan, {$dilewati} dilewati.";
        if ($hasil['failed'] !== []) {
            $pesan .= ' '.count($hasil['failed']).' gagal diterbitkan.';
        }

        return redirect()->route('organizer.events.certificates.index', [$organization->slug, $event->slug])
            ->with('status', $pesan);
    }

    public function revoke(RevokeCertificateRequest $request, Organization $organization, Event $event, Certificate $sertifikat): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $hasil = $this->sertifikat->revoke($sertifikat, $request->user(), $valid['reason']);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.certificates.show', [$organization->slug, $event->slug, $hasil->id])
            ->with('status', "Sertifikat {$hasil->certificate_no} dicabut.");
    }
}
