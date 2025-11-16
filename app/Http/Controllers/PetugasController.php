<?php
namespace App\Http\Controllers;

use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
                'status' => 'required|string|in:aktif,nonaktif'
            ]);

            \Log::info('✅ Validated data:', $validated);

            // Pastikan semua nilai string dikutip dengan benar
            $petugasData = [
                'nama_petugas' => (string) $validated['nama_petugas'],
                'no_telp' => (string) $validated['no_telp'],
                'alamat' => isset($validated['alamat']) ? (string) $validated['alamat'] : null,
                'tempat_lahir' => isset($validated['tempat_lahir']) ? (string) $validated['tempat_lahir'] : null,
                'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
                'role' => (string) $validated['role'],
                'email' => (string) $validated['email'],
                'status' => (string) $validated['status']
            ];

            \Log::info('🚀 Final data for create:', $petugasData);

            $petugas = Petugas::create($petugasData);

            \Log::info('🎉 Petugas created successfully:', $petugas->toArray());

            return response()->json([
                'success' => true,
                'data' => $petugas,
                'message' => 'Data petugas berhasil ditambahkan'
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
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

            $validated = $request->validate([
                'nama_petugas' => 'required|string|max:255',
                'no_telp' => 'required|string|max:15',
                'alamat' => 'nullable|string',
                'tempat_lahir' => 'nullable|string|max:100',
                'tanggal_lahir' => 'nullable|date',
                'role' => 'required|string|max:50',
                'email' => 'required|email|unique:petugas,email,' . $id . ',id_petugas',
                'status' => 'required|string|in:aktif,nonaktif'
            ]);

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

            \Log::info('🚀 Final data for update:', $updateData);

            $petugas->update($updateData);

            \Log::info('🎉 Petugas updated successfully:', $petugas->toArray());

            return response()->json([
                'success' => true,
                'data' => $petugas,
                'message' => 'Data petugas berhasil diupdate'
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
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
            $data = Petugas::orderBy('id_petugas', 'DESC')->get();
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
}