<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .periode { color: #666; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; }
        th { background: #f59e0b; text-align: left; }
        .kanan { text-align: right; }
        table.ringkasan { width: auto; margin-bottom: 12px; }
        table.ringkasan td { border: none; padding: 2px 14px 2px 0; }
        .foot { margin-top: 10px; color: #888; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $judul }}</h1>
    <div class="periode">{{ $periode }}</div>

    @if ($ringkasan)
        <table class="ringkasan">
            @foreach ($ringkasan as $item)
                <tr>
                    <td><strong>{{ $item['label'] }}</strong></td>
                    <td>{{ \App\Support\LaporanExporter::format($item['nilai'], $item['tipe'] ?? 'teks') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($kolom as $k)
                    <th class="{{ in_array($k['tipe'] ?? 'teks', ['angka', 'desimal', 'rupiah']) ? 'kanan' : '' }}">{{ $k['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($kolom as $i => $k)
                        @php($tipe = $k['tipe'] ?? 'teks')
                        <td class="{{ in_array($tipe, ['angka', 'desimal', 'rupiah']) ? 'kanan' : '' }}">{{ \App\Support\LaporanExporter::format($row[$i] ?? null, $tipe) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($kolom) }}" style="text-align:center">Tidak ada data pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>