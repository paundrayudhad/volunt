<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\Incident;
use App\Models\Registration;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportService
{
    public function __construct(private AuditLogService $audit) {}

    public function exportCsv(Event $event, string $dataset, User $actor): StreamedResponse
    {
        $filename = "{$event->slug}-{$dataset}-".now()->format('YmdHis').'.csv';

        $this->audit->record($actor, 'event.exported', Event::class, $event->id, [
            'dataset' => $dataset,
            'format' => 'csv',
        ]);

        return response()->streamDownload(function () use ($event, $dataset) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM untuk Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            $data = $this->getDatasetRows($event, $dataset);
            fputcsv($handle, $data['headers']);

            foreach ($data['rows'] as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function exportXlsx(Event $event, string $dataset, User $actor): StreamedResponse
    {
        $filename = "{$event->slug}-{$dataset}-".now()->format('YmdHis').'.xlsx';

        $this->audit->record($actor, 'event.exported', Event::class, $event->id, [
            'dataset' => $dataset,
            'format' => 'xlsx',
        ]);

        $data = $this->getDatasetRows($event, $dataset);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(ucfirst($dataset), 0, 31));

        $sheet->fromArray([$data['headers']], null, 'A1');

        $rowIndex = 2;
        foreach ($data['rows'] as $row) {
            $sheet->fromArray([$row], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * @return array{headers: array<int, string>, rows: iterable<array<int, mixed>>}
     */
    public function getDatasetRows(Event $event, string $dataset): array
    {
        return match ($dataset) {
            'registrations' => $this->getRegistrationsData($event),
            'attendances' => $this->getAttendancesData($event),
            'incidents' => $this->getIncidentsData($event),
            'certificates' => $this->getCertificatesData($event),
            default => abort(404, 'Dataset ekspor tidak ditemukan.'),
        };
    }

    /**
     * @return array{headers: array<int, string>, rows: iterable<array<int, mixed>>}
     */
    private function getRegistrationsData(Event $event): array
    {
        $headers = ['ID', 'Nama Relawan', 'Email', 'Role', 'Status', 'Tanggal Submit'];

        $rows = Registration::where('event_id', $event->id)
            ->with(['user', 'role'])
            ->cursor()
            ->map(fn (Registration $r) => [
                $r->id,
                $r->user->name ?? '-',
                $r->user->email ?? '-',
                $r->role->name ?? '-',
                $r->status,
                $r->created_at?->format('Y-m-d H:i:s') ?? '-',
            ]);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: array<int, string>, rows: iterable<array<int, mixed>>}
     */
    private function getAttendancesData(Event $event): array
    {
        $headers = ['ID', 'Nama Relawan', 'Role', 'Divisi', 'Shift', 'Metode', 'Status', 'Check-In'];

        $rows = Attendance::where('attendances.event_id', $event->id)
            ->with(['user', 'assignment.role', 'assignment.division', 'shift'])
            ->cursor()
            ->map(function (Attendance $a) {
                $shiftText = $a->shift && $a->shift->start_at ? $a->shift->start_at->format('d M Y H:i') : '-';

                return [
                    $a->id,
                    $a->user->name ?? '-',
                    $a->assignment->role->name ?? '-',
                    $a->assignment->division->name ?? '-',
                    $shiftText,
                    $a->method,
                    $a->status,
                    $a->checked_in_at?->format('Y-m-d H:i:s') ?? '-',
                ];
            });

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: array<int, string>, rows: iterable<array<int, mixed>>}
     */
    private function getIncidentsData(Event $event): array
    {
        $headers = ['ID', 'Kategori', 'Prioritas', 'Lokasi', 'Status', 'Pelapor', 'Deskripsi', 'Waktu Lapor'];

        $rows = Incident::where('event_id', $event->id)
            ->with(['reporter'])
            ->cursor()
            ->map(fn (Incident $i) => [
                $i->id,
                $i->category,
                $i->priority,
                $i->location,
                $i->status,
                $i->reporter->name ?? '-',
                $i->description,
                $i->created_at?->format('Y-m-d H:i:s') ?? '-',
            ]);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: array<int, string>, rows: iterable<array<int, mixed>>}
     */
    private function getCertificatesData(Event $event): array
    {
        $headers = ['ID', 'Nomor Sertifikat', 'Nama Penerima', 'Email', 'Status', 'Tanggal Terbit', 'Tanggal Dicabut', 'Alasan Cabut'];

        $rows = Certificate::where('event_id', $event->id)
            ->with(['user'])
            ->cursor()
            ->map(fn (Certificate $c) => [
                $c->id,
                $c->certificate_no,
                $c->user->name ?? '-',
                $c->user->email ?? '-',
                $c->revoked_at ? 'Dicabut' : 'Valid',
                $c->issued_at?->format('Y-m-d H:i:s') ?? '-',
                $c->revoked_at?->format('Y-m-d H:i:s') ?? '-',
                $c->revoke_reason ?? '-',
            ]);

        return ['headers' => $headers, 'rows' => $rows];
    }
}
