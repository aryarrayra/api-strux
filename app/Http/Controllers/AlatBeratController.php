<?php
namespace App\Http\Controllers;

use App\Models\AlatBerat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AlatBeratController extends BaseController
{
    protected $model = AlatBerat::class;
    protected $validationRules = [
        'nama_alat' => 'required|string|max:255',
        'jenis' => 'required|string|max:100',
        'kapasitas' => 'nullable|string|max:50',
        'harga_sewa_per_hari' => 'required|numeric|min:0',
        'status' => 'required|string|in:Tersedia,Disewa,Perawatan',
        'deskripsi' => 'nullable|string',
        'foto' => 'nullable|string'
    ];

    /**
     * Get all alat berat dengan full URL foto
     */
    public function index(): JsonResponse
    {
        try {
            $data = AlatBerat::orderBy('id_alat', 'DESC')->get();
            
            // Transform data untuk include full URL foto
            $data->transform(function ($item) {
                $item->foto = $this->getFotoUrl($item->foto);
                return $item;
            });

            return $this->successResponse($data, 'Data alat berat berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data alat berat', 500, $e->getMessage());
        }
    }

    /**
     * Create new alat berat dengan foto base64
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nama_alat' => 'required|string|max:255',
                'jenis' => 'required|string|max:100',
                'kapasitas' => 'nullable|string|max:50',
                'harga_sewa_per_hari' => 'required|numeric|min:0',
                'status' => 'required|string|in:Tersedia,Disewa,Perawatan',
                'deskripsi' => 'nullable|string',
                'foto' => 'nullable|string'
            ]);

            // Handle base64 image dan simpan ke public/storage/alat-berat/
            if (isset($validated['foto']) && !empty($validated['foto'])) {
                $validated['foto'] = $this->saveBase64Image($validated['foto']);
            }

            $alat = AlatBerat::create($validated);
            
            // Return dengan full URL foto
            $alat->foto = $this->getFotoUrl($alat->foto);
            
            return $this->successResponse($alat, 'Data alat berat berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data alat berat: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single alat berat
     */
    public function show($id): JsonResponse
    {
        try {
            $alat = AlatBerat::find($id);
            
            if (!$alat) {
                return $this->errorResponse('Data alat berat tidak ditemukan', 404);
            }

            // Return full URL untuk foto
            $alat->foto = $this->getFotoUrl($alat->foto);

            return $this->successResponse($alat, 'Data alat berat berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data alat berat', 500, $e->getMessage());
        }
    }

    /**
     * Update alat berat
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $alat = AlatBerat::find($id);
            
            if (!$alat) {
                return $this->errorResponse('Data alat berat tidak ditemukan', 404);
            }

            $validated = $request->validate([
                'nama_alat' => 'sometimes|required|string|max:255',
                'jenis' => 'sometimes|required|string|max:100',
                'kapasitas' => 'nullable|string|max:50',
                'harga_sewa_per_hari' => 'sometimes|required|numeric|min:0',
                'status' => 'sometimes|required|string|in:Tersedia,Disewa,Perawatan',
                'deskripsi' => 'nullable|string',
                'foto' => 'nullable|string'
            ]);

            // Handle base64 image untuk update
            if (isset($validated['foto']) && !empty($validated['foto'])) {
                // Hapus foto lama jika ada
                $this->deleteFoto($alat->foto);
                
                // Simpan foto baru
                $validated['foto'] = $this->saveBase64Image($validated['foto']);
            }

            $alat->update($validated);

            // Return full URL untuk foto
            $alat->foto = $this->getFotoUrl($alat->foto);

            return $this->successResponse($alat, 'Data alat berat berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data alat berat: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete alat berat
     */
    public function destroy($id): JsonResponse
    {
        try {
            $alat = AlatBerat::find($id);
            
            if (!$alat) {
                return $this->errorResponse('Data alat berat tidak ditemukan', 404);
            }

            // Hapus foto jika ada
            $this->deleteFoto($alat->foto);

            $alat->delete();
            return $this->successResponse(null, 'Data alat berat berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data alat berat', 500, $e->getMessage());
        }
    }

    /**
     * Get alat berat by status
     */
    public function getByStatus($status): JsonResponse
    {
        try {
            $validStatuses = ['Tersedia', 'Disewa', 'Perawatan'];
            
            if (!in_array($status, $validStatuses)) {
                return $this->errorResponse('Status tidak valid', 422);
            }

            $data = AlatBerat::where('status', $status)
                ->orderBy('id_alat', 'DESC')
                ->get();
            
            // Transform data untuk include full URL foto
            $data->transform(function ($item) {
                $item->foto = $this->getFotoUrl($item->foto);
                return $item;
            });
                
            return $this->successResponse($data, "Data alat berat dengan status {$status} berhasil diambil");
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data alat berat', 500, $e->getMessage());
        }
    }

    /**
     * Search alat berat by name
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $keyword = $request->query('q');
            
            if (!$keyword) {
                return $this->errorResponse('Keyword pencarian harus diisi', 422);
            }

            $data = AlatBerat::where('nama_alat', 'like', "%{$keyword}%")
                ->orWhere('jenis', 'like', "%{$keyword}%")
                ->orderBy('id_alat', 'DESC')
                ->get();
            
            // Transform data untuk include full URL foto
            $data->transform(function ($item) {
                $item->foto = $this->getFotoUrl($item->foto);
                return $item;
            });
                
            return $this->successResponse($data, "Hasil pencarian untuk '{$keyword}'");
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal melakukan pencarian', 500, $e->getMessage());
        }
    }

    /**
     * Upload foto via multipart/form-data
     */
    public function uploadFoto(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'foto' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120' // 5MB
            ]);

            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                
                // Generate unique filename
                $filename = 'alat-berat-' . time() . '-' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                
                // Simpan ke public/storage/alat-berat/
                $destinationPath = public_path('storage/alat-berat');
                
                // Buat folder jika belum ada
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $file->move($destinationPath, $filename);
                
                // Path relatif untuk disimpan di database
                $fotoPath = 'storage/alat-berat/' . $filename;
                
                // Full URL untuk response
                $fullUrl = asset($fotoPath);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Foto berhasil diupload',
                    'data' => [
                        'foto_path' => $fotoPath,
                        'foto_url' => $fullUrl,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Tidak ada file yang diupload'
            ], 400);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload foto: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========== HELPER METHODS ==========

    /**
     * ✅ SIMPLE FIX: Simpan base64 image langsung ke public/image/alat-berat/
     * Tanpa pake storage symlink, langsung ke folder public!
     */
    private function saveBase64Image(string $base64String): string
    {
        try {
            \Log::info('🔍 saveBase64Image - Input:', [
                'length' => strlen($base64String),
                'starts_with_data_uri' => str_starts_with($base64String, 'data:image')
            ]);

            // Check if it's a base64 string with data URI scheme
            if (str_starts_with($base64String, 'data:image')) {
                // Extract base64 data from data URI
                @list($type, $data) = explode(';', $base64String);
                @list(, $data) = explode(',', $data);
                $imageData = base64_decode($data);
                
                // Get image type from data URI
                preg_match('/data:image\/([a-zA-Z0-9]+);/', $base64String, $matches);
                $extension = $matches[1] ?? 'jpg';
            } else {
                // Pure base64 string (no data URI prefix)
                $imageData = base64_decode($base64String);
                $extension = 'jpg';
            }
            
            // Validasi image data
            if (!$imageData || strlen($imageData) < 100) {
                throw new \Exception('Invalid or empty image data');
            }

            // Generate unique filename
            $filename = 'alat-berat-' . time() . '-' . Str::random(10) . '.' . $extension;
            
            // ✅ SIMPAN LANGSUNG KE: public/image/alat-berat/
            $destinationPath = public_path('image/alat-berat');
            
            // Buat folder jika belum ada
            if (!file_exists($destinationPath)) {
                @mkdir($destinationPath, 0777, true);
            }
            
            // Set permission folder agar bisa ditulis
            @chmod($destinationPath, 0777);
            
            // Simpan file
            $fullFilePath = $destinationPath . '/' . $filename;
            $bytesWritten = @file_put_contents($fullFilePath, $imageData);
            
            if ($bytesWritten === false) {
                throw new \Exception('Failed to write image file to: ' . $fullFilePath);
            }

            // Set permissions file
            @chmod($fullFilePath, 0666);

            // ✅ Return FULL URL langsung
            // URL: http://localhost:8000/image/alat-berat/xxx.jpg
            $relativePath = 'image/alat-berat/' . $filename;
            $fullUrl = asset($relativePath);

            \Log::info('✅ Image saved successfully', [
                'filename' => $filename,
                'physical_path' => $fullFilePath,
                'relative_path' => $relativePath,
                'url' => $fullUrl,
                'size_bytes' => $bytesWritten
            ]);

            return $fullUrl;
            
        } catch (\Exception $e) {
            \Log::error('❌ Error saving base64 image:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Gagal menyimpan foto: ' . $e->getMessage());
        }
    }

    /**
     * ✅ FIXED: Get full URL untuk foto
     * Mengembalikan FULL URL ke file gambar
     */
    private function getFotoUrl(?string $fotoPath): string
    {
        // Jika null atau kosong
        if (empty($fotoPath)) {
            return asset('images/default-alat-berat.jpg');
        }

        \Log::info('🔍 getFotoUrl - Processing:', ['path' => $fotoPath]);

        // Jika sudah full URL (http/https), return as is
        if (filter_var($fotoPath, FILTER_VALIDATE_URL)) {
            \Log::info('✅ Already full URL:', ['url' => $fotoPath]);
            return $fotoPath;
        }
        
        // Cek berbagai kemungkinan path
        $possiblePaths = [
            $fotoPath,  // Path apa adanya
            'storage/alat-berat/' . basename($fotoPath),  // storage/alat-berat/xxx.jpg
            'storage/image/alat-berat/' . basename($fotoPath),  // storage/image/alat-berat/xxx.jpg
        ];

        foreach ($possiblePaths as $path) {
            $fullPath = public_path($path);
            if (file_exists($fullPath)) {
                $url = asset($path);
                \Log::info('✅ File found at:', ['path' => $path, 'url' => $url]);
                return $url;
            }
        }

        // Jika file tidak ada di mana pun
        \Log::warning('⚠️ File not found in any location:', [
            'originalPath' => $fotoPath,
            'checkedPaths' => $possiblePaths
        ]);
        
        return asset('images/default-alat-berat.jpg');
    }

    /**
     * ✅ FIXED: Hapus foto dari storage
     */
    private function deleteFoto(?string $fotoPath): void
    {
        if (empty($fotoPath)) {
            return;
        }

        \Log::info('🗑️ deleteFoto - Processing:', ['path' => $fotoPath]);

        try {
            // Jika full URL, extract path dan hapus file
            if (filter_var($fotoPath, FILTER_VALIDATE_URL)) {
                // Extract path dari URL
                // Contoh: http://localhost:8000/storage/alat-berat/xxx.jpg
                // Ambil bagian: storage/alat-berat/xxx.jpg
                $parsed = parse_url($fotoPath);
                $pathOnly = ltrim($parsed['path'], '/');
                
                // Cek apakah ini path lokal di storage/alat-berat/
                if (strpos($pathOnly, 'storage/alat-berat/') !== false) {
                    $fullPath = public_path($pathOnly);
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                        \Log::info('✅ File deleted:', ['path' => $fullPath]);
                    }
                }
                return;
            }
            
            // Jika path relatif, hapus file langsung
            $fullPath = public_path($fotoPath);
            if (file_exists($fullPath)) {
                unlink($fullPath);
                \Log::info('✅ File deleted:', ['path' => $fullPath]);
            }
            
        } catch (\Exception $e) {
            \Log::error('❌ Error deleting file:', [
                'path' => $fotoPath,
                'error' => $e->getMessage()
            ]);
        }
    }
}