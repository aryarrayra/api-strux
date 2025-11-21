<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #F59E0B; margin: 0; }
        .header p { color: #666; margin: 5px 0; }
        .summary { margin-bottom: 30px; }
        .summary-card { 
            background: #FEF3C7; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px;
        }
        .summary-card h3 { margin: 0 0 10px 0; color: #78350F; }
        .summary-card .amount { 
            font-size: 24px; 
            font-weight: bold; 
            color: #78350F; 
            margin: 0;
        }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background-color: #F59E0B; color: white; }
        .table tr:nth-child(even) { background-color: #f9f9f9; }
        .footer { margin-top: 50px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Keuangan</h1>
        <p>Periode: {{ $period }} ({{ $dateRange['start'] }} - {{ $dateRange['end'] }})</p>
        <p>Dibuat pada: {{ $generatedAt }}</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <h3>Total Pendapatan</h3>
            <p class="amount">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</p>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Kategori Alat Berat</th>
                <th>Total Pendapatan</th>
                <th>Jumlah Sewa</th>
                <th>Persentase</th>
            </tr>
        </thead>
        <tbody>
            @foreach($categories as $category)
            <tr>
                <td>{{ $category['name'] }}</td>
                <td>Rp {{ number_format($category['total_pendapatan'], 0, ',', '.') }}</td>
                <td>{{ $category['total_sewa'] }} sewa</td>
                <td>{{ $category['percentage'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Laporan ini dibuat secara otomatis oleh Sistem Manajemen Alat Berat</p>
    </div>
</body>
</html>