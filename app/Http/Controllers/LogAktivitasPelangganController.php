<?php
namespace App\Http\Controllers;

use App\Models\LogAktivitasPelanggan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LogAktivitasPelangganController extends BaseController
{
    protected $model = LogAktivitasPelanggan::class;
    protected $validationRules = [
        'id_pelanggan' => 'nullable|exists:pelanggan,id_pelanggan',
        'aktivitas' => 'required|string|max:100',
        'deskripsi' => 'nullable|string',
        'id_referensi' => 'nullable|string|max:50',
        'ip_address' => 'nullable|ip',
        'user_agent' => 'nullable|string'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = LogAktivitasPelanggan::with(['pelanggan'])
                ->orderBy('id_log', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data log aktivitas berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data log aktivitas', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $log = LogAktivitasPelanggan::create($validated);
            return $this->successResponse($log, 'Data log aktivitas berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data log aktivitas', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $log = LogAktivitasPelanggan::with(['pelanggan'])->find($id);
            
            if (!$log) {
                return $this->errorResponse('Data log aktivitas tidak ditemukan', 404);
            }

            return $this->successResponse($log, 'Data log aktivitas berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data log aktivitas', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $log = LogAktivitasPelanggan::find($id);
            
            if (!$log) {
                return $this->errorResponse('Data log aktivitas tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $log->update($validated);

            return $this->successResponse($log, 'Data log aktivitas berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data log aktivitas', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $log = LogAktivitasPelanggan::find($id);
            
            if (!$log) {
                return $this->errorResponse('Data log aktivitas tidak ditemukan', 404);
            }

            $log->delete();
            return $this->successResponse(null, 'Data log aktivitas berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data log aktivitas', 500, $e->getMessage());
        }
    }
}