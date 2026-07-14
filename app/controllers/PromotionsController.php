<?php
require_once 'app/models/PromotionsModel.php';

class PromotionsController {
    private PromotionsModel $model;

    public function __construct(private SupabaseDB $db) {
        $this->model = new PromotionsModel($db);
    }

    public function index(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
            header('Content-Type: application/json');
            $this->handlePost();
            return;
        }

        $tab  = preg_replace('/[^a-z_]/', '', $_GET['tab'] ?? 'pricing');
        if (!in_array($tab, ['pricing', 'promotions', 'promo_codes'])) $tab = 'pricing';

        $stats      = $this->model->getStats();
        $pricing    = [];
        $promotions = [];
        $promoCodes = [];
        $rideTypes  = [];

        if ($tab === 'pricing') {
            $pricing   = $this->model->getPricingConfigs();
            $rideTypes = $this->db->select('ride_types', [
                'select' => 'id,name',
                'order'  => 'sort_order.asc,name.asc',
            ]);
        } elseif ($tab === 'promotions') {
            $promotions = $this->model->getPromotions();
        } else {
            $promoCodes = $this->model->getPromoCodes();
            $rideTypes  = $this->db->select('ride_types', [
                'select' => 'id,name',
                'order'  => 'sort_order.asc,name.asc',
            ]);
        }

        $currentPage = 'promotions';
        $pageTitle   = 'Promotions & Pricing';
        $pageCrumbs  = ['Promotions & Pricing'];

        require_once 'includes/header.php';
        require_once 'app/views/promotions/index.php';
        require_once 'includes/footer.php';
    }

    private function handlePost(): void {
        $action = $_POST['action'] ?? '';
        match ($action) {
            'create_pricing' => $this->createPricing(),
            'update_pricing' => $this->updatePricing(),
            'delete_pricing' => $this->deletePricing(),
            'toggle_pricing' => $this->togglePricing(),
            'get_pricing'    => $this->getPricing(),
            'create_promo'      => $this->createPromo(),
            'update_promo'      => $this->updatePromo(),
            'delete_promo'      => $this->deletePromo(),
            'toggle_promo'      => $this->togglePromo(),
            'get_promo'         => $this->getPromo(),
            'create_promo_code' => $this->createPromoCode(),
            'update_promo_code' => $this->updatePromoCode(),
            'delete_promo_code' => $this->deletePromoCode(),
            'toggle_promo_code' => $this->togglePromoCode(),
            'get_promo_code'    => $this->getPromoCode(),
            default             => $this->json(['success' => false, 'message' => 'Unknown action.']),
        };
    }

    // ── Pricing ──────────────────────────────────────────────────────

    private function createPricing(): void {
        $r = $this->model->createPricingConfig($_POST);
        $this->json($r
            ? ['success' => true,  'message' => 'Pricing config created.', 'data' => $r]
            : ['success' => false, 'message' => 'Failed to create config. Check for duplicate ride type + period.']);
    }

    private function updatePricing(): void {
        $id = $_POST['id'] ?? '';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing ID.']); return; }
        $ok = $this->model->updatePricingConfig($id, $_POST);
        $this->json(['success' => $ok, 'message' => $ok ? 'Config updated.' : 'Update failed.']);
    }

    private function deletePricing(): void {
        $id = $_POST['id'] ?? '';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing ID.']); return; }
        $ok = $this->model->deletePricingConfig($id);
        $this->json(['success' => $ok, 'message' => $ok ? 'Config deleted.' : 'Delete failed.']);
    }

    private function togglePricing(): void {
        $id     = $_POST['id'] ?? '';
        $active = filter_var($_POST['active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing ID.']); return; }
        $ok = $this->model->togglePricing($id, $active);
        $this->json(['success' => $ok, 'message' => $ok ? 'Status updated.' : 'Update failed.']);
    }

    private function getPricing(): void {
        $id  = $_POST['id'] ?? '';
        $row = $this->model->getPricingConfigById($id);
        $this->json($row
            ? ['success' => true,  'data' => $row]
            : ['success' => false, 'message' => 'Not found.']);
    }

    // ── Promotions ───────────────────────────────────────────────────

    private function createPromo(): void {
        $r = $this->model->createPromotion($_POST);
        $this->json($r
            ? ['success' => true,  'message' => 'Promotion created.', 'data' => $r]
            : ['success' => false, 'message' => 'Failed to create promotion.']);
    }

    private function updatePromo(): void {
        $id = $_POST['id'] ?? '';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing ID.']); return; }
        $ok = $this->model->updatePromotion($id, $_POST);
        $this->json(['success' => $ok, 'message' => $ok ? 'Promotion updated.' : 'Update failed.']);
    }

    private function deletePromo(): void {
        $id = $_POST['id'] ?? '';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing ID.']); return; }
        $ok = $this->model->deletePromotion($id);
        $this->json(['success' => $ok, 'message' => $ok ? 'Promotion deleted.' : 'Delete failed.']);
    }

    private function togglePromo(): void {
        $id     = $_POST['id'] ?? '';
        $active = filter_var($_POST['active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing ID.']); return; }
        $ok = $this->model->togglePromotion($id, $active);
        $this->json(['success' => $ok, 'message' => $ok ? 'Status updated.' : 'Update failed.']);
    }

    private function getPromo(): void {
        $id  = $_POST['id'] ?? '';
        $row = $this->model->getPromotionById($id);
        $this->json($row
            ? ['success' => true,  'data' => $row]
            : ['success' => false, 'message' => 'Not found.']);
    }

    // ── Promo Codes ──────────────────────────────────────────────────

    private function createPromoCode(): void {
        $r = $this->model->createPromoCode($_POST);
        $this->json($r
            ? ['success' => true,  'message' => 'Promo code created.', 'data' => $r]
            : ['success' => false, 'message' => 'Failed to create promo code. Code may already exist.']);
    }

    private function updatePromoCode(): void {
        $id = $_POST['id'] ?? '';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing ID.']); return; }
        $ok = $this->model->updatePromoCode($id, $_POST);
        $this->json(['success' => $ok, 'message' => $ok ? 'Promo code updated.' : 'Update failed.']);
    }

    private function deletePromoCode(): void {
        $id = $_POST['id'] ?? '';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing ID.']); return; }
        $ok = $this->model->deletePromoCode($id);
        $this->json(['success' => $ok, 'message' => $ok ? 'Promo code deleted.' : 'Delete failed.']);
    }

    private function togglePromoCode(): void {
        $id     = $_POST['id'] ?? '';
        $active = filter_var($_POST['active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing ID.']); return; }
        $ok = $this->model->togglePromoCode($id, $active);
        $this->json(['success' => $ok, 'message' => $ok ? 'Status updated.' : 'Update failed.']);
    }

    private function getPromoCode(): void {
        $id  = $_POST['id'] ?? '';
        $row = $this->model->getPromoCodeById($id);
        $this->json($row
            ? ['success' => true,  'data' => $row]
            : ['success' => false, 'message' => 'Not found.']);
    }

    private function json(array $data): void {
        echo json_encode($data);
        exit;
    }
}
