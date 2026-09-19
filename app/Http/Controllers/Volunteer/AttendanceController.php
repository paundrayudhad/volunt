<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $kehadiran) {}

    public function show(Request $request, Assignment $assignmentVol): View
    {
        $kode = $request->session()->get('qr_token');
        if (! is_string($kode) || strlen($kode) !== 64) {
            $hasil = $this->kehadiran->issueToken($assignmentVol, $request->user());
            $kode = $hasil['raw'];
            $request->session()->flash('qr_token', $kode);
        }

        $assignmentVol->load(['shift', 'event']);

        return view('registrations.qr', [
            'assignment' => $assignmentVol,
            'qrToken' => $kode,
        ]);
    }

    public function rotate(Request $request, Assignment $assignmentVol): RedirectResponse
    {
        $hasil = $this->kehadiran->issueToken($assignmentVol, $request->user());

        return redirect()->route('my.qr.show', $assignmentVol->id)
            ->with('qr_token', $hasil['raw'])
            ->with('status', 'Kode QR baru berhasil dibuat.');
    }
}
