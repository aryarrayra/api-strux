<?php
namespace App\Http\Controllers;

use App\Models\Pembayaran;
use App\Models\Penyewaan;
use App\Models\AlatBerat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PembayaranController extends BaseController
{
    protected $model = Pembayaran::class;
    protected $validationRules = [
        'id_sewa' => 'required|exists:penyewaan,id_sewa',
        'tanggal_bayar' => 'required|date',
        'jumlah_bayar' => 'required|numeric|min:0',
        'metode' => 'nullable|string|max:50',
        'status_pembayaran' => 'nullable|string|max:20',
        'bukti_bayar' => 'nullable|string', // Base64 image
        'nama_bukti' => 'nullable|string|max:255'
    ];

    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('🟡 PembayaranController store called');
            
            $validated = $this->validateRequest($request);
            
            // Handle bukti bayar base64
            if (!empty($validated['bukti_bayar']) && !empty($validated['nama_bukti'])) {
                Log::info('📸 Processing bukti bayar upload');
                
                $base64Image = $validated['bukti_bayar'];
                $fileName = $validated['nama_bukti'];
                
                // Decode base64
                $imageData = base64_decode($base64Image);
                if ($imageData === false) {
                    throw new \Exception('Gagal decode base64 image');
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'jpg';
                $uniqueFileName = 'bukti_bayar_' . time() . '_' . Str::random(10) . '.' . $fileExtension;
                $filePath = 'bukti-bayar/' . $uniqueFileName;
                
                // Save file
                $storagePath = storage_path('app/public/' . $filePath);
                $directory = dirname($storagePath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                
                if (file_put_contents($storagePath, $imageData) === false) {
                    throw new \Exception('Gagal menyimpan file bukti bayar');
                }
                
                Log::info("✅ Bukti bayar saved: {$filePath}");
                
                // Replace dengan path file yang disimpan
                $validated['bukti_bayar'] = $filePath;
            } else {
                $validated['bukti_bayar'] = null;
            }
            
            // Set default values
            $validated['status_pembayaran'] = $validated['status_pembayaran'] ?? 'Belum Lunas';
            $validated['metode'] = $validated['metode'] ?? 'Transfer';
            
            Log::info('📦 Creating pembayaran with data:', $validated);
            
            $pembayaran = Pembayaran::create($validated);
            
            Log::info("✅ Pembayaran created with ID: {$pembayaran->id_pembayaran}");
            
            return $this->successResponse($pembayaran, 'Bukti pembayaran berhasil dikirim', 201);
            
        } catch (ValidationException $e) {
            Log::error('❌ Validation failed:', $e->errors());
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('❌ Store error: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengirim bukti pembayaran: ' . $e->getMessage(), 500);
        }
    }

    // Method untuk testing relasi database
    public function testRelasi(Request $request): JsonResponse
    {
        try {
            Log::info("🔍 [TEST RELASI] Testing database relationships");
            
            // Cek data pembayaran dengan relasi
            $pembayaranWithRelations = Pembayaran::with(['penyewaan.alatBerat'])->get();
            
            // Cek data penyewaan
            $penyewaanData = Penyewaan::with(['alatBerat', 'pembayaran'])->get();
            
            // Cek join manual
            $manualJoin = DB::table('pembayaran')
                ->join('penyewaan', 'pembayaran.id_sewa', '=', 'penyewaan.id_sewa')
                ->join('alat_berat', 'penyewaan.id_alat', '=', 'alat_berat.id_alat')
                ->select(
                    'pembayaran.*',
                    'penyewaan.id_sewa',
                    'penyewaan.id_alat',
                    'alat_berat.kategori',
                    'alat_berat.nama_alat'
                )
                ->get();

            $data = [
                'pembayaran_count' => $pembayaranWithRelations->count(),
                'penyewaan_count' => $penyewaanData->count(),
                'manual_join_count' => $manualJoin->count(),
                'pembayaran_with_relations' => $pembayaranWithRelations->toArray(),
                'penyewaan_data' => $penyewaanData->toArray(),
                'manual_join_data' => $manualJoin->toArray(),
            ];

            Log::info("🔍 [TEST RELASI] Result:", $data);
            
            return response()->json([
                'success' => true,
                'message' => 'Test relasi berhasil',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [TEST RELASI] Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Test relasi gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method original untuk mendapatkan laporan keuangan
    public function getLaporanKeuangan(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', '6 bulan terakhir');
            
            Log::info("🎯 [LAPORAN ORIGINAL] Starting laporan keuangan for period: {$period}");
            
            // Tentukan rentang tanggal berdasarkan periode
            $dateRange = $this->getDateRange($period);
            
            Log::info("📅 [LAPORAN ORIGINAL] Date range: {$dateRange['start']} to {$dateRange['end']}");

            // DEBUG: Cek semua data di database
            $allPayments = Pembayaran::all();
            Log::info("📊 [LAPORAN ORIGINAL] ALL PAYMENTS COUNT: " . $allPayments->count());

            // DEBUG: Cek data dengan status Lunas
            $lunasPayments = Pembayaran::where('status_pembayaran', 'Lunas')->get();
            Log::info("💰 [LAPORAN ORIGINAL] LUNAS PAYMENTS COUNT: " . $lunasPayments->count());

            // Total pendapatan (pembayaran dengan status 'Lunas')
            $totalPendapatan = Pembayaran::where('status_pembayaran', 'Lunas')
                ->whereBetween('tanggal_bayar', [$dateRange['start'], $dateRange['end']])
                ->sum('jumlah_bayar');

            Log::info("💰 [LAPORAN ORIGINAL] Total pendapatan found: {$totalPendapatan}");

            // Data per kategori alat berat
            $pendapatanPerKategori = Penyewaan::join('alat_berat', 'penyewaan.id_alat', '=', 'alat_berat.id_alat')
                ->join('pembayaran', 'penyewaan.id_sewa', '=', 'pembayaran.id_sewa')
                ->where('pembayaran.status_pembayaran', 'Lunas')
                ->whereBetween('pembayaran.tanggal_bayar', [$dateRange['start'], $dateRange['end']])
                ->select(
                    'alat_berat.kategori',
                    DB::raw('SUM(pembayaran.jumlah_bayar) as total_pendapatan'),
                    DB::raw('COUNT(penyewaan.id_sewa) as total_sewa')
                )
                ->groupBy('alat_berat.kategori')
                ->get();

            Log::info("📈 [LAPORAN ORIGINAL] Kategori data found: " . $pendapatanPerKategori->count());

            // Hitung persentase per kategori
            $categories = [];
            foreach ($pendapatanPerKategori as $item) {
                $percentage = $totalPendapatan > 0 ? ($item->total_pendapatan / $totalPendapatan) * 100 : 0;
                $categories[] = [
                    'name' => $item->kategori,
                    'percentage' => round($percentage, 1),
                    'total_pendapatan' => $item->total_pendapatan,
                    'total_sewa' => $item->total_sewa,
                    'color' => $this->getCategoryColor($item->kategori)
                ];
            }

            // Jika tidak ada data, beri response yang lebih informatif
            if ($totalPendapatan == 0 && $pendapatanPerKategori->isEmpty()) {
                Log::warning("⚠️ [LAPORAN ORIGINAL] No data found for period. Returning empty response.");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Data laporan keuangan berhasil diambil',
                    'data' => [
                        'totalPendapatan' => 0,
                        'totalPengeluaran' => 0,
                        'percentageChange' => 0,
                        'categories' => [],
                        'period' => $period,
                        'dateRange' => [
                            'start' => $dateRange['start'],
                            'end' => $dateRange['end']
                        ],
                        'debug_info' => [
                            'all_payments_count' => $allPayments->count(),
                            'lunas_payments_count' => $lunasPayments->count(),
                            'date_range' => $dateRange,
                        ]
                    ]
                ], 200);
            }

            $laporan = [
                'totalPendapatan' => $totalPendapatan,
                'totalPengeluaran' => 0,
                'percentageChange' => 0,
                'categories' => $categories,
                'period' => $period,
                'dateRange' => [
                    'start' => $dateRange['start'],
                    'end' => $dateRange['end']
                ]
            ];

            Log::info("✅ [LAPORAN ORIGINAL] Laporan generated successfully");
            return response()->json([
                'success' => true,
                'message' => 'Laporan keuangan berhasil diambil',
                'data' => $laporan
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ [LAPORAN ORIGINAL] Get laporan error: ' . $e->getMessage());
            Log::error('❌ [LAPORAN ORIGINAL] Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan keuangan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method alternatif untuk laporan keuangan sederhana (tanpa join kategori)
    public function getLaporanKeuanganSimple(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', '6 bulan terakhir');
            
            Log::info("🎯 [LAPORAN SIMPLE] Starting simple laporan for period: {$period}");
            
            // Tentukan rentang tanggal
            $dateRange = $this->getDateRange($period);
            
            // Query dasar - hanya dari tabel pembayaran
            $totalPendapatan = Pembayaran::where('status_pembayaran', 'Lunas')
                ->whereBetween('tanggal_bayar', [$dateRange['start'], $dateRange['end']])
                ->sum('jumlah_bayar');

            $totalTransaksi = Pembayaran::where('status_pembayaran', 'Lunas')
                ->whereBetween('tanggal_bayar', [$dateRange['start'], $dateRange['end']])
                ->count();

            // Data per metode pembayaran (dari field metode di tabel pembayaran)
            $pendapatanPerMetode = Pembayaran::where('status_pembayaran', 'Lunas')
                ->whereBetween('tanggal_bayar', [$dateRange['start'], $dateRange['end']])
                ->select(
                    'metode',
                    DB::raw('SUM(jumlah_bayar) as total_pendapatan'),
                    DB::raw('COUNT(*) as total_transaksi')
                )
                ->groupBy('metode')
                ->get();

            Log::info("💰 [LAPORAN SIMPLE] Total pendapatan: {$totalPendapatan}");
            Log::info("💰 [LAPORAN SIMPLE] Pendapatan per metode:", $pendapatanPerMetode->toArray());

            // Format categories dari metode pembayaran
            $categories = [];
            foreach ($pendapatanPerMetode as $item) {
                $percentage = $totalPendapatan > 0 ? ($item->total_pendapatan / $totalPendapatan) * 100 : 0;
                $categories[] = [
                    'name' => $item->metode ?: 'Tidak diketahui',
                    'percentage' => round($percentage, 1),
                    'total_pendapatan' => $item->total_pendapatan,
                    'total_sewa' => $item->total_transaksi,
                    'color' => $this->getPaymentMethodColor($item->metode)
                ];
            }

            $laporan = [
                'totalPendapatan' => $totalPendapatan,
                'totalPengeluaran' => 0,
                'percentageChange' => 0,
                'categories' => $categories,
                'period' => $period,
                'dateRange' => [
                    'start' => $dateRange['start'],
                    'end' => $dateRange['end']
                ],
                'summary' => [
                    'total_transaksi' => $totalTransaksi,
                    'rata_rata_transaksi' => $totalTransaksi > 0 ? $totalPendapatan / $totalTransaksi : 0
                ],
                'query_type' => 'simple_method'
            ];

            return response()->json([
                'success' => true,
                'message' => 'Laporan keuangan sederhana berhasil diambil',
                'data' => $laporan
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ [LAPORAN SIMPLE] Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan sederhana: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method dengan left join untuk handle data yang tidak lengkap
    public function getLaporanKeuanganSafe(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', '6 bulan terakhir');
            
            Log::info("🎯 [LAPORAN SAFE] Starting safe laporan for period: {$period}");
            
            $dateRange = $this->getDateRange($period);

            // Total pendapatan dasar
            $totalPendapatan = Pembayaran::where('status_pembayaran', 'Lunas')
                ->whereBetween('tanggal_bayar', [$dateRange['start'], $dateRange['end']])
                ->sum('jumlah_bayar');

            // Query dengan left join untuk handle data yang tidak lengkap
            $pendapatanData = DB::table('pembayaran')
                ->leftJoin('penyewaan', 'pembayaran.id_sewa', '=', 'penyewaan.id_sewa')
                ->leftJoin('alat_berat', 'penyewaan.id_alat', '=', 'alat_berat.id_alat')
                ->where('pembayaran.status_pembayaran', 'Lunas')
                ->whereBetween('pembayaran.tanggal_bayar', [$dateRange['start'], $dateRange['end']])
                ->select(
                    DB::raw('COALESCE(alat_berat.kategori, "Tidak Diketahui") as kategori'),
                    DB::raw('SUM(pembayaran.jumlah_bayar) as total_pendapatan'),
                    DB::raw('COUNT(pembayaran.id_pembayaran) as total_transaksi')
                )
                ->groupBy('kategori')
                ->get();

            Log::info("💰 [LAPORAN SAFE] Pendapatan data:", $pendapatanData->toArray());

            $categories = [];
            foreach ($pendapatanData as $item) {
                $percentage = $totalPendapatan > 0 ? ($item->total_pendapatan / $totalPendapatan) * 100 : 0;
                $categories[] = [
                    'name' => $item->kategori,
                    'percentage' => round($percentage, 1),
                    'total_pendapatan' => $item->total_pendapatan,
                    'total_sewa' => $item->total_transaksi,
                    'color' => $this->getCategoryColor($item->kategori)
                ];
            }

            $laporan = [
                'totalPendapatan' => $totalPendapatan,
                'totalPengeluaran' => 0,
                'percentageChange' => 0,
                'categories' => $categories,
                'period' => $period,
                'dateRange' => [
                    'start' => $dateRange['start'],
                    'end' => $dateRange['end']
                ],
                'query_type' => 'safe_left_join'
            ];

            return response()->json([
                'success' => true,
                'message' => 'Laporan keuangan aman berhasil diambil',
                'data' => $laporan
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ [LAPORAN SAFE] Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan aman: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method dengan raw SQL untuk kontrol penuh
    public function getLaporanKeuanganRaw(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', '6 bulan terakhir');
            $dateRange = $this->getDateRange($period);

            Log::info("🎯 [LAPORAN RAW] Starting raw SQL laporan");

            // Raw SQL query
            $sql = "
                SELECT 
                    COALESCE(ab.kategori, 'Tidak Diketahui') as kategori,
                    SUM(p.jumlah_bayar) as total_pendapatan,
                    COUNT(p.id_pembayaran) as total_transaksi
                FROM pembayaran p
                LEFT JOIN penyewaan py ON p.id_sewa = py.id_sewa
                LEFT JOIN alat_berat ab ON py.id_alat = ab.id_alat
                WHERE p.status_pembayaran = 'Lunas'
                AND p.tanggal_bayar BETWEEN ? AND ?
                GROUP BY ab.kategori
                ORDER BY total_pendapatan DESC
            ";

            $pendapatanData = DB::select($sql, [$dateRange['start'], $dateRange['end']]);

            $totalPendapatan = array_sum(array_column($pendapatanData, 'total_pendapatan'));

            Log::info("💰 [LAPORAN RAW] Raw SQL result:", $pendapatanData);
            Log::info("💰 [LAPORAN RAW] Total pendapatan: {$totalPendapatan}");

            $categories = [];
            foreach ($pendapatanData as $item) {
                $percentage = $totalPendapatan > 0 ? ($item->total_pendapatan / $totalPendapatan) * 100 : 0;
                $categories[] = [
                    'name' => $item->kategori,
                    'percentage' => round($percentage, 1),
                    'total_pendapatan' => $item->total_pendapatan,
                    'total_sewa' => $item->total_transaksi,
                    'color' => $this->getCategoryColor($item->kategori)
                ];
            }

            $laporan = [
                'totalPendapatan' => $totalPendapatan,
                'totalPengeluaran' => 0,
                'percentageChange' => 0,
                'categories' => $categories,
                'period' => $period,
                'dateRange' => [
                    'start' => $dateRange['start'],
                    'end' => $dateRange['end']
                ],
                'query_type' => 'raw_sql'
            ];

            return response()->json([
                'success' => true,
                'message' => 'Laporan keuangan raw SQL berhasil diambil',
                'data' => $laporan
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ [LAPORAN RAW] Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan raw: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method untuk export Laporan (HTML/CSV)
    public function exportLaporan(Request $request)
    {
        try {
            $period = $request->get('period', '6 bulan terakhir');
            $format = $request->get('format', 'html');
            
            $dateRange = $this->getDateRange($period);
            $laporanData = $this->getLaporanDataForPDF($period, $dateRange);
            
            if ($format === 'csv') {
                return $this->exportCSV($laporanData);
            }
            
            // Default ke HTML
            return $this->exportHTML($laporanData);
                
        } catch (\Exception $e) {
            Log::error('❌ Export laporan error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengekspor laporan'], 500);
        }
    }

    private function exportHTML($laporanData)
    {
        $html = $this->generatePDFHTML($laporanData);
        
        $filename = 'laporan-keuangan-' . date('Y-m-d') . '.html';
        
        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function exportCSV($laporanData)
    {
        $filename = 'laporan-keuangan-' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($laporanData) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Header
            fputcsv($file, ['LAPORAN KEUANGAN - SISTEM ALAT BERAT']);
            fputcsv($file, []);
            fputcsv($file, ['Periode Laporan:', $laporanData['period']]);
            fputcsv($file, ['Tanggal:', $laporanData['dateRange']['start'] . ' - ' . $laporanData['dateRange']['end']]);
            fputcsv($file, ['Dibuat pada:', $laporanData['generatedAt']]);
            fputcsv($file, []);
            fputcsv($file, ['TOTAL PENDAPATAN', 'Rp ' . number_format($laporanData['totalPendapatan'], 0, ',', '.')]);
            fputcsv($file, []);
            fputcsv($file, ['KATEGORI ALAT BERAT', 'TOTAL PENDAPATAN', 'JUMLAH SEWA', 'PERSENTASE']);
            
            // Data
            $totalAll = 0;
            $totalSewa = 0;
            foreach ($laporanData['categories'] as $category) {
                fputcsv($file, [
                    $category['name'],
                    'Rp ' . number_format($category['total_pendapatan'], 0, ',', '.'),
                    $category['total_sewa'] . ' sewa',
                    $category['percentage'] . '%'
                ]);
                $totalAll += $category['total_pendapatan'];
                $totalSewa += $category['total_sewa'];
            }
            
            fputcsv($file, []);
            fputcsv($file, ['TOTAL', 'Rp ' . number_format($totalAll, 0, ',', '.'), $totalSewa . ' sewa', '100%']);
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    // Helper methods
    private function getDateRange($period)
    {
        $end = now();
        
        switch ($period) {
            case '1 minggu terakhir':
                $start = now()->subWeek();
                break;
            case '1 bulan terakhir':
                $start = now()->subMonth();
                break;
            case '3 bulan terakhir':
                $start = now()->subMonths(3);
                break;
            case '1 tahun terakhir':
                $start = now()->subYear();
                break;
            default: // 6 bulan terakhir
                $start = now()->subMonths(6);
                break;
        }
        
        return [
            'start' => $start->startOfDay()->format('Y-m-d'),
            'end' => $end->endOfDay()->format('Y-m-d')
        ];
    }

    private function getCategoryColor($kategori)
    {
        $colors = [
            'Excavator' => '#F4E5C2',
            'Bulldozer' => '#F0B952',
            'Dump Truck' => '#F5C771',
            'Crane' => '#F5D7A1',
            'Loader' => '#E8A855',
            'Motor Grader' => '#D4A574',
            'Lainnya' => '#CCCCCC',
            'Tidak Diketahui' => '#CCCCCC'
        ];
        
        return $colors[$kategori] ?? '#CCCCCC';
    }

    private function getPaymentMethodColor($metode)
    {
        $colors = [
            'Transfer' => '#F4E5C2',
            'Cash' => '#F0B952',
            'E-Wallet' => '#F5C771',
            'Kredit' => '#F5D7A1',
            'Debit' => '#E8A855',
            'Tidak diketahui' => '#CCCCCC'
        ];
        
        return $colors[$metode] ?? '#CCCCCC';
    }

    private function getLaporanDataForPDF($period, $dateRange)
    {
        // Gunakan method simple untuk PDF
        $totalPendapatan = Pembayaran::where('status_pembayaran', 'Lunas')
            ->whereBetween('tanggal_bayar', [$dateRange['start'], $dateRange['end']])
            ->sum('jumlah_bayar');

        $pendapatanPerKategori = DB::table('pembayaran')
            ->leftJoin('penyewaan', 'pembayaran.id_sewa', '=', 'penyewaan.id_sewa')
            ->leftJoin('alat_berat', 'penyewaan.id_alat', '=', 'alat_berat.id_alat')
            ->where('pembayaran.status_pembayaran', 'Lunas')
            ->whereBetween('pembayaran.tanggal_bayar', [$dateRange['start'], $dateRange['end']])
            ->select(
                DB::raw('COALESCE(alat_berat.kategori, "Tidak Diketahui") as kategori'),
                DB::raw('SUM(pembayaran.jumlah_bayar) as total_pendapatan'),
                DB::raw('COUNT(pembayaran.id_pembayaran) as total_sewa')
            )
            ->groupBy('kategori')
            ->get();

        $categories = [];
        foreach ($pendapatanPerKategori as $item) {
            $percentage = $totalPendapatan > 0 ? ($item->total_pendapatan / $totalPendapatan) * 100 : 0;
            $categories[] = [
                'name' => $item->kategori,
                'percentage' => round($percentage, 1),
                'total_pendapatan' => $item->total_pendapatan,
                'total_sewa' => $item->total_sewa
            ];
        }

        return [
            'totalPendapatan' => $totalPendapatan,
            'categories' => $categories,
            'period' => $period,
            'dateRange' => [
                'start' => Carbon::parse($dateRange['start'])->format('d F Y'),
                'end' => Carbon::parse($dateRange['end'])->format('d F Y')
            ],
            'generatedAt' => now()->format('d F Y H:i:s')
        ];
    }

    private function generatePDFHTML($laporanData)
    {
        $categoriesHtml = '';
        $totalSewa = 0;
        
        foreach ($laporanData['categories'] as $category) {
            $categoriesHtml .= "
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$category['name']}</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Rp " . number_format($category['total_pendapatan'], 0, ',', '.') . "</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$category['total_sewa']} sewa</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$category['percentage']}%</td>
                </tr>";
            $totalSewa += $category['total_sewa'];
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Laporan Keuangan</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    color: #333;
                    line-height: 1.6;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    border-bottom: 3px solid #F59E0B; 
                    padding-bottom: 20px; 
                }
                .header h1 { 
                    color: #F59E0B; 
                    margin: 0; 
                    font-size: 24px;
                }
                .summary { 
                    background: #FEF3C7; 
                    padding: 20px; 
                    border-radius: 8px; 
                    margin-bottom: 30px;
                    border-left: 4px solid #F59E0B;
                }
                .summary h3 { 
                    margin: 0; 
                    color: #78350F;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 30px; 
                    font-size: 12px;
                }
                th { 
                    background-color: #F59E0B; 
                    color: white; 
                    padding: 12px; 
                    text-align: left;
                    border: 1px solid #E5E5E5;
                }
                td { 
                    padding: 10px; 
                    border: 1px solid #E5E5E5;
                }
                .footer { 
                    text-align: center; 
                    color: #666; 
                    font-size: 11px; 
                    margin-top: 50px; 
                    border-top: 1px solid #E5E5E5;
                    padding-top: 20px;
                }
                .period-info {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    font-size: 14px;
                }
                tfoot {
                    font-weight: bold;
                    background-color: #f8f9fa;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>LAPORAN KEUANGAN</h1>
                <p>Sistem Manajemen Alat Berat - Strux</p>
            </div>

            <div class='period-info'>
                <strong>Periode Laporan:</strong> {$laporanData['period']}<br>
                <strong>Rentang Tanggal:</strong> {$laporanData['dateRange']['start']} - {$laporanData['dateRange']['end']}
            </div>

            <div class='summary'>
                <h3>Total Pendapatan: Rp " . number_format($laporanData['totalPendapatan'], 0, ',', '.') . "</h3>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Kategori Alat Berat</th>
                        <th>Total Pendapatan</th>
                        <th>Jumlah Sewa</th>
                        <th>Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    {$categoriesHtml}
                </tbody>
                <tfoot>
                    <tr>
                        <td>TOTAL</td>
                        <td style='text-align: right;'>Rp " . number_format($laporanData['totalPendapatan'], 0, ',', '.') . "</td>
                        <td style='text-align: center;'>{$totalSewa} sewa</td>
                        <td style='text-align: center;'>100%</td>
                    </tr>
                </tfoot>
            </table>

            <div class='footer'>
                <p>Dibuat secara otomatis pada: {$laporanData['generatedAt']}</p>
                <p>© " . date('Y') . " Sistem Manajemen Alat Berat - Strux</p>
            </div>
        </body>
        </html>";
    }

    // Method untuk testing data
    public function testLaporan(Request $request): JsonResponse
    {
        try {
            // Cek data langsung dari database
            $allPayments = Pembayaran::all();
            $lunasPayments = Pembayaran::where('status_pembayaran', 'Lunas')->get();
            
            $data = [
                'total_payments' => $allPayments->count(),
                'lunas_payments' => $lunasPayments->count(),
                'all_payments' => $allPayments->toArray(),
                'lunas_payments_data' => $lunasPayments->toArray(),
                'database_structure' => [
                    'table_name' => 'pembayaran',
                    'columns' => [
                        'id_pembayaran', 'id_sewa', 'tanggal_bayar', 'jumlah_bayar', 
                        'metode', 'status_pembayaran', 'created_at', 'updated_at'
                    ]
                ]
            ];
            
            return $this->successResponse($data, 'Data testing berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }

    // CRUD Methods
    public function index(): JsonResponse
    {
        try {
            $data = Pembayaran::with(['penyewaan'])
                ->orderBy('id_pembayaran', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data pembayaran berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data pembayaran', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $pembayaran = Pembayaran::with(['penyewaan'])->find($id);
            
            if (!$pembayaran) {
                return $this->errorResponse('Data pembayaran tidak ditemukan', 404);
            }

            return $this->successResponse($pembayaran, 'Data pembayaran berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data pembayaran', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $pembayaran = Pembayaran::find($id);
            
            if (!$pembayaran) {
                return $this->errorResponse('Data pembayaran tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $pembayaran->update($validated);

            return $this->successResponse($pembayaran, 'Data pembayaran berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data pembayaran', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $pembayaran = Pembayaran::find($id);
            
            if (!$pembayaran) {
                return $this->errorResponse('Data pembayaran tidak ditemukan', 404);
            }

            $pembayaran->delete();
            return $this->successResponse(null, 'Data pembayaran berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data pembayaran', 500, $e->getMessage());
        }
    }

    /**
     * Override validateRequest method to use class validation rules
     */
    protected function validateRequest(Request $request, array $rules = null, array $messages = null)
    {
        $rules = $rules ?? $this->validationRules;
        return $request->validate($rules, $messages);
    }
}