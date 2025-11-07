<?php
namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PembayaranController extends BaseController
{
    protected $model = Pembayaran::class;
    protected $validationRules = [
        'id_sewa' => 'required|exists:penyewaan,id_sewa',
        'tanggal_bayar' => 'required|date',
        'jumlah_bayar' => 'required|numeric|min:0',
        'metode' => 'nullable|string|max:50',
        'status_pembayaran' => 'nullable|string|max:20'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = Pembayaran::with(['penyewaan'])
                ->orderBy('id_pembayaran', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data pembayaran berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data pembayaran', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $pembayaran = Pembayaran::create($validated);
            return $this->successResponse($pembayaran, 'Data pembayaran berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data pembayaran', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $pembayaran = Pembayaran::with(['penyewaan'])->find($id);
            
            if (!$pembayaran) {
                return $this->errorResponse('Data pembayaran tidak ditemukan', 404);
            }

            return $this->successResponse($pembayaran, 'Data pembayaran berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data pembayaran', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $pembayaran = Pembayaran::find($id);
            
            if (!$pembayaran) {
                return $this->errorResponse('Data pembayaran tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $pembayaran->update($validated);

            return $this->successResponse($pembayaran, 'Data pembayaran berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data pembayaran', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $pembayaran = Pembayaran::find($id);
            
            if (!$pembayaran) {
                return $this->errorResponse('Data pembayaran tidak ditemukan', 404);
            }

            $pembayaran->delete();
            return $this->successResponse(null, 'Data pembayaran berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data pembayaran', 500, $e->getMessage());
        }
    }
}