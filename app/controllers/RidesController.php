<?php
require_once 'app/models/RidesModel.php';

class RidesController {
    private RidesModel $model;

    public function __construct(private SupabaseDB $db) {
        $this->model = new RidesModel($db);
    }

    public function index(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handlePost();
            return;
        }

        $filters = [
            'status'    => $_GET['status'] ?? 'all',
            'search'    => trim($_GET['search'] ?? ''),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to']   ?? '',
        ];

        $page    = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 25;

        $pageData   = $this->model->loadPageData($filters, $page, $perPage);
        $rides      = $pageData['rides'];
        $total      = $pageData['total'];
        $counts     = $pageData['counts'];
        $totalPages = (int)ceil($total / $perPage);

        $currentPage = 'rides';
        $pageTitle   = 'Ride Management';
        $pageCrumbs  = ['Ride Management'];

        require_once 'includes/header.php';
        require_once 'app/views/rides/index.php';
        require_once 'includes/footer.php';
    }

    private function handlePost(): void {
        header('Content-Type: application/json');
        $action = $_POST['action'] ?? '';
        $id     = $_POST['id'] ?? '';

        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Missing ride ID.']);
            exit;
        }

        if ($action === 'cancel_ride') {
            $ok = $this->model->cancelRide($id);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Ride cancelled.' : 'Failed to cancel.']);
            exit;
        }

        if ($action === 'update_status') {
            $status = $_POST['status'] ?? '';
            $ok = $this->model->updateStatus($id, $status);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Status updated.' : 'Invalid status.']);
            exit;
        }

        if ($action === 'get_ride') {
            $ride = $this->model->getById($id);
            if ($ride) {
                echo json_encode(['success' => true, 'data' => $ride]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ride not found.']);
            }
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;
    }
}
