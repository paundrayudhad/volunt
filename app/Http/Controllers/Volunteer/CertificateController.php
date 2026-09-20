<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(): View
    {
        $sertifikat = request()->user()->certificates()
            ->with(['event', 'event.organization'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('volunteer.certificates.index', ['certificates' => $sertifikat]);
    }

    public function download(Certificate $certificateVol): Response
    {
        abort_if($certificateVol->isRevoked(), 422, 'Sertifikat ini telah dicabut.');

        $certificateVol->load(['event', 'event.organization', 'user']);

        $pdf = Pdf::loadView('certificates.pdf', [
            'certificate' => $certificateVol,
            'tautan' => route('certificates.verify', $certificateVol->certificate_no),
            'matriksQr' => $this->matriksQr($certificateVol),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("sertifikat-{$certificateVol->certificate_no}.pdf");
    }

    /**
     * @return array{lebar: int, baris: array<int, array<int, bool>>}
     */
    private function matriksQr(Certificate $sertifikat): array
    {
        $matriks = Encoder::encode(
            route('certificates.verify', $sertifikat->certificate_no),
            ErrorCorrectionLevel::L()
        )->getMatrix();
        $lebar = $matriks->getWidth();
        $baris = [];
        for ($y = 0; $y < $matriks->getHeight(); $y++) {
            $sel = [];
            for ($x = 0; $x < $lebar; $x++) {
                $sel[] = $matriks->get($x, $y) === 1;
            }
            $baris[] = $sel;
        }

        return ['lebar' => $lebar, 'baris' => $baris];
    }
}
