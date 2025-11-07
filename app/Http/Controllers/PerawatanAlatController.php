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
        'id_alat' => 'required|exists:alat_berat,id_alat',
        'tanggal_perawatan' => 'required|date',
        'keterangan' => 'nullable|string',
        'biaya_perawatan' => 'required|numeric|min:0',
        'status' => 'nullable|string|max:20'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = PerawatanAlat::with(['alat'])
                ->orderBy('id_perawatan', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data perawatan alat berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data perawatan alat', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $perawatan = PerawatanAlat::create($validated);
            return $this->successResponse($perawatan, 'Data perawatan alat berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data perawatan alat', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $perawatan = PerawatanAlat::with(['alat'])->find($id);
            
            if (!$perawatan) {
                return $this->errorResponse('Data perawatan alat tidak ditemukan', 404);
            }

            return $this->successResponse($perawatan, 'Data perawatan alat berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data perawatan alat', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $perawatan = PerawatanAlat::find($id);
            
            if (!$perawatan) {
                return $this->errorResponse('Data perawatan alat tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $perawatan->update($validated);

            return $this->successResponse($perawatan, 'Data perawatan alat berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data perawatan alat', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $perawatan = PerawatanAlat::find($id);
            
            if (!$perawatan) {
                return $this->errorResponse('Data perawatan alat tidak ditemukan', 404);
            }

            $perawatan->delete();
            return $this->successResponse(null, 'Data perawatan alat berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data perawatan alat', 500, $e->getMessage());
        }
    }
}