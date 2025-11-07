<?php
namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class AdminController extends BaseController
{
    protected $model = Admin::class;
    protected $validationRules = [
        'username' => 'required|string|max:50|unique:admin,username',
        'password' => 'required|string|min:6',
        'nama_admin' => 'nullable|string|max:255',
        'level' => 'nullable|string|max:20'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = Admin::orderBy('id_admin', 'DESC')->get();
            return $this->successResponse($data, 'Data admin berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data admin', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            // Hash password
            $validated['password'] = Hash::make($validated['password']);
            
            $admin = Admin::create($validated);
            return $this->successResponse($admin, 'Data admin berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data admin', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $admin = Admin::find($id);
            
            if (!$admin) {
                return $this->errorResponse('Data admin tidak ditemukan', 404);
            }

            return $this->successResponse($admin, 'Data admin berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data admin', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $admin = Admin::find($id);
            
            if (!$admin) {
                return $this->errorResponse('Data admin tidak ditemukan', 404);
            }

            $validationRules = [
                'username' => "required|string|max:50|unique:admin,username,{$id},id_admin",
                'password' => 'sometimes|string|min:6',
                'nama_admin' => 'nullable|string|max:255',
                'level' => 'nullable|string|max:20'
            ];

            $validated = $this->validateRequest($request, $validationRules);
            
            // Hash password jika diupdate
            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $admin->update($validated);

            return $this->successResponse($admin, 'Data admin berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data admin', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $admin = Admin::find($id);
            
            if (!$admin) {
                return $this->errorResponse('Data admin tidak ditemukan', 404);
            }

            $admin->delete();
            return $this->successResponse(null, 'Data admin berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data admin', 500, $e->getMessage());
        }
    }
}