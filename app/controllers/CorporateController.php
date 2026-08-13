<?php
require_once 'app/models/CorporateModel.php';

class CorporateController {
    private CorporateModel $model;

    public function __construct(private SupabaseDB $db) {
        $this->model = new CorporateModel($db);
    }

    public function index(): void {
        Permission::requireCan('gateway', 'corporate', 'view');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
            header('Content-Type: application/json');
            $this->handlePost();
            return;
        }

        $tab = preg_replace('/[^a-z_]/', '', $_GET['tab'] ?? 'accounts');
        if (!in_array($tab, ['accounts', 'employees', 'rides', 'invoices', 'revenue_history'])) $tab = 'accounts';

        $stats      = $this->model->getStats();
        $corporates = [];
        $employees  = [];
        $rides      = [];
        $allCorps   = [];
        $filterCid  = htmlspecialchars($_GET['cid'] ?? '', ENT_QUOTES);

        if ($tab === 'accounts') {
            $corporates = $this->model->getAll();
        } elseif ($tab === 'employees') {
            $allCorps  = $this->model->getAll();
            $employees = $filterCid
                ? $this->model->getEmployees($filterCid)
                : $this->model->getAllEmployees();
        } elseif ($tab === 'rides') {
            $allCorps = $this->model->getAll();
            if ($filterCid) {
                $rides = $this->model->getRides($filterCid, [
                    'date_from' => $_GET['date_from'] ?? '',
                    'date_to'   => $_GET['date_to']   ?? '',
                    'status'    => $_GET['status']     ?? '',
                ]);
            }
        } elseif ($tab === 'invoices') {
            $allCorps = $this->model->getAll();
        } elseif ($tab === 'revenue_history') {
            $allCorps = $this->model->getAll();
        }

        $currentPage = 'corporate';
        $pageTitle   = 'Corporate Accounts';
        $pageCrumbs  = ['Corporate Accounts'];

        require_once 'includes/header.php';
        require_once 'app/views/corporate/index.php';
        require_once 'includes/footer.php';
    }

    private function handlePost(): void {
        $action = $_POST['action'] ?? '';
        match ($action) {
            'create_corporate' => $this->requireAndRun('create', fn() => $this->createCorporate()),
            'update_corporate' => $this->requireAndRun('edit',   fn() => $this->updateCorporate()),
            'delete_corporate' => $this->requireAndRun('delete', fn() => $this->deleteCorporate()),
            'toggle_corporate' => $this->requireAndRun('edit',   fn() => $this->toggleCorporate()),
            'get_corporate'    => $this->getCorporate(),
            'get_employees'    => $this->getEmployees(),
            'compute_invoice'      => $this->computeInvoice(),
            'get_revenue_history'  => $this->getRevenueHistory(),
            default                => $this->json(['success' => false, 'message' => 'Unknown action.']),
        };
    }

    private function requireAndRun(string $action, callable $fn): void {
        Permission::requireCan('gateway', 'corporate', $action);
        $fn();
    }

    private function createCorporate(): void {
        if (empty($_POST['cid'])) {
            $_POST['cid'] = 'CID-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
        }
        $r = $this->model->create($_POST);
        $this->json($r
            ? ['success' => true,  'message' => 'Corporate account created.', 'data' => $r]
            : ['success' => false, 'message' => 'Failed to create account. CID may already exist.']);
    }

    private function updateCorporate(): void {
        $cid = $_POST['cid'] ?? '';
        if (!$cid) { $this->json(['success' => false, 'message' => 'Missing CID.']); return; }
        $ok = $this->model->update($cid, $_POST);
        $this->json(['success' => $ok, 'message' => $ok ? 'Account updated.' : 'Update failed.']);
    }

    private function deleteCorporate(): void {
        $cid = $_POST['cid'] ?? '';
        if (!$cid) { $this->json(['success' => false, 'message' => 'Missing CID.']); return; }
        $ok = $this->model->delete($cid);
        $this->json(['success' => $ok, 'message' => $ok ? 'Account deleted.' : 'Delete failed.']);
    }

    private function toggleCorporate(): void {
        $cid    = $_POST['cid']    ?? '';
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        if (!$cid) { $this->json(['success' => false, 'message' => 'Missing CID.']); return; }
        $ok = $this->model->toggleStatus($cid, $status);
        $this->json(['success' => $ok, 'message' => $ok ? 'Status updated.' : 'Update failed.']);
    }

    private function getCorporate(): void {
        $cid = $_POST['cid'] ?? '';
        $row = $this->model->getById($cid);
        $this->json($row
            ? ['success' => true,  'data' => $row]
            : ['success' => false, 'message' => 'Not found.']);
    }

    private function getEmployees(): void {
        $cid  = $_POST['cid'] ?? '';
        $rows = $cid ? $this->model->getEmployees($cid) : [];
        $this->json(['success' => true, 'data' => $rows]);
    }

    private function getRevenueHistory(): void {
        $cid  = $_POST['cid']       ?? '';
        $from = $_POST['date_from'] ?? '';
        $to   = $_POST['date_to']   ?? '';
        if (!$cid || !$from || !$to) {
            $this->json(['success' => false, 'message' => 'Company and date range are required.']);
            return;
        }
        $this->json($this->model->getRevenueHistory($cid, $from, $to));
    }

    private function computeInvoice(): void {
        $cid  = $_POST['cid']       ?? '';
        $from = $_POST['date_from'] ?? '';
        $to   = $_POST['date_to']   ?? '';
        if (!$cid || !$from || !$to) {
            $this->json(['success' => false, 'message' => 'Company, date from, and date to are required.']);
            return;
        }
        $this->json($this->model->computeInvoice($cid, $from, $to));
    }

    private function json(array $data): void {
        echo json_encode($data);
        exit;
    }
}
