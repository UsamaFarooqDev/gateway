<?php
require_once 'app/models/DashboardModel.php';

class DashboardController {
    public function __construct(private SupabaseDB $db) {}

    public function index(): void {
        $model    = new DashboardModel($this->db);
        $cacheKey = 'dash_all';

        // Bust cache on manual refresh
        if (isset($_GET['refresh'])) Cache::forget($cacheKey);

        $data = Cache::get($cacheKey);
        if (!$data) {
            $data = $model->getAllData();
            Cache::set($cacheKey, $data, 30); // 30-second TTL
        }

        [
            'stats'           => $stats,
            'driverCounts'    => $driverCounts,
            'weeklyRides'     => $weeklyRides,
            'weeklyByStatus'  => $weeklyByStatus,
            'rideCounts'      => $rideCounts,
            'recentRides'     => $recentRides,
            'dashboardAlerts' => $dashboardAlerts,
        ] = $data;

        $ridesTrend = $stats['rides_yesterday'] > 0
            ? round((($stats['rides_today'] - $stats['rides_yesterday']) / $stats['rides_yesterday']) * 100, 1)
            : 0;
        $revTrend = $stats['revenue_yesterday'] > 0
            ? round((($stats['revenue_today'] - $stats['revenue_yesterday']) / $stats['revenue_yesterday']) * 100, 1)
            : 0;

        $currentPage = 'dashboard';
        $pageTitle   = 'Dashboard';
        $pageCrumbs  = [];

        require_once 'includes/header.php';
        require_once 'app/views/dashboard/index.php';
        require_once 'includes/footer.php';
    }
}
