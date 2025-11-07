<?php
namespace App\Http\Controllers;

use App\Models\Pelanggan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PelangganController extends BaseController
{
    protected $model = Pelanggan::class;
    protected $validationRules = [
        'nama_pelanggan' => 'required|string|max:255',
        'no_ktp' => 'nullable|string|max:20|unique:pelanggan,no_ktp',
        'alamat' => 'nullable|string',
        'no_telp' => 'nullable|string|max:15',
        'email' => 'nullable|email|unique:pelanggan,email'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = Pelanggan::orderBy('id_pelanggan', 'DESC')->get();
            return $this->successResponse($data, 'Data pelanggan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data pelanggan', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            // Cek duplikat email jika diisi
            if (!empty($validated['email'])) {
                $existingEmail = Pelanggan::where('email', $validated['email'])->first();
                if ($existingEmail) {
                    return $this->errorResponse('Email sudah digunakan', 422);
                }
            }

            // Cek duplikat no_ktp jika diisi
            if (!empty($validated['no_ktp'])) {
                $existingKtp = Pelanggan::where('no_ktp', $validated['no_ktp'])->first();
                if ($existingKtp) {
                    return $this->errorResponse('No KTP sudah digunakan', 422);
                }
            }

            $pelanggan = Pelanggan::create($validated);
            return $this->successResponse($pelanggan, 'Data pelanggan berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data pelanggan', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $pelanggan = Pelanggan::find($id);
            
            if (!$pelanggan) {
                return $this->errorResponse('Data pelanggan tidak ditemukan', 404);
            }

            return $this->successResponse($pelanggan, 'Data pelanggan berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data pelanggan', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $pelanggan = Pelanggan::find($id);
            
            if (!$pelanggan) {
                return $this->errorResponse('Data pelanggan tidak ditemukan', 404);
            }

            $validationRules = [
                'nama_pelanggan' => 'required|string|max:255',
                'no_ktp' => "nullable|string|max:20|unique:pelanggan,no_ktp,{$id},id_pelanggan",
                'alamat' => 'nullable|string',
                'no_telp' => 'nullable|string|max:15',
                'email' => "nullable|email|unique:pelanggan,email,{$id},id_pelanggan"
            ];

            $validated = $this->validateRequest($request, $validationRules);
            $pelanggan->update($validated);

            return $this->successResponse($pelanggan, 'Data pelanggan berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data pelanggan', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $pelanggan = Pelanggan::find($id);
            
            if (!$pelanggan) {
                return $this->errorResponse('Data pelanggan tidak ditemukan', 404);
            }

            $pelanggan->delete();
            return $this->successResponse(null, 'Data pelanggan berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data pelanggan', 500, $e->getMessage());
        }
    }
}