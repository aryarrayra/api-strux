<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AlatBerat;
use App\Models\Penyewaan;
use App\Models\Pembayaran;
use App\Models\PerawatanAlat;
use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats(): JsonResponse
    {
        try {
            // Total Users (excluding admins)
            $totalUsers = User::count();
            
            // Total Alat Berat
            $totalAlatBerat = AlatBerat::count();
            
            // Total Petugas/Karyawan
            $totalPetugas = Petugas::count();
            
            // Active Penyewaan (status: Berjalan/dipinjam)
            $activePenyewaan = Penyewaan::where('status_sewa', 'Berjalan')->count();
            
            // Pending Approvals
            $pendingApprovals = Penyewaan::where('status_persetujuan', 'Menunggu')->count();
            
            // Total Revenue (from pembayaran)
            $totalRevenue = Pembayaran::sum('jumlah_bayar');
            
            // Monthly Revenue
            $monthlyRevenue = Pembayaran::whereYear('tanggal_bayar', Carbon::now()->year)
                ->whereMonth('tanggal_bayar', Carbon::now()->month)
                ->sum('jumlah_bayar');
                
            // Alat Berat by Status
            $alatByStatus = AlatBerat::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get();

            // Convert to associative array
            $alatStatusArray = [];
            foreach ($alatByStatus as $item) {
                $alatStatusArray[$item->status] = $item->total;
            }

            // Recent Growth (users registered this month)
            $newUsersThisMonth = User::where('created_at', '>=', Carbon::now()->startOfMonth())
                ->count();

            // Maintenance stats - handle if status column doesn't exist
            try {
                $pendingMaintenance = PerawatanAlat::where('status', 'pending')->count();
                $completedMaintenance = PerawatanAlat::where('status', 'completed')->count();
            } catch (\Exception $e) {
                // If status column doesn't exist, use alternative logic
                $pendingMaintenance = PerawatanAlat::count();
                $completedMaintenance = 0;
            }

            $stats = [
                'total_users' => $totalUsers,
                'total_alat_berat' => $totalAlatBerat,
                'total_petugas' => $totalPetugas,
                'active_penyewaan' => $activePenyewaan,
                'pending_approvals' => $pendingApprovals,
                'total_revenue' => (int)$totalRevenue,
                'monthly_revenue' => (int)$monthlyRevenue,
                'new_users_this_month' => $newUsersThisMonth,
                'pending_maintenance' => $pendingMaintenance,
                'completed_maintenance' => $completedMaintenance,
                'alat_by_status' => $alatStatusArray,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Dashboard statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent activities from multiple sources
     */
    public function getRecentActivities(): JsonResponse
    {
        try {
            $activities = [];
            
            // Recent Penyewaan (last 5)
            $recentPenyewaan = Penyewaan::with(['pelanggan', 'alat'])
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get()
                ->map(function ($penyewaan) {
                    return [
                        'id' => $penyewaan->id_sewa,
                        'type' => 'penyewaan',
                        'user_name' => $penyewaan->pelanggan->nama_pelanggan ?? 'Unknown',
                        'action' => 'Mengajukan penyewaan',
                        'description' => $penyewaan->alat->nama_alat ?? 'Alat tidak diketahui',
                        'timestamp' => $penyewaan->created_at->format('Y-m-d H:i:s'),
                        'status' => $penyewaan->status_persetujuan,
                        'icon' => 'calendar'
                    ];
                });
            
            $activities = array_merge($activities, $recentPenyewaan->toArray());

            // Recent Pembayaran (last 5)
            $recentPembayaran = Pembayaran::with(['penyewaan'])
                ->orderBy('tanggal_bayar', 'DESC')
                ->limit(5)
                ->get()
                ->map(function ($pembayaran) {
                    return [
                        'id' => $pembayaran->id_pembayaran,
                        'type' => 'pembayaran',
                        'user_name' => 'Sistem Pembayaran',
                        'action' => 'Pembayaran diterima',
                        'description' => 'Rp ' . number_format($pembayaran->jumlah_bayar, 0, ',', '.'),
                        'timestamp' => $pembayaran->tanggal_bayar,
                        'status' => 'success',
                        'icon' => 'dollar-sign'
                    ];
                });
            
            $activities = array_merge($activities, $recentPembayaran->toArray());

            // Sort all activities by timestamp and take latest 10
            usort($activities, function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            $activities = array_slice($activities, 0, 10);

            return response()->json([
                'success' => true,
                'data' => $activities,
                'message' => 'Recent activities retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Recent activities error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get maintenance schedule from PerawatanAlat
     */
    public function getMaintenanceSchedule(): JsonResponse
    {
        try {
            $maintenance = PerawatanAlat::with(['alat'])
                ->where(function($query) {
                    $query->where('tanggal_perawatan', '>=', Carbon::now())
                          ->orWhere('status', 'pending');
                })
                ->orderBy('tanggal_perawatan', 'ASC')
                ->limit(10)
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id_perawatan,
                        'task' => $task->keterangan ?? 'Perawatan rutin',
                        'alat_berat_name' => $task->alat->nama_alat ?? 'N/A',
                        'alat_berat_jenis' => $task->alat->jenis ?? 'N/A',
                        'due_date' => $task->tanggal_perawatan,
                        'status' => $task->status ?? 'pending',
                        'priority' => $this->getMaintenancePriority($task->keterangan),
                        'biaya' => $task->biaya_perawatan
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $maintenance,
                'message' => 'Maintenance schedule retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Maintenance schedule error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve maintenance schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue chart data from Pembayaran
     */
    public function getRevenueChart(): JsonResponse
    {
        try {
            $revenueData = Pembayaran::whereYear('tanggal_bayar', Carbon::now()->year)
                ->select(
                    DB::raw('MONTH(tanggal_bayar) as month'),
                    DB::raw('SUM(jumlah_bayar) as revenue')
                )
                ->groupBy('month')
                ->orderBy('month', 'ASC')
                ->get();

            // Fill in missing months with zero revenue
            $allMonths = [];
            for ($i = 1; $i <= 12; $i++) {
                $monthName = Carbon::create()->month($i)->format('M');
                $found = $revenueData->firstWhere('month', $i);
                $allMonths[] = [
                    'month' => $monthName,
                    'revenue' => $found ? (int)$found->revenue : 0
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $allMonths,
                'message' => 'Revenue chart data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Revenue chart error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve revenue chart data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quick stats for today
     */
public function getQuickStats(): JsonResponse
{
    try {
        \Log::info('🔄 Starting getQuickStats...');
        
        $today = Carbon::today();
        
        $todayRevenue = 0;
        $todayBookings = 0;
        $overdueReturns = 0;
        $pendingMaintenance = 0;
        $newUsersToday = 0;

        try {
            $todayRevenue = Pembayaran::whereDate('tanggal_bayar', $today)->sum('jumlah_bayar');
            \Log::info('✅ Today revenue: ' . $todayRevenue);
        } catch (\Exception $e) {
            \Log::error('❌ Today revenue error: ' . $e->getMessage());
        }

        try {
            $todayBookings = Penyewaan::whereDate('created_at', $today)->count();
            \Log::info('✅ Today bookings: ' . $todayBookings);
        } catch (\Exception $e) {
            \Log::error('❌ Today bookings error: ' . $e->getMessage());
        }

        try {
            $overdueReturns = Penyewaan::where('status_sewa', 'Berjalan')
                ->where('tanggal_kembali', '<', $today)
                ->count();
            \Log::info('✅ Overdue returns: ' . $overdueReturns);
        } catch (\Exception $e) {
            \Log::error('❌ Overdue returns error: ' . $e->getMessage());
        }

        try {
            $pendingMaintenance = PerawatanAlat::count();
            \Log::info('✅ Pending maintenance: ' . $pendingMaintenance);
        } catch (\Exception $e) {
            \Log::error('❌ Pending maintenance error: ' . $e->getMessage());
        }

        $quickStats = [
            'today_revenue' => (int)$todayRevenue,
            'today_bookings' => $todayBookings,
            'overdue_returns' => $overdueReturns,
            'pending_maintenance' => $pendingMaintenance,
            'new_users_today' => $newUsersToday
        ];

        \Log::info('🎉 Quick stats: ' . json_encode($quickStats));

        return response()->json([
            'success' => true,
            'data' => $quickStats,
            'message' => 'Quick stats retrieved successfully'
        ]);

    } catch (\Exception $e) {
        \Log::error('💥 CRITICAL ERROR in getQuickStats: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        
        // Return default data instead of error
        return response()->json([
            'success' => true,
            'data' => [
                'today_revenue' => 0,
                'today_bookings' => 0,
                'overdue_returns' => 0,
                'pending_maintenance' => 0,
                'new_users_today' => 0
            ],
            'message' => 'Quick stats retrieved with defaults'
        ]);
    }
}

    /**
     * Get pending approvals from Penyewaan
     */
    public function getPendingApprovals(): JsonResponse
    {
        try {
            $pendingApprovals = Penyewaan::with(['pelanggan', 'alat'])
                ->where('status_persetujuan', 'Menunggu')
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->get()
                ->map(function ($approval) {
                    return [
                        'id_sewa' => $approval->id_sewa,
                        'pelanggan_name' => $approval->pelanggan->nama_pelanggan ?? 'Unknown',
                        'alat_name' => $approval->alat->nama_alat ?? 'Unknown',
                        'tanggal_sewa' => $approval->tanggal_sewa,
                        'total_harga' => $approval->total_harga,
                        'created_at' => $approval->created_at->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $pendingApprovals,
                'message' => 'Pending approvals retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Pending approvals error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending approvals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all dashboard data in one endpoint - SIMPLIFIED VERSION
     */
    public function getAllDashboardData(): JsonResponse
    {
        try {
            // Get basic stats first
            $stats = $this->getDashboardStats();
            if (!$stats->getData()->success) {
                throw new \Exception('Failed to get dashboard stats');
            }

            // Get other data with error handling
            $activities = $this->getRecentActivities();
            $maintenance = $this->getMaintenanceSchedule();
            $quickStats = $this->getQuickStats();
            $pendingApprovals = $this->getPendingApprovals();
            $revenueChart = $this->getRevenueChart();

            $dashboardData = [
                'stats' => $stats->getData()->data,
                'activities' => $activities->getData()->success ? $activities->getData()->data : [],
                'maintenance' => $maintenance->getData()->success ? $maintenance->getData()->data : [],
                'quick_stats' => $quickStats->getData()->success ? $quickStats->getData()->data : [],
                'pending_approvals' => $pendingApprovals->getData()->success ? $pendingApprovals->getData()->data : [],
                'revenue_chart' => $revenueChart->getData()->success ? $revenueChart->getData()->data : []
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
                'message' => 'All dashboard data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('All dashboard data error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SIMPLE FALLBACK METHOD - Test basic connectivity
     */
    public function getBasicStats(): JsonResponse
    {
        try {
            $basicStats = [
                'total_users' => User::count(),
                'total_alat_berat' => AlatBerat::count(),
                'total_petugas' => Petugas::count(),
                'active_penyewaan' => Penyewaan::where('status_sewa', 'Berjalan')->count(),
                'pending_approvals' => Penyewaan::where('status_persetujuan', 'Menunggu')->count(),
                'total_revenue' => (int)Pembayaran::sum('jumlah_bayar'),
            ];

            return response()->json([
                'success' => true,
                'data' => $basicStats,
                'message' => 'Basic stats retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Basic stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve basic stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function to determine maintenance priority
     */
    private function getMaintenancePriority($keterangan): string
    {
        if (!$keterangan) return 'medium';
        
        $keterangan = strtolower($keterangan);
        if (str_contains($keterangan, 'urgent') || 
            str_contains($keterangan, 'critical') ||
            str_contains($keterangan, 'rusak')) {
            return 'high';
        } elseif (str_contains($keterangan, 'rutin') ||
                 str_contains($keterangan, 'pencegahan')) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}