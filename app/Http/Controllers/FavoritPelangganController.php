<?php
namespace App\Http\Controllers;

use App\Models\FavoritPelanggan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class FavoritPelangganController extends BaseController
{
    protected $model = FavoritPelanggan::class;
    protected $validationRules = [
        'id_pelanggan' => 'required|exists:pelanggan,id_pelanggan',
        'id_alat' => 'required|exists:alat_berat,id_alat'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = FavoritPelanggan::with(['pelanggan', 'alat'])
                ->orderBy('id_favorit', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data favorit pelanggan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data favorit pelanggan', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            // Cek duplikat
            $existing = FavoritPelanggan::where('id_pelanggan', $validated['id_pelanggan'])
                ->where('id_alat', $validated['id_alat'])
                ->first();
                
            if ($existing) {
                return $this->errorResponse('Favorit sudah ada', 422);
            }
            
            $favorit = FavoritPelanggan::create($validated);
            return $this->successResponse($favorit, 'Data favorit berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data favorit', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $favorit = FavoritPelanggan::with(['pelanggan', 'alat'])->find($id);
            
            if (!$favorit) {
                return $this->errorResponse('Data favorit tidak ditemukan', 404);
            }

            return $this->successResponse($favorit, 'Data favorit berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data favorit', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $favorit = FavoritPelanggan::find($id);
            
            if (!$favorit) {
                return $this->errorResponse('Data favorit tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $favorit->update($validated);

            return $this->successResponse($favorit, 'Data favorit berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data favorit', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $favorit = FavoritPelanggan::find($id);
            
            if (!$favorit) {
                return $this->errorResponse('Data favorit tidak ditemukan', 404);
            }

            $favorit->delete();
            return $this->successResponse(null, 'Data favorit berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data favorit', 500, $e->getMessage());
        }
    }
}