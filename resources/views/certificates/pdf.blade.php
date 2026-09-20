<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sertifikat {{ $certificate->certificate_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; margin: 0; padding: 40px; }
        .bingkai { border: 4px double #1e40af; padding: 36px; text-align: center; }
        .kop { font-size: 14px; color: #374151; }
        .judul { font-size: 30px; font-weight: bold; letter-spacing: 4px; margin: 18px 0 6px; color: #1e3a8a; }
        .nama { font-size: 26px; font-weight: bold; margin: 14px 0; }
        .teks { font-size: 13px; line-height: 1.7; color: #374151; }
        .meta { margin-top: 18px; font-size: 12px; color: #4b5563; }
        .bawah { margin-top: 28px; width: 100%; }
        .kolom { display: inline-block; width: 46%; vertical-align: top; font-size: 12px; }
        .qr { border-collapse: collapse; margin: 0 auto; border: 12px solid #ffffff; }
        .qr td { width: 4px; height: 4px; padding: 0; }
        .qr td.hitam { background: #000000; }
        .qr td.putih { background: #ffffff; }
        .tanda { margin-top: 60px; border-top: 1px solid #111827; display: inline-block; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="bingkai">
        <div class="kop">{{ $certificate->event?->organization?->name ?? 'Penyelenggara' }} — {{ $certificate->event?->name ?? 'Event' }}</div>
        <div class="judul">SERTIFIKAT PENGHARGAAN</div>
        <div class="teks">Diberikan dengan bangga kepada</div>
        <div class="nama">{{ $certificate->user?->name ?? 'Relawan' }}</div>
        <div class="teks">
            telah berpartisipasi sebagai relawan pada {{ $certificate->event?->name ?? 'event' }}
            ({{ $certificate->event?->start_at?->format('d M Y') ?? '—' }} — {{ $certificate->event?->end_at?->format('d M Y') ?? '—' }}).
        </div>
        <div class="meta">
            Nomor sertifikat: {{ $certificate->certificate_no }}<br>
            Tanggal terbit: {{ $certificate->issued_at?->format('d M Y') ?? '—' }}
        </div>
        <div class="bawah">
            <div class="kolom">
                <table class="qr" role="img" aria-label="Kode QR verifikasi">
                    @foreach ($matriksQr['baris'] as $sel)
                        <tr>
                            @foreach ($sel as $hitam)
                                <td class="{{ $hitam ? 'hitam' : 'putih' }}"></td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
                <div class="teks">Pindai untuk verifikasi: {{ $tautan }}</div>
            </div>
            <div class="kolom">
                <div class="teks">Panitia, {{ $certificate->event?->organization?->name ?? 'Penyelenggara' }}</div>
                <div class="teks">{{ $certificate->issued_at?->format('d M Y') ?? '—' }}</div>
                <div class="tanda">Panitia penyelenggara</div>
            </div>
        </div>
    </div>
</body>
</html>
