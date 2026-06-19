<?php

class RidesModel {
    public function __construct(private SupabaseDB $db) {}

    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array {
        $params = [
            'select' => 'id,status,fare_eur,final_fare,created_at,updated_at,pickup_addr,dest_addr,pickup_lat,pickup_lng,dest_lat,dest_lng,user_id,driver_id,notes,cancelled_by:canceled_by,distance_km,duration_min',
            'order'  => 'created_at.desc',
            'limit'  => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $this->applyFilters($params, $filters);
        $rows = $this->db->select('rides', $params);
        return $this->joinNames($rows);
    }

    public function count(array $filters = []): int {
        $params = ['select' => 'id'];
        $this->applyFilters($params, $filters);
        return $this->db->select('rides', $params, true)['count'];
    }

    public function getById(string $id): ?array {
        $rows = $this->db->select('rides', [
            'select' => 'id,status,fare_eur,final_fare,created_at,pickup_addr,dest_addr,pickup_lat,pickup_lng,dest_lat,dest_lng,user_id,driver_id,notes,cancelled_by:canceled_by,distance_km,duration_min',
            'id'     => 'eq.' . $id,
            'limit'  => 1,
        ]);
        if (empty($rows)) return null;
        return $this->joinNames($rows)[0];
    }

    /**
     * Count rides by status using parallel count-only queries (no row data fetched).
     * Mirrors the active table filters so tab badge counts always match the table.
     */
    public function getStatusCounts(array $tableFilters = []): array {
        // Base params: count only, apply same non-status filters as the table
        $base = ['select' => 'id'];
        if (!empty($tableFilters['search'])) {
            $base['pickup_addr'] = 'ilike.*' . $tableFilters['search'] . '*';
        }
        if (!empty($tableFilters['date_from'])) {
            $base['created_at'][] = 'gte.' . $tableFilters['date_from'] . 'T00:00:00' . date('P');
        }
        if (!empty($tableFilters['date_to'])) {
            $base['created_at'][] = 'lte.' . $tableFilters['date_to'] . 'T23:59:59' . date('P');
        }

        $statuses = ['searching', 'assigned', 'enroute', 'completed', 'cancelled', 'scheduled'];

        // Fire total + one count per status — all in parallel, zero row data
        $queries   = [['table' => 'rides', 'params' => $base, 'withCount' => true]];
        foreach ($statuses as $s) {
            $queries[] = ['table' => 'rides', 'params' => [...$base, 'status' => "eq.{$s}"], 'withCount' => true];
        }

        $res    = $this->db->selectParallel($queries);
        $counts = ['total' => $res[0]['count']];
        foreach ($statuses as $i => $s) {
            $counts[$s] = $res[$i + 1]['count'];
        }
        $counts['live'] = $counts['enroute'];

        return $counts;
    }

    /**
     * Load all ride page data in one parallel batch (list + counts), then join names.
     */
    public function loadPageData(array $filters, int $page, int $perPage): array {
        $listParams = [
            'select' => 'id,status,fare_eur,final_fare,created_at,updated_at,pickup_addr,dest_addr,pickup_lat,pickup_lng,dest_lat,dest_lng,user_id,driver_id,notes,cancelled_by:canceled_by,distance_km,duration_min',
            'order'  => 'created_at.desc',
            'limit'  => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
        $this->applyFilters($listParams, $filters);

        $countParams = ['select' => 'id'];
        $this->applyFilters($countParams, $filters);

        // Base for status counts: date/search only — no status filter
        $base = ['select' => 'id'];
        if (!empty($filters['search'])) {
            $base['pickup_addr'] = 'ilike.*' . $filters['search'] . '*';
        }
        if (!empty($filters['date_from'])) {
            $base['created_at'][] = 'gte.' . $filters['date_from'] . 'T00:00:00' . date('P');
        }
        if (!empty($filters['date_to'])) {
            $base['created_at'][] = 'lte.' . $filters['date_to'] . 'T23:59:59' . date('P');
        }

        $statuses = ['searching', 'assigned', 'enroute', 'completed', 'cancelled', 'scheduled'];

        $queries = [
            0 => ['table' => 'rides', 'params' => $listParams],
            1 => ['table' => 'rides', 'params' => $countParams, 'withCount' => true],
            2 => ['table' => 'rides', 'params' => $base, 'withCount' => true],
        ];
        foreach ($statuses as $i => $s) {
            $queries[3 + $i] = ['table' => 'rides', 'params' => [...$base, 'status' => "eq.{$s}"], 'withCount' => true];
        }

        $res  = $this->db->selectParallel($queries);
        $rows = $this->joinNames($res[0]);

        $counts = ['total' => $res[2]['count']];
        foreach ($statuses as $i => $s) {
            $counts[$s] = $res[3 + $i]['count'];
        }
        $counts['live'] = $counts['enroute'];

        return [
            'rides'  => $rows,
            'total'  => $res[1]['count'],
            'counts' => $counts,
        ];
    }

    public function cancelRide(string $id): bool {
        return $this->db->update('rides', [
            'status'     => 'cancelled',
            'updated_at' => date('c'),
        ], ['id' => 'eq.' . $id]);
    }

    public function updateStatus(string $id, string $status): bool {
        $allowed = ['searching', 'assigned', 'enroute', 'completed', 'cancelled', 'scheduled'];
        if (!in_array($status, $allowed, true)) return false;

        return $this->db->update('rides', [
            'status'     => $status,
            'updated_at' => date('c'),
        ], ['id' => 'eq.' . $id]);
    }

    public function getDriverLocation(string $driverId): ?array {
        $rows = $this->db->select('drivers', [
            'select' => 'id,current_lat,current_lng',
            'id'     => 'eq.' . $driverId,
            'limit'  => 1,
        ]);
        return $rows[0] ?? null;
    }

    public function getDailyRevenue(int $days = 30): array {
        $offset = date('P');
        $start  = date('Y-m-d', strtotime("-{$days} days")) . 'T00:00:00' . $offset;
        $end    = date('Y-m-d', strtotime('+1 day'))        . 'T00:00:00' . $offset;

        $rows = $this->db->select('rides', [
            'select'     => 'created_at,fare_eur,final_fare',
            'status'     => 'eq.completed',
            'created_at' => ["gte.{$start}", "lt.{$end}"],
            'order'      => 'created_at.asc',
        ]);

        $daily = [];
        foreach ($rows as $r) {
            $day = substr($r['created_at'], 0, 10);
            $daily[$day] = ($daily[$day] ?? 0) + (float)($r['final_fare'] ?? $r['fare_eur'] ?? 0);
        }
        return $daily;
    }

    public function getDailyRideCount(int $days = 30): array {
        $offset = date('P');
        $start  = date('Y-m-d', strtotime("-{$days} days")) . 'T00:00:00' . $offset;
        $end    = date('Y-m-d', strtotime('+1 day'))        . 'T00:00:00' . $offset;

        $rows = $this->db->select('rides', [
            'select'     => 'created_at',
            'created_at' => ["gte.{$start}", "lt.{$end}"],
            'order'      => 'created_at.asc',
        ]);

        $daily = [];
        foreach ($rows as $r) {
            $day = substr($r['created_at'], 0, 10);
            $daily[$day] = ($daily[$day] ?? 0) + 1;
        }
        return $daily;
    }

    private function applyFilters(array &$params, array $filters): void {
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'live') {
                // live = enroute (driver actively driving with passenger)
                $params['status'] = 'eq.enroute';
            } else {
                $params['status'] = 'eq.' . $filters['status'];
            }
        }

        if (!empty($filters['date_from'])) {
            $params['created_at'][] = 'gte.' . $filters['date_from'] . 'T00:00:00' . date('P');
        }
        if (!empty($filters['date_to'])) {
            $params['created_at'][] = 'lte.' . $filters['date_to'] . 'T23:59:59' . date('P');
        }

        if (!empty($filters['search'])) {
            $params['pickup_addr'] = 'ilike.*' . $filters['search'] . '*';
        }
    }

    private function joinNames(array $rows): array {
        if (empty($rows)) return [];

        $driverIds = array_values(array_unique(array_filter(array_column($rows, 'driver_id'))));
        $userIds   = array_values(array_unique(array_filter(array_column($rows, 'user_id'))));

        // Fetch drivers and passengers in parallel (two HTTP calls at once)
        $lookups = [];
        if (!empty($driverIds)) $lookups['d'] = ['table'=>'drivers',    'params'=>['select'=>'id,full_name,phone,email,current_lat,current_lng','id'=>'in.('.implode(',', $driverIds).')']];
        if (!empty($userIds))   $lookups['p'] = ['table'=>'passengers', 'params'=>['select'=>'id,name,phone,email','id'=>'in.('.implode(',', $userIds).')']];

        $driverMap    = [];
        $passengerMap = [];

        if (!empty($lookups)) {
            $fetched = $this->db->selectParallel(array_values($lookups));
            foreach (array_keys($lookups) as $idx => $key) {
                foreach ($fetched[$idx] as $row) {
                    if ($key === 'd') $driverMap[$row['id']]    = $row;
                    if ($key === 'p') $passengerMap[$row['id']] = $row;
                }
            }
        }

        return array_map(function ($r) use ($driverMap, $passengerMap) {
            $driver    = isset($r['driver_id']) ? ($driverMap[$r['driver_id']] ?? null) : null;
            $passenger = isset($r['user_id']) ? ($passengerMap[$r['user_id']] ?? null) : null;
            return [
                ...$r,
                'fare'            => $r['final_fare'] ?? $r['fare_eur'],
                'driver_name'     => $driver['full_name'] ?? null,
                'driver_phone'    => $driver['phone'] ?? null,
                'driver_email'    => $driver['email'] ?? null,
                'driver_lat'      => $driver['current_lat'] ?? null,
                'driver_lng'      => $driver['current_lng'] ?? null,
                'passenger_name'  => $passenger['name'] ?? null,
                'passenger_phone' => $passenger['phone'] ?? null,
                'passenger_email' => $passenger['email'] ?? null,
            ];
        }, $rows);
    }
}
