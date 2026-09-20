<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicCertificateController extends Controller
{
    public function show(Request $request, string $nomor): View
    {
        $sertifikat = Certificate::where('certificate_no', $nomor)
            ->with(['event', 'event.organization', 'user'])
            ->firstOrFail();

        CertificateVerification::unguarded(fn (): CertificateVerification => CertificateVerification::create([
            'certificate_id' => $sertifikat->id,
            'verified_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
        ]));

        return view('certificates.verify', ['certificate' => $sertifikat]);
    }
}
