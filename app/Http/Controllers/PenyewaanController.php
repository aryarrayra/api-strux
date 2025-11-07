<?php
namespace App\Http\Controllers;

use App\Models\Penyewaan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PenyewaanController extends BaseController
{
    protected $model = Penyewaan::class;
    protected $validationRules = [
        'id_pelanggan' => 'required|exists:pelanggan,id_pelanggan',
        'id_alat' => 'required|exists:alat_berat,id_alat',
        'tanggal_sewa' => 'required|date',
        'tanggal_kembali' => 'nullable|date',
        'total_harga' => 'required|numeric|min:0',
        'status_sewa' => 'nullable|string|max:20'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = Penyewaan::with(['pelanggan', 'alat'])
                ->orderBy('id_sewa', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data penyewaan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data penyewaan', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $penyewaan = Penyewaan::create($validated);
            return $this->successResponse($penyewaan, 'Data penyewaan berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data penyewaan', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::with(['pelanggan', 'alat', 'pembayaran', 'jadwal'])->find($id);
            
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

            return $this->successResponse($penyewaan, 'Data penyewaan berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data penyewaan', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::find($id);
            
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

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
            
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

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
            
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

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
}