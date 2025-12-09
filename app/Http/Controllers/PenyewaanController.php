<?php

namespace App\Http\Controllers;

use App\Models\Penyewaan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PenyewaanController extends BaseController
{
    protected $model = Penyewaan::class;
    protected $validationRules = [
        'id_pelanggan' => 'required|exists:pelanggan,id_pelanggan',
        'id_alat' => 'required|exists:alat_berat,id_alat',
        'tanggal_sewa' => 'required|date',
        'tanggal_kembali' => 'nullable|date',
        'total_harga' => 'required|numeric|min:0',
        'status_sewa' => 'nullable|string|max:20',
        'status_persetujuan' => 'nullable|string|max:20',
        'alasan_penolakan' => 'nullable|string',
        'nama_proyek' => 'nullable|string|max:255',
        'lokasi_proyek' => 'nullable|string|max:255',
        'deskripsi_proyek' => 'nullable|string',
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'dokumen_data' => 'nullable|string'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = Penyewaan::with(['pelanggan', 'alat', 'pembayaran', 'dokumen'])
                ->select('*')
                ->orderBy('id_sewa', 'DESC')
                ->get();

            return $this->successResponse($data, 'Data penyewaan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data penyewaan', 500, $e->getMessage());
        }
    }

    public function uploadDokumen(Request $request, $idSewa): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::find($idSewa);
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

            $validator = Validator::make($request->all(), [
                'dokumen' => 'required|array|min:1',
                'dokumen.*.nama_dokumen' => 'required|string|max:255',
                'dokumen.*.tipe_dokumen' => 'required|string|max:100',
                'dokumen.*.file_base64' => 'required|string',
                'dokumen.*.file_name' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validasi gagal', 422, $validator->errors());
            }

            $uploadedFiles = [];
            DB::beginTransaction();

            foreach ($request->dokumen as $doc) {
                $base64Data = $doc['file_base64'];
                if (strpos($base64Data, 'base64,') !== false) {
                    $base64Data = substr($base64Data, strpos($base64Data, 'base64,') + 7);
                }

                $fileData = base64_decode($base64Data, true);
                if ($fileData === false) continue;

                $extension = pathinfo($doc['file_name'], PATHINFO_EXTENSION) ?: 'pdf';
                $fileName = 'doc_' . $idSewa . '_' . uniqid() . '.' . $extension;
                $filePath = 'dokumen-pinjaman/' . $fileName;

                Storage::disk('public')->put($filePath, $fileData);

                $dokumenId = DB::table('dokumen_pinjaman')->insertGetId([
                    'id_sewa' => $idSewa,
                    'nama_dokumen' => $doc['nama_dokumen'],
                    'file_path' => $filePath,
                    'tipe_dokumen' => $doc['tipe_dokumen'],
                    'ukuran_file' => strlen($fileData),
                    'uploaded_by' => auth()->id(),
                    'created_at' => now()
                ]);

                $uploadedFiles[] = [
                    'id' => $dokumenId,
                    'nama' => $doc['file_name'],
                    'size' => strlen($fileData),
                    'uploaded_at' => now()->toISOString()
                ];
            }

            if (!empty($uploadedFiles)) {
                $existingDokumen = $penyewaan->dokumen_data ? json_decode($penyewaan->dokumen_data, true) : [];
                $allDokumen = array_merge($existingDokumen, $uploadedFiles);
                $penyewaan->update(['dokumen_data' => json_encode($allDokumen)]);
            }

            DB::commit();
            return $this->successResponse(['id_sewa' => $idSewa, 'dokumen_uploaded' => $uploadedFiles], 'Dokumen berhasil diupload');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal upload dokumen', 500, $e->getMessage());
        }
    }

    public function getPersetujuanPinjaman(): JsonResponse
    {
        try {
            $data = Penyewaan::with(['pelanggan', 'alat', 'dokumen', 'pembayaran'])
                ->where('status_persetujuan', 'Menunggu')
                ->orderBy('id_sewa', 'DESC')
                ->get();

            return $this->successResponse($data, 'Data persetujuan pinjaman berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data persetujuan pinjaman', 500, $e->getMessage());
        }
    }

    public function getDokumenPenyewaan($idSewa): JsonResponse
    {
        try {
            $dokumen = DB::table('dokumen_pinjaman')
                ->where('id_sewa', $idSewa)
                ->select('id', 'nama_dokumen', 'file_path', 'tipe_dokumen', 'ukuran_file', 'created_at')
                ->get();

            return $this->successResponse($dokumen, 'Dokumen penyewaan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil dokumen', 500, $e->getMessage());
        }
    }

    public function viewDokumen($idDokumen): JsonResponse
    {
        try {
            $dokumen = DB::table('dokumen_pinjaman')->where('id', $idDokumen)->first();
            if (!$dokumen) return $this->errorResponse('Dokumen tidak ditemukan', 404);
            if (!Storage::disk('public')->exists($dokumen->file_path)) {
                return $this->errorResponse('File tidak ditemukan', 404);
            }

            $fileContent = Storage::disk('public')->get($dokumen->file_path);
            $base64Content = base64_encode($fileContent);
            $mimeType = Storage::disk('public')->mimeType($dokumen->file_path);

            return $this->successResponse([
                'id' => $dokumen->id,
                'nama_dokumen' => $dokumen->nama_dokumen,
                'file_name' => basename($dokumen->file_path),
                'mime_type' => $mimeType,
                'file_content' => $base64Content,
                'size' => $dokumen->ukuran_file
            ], 'Dokumen berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil dokumen', 500, $e->getMessage());
        }
    }

    public function approvePinjaman(Request $request, $id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::with('dokumen')->find($id);
            if (!$penyewaan) return $this->errorResponse('Data penyewaan tidak ditemukan', 404);

            $validated = $request->validate([
                'status_persetujuan' => 'required|in:Disetujui,Ditolak',
                'alasan_penolakan' => 'required_if:status_persetujuan,Ditolak|string|max:500',
                'status_sewa' => 'nullable|string|max:20',
                'catatan_dokumen' => 'nullable|string'
            ]);

            DB::beginTransaction();

            $updateData = [
                'status_persetujuan' => $validated['status_persetujuan'],
                'disetujui_oleh' => auth()->id(),
                'tanggal_persetujuan' => now()
            ];

            if ($validated['status_persetujuan'] === 'Ditolak') {
                $updateData['alasan_penolakan'] = $validated['alasan_penolakan'];
                $updateData['status_sewa'] = 'Dibatalkan';
            } else {
                $updateData['status_sewa'] = 'Dalam Pengantaran';
                $updateData['alasan_penolakan'] = null;

                DB::table('alat_berat')
                    ->where('id_alat', $penyewaan->id_alat)
                    ->update(['status' => 'Disewa']);
            }

            $penyewaan->update($updateData);
            DB::commit();

            $message = $validated['status_persetujuan'] === 'Disetujui'
                ? 'Penyewaan berhasil disetujui dan status diubah menjadi Dalam Pengantaran'
                : 'Penyewaan berhasil ditolak';

            return $this->successResponse($penyewaan, $message);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal memproses persetujuan', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);

            $validated['status_persetujuan'] = 'Menunggu';
            $validated['status_sewa'] = 'Menunggu Persetujuan';

            $validated['nama_proyek'] = $validated['nama_proyek'] ?? null;
            $validated['lokasi_proyek'] = $validated['lokasi_proyek'] ?? null;
            $validated['deskripsi_proyek'] = $validated['deskripsi_proyek'] ?? null;
            $validated['latitude'] = $validated['latitude'] ?? null;
            $validated['longitude'] = $validated['longitude'] ?? null;
            $validated['dokumen_data'] = $validated['dokumen_data'] ?? null;

            DB::beginTransaction();
            $penyewaan = Penyewaan::create($validated);
            DB::commit();

            return $this->successResponse($penyewaan, 'Penyewaan berhasil diajukan, menunggu persetujuan', 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal mengajukan penyewaan', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::with(['pelanggan', 'alat', 'pembayaran', 'jadwal', 'dokumen'])->find($id);
            if (!$penyewaan) return $this->errorResponse('Data penyewaan tidak ditemukan', 404);

            return $this->successResponse($penyewaan, 'Data penyewaan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data penyewaan', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::find($id);
            if (!$penyewaan) return $this->errorResponse('Data penyewaan tidak ditemukan', 404);

            $validated = $this->validateRequest($request);
            $penyewaan->update($validated);

            return $this->successResponse($penyewaan, 'Data penyewaan berhasil diupdate');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data penyewaan', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::find($id);
            if (!$penyewaan) return $this->errorResponse('Data penyewaan tidak ditemukan', 404);

            $penyewaan->delete();
            return $this->successResponse(null, 'Data penyewaan berhasil dihapus');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data penyewaan', 500, $e->getMessage());
        }
    }

    public function getByPelanggan($id)
    {
        try {
            $penyewaan = Penyewaan::with(['alat', 'pembayaran'])
                ->where('id_pelanggan', $id)
                ->orderBy('id_sewa', 'DESC')
                ->get();

            return $this->successResponse($penyewaan, 'Data penyewaan pelanggan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data penyewaan', 500, $e->getMessage());
        }
    }

    public function addRating(Request $request, $id)
    {
        try {
            $penyewaan = Penyewaan::find($id);
            if (!$penyewaan) return $this->errorResponse('Data penyewaan tidak ditemukan', 404);

            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'ulasan' => 'nullable|string'
            ]);

            $penyewaan->update($validated);
            return $this->successResponse($penyewaan, 'Rating dan ulasan berhasil ditambahkan');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah rating', 500, $e->getMessage());
        }
    }

    // ENDPOINT KHUSUS UNTUK PETUGAS PENGANTARAN
    public function selesai($id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::find($id);

            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

            if ($penyewaan->status_sewa !== 'Dalam Pengantaran') {
                return $this->errorResponse(
                    'Status tidak valid. Hanya status Dalam Pengantaran yang bisa ditandai selesai.',
                    422
                );
            }

            DB::beginTransaction();

            $penyewaan->update(['status_sewa' => 'Selesai']);

            DB::table('alat_berat')
                ->where('id_alat', $penyewaan->id_alat)
                ->update(['status' => 'Tersedia']);

            DB::commit();

            return $this->successResponse($penyewaan->fresh(), 'Unit berhasil ditandai sebagai sudah sampai lokasi');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal memperbarui status', 500, $e->getMessage());
        }
    }
}