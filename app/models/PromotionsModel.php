<?php

class PromotionsModel {
    public function __construct(private SupabaseDB $db) {}

    // ── Pricing Config ────────────────────────────────────────────────

    public function getPricingConfigs(): array {
        return $this->db->select('pricing_config', [
            'select' => '*',
            'order'  => 'ride_type.asc,time_period.asc',
        ]);
    }

    public function getPricingConfigById(string $id): ?array {
        $rows = $this->db->select('pricing_config', ['id' => 'eq.' . $id, 'limit' => 1]);
        return $rows[0] ?? null;
    }

    public function createPricingConfig(array $data): ?array {
        return $this->db->insert('pricing_config', $this->sanitizePricing($data));
    }

    public function updatePricingConfig(string $id, array $data): bool {
        $payload               = $this->sanitizePricing($data);
        $payload['updated_at'] = date('c');
        return $this->db->update('pricing_config', $payload, ['id' => 'eq.' . $id]);
    }

    public function deletePricingConfig(string $id): bool {
        return $this->db->delete('pricing_config', ['id' => 'eq.' . $id]);
    }

    public function togglePricing(string $id, bool $active): bool {
        return $this->db->update('pricing_config', [
            'is_active'  => $active,
            'updated_at' => date('c'),
        ], ['id' => 'eq.' . $id]);
    }

    private function sanitizePricing(array $d): array {
        $flt  = fn(string $k, float $min = 0.0, float $def = 0.0) => round(max($min, (float)($d[$k] ?? $def)), 4);
        $int  = fn(string $k, int $def = 0) => max(0, (int)($d[$k] ?? $def));
        $bool = fn(string $k, bool $def = false) => filter_var($d[$k] ?? $def, FILTER_VALIDATE_BOOLEAN);
        $str  = fn(string $k) => trim($d[$k] ?? '') ?: null;

        return array_filter([
            'ride_type'              => strtolower(str_replace(' ','_',trim($d['ride_type'] ?? 'all'))) ?: 'all',
            'time_period'            => in_array($d['time_period'] ?? 'both', ['day','night','both']) ? $d['time_period'] : 'both',
            'day_start_hour'         => !empty($d['day_start_hour']) ? max(0, min(23, (int)$d['day_start_hour'])) : null,
            'day_end_hour'           => !empty($d['day_end_hour'])   ? max(0, min(23, (int)$d['day_end_hour']))   : null,
            'base_fare'              => $flt('base_fare', 0, 2.50),
            'booking_fee'            => $flt('booking_fee', 0, 0.0),
            'per_km_rate'            => $flt('per_km_rate', 0, 1.50),
            'per_min_rate'           => $flt('per_min_rate', 0, 0.20),
            'type_multiplier'        => round(max(0.1, (float)($d['type_multiplier'] ?? 1.0)), 2),
            'minimum_fare'           => $flt('minimum_fare', 0, 5.0),
            'surge_enabled'          => $bool('surge_enabled'),
            'surge_multiplier'       => round(max(1.0, (float)($d['surge_multiplier'] ?? 1.5)), 2),
            'surge_label'            => $str('surge_label'),
            'range_low_pct'          => $int('range_low_pct', 80),
            'range_high_pct'         => $int('range_high_pct', 100),
            'discount_enabled'       => $bool('discount_enabled'),
            'discount_type'          => in_array($d['discount_type'] ?? 'percentage', ['percentage','fixed']) ? $d['discount_type'] : 'percentage',
            'discount_value'         => $flt('discount_value', 0),
            'discount_label'         => $str('discount_label'),
            'discount_valid_from'    => !empty($d['discount_valid_from']) ? $d['discount_valid_from'] : null,
            'discount_valid_until'   => !empty($d['discount_valid_until']) ? $d['discount_valid_until'] : null,
            'discount_max_uses'      => !empty($d['discount_max_uses']) ? (int)$d['discount_max_uses'] : null,
            'discount_min_fare'      => $flt('discount_min_fare', 0),
            'card_surcharge_pct'     => $flt('card_surcharge_pct', 0),
            'card_surcharge_fixed'   => $flt('card_surcharge_fixed', 0),
            'card_surcharge_label'   => $str('card_surcharge_label'),
            'schedule_notify_mins'   => $int('schedule_notify_mins', 30),
            'schedule_fallback_mins' => $int('schedule_fallback_mins', 15),
            'schedule_cancel_mins'   => $int('schedule_cancel_mins', 5),
            'driver_min_balance'     => $flt('driver_min_balance', 0),
            'driver_warning_balance' => $flt('driver_warning_balance', 0),
            'driver_commission_pct'  => $flt('driver_commission_pct', 0, 20.0),
            'is_active'              => $bool('is_active', true),
        ], fn($v) => $v !== null);
    }

    // ── Promotions ────────────────────────────────────────────────────
    // NOTE: action_url requires: ALTER TABLE public.promotions ADD COLUMN action_url text;

    public function getPromotions(): array {
        return $this->db->select('promotions', [
            'select' => '*',
            'order'  => 'created_at.desc',
        ]);
    }

    public function getPromotionById(string $id): ?array {
        $rows = $this->db->select('promotions', ['id' => 'eq.' . $id, 'limit' => 1]);
        return $rows[0] ?? null;
    }

    public function createPromotion(array $data): ?array {
        return $this->db->insert('promotions', $this->sanitizePromotion($data));
    }

    public function updatePromotion(string $id, array $data): bool {
        $payload               = $this->sanitizePromotion($data);
        $payload['updated_at'] = date('c');
        return $this->db->update('promotions', $payload, ['id' => 'eq.' . $id]);
    }

    public function deletePromotion(string $id): bool {
        return $this->db->delete('promotions', ['id' => 'eq.' . $id]);
    }

    public function togglePromotion(string $id, bool $active): bool {
        return $this->db->update('promotions', [
            'is_active'  => $active,
            'updated_at' => date('c'),
        ], ['id' => 'eq.' . $id]);
    }

    private function sanitizePromotion(array $d): array {
        $url = filter_var(trim($d['action_url'] ?? ''), FILTER_VALIDATE_URL) ?: null;
        return array_filter([
            'title'           => trim($d['title'] ?? ''),
            'description'     => trim($d['description'] ?? '') ?: null,
            'color'           => preg_match('/^#[0-9a-fA-F]{3,6}$/', $d['color'] ?? '') ? $d['color'] : '#F37A20',
            'icon'            => trim($d['icon'] ?? '') ?: null,
            'starts_at'       => !empty($d['starts_at']) ? $d['starts_at'] : null,
            'ends_at'         => !empty($d['ends_at']) ? $d['ends_at'] : null,
            'is_active'       => filter_var($d['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'target_audience' => in_array($d['target_audience'] ?? 'all', ['all','passenger','driver']) ? $d['target_audience'] : 'all',
            'action_url'      => $url,
        ], fn($v) => $v !== null);
    }

    // ── Stats ─────────────────────────────────────────────────────────

    public function getStats(): array {
        $now = date('c');
        $res = $this->db->selectParallel([
            0 => ['table' => 'pricing_config', 'params' => ['select' => 'id'], 'withCount' => true],
            1 => ['table' => 'pricing_config', 'params' => ['select' => 'id', 'is_active' => 'eq.true'], 'withCount' => true],
            2 => ['table' => 'promotions',     'params' => ['select' => 'id'], 'withCount' => true],
            3 => ['table' => 'promotions',     'params' => ['select' => 'id', 'is_active' => 'eq.true', 'ends_at' => 'gte.' . $now], 'withCount' => true],
        ]);
        return [
            'total_pricing'  => $res[0]['count'],
            'active_pricing' => $res[1]['count'],
            'total_promos'   => $res[2]['count'],
            'active_promos'  => $res[3]['count'],
        ];
    }
}
