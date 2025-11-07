<?php
namespace App\Http\Controllers;

use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PetugasController extends BaseController
{
    protected $model = Petugas::class;
    protected $validationRules = [
        'nama_petugas' => 'required|string|max:255|unique:petugas,nama_petugas',
        'no_telp' => 'nullable|string|max:15',
        'role' => 'nullable|string|max:50',
        'status' => 'nullable|string|max:20'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = Petugas::orderBy('id_petugas', 'DESC')->get();
            return $this->successResponse($data, 'Data petugas berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data petugas', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $petugas = Petugas::create($validated);
            return $this->successResponse($petugas, 'Data petugas berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data petugas', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $petugas = Petugas::with(['jadwal'])->find($id);
            
            if (!$petugas) {
                return $this->errorResponse('Data petugas tidak ditemukan', 404);
            }

            return $this->successResponse($petugas, 'Data petugas berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data petugas', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $petugas = Petugas::find($id);
            
            if (!$petugas) {
                return $this->errorResponse('Data petugas tidak ditemukan', 404);
            }

            $validationRules = [
                'nama_petugas' => "required|string|max:255|unique:petugas,nama_petugas,{$id},id_petugas",
                'no_telp' => 'nullable|string|max:15',
                'role' => 'nullable|string|max:50',
                'status' => 'nullable|string|max:20'
            ];

            $validated = $this->validateRequest($request, $validationRules);
            $petugas->update($validated);

            return $this->successResponse($petugas, 'Data petugas berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data petugas', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $petugas = Petugas::find($id);
            
            if (!$petugas) {
                return $this->errorResponse('Data petugas tidak ditemukan', 404);
            }

            $petugas->delete();
            return $this->successResponse(null, 'Data petugas berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data petugas', 500, $e->getMessage());
        }
    }
}