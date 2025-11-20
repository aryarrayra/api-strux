<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DokumenPinjaman;
use App\Models\Penyewaan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DokumenPinjamanController extends Controller
{
    /**
     * Upload multiple dokumen untuk penyewaan - SESUAI STRUKTUR DATABASE
     */
    public function uploadForPenyewaan(Request $request)
    {
        Log::info('=== UPLOAD FOR PENYEWAAN - SESUAI STRUKTUR ===');
        
        try {
            // Validasi dasar
            $validator = Validator::make($request->all(), [
                'id_sewa' => 'required|integer|exists:penyewaan,id_sewa',
                'dokumen' => 'required|array|min:1',
                'dokumen.*.nama_dokumen' => 'required|string|max:255',
                'dokumen.*.tipe_dokumen' => 'required|string|max:100',
                'dokumen.*.file_base64' => 'required|string',
                'dokumen.*.file_name' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                Log::error('❌ Validasi gagal:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $idSewa = $request->id_sewa;
            $uploadedIds = [];

            Log::info("🟡 Processing sewa ID: {$idSewa}, documents: " . count($request->dokumen));

            foreach ($request->dokumen as $index => $doc) {
                try {
                    Log::info("📄 Processing doc {$index}: {$doc['nama_dokumen']}");
                    
                    // Handle base64
                    $base64Data = $doc['file_base64'];
                    if (strpos($base64Data, 'base64,') !== false) {
                        $base64Data = substr($base64Data, strpos($base64Data, 'base64,') + 7);
                    }
                    
                    $fileData = base64_decode($base64Data, true);
                    if ($fileData === false) {
                        Log::error("❌ Failed to decode base64 for: {$doc['nama_dokumen']}");
                        continue;
                    }

                    // Generate file path
                    $extension = pathinfo($doc['file_name'], PATHINFO_EXTENSION) ?: 'txt';
                    $fileName = 'doc_' . $idSewa . '_' . Str::random(10) . '.' . $extension;
                    $filePath = 'dokumen-pinjaman/' . $fileName;

                    // Save file
                    $storagePath = storage_path('app/public/' . $filePath);
                    $directory = dirname($storagePath);
                    if (!is_dir($directory)) mkdir($directory, 0755, true);
                    
                    if (file_put_contents($storagePath, $fileData) === false) {
                        Log::error("❌ Failed to save file: {$filePath}");
                        continue;
                    }

                    Log::info("✅ File saved: {$filePath}");

                    // ✅✅✅ INSERT SESUAI STRUKTUR DATABASE YANG ADA ✅✅✅
                    $dokumenId = DB::table('dokumen_pinjaman')->insertGetId([
                        'id_sewa' => $idSewa,
                        'nama_dokumen' => $doc['nama_dokumen'],
                        'file_path' => $filePath, // ✅ PAKAI file_path BUKAN path_file
                        'tipe_dokumen' => $this->getValidTipeDokumen($doc['tipe_dokumen']), // ✅ SESUAI ENUM
                        'ukuran_file' => strlen($fileData),
                        'uploaded_by' => null, // atau user ID jika ada
                        'created_at' => now()
                        // ❌ TIDAK ADA: nama_file, path_file, tanggal_upload, status_dokumen, updated_at
                    ]);

                    $uploadedIds[] = $dokumenId;
                    Log::info("✅ Database record created, ID: {$dokumenId}");

                } catch (\Exception $e) {
                    Log::error("❌ Error doc {$index}: " . $e->getMessage());
                }
            }

            $successCount = count($uploadedIds);
            Log::info("🎉 Upload completed. Success: {$successCount}");

            return response()->json([
                'success' => true,
                'message' => "Upload selesai. Berhasil: {$successCount} dokumen",
                'data' => [
                    'id_sewa' => $idSewa,
                    'total_uploaded' => $successCount,
                    'dokumen_ids' => $uploadedIds
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Controller Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
 * Download dokumen
 */
public function download($id_dokumen)
{
    try {
        Log::info("📥 Download request for dokumen ID: {$id_dokumen}");
        
        // Cari dokumen
        $dokumen = DB::table('dokumen_pinjaman')->where('id_dokumen', $id_dokumen)->first();
        
        if (!$dokumen) {
            Log::error("❌ Dokumen tidak ditemukan: {$id_dokumen}");
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak ditemukan'
            ], 404);
        }

        // Pastikan file exists
        $filePath = storage_path('app/public/' . $dokumen->file_path);
        
        if (!file_exists($filePath)) {
            Log::error("❌ File tidak ditemukan: {$filePath}");
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di server'
            ], 404);
        }

        Log::info("✅ File found, downloading: {$dokumen->nama_dokumen}");
        
        // Download file
        return response()->download($filePath, $dokumen->nama_dokumen);
        
    } catch (\Exception $e) {
        Log::error('❌ Download error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Gagal download dokumen: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Get semua dokumen untuk penyewaan tertentu
 */
public function getByPenyewaan($id_sewa)
{
    try {
        Log::info("📋 Get dokumen for sewa ID: {$id_sewa}");
        
        $dokumen = DB::table('dokumen_pinjaman')
            ->where('id_sewa', $id_sewa)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dokumen,
            'total' => count($dokumen)
        ]);
        
    } catch (\Exception $e) {
        Log::error('❌ Get dokumen error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil dokumen: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * View dokumen di browser
 */
public function view($id_dokumen)
{
    try {
        Log::info("👀 View request for dokumen ID: {$id_dokumen}");
        
        // Cari dokumen
        $dokumen = DB::table('dokumen_pinjaman')->where('id_dokumen', $id_dokumen)->first();
        
        if (!$dokumen) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak ditemukan'
            ], 404);
        }

        // Pastikan file exists
        $filePath = storage_path('app/public/' . $dokumen->file_path);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di server'
            ], 404);
        }

        // Get file content and mime type
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);

        // Return response untuk view di browser
        return response($fileContent, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $dokumen->nama_dokumen . '"');
        
    } catch (\Exception $e) {
        Log::error('❌ View error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Gagal menampilkan dokumen: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Helper untuk pastikan tipe_dokumen sesuai enum
     */
    private function getValidTipeDokumen($inputTipe)
    {
        $validTypes = ['Surat_Pinjaman', 'KTP', 'SUJP', 'NPWP']; // Sesuaikan dengan enum di database
        
        // Mapping dari input ke enum value
        $mapping = [
            'KTP' => 'KTP',
            'NPWP' => 'NPWP',
            'SUJP' => 'SUJP', 
            'Surat Pinjaman' => 'Surat_Pinjaman',
            'Lainnya' => 'Surat_Pinjaman'
        ];
        
        $mapped = $mapping[$inputTipe] ?? 'Surat_Pinjaman';
        
        // Pastikan mapped value ada di valid types
        return in_array($mapped, $validTypes) ? $mapped : 'Surat_Pinjaman';
    }
}