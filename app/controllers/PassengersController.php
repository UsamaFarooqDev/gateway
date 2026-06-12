<?php
require_once 'app/models/PassengerModel.php';

class PassengersController {
    private PassengerModel $model;

    public function __construct(private SupabaseDB $db) {
        $this->model = new PassengerModel($db);
    }

    public function index(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handlePost();
            return;
        }

        $filters = [
            'status' => $_GET['status'] ?? 'all',
            'search' => trim($_GET['search'] ?? ''),
        ];

        $page    = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 20;

        // Single parallel batch: list + count + all status counts
        $pageData   = $this->model->loadPageData($filters, $page, $perPage);
        $passengers = $pageData['passengers'];
        $total      = $pageData['total'];
        $counts     = $pageData['counts'];

        $totalPages  = (int) ceil($total / $perPage);
        $currentPage = 'passengers';
        $pageTitle   = 'Passenger Management';
        $pageCrumbs  = ['Passengers'];

        require_once 'includes/header.php';
        require_once 'app/views/passengers/index.php';
        require_once 'includes/footer.php';
    }

    private function handlePost(): void {
        header('Content-Type: application/json');

        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);

        if ($action === 'update_status' && !empty($_POST['id'])) {
            $id     = $_POST['id'];  // UUID string
            $status = $_POST['status'] ?? '';
            try {
                $ok = $this->model->updateStatus($id, $status);
                echo json_encode(['success' => $ok, 'message' => $ok ? 'Status updated.' : 'Invalid status.']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;
    }
}
