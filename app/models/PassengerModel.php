<?php

class PassengerModel {
    public function __construct(private SupabaseDB $db) {}

    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array {
        $params = [
            'select'     => 'id,name,email,phone,photo_url,status,created_at,is_email_verified,total_rides,total_spent,avg_rating',
            'deleted_at' => 'is.null',
            'order'      => 'created_at.desc',
            'limit'      => $perPage,
            'offset'     => ($page - 1) * $perPage,
        ];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $params['status'] = 'eq.' . $filters['status'];
        }

        if (!empty($filters['search'])) {
            $params['name'] = 'ilike.*' . $filters['search'] . '*';
        }

        return $this->db->select('admin_passenger_stats', $params);
    }

    public function count(array $filters = []): int {
        $params = [
            'select'     => 'id',
            'deleted_at' => 'is.null',
        ];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $params['status'] = 'eq.' . $filters['status'];
        }

        if (!empty($filters['search'])) {
            $params['name'] = 'ilike.*' . $filters['search'] . '*';
        }

        return $this->db->select('admin_passenger_stats', $params, true)['count'];
    }

    public function getById(string $id): ?array {
        $rows = $this->db->select('admin_passenger_stats', [
            'id'         => 'eq.' . $id,
            'deleted_at' => 'is.null',
            'limit'      => 1,
        ]);
        return $rows[0] ?? null;
    }

    public function updateStatus(string $id, string $status): bool {
        $allowed = ['active', 'suspended'];
        if (!in_array($status, $allowed, true)) return false;

        return $this->db->update(
            'passengers',
            ['status' => $status, 'updated_at' => date('c')],
            ['id' => 'eq.' . $id]
        );
    }

    /**
     * Load all passenger page data in one parallel batch.
     */
    public function loadPageData(array $filters, int $page, int $perPage): array {
        $listParams  = [
            'select'     => 'id,name,email,phone,photo_url,status,created_at,is_email_verified,total_rides,total_spent,avg_rating',
            'deleted_at' => 'is.null',
            'order'      => 'created_at.desc',
            'limit'      => $perPage,
            'offset'     => ($page - 1) * $perPage,
        ];
        $countParams = ['select' => 'id', 'deleted_at' => 'is.null'];

        foreach ([$listParams, $countParams] as &$p) {
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $p['status'] = 'eq.' . $filters['status'];
            }
            if (!empty($filters['search'])) {
                $p['name'] = 'ilike.*' . $filters['search'] . '*';
            }
        }
        unset($p);

        $offset = date('P');
        $today  = date('Y-m-d') . 'T00:00:00' . $offset;

        $res = $this->db->selectParallel([
            0 => ['table' => 'admin_passenger_stats', 'params' => $listParams],
            1 => ['table' => 'admin_passenger_stats', 'params' => $countParams, 'withCount' => true],
            2 => ['table' => 'passengers', 'params' => ['select' => 'id', 'deleted_at' => 'is.null'], 'withCount' => true],
            3 => ['table' => 'passengers', 'params' => ['select' => 'id', 'status' => 'eq.active',    'deleted_at' => 'is.null'], 'withCount' => true],
            4 => ['table' => 'passengers', 'params' => ['select' => 'id', 'status' => 'eq.suspended', 'deleted_at' => 'is.null'], 'withCount' => true],
            5 => ['table' => 'passengers', 'params' => ['select' => 'id', 'created_at' => "gte.{$today}", 'deleted_at' => 'is.null'], 'withCount' => true],
        ]);

        return [
            'passengers' => $res[0],
            'total'      => $res[1]['count'],
            'counts'     => [
                'total'     => $res[2]['count'],
                'active'    => $res[3]['count'],
                'suspended' => $res[4]['count'],
                'new_today' => $res[5]['count'],
            ],
        ];
    }

    public function getStatusCounts(): array {
        $offset = date('P');
        $today  = date('Y-m-d') . 'T00:00:00' . $offset;
        $base   = ['select' => 'id', 'deleted_at' => 'is.null'];

        $res = $this->db->selectParallel([
            ['table' => 'passengers', 'params' => $base, 'withCount' => true],
            ['table' => 'passengers', 'params' => [...$base, 'status' => 'eq.active'],    'withCount' => true],
            ['table' => 'passengers', 'params' => [...$base, 'status' => 'eq.suspended'], 'withCount' => true],
            ['table' => 'passengers', 'params' => ['select' => 'id', 'created_at' => "gte.{$today}", 'deleted_at' => 'is.null'], 'withCount' => true],
        ]);

        return [
            'total'     => $res[0]['count'],
            'active'    => $res[1]['count'],
            'suspended' => $res[2]['count'],
            'new_today' => $res[3]['count'],
        ];
    }

    public function getRecentRides(string $passengerId, int $limit = 5): array {
        $rows = $this->db->select('rides', [
            'select'  => 'id,status,fare_eur,final_fare,created_at,pickup_addr,dest_addr,driver_id',
            'user_id' => 'eq.' . $passengerId,
            'order'   => 'created_at.desc',
            'limit'   => $limit,
        ]);

        // Fetch driver names
        $driverIds = array_values(array_unique(array_filter(array_column($rows, 'driver_id'))));
        $dMap      = [];
        if (!empty($driverIds)) {
            $dRows = $this->db->select('drivers', [
                'select' => 'id,full_name',
                'id'     => 'in.(' . implode(',', $driverIds) . ')',
            ]);
            foreach ($dRows as $d) $dMap[$d['id']] = $d['full_name'];
        }

        return array_map(fn($r) => [
            ...$r,
            'fare'        => $r['final_fare'] ?? $r['fare_eur'],
            'driver_name' => $dMap[$r['driver_id']] ?? null,
        ], $rows);
    }
}
