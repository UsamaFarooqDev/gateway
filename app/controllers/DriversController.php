<?php
require_once 'app/models/DriverModel.php';

class DriversController {
    private DriverModel $model;

    public function __construct(private SupabaseDB $db) {
        $this->model = new DriverModel($db);
    }

    public function index(): void {
        // AJAX: live search
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search_ajax') {
            header('Content-Type: application/json');
            $q      = trim($_GET['q']      ?? '');
            $status = preg_replace('/[^a-z_]/', '', $_GET['status'] ?? 'all');
            $rows   = $this->model->searchDrivers($q, $status);
            echo json_encode($rows);
            exit;
        }

        // Handle AJAX status-update POST
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

        // Parallel: page data + ride types
        $pageData  = $this->model->loadPageData($filters, $page, $perPage);
        $drivers   = $pageData['drivers'];
        $total     = $pageData['total'];
        $counts    = $pageData['counts'];
        $rideTypes = $this->model->getRideTypes();

        $totalPages  = (int) ceil($total / $perPage);
        $currentPage = 'drivers';
        $pageTitle   = 'Driver Management';
        $pageCrumbs  = ['Drivers'];

        require_once 'includes/header.php';
        require_once 'app/views/drivers/index.php';
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

        if ($action === 'assign_ride_types' && !empty($_POST['id'])) {
            $id        = $_POST['id'];
            $names     = json_decode($_POST['type_names'] ?? '[]', true);
            $names     = is_array($names) ? $names : [];
            $rideTypes = $this->model->getRideTypes();
            $ok        = $this->model->assignRideTypes($id, $names, $rideTypes);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Ride types updated.' : 'Update failed.']);
            exit;
        }

        if ($action === 'delete_driver' && !empty($_POST['id'])) {
            $result = $this->model->deleteDriver($_POST['id']);
            echo json_encode($result);
            exit;
        }

        if ($action === 'upload_doc' && !empty($_POST['id']) && !empty($_POST['doc_type'])) {
            $driverId = $_POST['id'];
            $docType  = $_POST['doc_type'];
            $file     = $_FILES['doc_file'] ?? null;

            if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
                    UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
                    UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                ];
                $msg = $uploadErrors[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload error.';
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }

            $result = $this->model->uploadDoc($driverId, $docType, $file['tmp_name'], $file['name']);
            echo json_encode($result);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;
    }
}
