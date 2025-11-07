<?php
namespace App\Http\Controllers;

use App\Models\AlatBerat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AlatBeratController extends BaseController
{
    protected $model = AlatBerat::class;
    protected $validationRules = [
        'nama_alat' => 'required|string|max:255',
        'jenis' => 'nullable|string|max:100',
        'kapasitas' => 'nullable|string|max:50',
        'harga_sewa_per_hari' => 'required|numeric|min:0',
        'status' => 'nullable|string|max:20',
        'deskripsi' => 'nullable|string',
        'foto' => 'nullable|string'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = AlatBerat::orderBy('id_alat', 'DESC')->get();
            return $this->successResponse($data, 'Data alat berat berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data alat berat', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $alat = AlatBerat::create($validated);
            return $this->successResponse($alat, 'Data alat berat berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data alat berat', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $alat = AlatBerat::find($id);
            
            if (!$alat) {
                return $this->errorResponse('Data alat berat tidak ditemukan', 404);
            }

            return $this->successResponse($alat, 'Data alat berat berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data alat berat', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $alat = AlatBerat::find($id);
            
            if (!$alat) {
                return $this->errorResponse('Data alat berat tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $alat->update($validated);

            return $this->successResponse($alat, 'Data alat berat berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data alat berat', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $alat = AlatBerat::find($id);
            
            if (!$alat) {
                return $this->errorResponse('Data alat berat tidak ditemukan', 404);
            }

            $alat->delete();
            return $this->successResponse(null, 'Data alat berat berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data alat berat', 500, $e->getMessage());
        }
    }

        public function getByStatus($status)
    {
        try {
            $validStatuses = ['Tersedia', 'Disewa', 'Perawatan'];
            
            if (!in_array($status, $validStatuses)) {
                return $this->errorResponse('Status tidak valid', 422);
            }

            $data = AlatBerat::where('status', $status)
                ->orderBy('id_alat', 'DESC')
                ->get();
                
            return $this->successResponse($data, "Data alat berat dengan status {$status} berhasil diambil");
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data alat berat', 500, $e->getMessage());
        }
    }
}