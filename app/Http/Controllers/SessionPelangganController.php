<?php
namespace App\Http\Controllers;

use App\Models\SessionPelanggan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SessionPelangganController extends BaseController
{
    protected $model = SessionPelanggan::class;
    protected $validationRules = [
        'id_pelanggan' => 'nullable|exists:pelanggan,id_pelanggan',
        'token' => 'required|string',
        'expires_at' => 'required|date',
        'device_type' => 'nullable|string|max:50',
        'fcm_token' => 'nullable|string'
        // last_activity tidak perlu di validation karena auto update
    ];

    public function index(): JsonResponse
    {
        try {
            $data = SessionPelanggan::with(['pelanggan'])
                ->orderBy('id_session', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data session pelanggan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data session pelanggan', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            $session = SessionPelanggan::create($validated);
            return $this->successResponse($session, 'Data session pelanggan berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data session pelanggan', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $session = SessionPelanggan::with(['pelanggan'])->find($id);
            
            if (!$session) {
                return $this->errorResponse('Data session pelanggan tidak ditemukan', 404);
            }

            return $this->successResponse($session, 'Data session pelanggan berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data session pelanggan', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $session = SessionPelanggan::find($id);
            
            if (!$session) {
                return $this->errorResponse('Data session pelanggan tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $session->update($validated);

            return $this->successResponse($session, 'Data session pelanggan berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data session pelanggan', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $session = SessionPelanggan::find($id);
            
            if (!$session) {
                return $this->errorResponse('Data session pelanggan tidak ditemukan', 404);
            }

            $session->delete();
            return $this->successResponse(null, 'Data session pelanggan berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data session pelanggan', 500, $e->getMessage());
        }
    }
}