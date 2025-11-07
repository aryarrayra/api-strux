<?php
namespace App\Http\Controllers;

use App\Models\JadwalSewa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class JadwalSewaController extends BaseController
{
    protected $model = JadwalSewa::class;
    protected $validationRules = [
        'id_sewa' => 'nullable|exists:penyewaan,id_sewa',
        'tanggal_mulai' => 'required|date',
        'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
        'lokasi_pengiriman' => 'nullable|string',
        'lokasi_pengambilan' => 'nullable|string', // Diubah dari pengembalian
        'status_jadwal' => 'nullable|string|max:20',
        'id_petugas' => 'nullable|exists:petugas,id_petugas'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = JadwalSewa::with(['penyewaan', 'petugas'])
                ->orderBy('id_jadwal', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data jadwal sewa berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data jadwal sewa', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $jadwal = JadwalSewa::create($validated);
            return $this->successResponse($jadwal, 'Data jadwal sewa berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data jadwal sewa', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $jadwal = JadwalSewa::with(['penyewaan', 'petugas'])->find($id);
            
            if (!$jadwal) {
                return $this->errorResponse('Data jadwal sewa tidak ditemukan', 404);
            }

            return $this->successResponse($jadwal, 'Data jadwal sewa berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data jadwal sewa', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $jadwal = JadwalSewa::find($id);
            
            if (!$jadwal) {
                return $this->errorResponse('Data jadwal sewa tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $jadwal->update($validated);

            return $this->successResponse($jadwal, 'Data jadwal sewa berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data jadwal sewa', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $jadwal = JadwalSewa::find($id);
            
            if (!$jadwal) {
                return $this->errorResponse('Data jadwal sewa tidak ditemukan', 404);
            }

            $jadwal->delete();
            return $this->successResponse(null, 'Data jadwal sewa berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data jadwal sewa', 500, $e->getMessage());
        }
    }
}