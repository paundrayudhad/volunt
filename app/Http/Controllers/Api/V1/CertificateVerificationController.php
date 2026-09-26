<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificateVerificationController extends Controller
{
    public function verify(Request $request, string $no): JsonResponse
    {
        $cert = Certificate::query()
            ->with(['event', 'user', 'registration.role', 'event.organization'])
            ->where('certificate_no', $no)
            ->first();

        if (! $cert || $cert->isRevoked()) {
            return response()->json([
                'valid' => false,
                'message' => 'Sertifikat tidak valid atau tidak ditemukan.',
            ], 404);
        }

        CertificateVerification::unguarded(fn (): CertificateVerification => CertificateVerification::create([
            'certificate_id' => $cert->id,
            'verified_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
        ]));

        return response()->json([
            'valid' => true,
            'certificate' => [
                'certificate_no' => $cert->certificate_no,
                'recipient_name' => $cert->user?->name,
                'event_name' => $cert->event?->name,
                'organization_name' => $cert->event?->organization?->name,
                'role_name' => $cert->registration?->role?->name,
                'issued_at' => $cert->issued_at?->toIso8601String(),
            ],
        ]);
    }
}
