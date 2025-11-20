<?php
namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
            $validated['status_pembayaran'] = $validated['status_pembayaran'] ?? 'Menunggu Verifikasi';
            $validated['metode'] = $validated['metode'] ?? 'Transfer Bank';
            
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

    // Method lainnya tetap sama...
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
}