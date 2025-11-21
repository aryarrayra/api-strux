<?php
namespace App\Http\Controllers;

use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PetugasController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            \Log::info('📥 Received data for create:', $request->all());

            $validated = $request->validate([
                'nama_petugas' => 'required|string|max:255',
                'no_telp' => 'required|string|max:15',
                'alamat' => 'nullable|string',
                'tempat_lahir' => 'nullable|string|max:100',
                'tanggal_lahir' => 'nullable|date',
                'role' => 'required|string|max:50',
                'email' => 'required|email|unique:petugas,email',
                'password' => 'required|string|min:6',
                'status' => 'required|string|in:aktif,nonaktif'
            ]);

            \Log::info('✅ Validated data:', $validated);

            // Hash password sebelum disimpan
            $hashedPassword = Hash::make($validated['password']);

            // Pastikan semua nilai string dikutip dengan benar
            $petugasData = [
                'nama_petugas' => (string) $validated['nama_petugas'],
                'no_telp' => (string) $validated['no_telp'],
                'alamat' => isset($validated['alamat']) ? (string) $validated['alamat'] : null,
                'tempat_lahir' => isset($validated['tempat_lahir']) ? (string) $validated['tempat_lahir'] : null,
                'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
                'role' => (string) $validated['role'],
                'email' => (string) $validated['email'],
                'password' => $hashedPassword,
                'status' => (string) $validated['status']
            ];

            \Log::info('🚀 Final data for create:', array_merge($petugasData, ['password' => '***HIDDEN***']));

            $petugas = Petugas::create($petugasData);

            // Jangan kembalikan password dalam response
            $responseData = $petugas->toArray();
            unset($responseData['password']);

            \Log::info('🎉 Petugas created successfully:', $responseData);

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'message' => 'Data petugas berhasil ditambahkan'
            ], 201);
            
        } catch (ValidationException $e) {
            \Log::error('❌ Validation error:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('💥 Error creating petugas: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah data petugas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            \Log::info('📥 Received data for update:', $request->all());

            $petugas = Petugas::find($id);
            
            if (!$petugas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data petugas tidak ditemukan'
                ], 404);
            }

            $validationRules = [
                'nama_petugas' => 'required|string|max:255',
                'no_telp' => 'required|string|max:15',
                'alamat' => 'nullable|string',
                'tempat_lahir' => 'nullable|string|max:100',
                'tanggal_lahir' => 'nullable|date',
                'role' => 'required|string|max:50',
                'email' => 'required|email|unique:petugas,email,' . $id . ',id_petugas',
                'status' => 'required|string|in:aktif,nonaktif'
            ];

            // Password bersifat opsional saat update
            if ($request->has('password') && !empty($request->password)) {
                $validationRules['password'] = 'string|min:6';
            }

            $validated = $request->validate($validationRules);

            \Log::info('✅ Validated data:', $validated);

            // Pastikan semua nilai string dikutip dengan benar
            $updateData = [
                'nama_petugas' => (string) $validated['nama_petugas'],
                'no_telp' => (string) $validated['no_telp'],
                'alamat' => isset($validated['alamat']) ? (string) $validated['alamat'] : null,
                'tempat_lahir' => isset($validated['tempat_lahir']) ? (string) $validated['tempat_lahir'] : null,
                'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
                'role' => (string) $validated['role'],
                'email' => (string) $validated['email'],
                'status' => (string) $validated['status']
            ];

            // Update password hanya jika diisi
            if (isset($validated['password']) && !empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
                \Log::info('🔐 Password will be updated');
            }

            \Log::info('🚀 Final data for update:', array_merge($updateData, ['password' => '***HIDDEN***']));

            $petugas->update($updateData);

            // Jangan kembalikan password dalam response
            $responseData = $petugas->toArray();
            unset($responseData['password']);

            \Log::info('🎉 Petugas updated successfully:', $responseData);

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'message' => 'Data petugas berhasil diupdate'
            ]);
            
        } catch (ValidationException $e) {
            \Log::error('❌ Validation error:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('💥 Error updating petugas: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate data petugas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            // Pilih kolom yang ingin ditampilkan, kecuali password
            $data = Petugas::select([
                'id_petugas',
                'nama_petugas', 
                'no_telp',
                'alamat',
                'tempat_lahir',
                'tanggal_lahir',
                'role',
                'email',
                'status'
            ])->orderBy('id_petugas', 'DESC')->get();
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Data petugas berhasil diambil'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching petugas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data petugas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $petugas = Petugas::select([
                'id_petugas',
                'nama_petugas', 
                'no_telp',
                'alamat',
                'tempat_lahir',
                'tanggal_lahir',
                'role',
                'email',
                'status',
                'created_at',
                'updated_at'
            ])->find($id);
            
            if (!$petugas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data petugas tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $petugas,
                'message' => 'Data petugas berhasil diambil'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching petugas detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data petugas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $petugas = Petugas::find($id);
            
            if (!$petugas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data petugas tidak ditemukan'
                ], 404);
            }

            $petugas->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Data petugas berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error deleting petugas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data petugas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset password petugas
     */
    public function resetPassword(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'new_password' => 'required|string|min:6',
                'confirm_password' => 'required|string|min:6|same:new_password'
            ]);

            $petugas = Petugas::find($id);
            
            if (!$petugas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data petugas tidak ditemukan'
                ], 404);
            }

            $petugas->update([
                'password' => Hash::make($validated['new_password'])
            ]);

            \Log::info('🔐 Password reset successfully for petugas ID: ' . $id);

            return response()->json([
                'success' => true,
                'message' => 'Password berhasil direset'
            ]);
            
        } catch (ValidationException $e) {
            \Log::error('❌ Validation error reset password:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('💥 Error resetting password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mereset password: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login petugas (jika diperlukan untuk autentikasi)
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string'
            ]);

            $petugas = Petugas::where('email', $validated['email'])->first();

            if (!$petugas || !Hash::check($validated['password'], $petugas->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email atau password salah'
                ], 401);
            }

            if ($petugas->status !== 'aktif') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak aktif'
                ], 401);
            }

            // Hapus password dari response
            $userData = $petugas->toArray();
            unset($userData['password']);

            // Generate token jika menggunakan Sanctum/Passport
            // $token = $petugas->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => $userData,
                'message' => 'Login berhasil',
                // 'token' => $token // jika menggunakan token
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('💥 Error login petugas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal login: ' . $e->getMessage()
            ], 500);
        }
    }
}