<?php
namespace App\Http\Controllers;

use App\Models\DokumenPinjaman;
use App\Models\Penyewaan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DokumenPinjamanController extends BaseController
{
    protected $model = DokumenPinjaman::class;
    
    protected $validationRules = [
        'id_sewa' => 'required|exists:penyewaan,id_sewa',
        'nama_dokumen' => 'required|string|max:255',
        'tipe_dokumen' => 'required|in:Surat_Pinjaman,KTP,SIUP,NPWP,Sertifikat,Lainnya'
    ];

    // GET: Semua dokumen
    public function index(): JsonResponse
    {
        try {
            $data = DokumenPinjaman::with(['penyewaan', 'penyewaan.pelanggan', 'penyewaan.alat', 'admin'])
                ->orderBy('id_dokumen', 'DESC')
                ->get();
                
            return $this->successResponse($data, 'Data dokumen pinjaman berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data dokumen pinjaman', 500, $e->getMessage());
        }
    }

    // POST: Upload dokumen baru
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id_sewa' => 'required|exists:penyewaan,id_sewa',
                'nama_dokumen' => 'required|string|max:255',
                'tipe_dokumen' => 'required|in:Surat_Pinjaman,KTP,SIUP,NPWP,Sertifikat,Lainnya',
                'file_dokumen' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240' // max 10MB
            ]);

            DB::beginTransaction();

            // Cek apakah penyewaan exists
            $penyewaan = Penyewaan::find($validated['id_sewa']);
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

            // Upload file
            if ($request->hasFile('file_dokumen')) {
                $file = $request->file('file_dokumen');
                
                // Generate unique filename
                $fileName = 'doc_' . time() . '_' . $validated['id_sewa'] . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('dokumen_pinjaman', $fileName, 'public');

                // Create dokumen record
                $dokumen = DokumenPinjaman::create([
                    'id_sewa' => $validated['id_sewa'],
                    'nama_dokumen' => $validated['nama_dokumen'],
                    'file_path' => $filePath,
                    'tipe_dokumen' => $validated['tipe_dokumen'],
                    'ukuran_file' => $file->getSize(),
                    'uploaded_by' => auth()->id() // admin yang upload
                ]);

                DB::commit();

                // Load relations untuk response
                $dokumen->load(['penyewaan', 'penyewaan.pelanggan', 'penyewaan.alat']);

                return $this->successResponse($dokumen, 'Dokumen berhasil diupload', 201);
            }

            DB::rollBack();
            return $this->errorResponse('File dokumen tidak ditemukan', 400);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal upload dokumen: ' . $e->getMessage(), 500);
        }
    }

    // GET: Detail dokumen
    public function show($id): JsonResponse
    {
        try {
            $dokumen = DokumenPinjaman::with([
                'penyewaan', 
                'penyewaan.pelanggan', 
                'penyewaan.alat',
                'admin'
            ])->find($id);
            
            if (!$dokumen) {
                return $this->errorResponse('Data dokumen tidak ditemukan', 404);
            }

            return $this->successResponse($dokumen, 'Data dokumen berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data dokumen', 500, $e->getMessage());
        }
    }

    // PUT: Update info dokumen (tanpa file)
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $dokumen = DokumenPinjaman::find($id);
            
            if (!$dokumen) {
                return $this->errorResponse('Data dokumen tidak ditemukan', 404);
            }

            $validated = $request->validate([
                'nama_dokumen' => 'sometimes|string|max:255',
                'tipe_dokumen' => 'sometimes|in:Surat_Pinjaman,KTP,SIUP,NPWP,Sertifikat,Lainnya'
            ]);

            $dokumen->update($validated);

            return $this->successResponse($dokumen, 'Data dokumen berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data dokumen', 500, $e->getMessage());
        }
    }

    // DELETE: Hapus dokumen
    public function destroy($id): JsonResponse
    {
        try {
            $dokumen = DokumenPinjaman::find($id);
            
            if (!$dokumen) {
                return $this->errorResponse('Data dokumen tidak ditemukan', 404);
            }

            DB::beginTransaction();

            // Hapus file dari storage
            if (Storage::disk('public')->exists($dokumen->file_path)) {
                Storage::disk('public')->delete($dokumen->file_path);
            }

            $dokumen->delete();

            DB::commit();
            return $this->successResponse(null, 'Data dokumen berhasil dihapus');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menghapus data dokumen', 500, $e->getMessage());
        }
    }

    // ==================== CUSTOM METHODS ====================

    // GET: Download dokumen
    public function download($id): JsonResponse
    {
        try {
            $dokumen = DokumenPinjaman::find($id);
            
            if (!$dokumen) {
                return $this->errorResponse('Dokumen tidak ditemukan', 404);
            }

            if (!Storage::disk('public')->exists($dokumen->file_path)) {
                return $this->errorResponse('File dokumen tidak ditemukan di server', 404);
            }

            $filePath = Storage::disk('public')->path($dokumen->file_path);
            $headers = [
                'Content-Type' => $this->getMimeType($dokumen->file_path),
                'Content-Disposition' => 'inline; filename="' . $dokumen->nama_dokumen . '"'
            ];

            return response()->download($filePath, $dokumen->nama_dokumen, $headers);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal download dokumen: ' . $e->getMessage(), 500);
        }
    }

    // GET: Dokumen by penyewaan ID
    public function getBySewa($id_sewa): JsonResponse
    {
        try {
            $dokumen = DokumenPinjaman::with(['penyewaan', 'penyewaan.pelanggan', 'penyewaan.alat', 'admin'])
                ->where('id_sewa', $id_sewa)
                ->orderBy('created_at', 'DESC')
                ->get();
                
            return $this->successResponse($dokumen, 'Data dokumen berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data dokumen', 500, $e->getMessage());
        }
    }

    // GET: Dokumen by tipe
    public function getByTipe($tipe): JsonResponse
    {
        try {
            $dokumen = DokumenPinjaman::with(['penyewaan', 'penyewaan.pelanggan', 'penyewaan.alat'])
                ->where('tipe_dokumen', $tipe)
                ->orderBy('id_dokumen', 'DESC')
                ->get();
                
            return $this->successResponse($dokumen, 'Data dokumen berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data dokumen', 500, $e->getMessage());
        }
    }

    // POST: Upload multiple dokumen
    public function uploadMultiple(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id_sewa' => 'required|exists:penyewaan,id_sewa',
                'dokumen' => 'required|array|min:1',
                'dokumen.*.nama_dokumen' => 'required|string|max:255',
                'dokumen.*.tipe_dokumen' => 'required|in:Surat_Pinjaman,KTP,SIUP,NPWP,Sertifikat,Lainnya',
                'dokumen.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
            ]);

            DB::beginTransaction();

            $uploadedDokumen = [];

            foreach ($request->file('dokumen') as $index => $file) {
                $data = $validated['dokumen'][$index];
                
                // Generate unique filename
                $fileName = 'doc_' . time() . '_' . $index . '_' . $validated['id_sewa'] . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('dokumen_pinjaman', $fileName, 'public');

                $dokumen = DokumenPinjaman::create([
                    'id_sewa' => $validated['id_sewa'],
                    'nama_dokumen' => $data['nama_dokumen'],
                    'file_path' => $filePath,
                    'tipe_dokumen' => $data['tipe_dokumen'],
                    'ukuran_file' => $file->getSize(),
                    'uploaded_by' => auth()->id()
                ]);

                $uploadedDokumen[] = $dokumen;
            }

            DB::commit();

            return $this->successResponse($uploadedDokumen, 'Multiple dokumen berhasil diupload', 201);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal upload multiple dokumen', 500, $e->getMessage());
        }
    }

    // GET: Preview dokumen (return file URL)
    public function preview($id): JsonResponse
    {
        try {
            $dokumen = DokumenPinjaman::find($id);
            
            if (!$dokumen) {
                return $this->errorResponse('Dokumen tidak ditemukan', 404);
            }

            if (!Storage::disk('public')->exists($dokumen->file_path)) {
                return $this->errorResponse('File dokumen tidak ditemukan', 404);
            }

            $fileUrl = Storage::disk('public')->url($dokumen->file_path);

            return $this->successResponse([
                'file_url' => $fileUrl,
                'dokumen' => $dokumen
            ], 'URL dokumen berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil preview dokumen', 500, $e->getMessage());
        }
    }

    // Helper function untuk get MIME type
    private function getMimeType($filePath)
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}