<?php
namespace App\Http\Controllers;

use App\Models\KontrakDigital;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class KontrakDigitalController extends BaseController
{
    protected $model = KontrakDigital::class;
    protected $validationRules = [
        'id_sewa' => 'required|exists:penyewaan,id_sewa',
        'file_kontrak' => 'required|string',
        'tanggal_tanda_tangan' => 'nullable|date',
        'status_kontrak' => 'nullable|string|max:20'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = KontrakDigital::with(['penyewaan'])
                ->orderBy('id_kontrak', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data kontrak digital berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data kontrak digital', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $kontrak = KontrakDigital::create($validated);
            return $this->successResponse($kontrak, 'Data kontrak digital berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data kontrak digital', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $kontrak = KontrakDigital::with(['penyewaan'])->find($id);
            
            if (!$kontrak) {
                return $this->errorResponse('Data kontrak digital tidak ditemukan', 404);
            }

            return $this->successResponse($kontrak, 'Data kontrak digital berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data kontrak digital', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $kontrak = KontrakDigital::find($id);
            
            if (!$kontrak) {
                return $this->errorResponse('Data kontrak digital tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $kontrak->update($validated);

            return $this->successResponse($kontrak, 'Data kontrak digital berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data kontrak digital', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $kontrak = KontrakDigital::find($id);
            
            if (!$kontrak) {
                return $this->errorResponse('Data kontrak digital tidak ditemukan', 404);
            }

            $kontrak->delete();
            return $this->successResponse(null, 'Data kontrak digital berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data kontrak digital', 500, $e->getMessage());
        }
    }
}