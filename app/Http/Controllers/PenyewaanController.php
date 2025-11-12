<?php
namespace App\Http\Controllers;

use App\Models\Penyewaan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class PenyewaanController extends BaseController
{
    protected $model = Penyewaan::class;
    protected $validationRules = [
        'id_pelanggan' => 'required|exists:pelanggan,id_pelanggan',
        'id_alat' => 'required|exists:alat_berat,id_alat',
        'tanggal_sewa' => 'required|date',
        'tanggal_kembali' => 'nullable|date',
        'total_harga' => 'required|numeric|min:0',
        'status_sewa' => 'nullable|string|max:20',
        'status_persetujuan' => 'nullable|string|max:20',
        'alasan_penolakan' => 'nullable|string'
    ];

    // ✅ TAMBAHKAN METHOD INDEX YANG HILANG
    public function index(): JsonResponse
    {
        try {
            $data = Penyewaan::with(['pelanggan', 'alat', 'pembayaran', 'dokumen'])
                ->orderBy('id_sewa', 'DESC')
                ->get();
            return $this->successResponse($data, 'Data penyewaan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data penyewaan', 500, $e->getMessage());
        }
    }

    // ✅ Method untuk persetujuan pinjaman
    public function getPersetujuanPinjaman(): JsonResponse
    {
        try {
            $data = Penyewaan::with([
                    'pelanggan', 
                    'alat',
                    'dokumen',
                    'pembayaran'
                ])
                ->where('status_persetujuan', 'Menunggu')
                ->orderBy('id_sewa', 'DESC')
                ->get();
                
            return $this->successResponse($data, 'Data persetujuan pinjaman berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data persetujuan pinjaman', 500, $e->getMessage());
        }
    }

    // ✅ Method untuk approve/reject
    public function approvePinjaman(Request $request, $id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::find($id);
            
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

            $validated = $request->validate([
                'status_persetujuan' => 'required|in:Disetujui,Ditolak',
                'alasan_penolakan' => 'required_if:status_persetujuan,Ditolak|string|max:500'
            ]);

            DB::beginTransaction();

            $updateData = [
                'status_persetujuan' => $validated['status_persetujuan'],
                'disetujui_oleh' => auth()->id(),
                'tanggal_persetujuan' => now()
            ];

            if ($validated['status_persetujuan'] === 'Ditolak') {
                $updateData['alasan_penolakan'] = $validated['alasan_penolakan'];
                $updateData['status_sewa'] = 'Dibatalkan';
            } else {
                $updateData['status_sewa'] = 'Berjalan';
                $updateData['alasan_penolakan'] = null;
                
                // Update status alat jadi Disewa
                DB::table('alat_berat')
                    ->where('id_alat', $penyewaan->id_alat)
                    ->update(['status' => 'Disewa']);
            }

            $penyewaan->update($updateData);

            DB::commit();

            $message = $validated['status_persetujuan'] === 'Disetujui' 
                ? 'Penyewaan berhasil disetujui' 
                : 'Penyewaan berhasil ditolak';

            return $this->successResponse($penyewaan, $message);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal memproses persetujuan', 500, $e->getMessage());
        }
    }

    // ✅ Method store
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            // Set default status
            $validated['status_persetujuan'] = 'Menunggu';
            $validated['status_sewa'] = 'Menunggu Persetujuan';
            
            DB::beginTransaction();
            
            $penyewaan = Penyewaan::create($validated);
            
            DB::commit();

            return $this->successResponse($penyewaan, 'Penyewaan berhasil diajukan, menunggu persetujuan', 201);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal mengajukan penyewaan', 500, $e->getMessage());
        }
    }

    // ✅ Method show
    public function show($id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::with(['pelanggan', 'alat', 'pembayaran', 'jadwal'])->find($id);
            
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

            return $this->successResponse($penyewaan, 'Data penyewaan berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data penyewaan', 500, $e->getMessage());
        }
    }

    // ✅ Method update
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::find($id);
            
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

            $validated = $this->validateRequest($request);
            $penyewaan->update($validated);

            return $this->successResponse($penyewaan, 'Data penyewaan berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data penyewaan', 500, $e->getMessage());
        }
    }

    // ✅ Method destroy
    public function destroy($id): JsonResponse
    {
        try {
            $penyewaan = Penyewaan::find($id);
            
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

            $penyewaan->delete();
            return $this->successResponse(null, 'Data penyewaan berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data penyewaan', 500, $e->getMessage());
        }
    }

    // ✅ Method getByPelanggan
    public function getByPelanggan($id)
    {
        try {
            $penyewaan = Penyewaan::with(['alat', 'pembayaran'])
                ->where('id_pelanggan', $id)
                ->orderBy('id_sewa', 'DESC')
                ->get();
                
            return $this->successResponse($penyewaan, 'Data penyewaan pelanggan berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data penyewaan', 500, $e->getMessage());
        }
    }

    // ✅ Method addRating
    public function addRating(Request $request, $id)
    {
        try {
            $penyewaan = Penyewaan::find($id);
            
            if (!$penyewaan) {
                return $this->errorResponse('Data penyewaan tidak ditemukan', 404);
            }

            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'ulasan' => 'nullable|string'
            ]);

            $penyewaan->update($validated);

            return $this->successResponse($penyewaan, 'Rating dan ulasan berhasil ditambahkan');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah rating', 500, $e->getMessage());
        }
    }
}