<?php
require_once 'app/models/AnalyticsModel.php';

class AnalyticsController {
    private AnalyticsModel $model;

    public function __construct(private SupabaseDB $db) {
        $this->model = new AnalyticsModel($db);
    }

    public function index(): void {
        $tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'revenue');
        $tabs = ['revenue', 'rides', 'drivers', 'passengers', 'heatmap'];
        if (!in_array($tab, $tabs, true)) $tab = 'revenue';

        $mtdStats   = $this->model->getMtdStats();
        $dailyRev   = $this->model->getDailyRevenue(30);
        $dailyRides = $this->model->getDailyRides(30);
        $hourly     = $this->model->getHourlyDistribution();
        $dayOfWeek  = $this->model->getDayOfWeekDistribution();
        $topDrivers = $this->model->getTopDrivers(10);

        $currentPage = 'analytics';
        $pageTitle   = 'Analytics & Reports';
        $pageCrumbs  = ['Analytics & Reports'];

        require_once 'includes/header.php';
        require_once 'app/views/analytics/index.php';
        require_once 'includes/footer.php';
    }
}
