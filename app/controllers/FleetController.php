<?php
require_once 'app/models/FleetModel.php';

class FleetController {
    private FleetModel $model;

    public function __construct(private SupabaseDB $db) {
        $this->model = new FleetModel($db);
    }

    public function index(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
            header('Content-Type: application/json');
            $this->handlePost();
            return;
        }

        $tab  = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'types');
        $tabs = ['types' => 'Vehicle Types', 'fleet' => 'Fleet Overview', 'compliance' => 'Compliance'];
        if (!isset($tabs[$tab])) $tab = 'types';

        $rideTypes  = [];
        $fleet      = [];
        $fleetTotal = 0;
        $compliance = [];
        $stats      = $this->model->getStats();

        $fleetFilters = ['search' => trim($_GET['search'] ?? ''), 'status' => $_GET['fstatus'] ?? 'all'];
        $fleetPage    = max(1, (int)($_GET['fp'] ?? 1));
        $fleetPer     = 25;

        if ($tab === 'types') {
            $rideTypes = $this->model->getRideTypes();
        } elseif ($tab === 'fleet') {
            $res        = $this->model->getFleetDrivers($fleetFilters, $fleetPage, $fleetPer);
            $fleet      = $res['drivers'];
            $fleetTotal = $res['total'];
        } elseif ($tab === 'compliance') {
            $compliance = $this->model->getComplianceDrivers();
        }

        $currentPage = 'fleet';
        $pageTitle   = 'Fleet Management';
        $pageCrumbs  = ['Fleet Management'];

        require_once 'includes/header.php';
        require_once 'app/views/fleet/index.php';
        require_once 'includes/footer.php';
    }

    private function handlePost(): void {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_type') {
            $result = $this->model->createRideType($_POST);
            echo json_encode($result
                ? ['success' => true,  'message' => 'Vehicle type created.', 'data' => $result]
                : ['success' => false, 'message' => 'Failed to create type. Name may already exist.']);
            exit;
        }

        if ($action === 'update_type' && !empty($_POST['id'])) {
            $ok = $this->model->updateRideType($_POST['id'], $_POST);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Vehicle type updated.' : 'Update failed.']);
            exit;
        }

        if ($action === 'toggle_type' && !empty($_POST['id'])) {
            $active = filter_var($_POST['active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $ok     = $this->model->toggleRideType($_POST['id'], $active);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Status updated.' : 'Update failed.']);
            exit;
        }

        if ($action === 'delete_type' && !empty($_POST['id'])) {
            $result = $this->model->deleteRideType($_POST['id']);
            echo json_encode($result);
            exit;
        }

        if ($action === 'get_type' && !empty($_POST['id'])) {
            $row = $this->model->getRideTypeById($_POST['id']);
            echo json_encode($row ? ['success' => true, 'data' => $row] : ['success' => false, 'message' => 'Not found.']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;
    }
}
