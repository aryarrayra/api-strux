<?php
namespace App\Http\Controllers;

use App\Models\PerawatanAlat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PerawatanAlatController extends BaseController
{
    protected $model = PerawatanAlat::class;
    protected $validationRules = [
        'id_alat' => 'required|exists:alat_berat,id_alat',
        'tanggal_perawatan' => 'required|date',
        'keterangan' => 'nullable|string',
        'biaya_perawatan' => 'required|numeric|min:0',
        'status' => 'nullable|string|in:Dijadwalkan,Selesai'
    ];

    public function index(): JsonResponse
    {
        try {
            $data = PerawatanAlat::with(['alat'])
                ->orderBy('id_perawatan', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data perawatan alat berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data perawatan alat', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        Log::info('🔄 [PERAWATAN_UPDATE] Starting update', [
            'id' => $id,
            'request_data' => $request->all(),
            'method' => $request->method()
        ]);

        try {
            $perawatan = PerawatanAlat::find($id);
            
            if (!$perawatan) {
                Log::error('❌ [PERAWATAN_UPDATE] Data not found:', ['id' => $id]);
                return $this->errorResponse('Data perawatan alat tidak ditemukan', 404);
            }

            Log::info('📝 [PERAWATAN_UPDATE] Found record:', [
                'current_data' => $perawatan->toArray()
            ]);

            // Gunakan sometimes untuk update (tidak semua field required)
            $validated = $request->validate([
                'id_alat' => 'sometimes|required|exists:alat_berat,id_alat',
                'tanggal_perawatan' => 'sometimes|required|date',
                'keterangan' => 'nullable|string',
                'biaya_perawatan' => 'sometimes|required|numeric|min:0',
                'status' => 'sometimes|required|string|in:Dijadwalkan,Selesai'
            ]);

            Log::info('✅ [PERAWATAN_UPDATE] Validation passed:', $validated);

            // Update data
            $perawatan->update([
                'tanggal_perawatan' => $validated['tanggal_perawatan'] ?? $perawatan->tanggal_perawatan,
                'keterangan' => $validated['keterangan'] ?? $perawatan->keterangan,
                'biaya_perawatan' => $validated['biaya_perawatan'] ?? $perawatan->biaya_perawatan,
                'status' => $validated['status'] ?? $perawatan->status,
                // id_alat biasanya tidak diupdate
            ]);

            Log::info('💾 [PERAWATAN_UPDATE] Database updated:', [
                'id' => $perawatan->id_perawatan,
                'new_data' => $perawatan->toArray()
            ]);

            // Load alat relation
            $perawatan->load('alat');

            return $this->successResponse($perawatan, 'Data perawatan alat berhasil diupdate');
            
        } catch (ValidationException $e) {
            Log::error('❌ [PERAWATAN_UPDATE] Validation error:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ [PERAWATAN_UPDATE] Error: ' . $e->getMessage());
            Log::error('❌ [PERAWATAN_UPDATE] Trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Gagal mengupdate data perawatan alat: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $perawatan = PerawatanAlat::find($id);
            
            if (!$perawatan) {
                return $this->errorResponse('Data perawatan alat tidak ditemukan', 404);
            }

            $perawatan->delete();
            return $this->successResponse(null, 'Data perawatan alat berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data perawatan alat', 500, $e->getMessage());
        }
    }
}