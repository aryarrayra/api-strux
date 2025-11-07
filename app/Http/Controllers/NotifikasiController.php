<?php
namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class NotifikasiController extends BaseController
{
    protected $model = Notifikasi::class;
    protected $validationRules = [
        'id_admin' => 'nullable|exists:admin,id_admin',
        'judul' => 'required|string|max:255',
        'pesan' => 'required|string',
        'dibaca' => 'nullable|string|max:10'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = Notifikasi::with(['admin'])
                ->orderBy('id_notifikasi', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data notifikasi berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data notifikasi', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $notifikasi = Notifikasi::create($validated);
            return $this->successResponse($notifikasi, 'Data notifikasi berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data notifikasi', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $notifikasi = Notifikasi::with(['admin'])->find($id);
            
            if (!$notifikasi) {
                return $this->errorResponse('Data notifikasi tidak ditemukan', 404);
            }

            return $this->successResponse($notifikasi, 'Data notifikasi berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data notifikasi', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $notifikasi = Notifikasi::find($id);
            
            if (!$notifikasi) {
                return $this->errorResponse('Data notifikasi tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $notifikasi->update($validated);

            return $this->successResponse($notifikasi, 'Data notifikasi berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data notifikasi', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $notifikasi = Notifikasi::find($id);
            
            if (!$notifikasi) {
                return $this->errorResponse('Data notifikasi tidak ditemukan', 404);
            }

            $notifikasi->delete();
            return $this->successResponse(null, 'Data notifikasi berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data notifikasi', 500, $e->getMessage());
        }
    }
}