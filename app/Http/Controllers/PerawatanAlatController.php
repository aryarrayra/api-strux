<?php

namespace App\Http\Controllers;

use App\Models\PerawatanAlat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PerawatanAlatController extends BaseController
{
    protected $model = PerawatanAlat::class;

    protected $validationRules = [
        'id_alat'           => 'required|exists:alat_berat,id_alat',
        'tanggal_perawatan' => 'nullable|date',
        'keterangan'        => 'nullable|string',
        'biaya_perawatan'   => 'nullable|numeric|min:0',
        'status'            => 'nullable|in:Menunggu,Dijadwalkan,Selesai'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = PerawatanAlat::with(['alat'])
                ->orderBy('id_perawatan', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);

            $validated['status'] = 'Menunggu';
            $validated['tanggal_perawatan'] = null;
            $validated['biaya_perawatan'] = 0;

            $perawatan = PerawatanAlat::create($validated);
            return $this->successResponse($perawatan->load('alat'), 'Rekomendasi berhasil dikirim', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengirim', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $perawatan = PerawatanAlat::with(['alat'])->find($id);
            if (!$perawatan) return $this->errorResponse('Tidak ditemukan', 404);
            return $this->successResponse($perawatan, 'Data berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Error', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $perawatan = PerawatanAlat::find($id);
            if (!$perawatan) return $this->errorResponse('Tidak ditemukan', 404);

            $rules = [
                'id_alat'           => 'sometimes|exists:alat_berat,id_alat',
                'tanggal_perawatan' => 'sometimes|nullable|date',
                'keterangan'        => 'sometimes|nullable|string',
                'biaya_perawatan'   => 'sometimes|nullable|numeric|min:0',
                'status'            => 'sometimes|nullable|in:Menunggu,Dijadwalkan,Selesai'
            ];

            $validated = $request->validate($rules);
            
            if (isset($validated['biaya_perawatan'])) {
                $validated['biaya_perawatan'] = $validated['biaya_perawatan'] ?? 0;
            }
            $perawatan->update($validated);
            
            return $this->successResponse($perawatan->load('alat'), 'Data berhasil diupdate');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal update', 500, $e->getMessage());
        }
    }

    public function tolak(Request $request, $id): JsonResponse
    {
        try {
            $perawatan = PerawatanAlat::find($id);
            if (!$perawatan) return $this->errorResponse('Tidak ditemukan', 404);

            // Langsung ubah status jadi Selesai (ditolak)
            $perawatan->update(['status' => 'Selesai']);

            return $this->successResponse($perawatan->load('alat'), 'Rekomendasi berhasil ditolak');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menolak', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $perawatan = PerawatanAlat::find($id);
            if (!$perawatan) return $this->errorResponse('Tidak ditemukan', 404);
            $perawatan->delete();
            return $this->successResponse(null, 'Data berhasil dihapus');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus', 500, $e->getMessage());
        }
    }
}