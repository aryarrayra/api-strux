<?php
namespace App\Http\Controllers;

use App\Models\AlatBerat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

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
     * Get all alat berat - PERBAIKI UNTUK RETURN FULL URL
     */
    public function index(): JsonResponse
    {
        try {
            $data = AlatBerat::orderBy('id_alat', 'DESC')->get();
            
            // Transform data untuk include full URL foto
            $data->transform(function ($item) {
                if ($item->foto) {
                    // Jika foto sudah URL, biarkan
                    if (filter_var($item->foto, FILTER_VALIDATE_URL)) {
                        // Sudah URL, tidak perlu diubah
                    } 
                    // Jika foto adalah path storage, convert ke URL
                    else if (Storage::disk('public')->exists($item->foto)) {
                        $item->foto = asset('storage/' . $item->foto);
                    }
                    // Jika foto adalah path tapi file tidak ada, gunakan default
                    else {
                        $item->foto = asset('images/default-alat-berat.jpg');
                    }
                } else {
                    // Jika tidak ada foto, gunakan default
                    $item->foto = asset('images/default-alat-berat.jpg');
                }
                return $item;
            });

            return $this->successResponse($data, 'Data alat berat berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data alat berat', 500, $e->getMessage());
        }
    }

    /**
     * Create new alat berat - PERBAIKI
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

            // Handle base64 image
            if (isset($validated['foto']) && str_starts_with($validated['foto'], 'data:image')) {
                $base64Image = $validated['foto'];
                
                // Extract base64 data
                @list($type, $data) = explode(';', $base64Image);
                @list(, $data) = explode(',', $data);
                
                // Decode base64
                $imageData = base64_decode($data);
                
                // Generate unique filename
                $filename = 'alat-berat-' . time() . '.jpg';
                $path = 'alat-berat/' . $filename;
                
                // Save to storage
                Storage::disk('public')->put($path, $imageData);
                
                $validated['foto'] = $path;
            }

            $alat = AlatBerat::create($validated);
            
            // Return full URL untuk foto
            if ($alat->foto) {
                $alat->foto = asset('storage/' . $alat->foto);
            } else {
                $alat->foto = asset('images/default-alat-berat.jpg');
            }
            
            return $this->successResponse($alat, 'Data alat berat berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data alat berat: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single alat berat - PERBAIKI
     */
    public function show($id): JsonResponse
    {
        try {
            $alat = AlatBerat::find($id);
            
            if (!$alat) {
                return $this->errorResponse('Data alat berat tidak ditemukan', 404);
            }

            // Return full URL untuk foto
            if ($alat->foto) {
                if (filter_var($alat->foto, FILTER_VALIDATE_URL)) {
                    // Sudah URL, tidak perlu diubah
                } else if (Storage::disk('public')->exists($alat->foto)) {
                    $alat->foto = asset('storage/' . $alat->foto);
                } else {
                    $alat->foto = asset('images/default-alat-berat.jpg');
                }
            } else {
                $alat->foto = asset('images/default-alat-berat.jpg');
            }

            return $this->successResponse($alat, 'Data alat berat berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data alat berat', 500, $e->getMessage());
        }
    }

    /**
     * Update alat berat - PERBAIKI
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

            // Handle base64 image
            if (isset($validated['foto']) && str_starts_with($validated['foto'], 'data:image')) {
                $base64Image = $validated['foto'];
                
                // Delete old photo if exists
                if ($alat->foto && Storage::disk('public')->exists($alat->foto)) {
                    Storage::disk('public')->delete($alat->foto);
                }
                
                // Extract base64 data
                @list($type, $data) = explode(';', $base64Image);
                @list(, $data) = explode(',', $data);
                
                // Decode base64
                $imageData = base64_decode($data);
                
                // Generate unique filename
                $filename = 'alat-berat-' . time() . '.jpg';
                $path = 'alat-berat/' . $filename;
                
                // Save to storage
                Storage::disk('public')->put($path, $imageData);
                
                $validated['foto'] = $path;
            }

            $alat->update($validated);

            // Return full URL untuk foto
            if ($alat->foto) {
                $alat->foto = asset('storage/' . $alat->foto);
            } else {
                $alat->foto = asset('images/default-alat-berat.jpg');
            }

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

            // Delete photo if exists dan bukan URL external
            if ($alat->foto && !filter_var($alat->foto, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($alat->foto)) {
                Storage::disk('public')->delete($alat->foto);
            }

            $alat->delete();
            return $this->successResponse(null, 'Data alat berat berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data alat berat', 500, $e->getMessage());
        }
    }

    /**
     * Get alat berat by status - PERBAIKI
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
                if ($item->foto) {
                    if (filter_var($item->foto, FILTER_VALIDATE_URL)) {
                        // Sudah URL, tidak perlu diubah
                    } else if (Storage::disk('public')->exists($item->foto)) {
                        $item->foto = asset('storage/' . $item->foto);
                    } else {
                        $item->foto = asset('images/default-alat-berat.jpg');
                    }
                } else {
                    $item->foto = asset('images/default-alat-berat.jpg');
                }
                return $item;
            });
                
            return $this->successResponse($data, "Data alat berat dengan status {$status} berhasil diambil");
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data alat berat', 500, $e->getMessage());
        }
    }

    /**
     * Search alat berat by name - PERBAIKI
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
                if ($item->foto) {
                    if (filter_var($item->foto, FILTER_VALIDATE_URL)) {
                        // Sudah URL, tidak perlu diubah
                    } else if (Storage::disk('public')->exists($item->foto)) {
                        $item->foto = asset('storage/' . $item->foto);
                    } else {
                        $item->foto = asset('images/default-alat-berat.jpg');
                    }
                } else {
                    $item->foto = asset('images/default-alat-berat.jpg');
                }
                return $item;
            });
                
            return $this->successResponse($data, "Hasil pencarian untuk '{$keyword}'");
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal melakukan pencarian', 500, $e->getMessage());
        }
    }

    /**
     * Upload foto - PERBAIKI
     */
    public function uploadFoto(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'foto' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120' // 5MB
            ]);

            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store('alat-berat', 'public');
                $fullUrl = asset('storage/' . $fotoPath);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Foto berhasil diupload',
                    'data' => [
                        'foto_path' => $fotoPath, // Path untuk disimpan di database
                        'foto_url' => $fullUrl,   // Full URL untuk ditampilkan
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
}