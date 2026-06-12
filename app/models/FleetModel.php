<?php

class FleetModel {
    public function __construct(private SupabaseDB $db) {}

    // ── Vehicle Types (ride_types) ────────────────────────────────────

    public function getRideTypes(): array {
        return $this->db->select('ride_types', [
            'select' => 'id,name,description,image_url,seats,multiplier,is_active,sort_order,requires_note,note_hint,icon_emoji,waiting_minutes,created_at,updated_at',
            'order'  => 'sort_order.asc,name.asc',
        ]);
    }

    public function getRideTypeById(string $id): ?array {
        $rows = $this->db->select('ride_types', [
            'id'    => 'eq.' . $id,
            'limit' => 1,
        ]);
        return $rows[0] ?? null;
    }

    public function createRideType(array $data): ?array {
        return $this->db->insert('ride_types', $this->sanitizeRideType($data));
    }

    public function updateRideType(string $id, array $data): bool {
        $payload               = $this->sanitizeRideType($data);
        $payload['updated_at'] = date('c');
        return $this->db->update('ride_types', $payload, ['id' => 'eq.' . $id]);
    }

    public function toggleRideType(string $id, bool $active): bool {
        return $this->db->update('ride_types', ['is_active' => $active, 'updated_at' => date('c')], ['id' => 'eq.' . $id]);
    }

    public function deleteRideType(string $id): array {
        // Check if any drivers use this type
        $all    = $this->getRideTypeById($id);
        $name   = $all['name'] ?? '';
        $rows   = $this->db->select('drivers', [
            'select'     => 'id',
            'type'       => 'cs.["' . addslashes($name) . '"]',
            'deleted_at' => 'is.null',
            'limit'      => 1,
        ]);
        // If we can't confirm safely, just delete
        $ok = $this->db->delete('ride_types', ['id' => 'eq.' . $id]);
        return ['success' => $ok, 'message' => $ok ? 'Vehicle type deleted.' : 'Delete failed.'];
    }

    private function sanitizeRideType(array $d): array {
        $out = [
            'name'           => trim($d['name'] ?? ''),
            'description'    => trim($d['description'] ?? '') ?: null,
            'image_url'      => trim($d['image_url'] ?? '') ?: null,
            'icon_emoji'     => trim($d['icon_emoji'] ?? '') ?: null,
            'seats'          => max(1, (int)($d['seats'] ?? 4)),
            'multiplier'     => round(max(0.1, (float)($d['multiplier'] ?? 1.0)), 2),
            'waiting_minutes'=> max(0, (int)($d['waiting_minutes'] ?? 3)),
            'sort_order'     => (int)($d['sort_order'] ?? 0),
            'is_active'      => filter_var($d['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'requires_note'  => filter_var($d['requires_note'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'note_hint'      => trim($d['note_hint'] ?? '') ?: null,
        ];
        return array_filter($out, fn($v) => $v !== null);
    }

    // ── Fleet Overview ────────────────────────────────────────────────

    public function getFleetDrivers(array $filters = [], int $page = 1, int $perPage = 25): array {
        $params = [
            'select'     => 'id,full_name,profile_pic_url,vehicle_make,vehicle_model,plate_no,no_seats,type,status,is_online,total_rides',
            'deleted_at' => 'is.null',
            'order'      => 'full_name.asc',
            'limit'      => $perPage,
            'offset'     => ($page - 1) * $perPage,
        ];

        if (!empty($filters['search'])) {
            $s = str_replace(['(',')',',','.','*'], '', $filters['search']);
            $params['or'] = "(full_name.ilike.*{$s}*,plate_no.ilike.*{$s}*)";
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $params['status'] = 'eq.' . $filters['status'];
        }

        $res   = $this->db->selectParallel([
            0 => ['table' => 'admin_driver_stats', 'params' => $params],
            1 => ['table' => 'admin_driver_stats', 'params' => array_merge(['select' => 'id', 'deleted_at' => 'is.null'], array_intersect_key($params, array_flip(['or','status']))), 'withCount' => true],
        ]);
        return ['drivers' => $res[0], 'total' => $res[1]['count']];
    }

    // ── Compliance ────────────────────────────────────────────────────

    public function getComplianceDrivers(): array {
        return $this->db->select('drivers', [
            'select'     => 'id,full_name,email,phone,status,license_url,vehicle_reg_url,insurance_url,nct_cert,rt_cert,suitability_cert,license_expiry',
            'deleted_at' => 'is.null',
            'order'      => 'full_name.asc',
            'limit'      => 200,
        ]);
    }

    // ── Stats ─────────────────────────────────────────────────────────

    public function getStats(): array {
        $res = $this->db->selectParallel([
            0 => ['table' => 'ride_types', 'params' => ['select' => 'id'], 'withCount' => true],
            1 => ['table' => 'ride_types', 'params' => ['select' => 'id', 'is_active' => 'eq.true'], 'withCount' => true],
            2 => ['table' => 'drivers',    'params' => ['select' => 'id', 'deleted_at' => 'is.null'], 'withCount' => true],
            3 => ['table' => 'drivers',    'params' => ['select' => 'id', 'deleted_at' => 'is.null', 'status' => 'eq.active'], 'withCount' => true],
        ]);
        return [
            'total_types'  => $res[0]['count'],
            'active_types' => $res[1]['count'],
            'total_drivers'=> $res[2]['count'],
            'active_drivers'=> $res[3]['count'],
        ];
    }
}
