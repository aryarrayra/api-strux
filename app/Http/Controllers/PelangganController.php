<?php
namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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

    // User registration method
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string|max:255|unique:users,username',
                'password' => 'required|string|min:6',
                'email' => 'required|email|unique:users,email|unique:pelanggan,email',
                'full_name' => 'required|string|max:255',
                'company_name' => 'nullable|string|max:255',
                'company_address' => 'nullable|string',
                'phone' => 'nullable|string|max:15',
                'id_card_number' => 'nullable|string|max:20|unique:pelanggan,no_ktp',
            ]);

            // Start transaction
            DB::beginTransaction();

            // Create User
            $user = User::create([
                'name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'username' => $validated['username'],
                // email_verified_at, remember_token will be null by default
                // created_at and updated_at will be automatically set
            ]);

            // Create Pelanggan
            $pelanggan = Pelanggan::create([
                'nama_pelanggan' => $validated['full_name'],
                'no_ktp' => $validated['id_card_number'] ?? null,
                'alamat' => $validated['company_address'] ?? null,
                'no_telp' => $validated['phone'] ?? null,
                'email' => $validated['email'],
                'foto_ktp' => null, // You can handle file upload later
                'foto_profil' => null, // You can handle file upload later
                // created_at and updated_at will be automatically set
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                    ],
                    'pelanggan' => [
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'nama_pelanggan' => $pelanggan->nama_pelanggan,
                        'email' => $pelanggan->email,
                        'no_telp' => $pelanggan->no_telp,
                    ]
                ]
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan registrasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Your existing methods...
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
            
            if (!empty($validated['email'])) {
                $existingEmail = Pelanggan::where('email', $validated['email'])->first();
                if ($existingEmail) {
                    return $this->errorResponse('Email sudah digunakan', 422);
                }
            }

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