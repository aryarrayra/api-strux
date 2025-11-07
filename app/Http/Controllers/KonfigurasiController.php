<?php
namespace App\Http\Controllers;

use App\Models\Konfigurasi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class KonfigurasiController extends BaseController
{
    protected $model = Konfigurasi::class;
    protected $validationRules = [
        'nama_setting' => 'required|string|max:100|unique:konfigurasi,nama_setting',
        'nilai_setting' => 'required|string',
        'keterangan' => 'nullable|string'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = Konfigurasi::orderBy('id_konfigurasi', 'DESC')->get();
            return $this->successResponse($data, 'Data konfigurasi berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data konfigurasi', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $konfigurasi = Konfigurasi::create($validated);
            return $this->successResponse($konfigurasi, 'Data konfigurasi berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data konfigurasi', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $konfigurasi = Konfigurasi::find($id);
            
            if (!$konfigurasi) {
                return $this->errorResponse('Data konfigurasi tidak ditemukan', 404);
            }

            return $this->successResponse($konfigurasi, 'Data konfigurasi berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data konfigurasi', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $konfigurasi = Konfigurasi::find($id);
            
            if (!$konfigurasi) {
                return $this->errorResponse('Data konfigurasi tidak ditemukan', 404);
            }

            $validationRules = [
                'nama_setting' => "required|string|max:100|unique:konfigurasi,nama_setting,{$id},id_konfigurasi",
                'nilai_setting' => 'required|string',
                'keterangan' => 'nullable|string'
            ];

            $validated = $this->validateRequest($request, $validationRules);
            $konfigurasi->update($validated);

            return $this->successResponse($konfigurasi, 'Data konfigurasi berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data konfigurasi', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $konfigurasi = Konfigurasi::find($id);
            
            if (!$konfigurasi) {
                return $this->errorResponse('Data konfigurasi tidak ditemukan', 404);
            }

            $konfigurasi->delete();
            return $this->successResponse(null, 'Data konfigurasi berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data konfigurasi', 500, $e->getMessage());
        }
    }
}