<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verifikasi Sertifikat {{ $certificate->certificate_no }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; color: #111827; margin: 0; padding: 32px 16px; }
        .kartu { max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .lencana { display: inline-block; font-weight: 700; font-size: 14px; padding: 6px 14px; border-radius: 999px; }
        .valid { background: #dcfce7; color: #166534; border: 1px solid #16a34a; }
        .dicabut { background: #fee2e2; color: #991b1b; border: 1px solid #dc2626; }
        dl { margin-top: 18px; font-size: 15px; }
        dt { font-weight: 600; }
        dd { margin: 2px 0 12px; }
        .catatan { margin-top: 16px; font-size: 13px; color: #4b5563; }
    </style>
</head>
<body>
    <main class="kartu">
        <h1>Verifikasi Sertifikat</h1>
        @if ($certificate->isRevoked())
            <p><span class="lencana dicabut">DICABUT</span></p>
            <p>Sertifikat ini telah dicabut dan tidak berlaku lagi.</p>
        @else
            <p><span class="lencana valid">VALID</span></p>
        @endif
        <dl>
            <div><dt>Nomor sertifikat</dt><dd>{{ $certificate->certificate_no }}</dd></div>
            <div><dt>Nama relawan</dt><dd>{{ $certificate->user?->name ?? '—' }}</dd></div>
            <div><dt>Event</dt><dd>{{ $certificate->event?->name ?? '—' }}</dd></div>
            <div><dt>Tanggal event</dt><dd>{{ $certificate->event?->start_at?->format('d M Y') ?? '—' }} — {{ $certificate->event?->end_at?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt>Tanggal terbit</dt><dd>{{ $certificate->issued_at?->format('d M Y') ?? '—' }}</dd></div>
        </dl>
        <p class="catatan">Halaman ini bersifat publik untuk memeriksa keaslian nomor sertifikat.</p>
    </main>
</body>
</html>
