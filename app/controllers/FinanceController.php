<?php
require_once 'app/models/FinanceModel.php';

class FinanceController {
    private FinanceModel $model;
    private string $adjustmentsFile;
    private const TABS = ['overview', 'ride_invoices', 'payouts', 'refunds', 'transactions', 'commission', 'invoices'];

    public function __construct(private SupabaseDB $db) {
        $this->model           = new FinanceModel($db);
        $this->adjustmentsFile = __DIR__ . '/../../config/finance_adjustments.json';
    }

    public function index(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'issue_credit') {
            $this->handleIssueCredit();
            return;
        }

        $tab = preg_replace('/[^a-z_]/', '', $_GET['tab'] ?? 'overview');
        if (!in_array($tab, self::TABS, true)) $tab = 'overview';

        $invoiceFilters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to']   ?? '',
            'driver_id' => $_GET['driver_id'] ?? '',
            'search'    => trim($_GET['search'] ?? ''),
            'fare_min'  => $_GET['fare_min'] ?? '',
            'fare_max'  => $_GET['fare_max'] ?? '',
            'sort'      => $_GET['sort'] ?? 'date_desc',
        ];

        if ($tab === 'ride_invoices' && ($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv($invoiceFilters);
            return;
        }
        if ($tab === 'ride_invoices' && ($_GET['export'] ?? '') === 'json') {
            $this->exportJson($invoiceFilters);
            return;
        }
        if ($tab === 'invoices' && ($_GET['export'] ?? '') === 'json' && !empty($_GET['cid'])) {
            $this->exportCorporateJson($_GET['cid'], [
                'date_from' => $_GET['date_from'] ?? '',
                'date_to'   => $_GET['date_to']   ?? '',
            ]);
            return;
        }

        $kpis       = $this->model->getKpis();
        $adjustments = $this->loadAdjustments();
        $monthStart  = date('Y-m-01');
        $adjMtd      = 0.0;
        foreach ($adjustments as $a) {
            if (($a['created_at'] ?? '') >= $monthStart) $adjMtd += (float)$a['amount'];
        }
        $kpis['adjustments_mtd'] = round($adjMtd, 2);
        $kpis['breakdown'] = [
            'commission_pct'  => $kpis['avg_commission_pct'],
            'driver_pct'      => round(100 - $kpis['avg_commission_pct'], 1),
            'adjustments_pct' => $kpis['revenue_mtd'] > 0 ? round(min(100, $adjMtd / $kpis['revenue_mtd'] * 100), 1) : 0.0,
        ];

        $drivers        = [];
        $passengers     = [];
        $invoices       = [];
        $total          = 0;
        $stats          = null;
        $page           = max(1, (int)($_GET['p'] ?? 1));
        $perPage        = 25;
        $dailyRevenue   = [];
        $driverWallets  = [];
        $transactions   = [];
        $commissionRows = [];
        $corporate      = [];

        if ($tab === 'overview') {
            $dailyRevenue = $this->model->getDailyRevenueSeries(30);
        } elseif ($tab === 'ride_invoices') {
            $drivers  = $this->model->getDriversForFilter();
            $invoices = $this->model->getInvoiceRides($invoiceFilters, $page, $perPage);
            $total    = $this->model->countInvoiceRides($invoiceFilters);
            $stats    = $this->model->getInvoiceStats($invoiceFilters);
        } elseif ($tab === 'payouts') {
            $driverWallets = $this->model->getDriverWallets(trim($_GET['search'] ?? ''));
        } elseif ($tab === 'refunds') {
            $drivers    = $this->model->getDriversForFilter();
            $passengers = $this->model->getPassengersForFilter();
            $adjustments = array_slice($adjustments, 0, 50);
        } elseif ($tab === 'transactions') {
            $txFilters = [
                'date_from' => $_GET['date_from'] ?? '',
                'date_to'   => $_GET['date_to']   ?? '',
                'method'    => $_GET['method'] ?? 'all',
                'search'    => trim($_GET['search'] ?? ''),
            ];
            $transactions = $this->model->getTransactions($txFilters, $page, $perPage);
            $total        = $this->model->countTransactions($txFilters);
        } elseif ($tab === 'commission') {
            $commissionRows = $this->model->getCommissionSettings();
        } elseif ($tab === 'invoices') {
            $corporate = $this->model->getCorporateInvoices([
                'date_from' => $_GET['date_from'] ?? '',
                'date_to'   => $_GET['date_to']   ?? '',
            ]);
        }

        $totalPages = (int)ceil($total / $perPage);

        $currentPage = 'finance';
        $pageTitle   = 'Finance & Payments';
        $pageCrumbs  = ['Finance & Payments'];

        require_once 'includes/header.php';
        require_once 'app/views/finance/index.php';
        require_once 'includes/footer.php';
    }

    private function handleIssueCredit(): void {
        header('Content-Type: application/json');

        $type   = $_POST['credit_type'] ?? '';
        $id     = trim($_POST['target_id'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (!in_array($type, ['driver', 'passenger'], true) || $id === '' || $amount <= 0 || $reason === '') {
            echo json_encode(['success' => false, 'message' => 'Please select a target, a positive amount, and a reason.']);
            exit;
        }

        if ($type === 'driver') {
            $rows = $this->db->select('drivers', ['select' => 'id,full_name', 'id' => 'eq.' . $id, 'limit' => 1]);
            $name = $rows[0]['full_name'] ?? 'Driver';
            $ok   = $this->model->issueDriverCredit($id, $amount, $reason);
        } else {
            $rows = $this->db->select('passengers', ['select' => 'id,name', 'id' => 'eq.' . $id, 'limit' => 1]);
            $name = $rows[0]['name'] ?? 'Passenger';
            $ok   = $this->model->issuePassengerCredit($id, $amount, $reason);
        }

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Wallet credit failed — the wallet service did not accept the request.']);
            exit;
        }

        $log = $this->loadAdjustments();
        array_unshift($log, [
            'id'          => uniqid('adj_', true),
            'type'        => $type,
            'target_id'   => $id,
            'target_name' => $name,
            'amount'      => round($amount, 2),
            'reason'      => $reason,
            'created_at'  => date('c'),
        ]);
        if (count($log) > 200) $log = array_slice($log, 0, 200);
        file_put_contents($this->adjustmentsFile, json_encode($log, JSON_PRETTY_PRINT));

        echo json_encode(['success' => true, 'message' => ucfirst($type) . ' wallet credited €' . number_format($amount, 2) . '.']);
        exit;
    }

    private function loadAdjustments(): array {
        if (!file_exists($this->adjustmentsFile)) return [];
        return json_decode(file_get_contents($this->adjustmentsFile), true) ?? [];
    }

    private function exportCsv(array $filters): void {
        $rows = $this->model->getAllForExport($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="powercabs-invoices-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Ride ID', 'Date', 'Passenger', 'Passenger Email', 'Driver', 'Driver Email', 'Driver Phone', 'Fare (EUR)', 'Commission (10%)', 'Driver Earnings', 'Pickup', 'Destination', 'Distance (km)', 'Duration (min)']);

        foreach ($rows as $r) {
            $fare       = (float)($r['fare'] ?? 0);
            $commission = round($fare * 0.10, 2);
            fputcsv($out, [
                $r['id'],
                $r['created_at'],
                $r['passenger_name']  ?? '',
                $r['passenger_email'] ?? '',
                $r['driver_name']     ?? '',
                $r['driver_email']    ?? '',
                $r['driver_phone']    ?? '',
                number_format($fare, 2),
                number_format($commission, 2),
                number_format($fare - $commission, 2),
                $r['pickup_addr']  ?? '',
                $r['dest_addr']    ?? '',
                $r['distance_km']  ?? '',
                $r['duration_min'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    private function exportJson(array $filters): void {
        header('Content-Type: application/json');

        $stats = $this->model->getInvoiceStats($filters);
        $rows  = $this->model->getAllForExport($filters);

        $driverName = null;
        if (!empty($filters['driver_id'])) {
            foreach ($this->model->getDriversForFilter() as $d) {
                if ($d['id'] === $filters['driver_id']) { $driverName = $d['full_name']; break; }
            }
        }

        $driverMode = !empty($filters['driver_id']);

        $items = array_map(function ($r) {
            $fareEur   = (float)($r['fare_eur'] ?? 0);
            $finalFare = (float)($r['final_fare'] ?? $fareEur);
            $charged   = (float)($r['total_charged'] ?? $finalFare) ?: $finalFare;
            $commission = round($charged * 0.10, 2);
            return [
                'id'             => $r['id'],
                'date'           => $r['created_at'],
                'passenger'      => $r['passenger_name'] ?? '—',
                'driver'         => $r['driver_name'] ?? 'Unassigned',
                'driver_id'      => $r['driver_id'] ?? '',
                'driver_license' => $r['driver_license'] ?? '',
                'driver_phone'   => $r['driver_phone'] ?? '',
                'pickup'         => $r['pickup_addr'] ?? '—',
                'dest'           => $r['dest_addr'] ?? '—',
                'payment_method' => $r['payment_method'] ?? '',
                'vehicle_type'   => $r['vehicle_type'] ?? null,
                'fare_eur'       => round($fareEur, 2),
                'final_fare'     => round($finalFare, 2),
                'fare'           => round($charged, 2),
                'commission'     => $commission,
                'earnings'       => round($charged - $commission, 2),
            ];
        }, $rows);

        $driverLicense = $items[0]['driver_license'] ?? '';

        echo json_encode([
            'success'        => true,
            'driver_mode'    => $driverMode,
            'driver_name'    => $driverName,
            'driver_id'      => $filters['driver_id'] ?? '',
            'driver_license' => $driverLicense,
            'date_from'      => $filters['date_from'],
            'date_to'        => $filters['date_to'],
            'stats'          => $stats,
            'rows'           => $items,
        ]);
        exit;
    }

    private function exportCorporateJson(string $cid, array $filters): void {
        header('Content-Type: application/json');

        $rows  = $this->model->getCorporateInvoiceDetail($cid, $filters);
        $corp  = $this->db->select('corporate', ['select' => 'cid,name,email,address', 'cid' => 'eq.' . $cid, 'limit' => 1]);
        $clean = fn($v) => (is_string($v) && strtoupper(trim($v)) === 'NULL') ? null : $v;
        $c     = $corp[0] ?? null;

        $items = array_map(fn($r) => [
            'id'       => $r['id'],
            'date'     => $r['created_at'],
            'employee' => !empty($r['employee']) ? trim($r['employee']) : '—',
            'pickup'   => $r['pickup_addr'] ?? '—',
            'dest'     => $r['dest_addr'] ?? '—',
            'fare'     => round((float)($r['final_fare'] ?? $r['fare_eur'] ?? 0), 2),
        ], $rows);

        echo json_encode([
            'success'   => true,
            'cid'       => $cid,
            'company'   => $clean($c['name'] ?? null) ?: $cid,
            'email'     => $clean($c['email'] ?? null),
            'address'   => $clean($c['address'] ?? null),
            'date_from' => $filters['date_from'],
            'date_to'   => $filters['date_to'],
            'rows'      => $items,
            'total'     => round(array_sum(array_column($items, 'fare')), 2),
        ]);
        exit;
    }
}
