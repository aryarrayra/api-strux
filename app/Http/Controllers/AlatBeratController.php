<?php
namespace App\Http\Controllers;

use App\Models\AlatBerat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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
        'foto' => 'nullable'
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
     * FIXED: Create new alat berat
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('🚀 [ALAT_BERAT_STORE] Starting store method');
        Log::info('📝 Request data keys:', array_keys($request->all()));
        Log::info('📁 Has file foto?:', ['has_file' => $request->hasFile('foto') ? 'YES' : 'NO']);
        
        if ($request->hasFile('foto')) {
            Log::info('📄 File info:', [
                'name' => $request->file('foto')->getClientOriginalName(),
                'size' => $request->file('foto')->getSize(),
                'mime' => $request->file('foto')->getMimeType(),
                'valid' => $request->file('foto')->isValid() ? 'YES' : 'NO'
            ]);
        }

        try {
            // Validasi
            $validated = $request->validate([
                'nama_alat' => 'required|string|max:255',
                'jenis' => 'required|string|max:100',
                'kapasitas' => 'nullable|string|max:50',
                'harga_sewa_per_hari' => 'required|numeric|min:0',
                'status' => 'required|string|in:Tersedia,Disewa,Perawatan',
                'deskripsi' => 'nullable|string',
                'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120'
            ]);

            Log::info('✅ Validation passed');

            $fotoPath = null; // Ini yang akan disimpan di database

            // Handle file upload
            if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
                $file = $request->file('foto');
                
                Log::info('📁 Processing file upload...');

                // **FIX: Pastikan folder ada**
                $storagePath = storage_path('app/public/alat-berat');
                Log::info('📂 Storage path:', ['path' => $storagePath]);
                
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0777, true);
                    Log::info('📂 Created directory');
                }

                // Generate filename
                $filename = 'alat_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                Log::info('📝 Generated filename:', ['filename' => $filename]);

                // **FIX: Simpan dengan Storage facade**
                $path = $file->storeAs('public/alat-berat', $filename);
                Log::info('💾 Storage result:', ['path' => $path]);

                if ($path) {
                    // **FIX: Simpan path RELATIF tanpa 'public/' di database**
                    $fotoPath = 'alat-berat/' . $filename; // ✅ INI YANG BENAR
                    Log::info('✅ File saved. Path for DB:', ['db_path' => $fotoPath]);
                    
                    // Verifikasi file tersimpan
                    $fullPath = storage_path('app/' . $path);
                    if (file_exists($fullPath)) {
                        Log::info('✅ File verified on disk', [
                            'size' => filesize($fullPath),
                            'path' => $fullPath
                        ]);
                    } else {
                        Log::error('❌ File not found after save!');
                    }
                } else {
                    Log::error('❌ Storage failed!');
                }
            } else {
                Log::info('ℹ️ No file uploaded');
                
                if ($request->hasFile('foto')) {
                    Log::error('❌ File uploaded but invalid:', [
                        'error' => $request->file('foto')->getError(),
                        'message' => $request->file('foto')->getErrorMessage()
                    ]);
                }
            }

            // **FIX: Log sebelum create**
            Log::info('📊 Data to save in DB:', [
                'nama_alat' => $validated['nama_alat'],
                'foto_path' => $fotoPath,
                'foto_is_null' => is_null($fotoPath) ? 'YES' : 'NO'
            ]);

            // Create alat berat
            $alat = AlatBerat::create([
                'nama_alat' => $validated['nama_alat'],
                'jenis' => $validated['jenis'],
                'kapasitas' => $validated['kapasitas'] ?? null,
                'harga_sewa_per_hari' => $validated['harga_sewa_per_hari'],
                'status' => $validated['status'],
                'deskripsi' => $validated['deskripsi'] ?? null,
                'foto' => $fotoPath  // Simpan path relatif: 'alat-berat/filename.jpg'
            ]);

            Log::info('💾 Database record created:', [
                'id' => $alat->id_alat,
                'nama_alat' => $alat->nama_alat,
                'foto_in_db' => $alat->foto,
                'foto_type' => gettype($alat->foto)
            ]);

            // **FIX: Return dengan URL yang benar**
            $responseData = $alat->toArray();
            $responseData['foto'] = $this->getFotoUrl($alat->foto); // Convert ke URL
            
            // Log response
            Log::info('📤 Response data:', [
                'foto_url' => $responseData['foto'],
                'foto_in_db' => $alat->foto
            ]);

            return $this->successResponse($responseData, 'Data alat berat berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            Log::error('❌ Validation error', $e->errors());
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('❌ Error: ' . $e->getMessage());
            Log::error('❌ Trace: ' . $e->getTraceAsString());
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
     * FIXED: Update alat berat
     */
    public function update(Request $request, $id): JsonResponse
    {
        Log::info('🔄 [ALAT_BERAT_UPDATE] Starting update', ['id' => $id]);

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
                'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120'
            ]);

            $fotoPath = $alat->foto; // Keep existing path

            // Handle file upload jika ada file baru
            if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
                Log::info('📁 New file detected for update');
                
                // Hapus file lama jika ada
                if ($alat->foto) {
                    $this->deleteFoto($alat->foto);
                }
                
                // Upload file baru
                $file = $request->file('foto');
                $filename = 'alat_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                
                // Simpan dengan Storage
                $path = $file->storeAs('public/alat-berat', $filename);
                
                if ($path) {
                    $fotoPath = 'alat-berat/' . $filename; // ✅ Simpan path relatif
                    Log::info('✅ New file saved:', ['path' => $fotoPath]);
                }
            }
            
            // Update data
            $alat->update([
                'nama_alat' => $validated['nama_alat'] ?? $alat->nama_alat,
                'jenis' => $validated['jenis'] ?? $alat->jenis,
                'kapasitas' => $validated['kapasitas'] ?? $alat->kapasitas,
                'harga_sewa_per_hari' => $validated['harga_sewa_per_hari'] ?? $alat->harga_sewa_per_hari,
                'status' => $validated['status'] ?? $alat->status,
                'deskripsi' => $validated['deskripsi'] ?? $alat->deskripsi,
                'foto' => $fotoPath // Simpan path relatif
            ]);
            
            Log::info('✅ Database updated:', [
                'id' => $alat->id_alat,
                'foto_in_db' => $alat->foto
            ]);
            
            // Return dengan full URL
            $responseData = $alat->toArray();
            $responseData['foto'] = $this->getFotoUrl($alat->foto);
            
            return $this->successResponse($responseData, 'Data alat berat berhasil diupdate');
            
        } catch (ValidationException $e) {
            Log::error('❌ Validation error', $e->errors());
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('❌ Error: ' . $e->getMessage());
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

    // ========== HELPER METHODS ==========

    /**
     * FIXED: Get full URL untuk foto
     * Database menyimpan: 'alat-berat/filename.jpg'
     * Return: 'http://localhost:8000/storage/alat-berat/filename.jpg'
     */
    private function getFotoUrl(?string $fotoPath): string
    {
        Log::info('🔍 getFotoUrl called with:', ['path' => $fotoPath]);

        // Jika null atau kosong
        if (empty($fotoPath)) {
            Log::info('⚠️ Foto path empty, returning default');
            return asset('images/default-alat-berat.jpg');
        }

        // Jika sudah full URL (http/https), return as is
        if (filter_var($fotoPath, FILTER_VALIDATE_URL)) {
            Log::info('✅ Already full URL:', ['url' => $fotoPath]);
            return $fotoPath;
        }

        // **FIX: Database menyimpan 'alat-berat/filename.jpg'**
        // Convert ke: 'storage/alat-berat/filename.jpg'
        if (strpos($fotoPath, 'alat-berat/') === 0) {
            $url = asset('storage/' . $fotoPath);
            Log::info('✅ Converted alat-berat path:', [
                'input' => $fotoPath,
                'output' => $url
            ]);
            return $url;
        }

        // Jika format lain, coba tambahkan storage/
        $possiblePaths = [
            'storage/' . $fotoPath,
            $fotoPath,
            'storage/alat-berat/' . basename($fotoPath)
        ];

        foreach ($possiblePaths as $path) {
            $fullPath = public_path($path);
            if (file_exists($fullPath)) {
                $url = asset($path);
                Log::info('✅ File found:', ['path' => $path, 'url' => $url]);
                return $url;
            }
        }

        // Jika tidak ditemukan
        Log::warning('⚠️ File not found:', ['path' => $fotoPath]);
        return asset('images/default-alat-berat.jpg');
    }

    /**
     * FIXED: Hapus foto dari storage
     */
    private function deleteFoto(?string $fotoPath): void
    {
        if (empty($fotoPath)) {
            Log::info('ℹ️ No foto to delete');
            return;
        }

        Log::info('🗑️ deleteFoto:', ['path' => $fotoPath]);

        try {
            // Jika path adalah 'alat-berat/filename.jpg'
            if (strpos($fotoPath, 'alat-berat/') === 0) {
                // Hapus dari storage
                if (Storage::exists('public/' . $fotoPath)) {
                    Storage::delete('public/' . $fotoPath);
                    Log::info('✅ File deleted from storage:', ['path' => $fotoPath]);
                }
                
                // Juga hapus dari public/storage/ (symlink)
                $publicPath = public_path('storage/' . $fotoPath);
                if (file_exists($publicPath)) {
                    unlink($publicPath);
                    Log::info('✅ File deleted from public storage:', ['path' => $publicPath]);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('❌ Error deleting file:', [
                'path' => $fotoPath,
                'error' => $e->getMessage()
            ]);
        }
    }
}