<?php

class AnalyticsModel {
    public function __construct(private SupabaseDB $db) {}

    public function getMtdStats(): array {
        $offset     = date('P');
        $monthStart = date('Y-m-01') . 'T00:00:00' . $offset;
        $tomorrow   = date('Y-m-d', strtotime('+1 day')) . 'T00:00:00' . $offset;

        // MTD rides count
        $r = $this->db->select('rides', [
            'select'     => 'id',
            'created_at' => ["gte.{$monthStart}", "lt.{$tomorrow}"],
        ], true);
        $totalRides = $r['count'];

        // MTD revenue
        $rows = $this->db->select('rides', [
            'select'     => 'fare_eur,final_fare',
            'status'     => 'eq.completed',
            'created_at' => ["gte.{$monthStart}", "lt.{$tomorrow}"],
        ]);
        $revenue = array_sum(array_map(
            fn($r) => (float)($r['final_fare'] ?? $r['fare_eur'] ?? 0),
            $rows
        ));

        // MTD new passengers
        $r = $this->db->select('passengers', [
            'select'     => 'id',
            'created_at' => ["gte.{$monthStart}", "lt.{$tomorrow}"],
            'deleted_at' => 'is.null',
        ], true);
        $newPassengers = $r['count'];

        // Avg driver rating — fetch from drivers table if available
        $dRows = $this->db->select('drivers', [
            'select'     => 'rating',
            'or'         => '(status.eq.active,status.eq.approved)',
            'deleted_at' => 'is.null',
        ]);
        $ratings = array_filter(array_column($dRows, 'rating'));
        $avgRating = !empty($ratings) ? round(array_sum($ratings) / count($ratings), 1) : null;

        return compact('totalRides', 'revenue', 'newPassengers', 'avgRating');
    }

    public function getDailyRevenue(int $days = 30): array {
        $offset = date('P');
        $start  = date('Y-m-d', strtotime("-{$days} days")) . 'T00:00:00' . $offset;
        $end    = date('Y-m-d', strtotime('+1 day'))        . 'T00:00:00' . $offset;

        $rows = $this->db->select('rides', [
            'select'     => 'created_at,fare_eur,final_fare',
            'status'     => 'eq.completed',
            'created_at' => ["gte.{$start}", "lt.{$end}"],
        ]);

        // Build full date range
        $daily = [];
        for ($i = $days; $i >= 0; $i--) {
            $daily[date('Y-m-d', strtotime("-{$i} days"))] = 0;
        }
        foreach ($rows as $r) {
            $day = substr($r['created_at'], 0, 10);
            if (isset($daily[$day])) {
                $daily[$day] += (float)($r['final_fare'] ?? $r['fare_eur'] ?? 0);
            }
        }
        return $daily;
    }

    public function getDailyRides(int $days = 30): array {
        $offset = date('P');
        $start  = date('Y-m-d', strtotime("-{$days} days")) . 'T00:00:00' . $offset;
        $end    = date('Y-m-d', strtotime('+1 day'))        . 'T00:00:00' . $offset;

        $rows = $this->db->select('rides', [
            'select'     => 'created_at',
            'created_at' => ["gte.{$start}", "lt.{$end}"],
        ]);

        $daily = [];
        for ($i = $days; $i >= 0; $i--) {
            $daily[date('Y-m-d', strtotime("-{$i} days"))] = 0;
        }
        foreach ($rows as $r) {
            $day = substr($r['created_at'], 0, 10);
            if (isset($daily[$day])) $daily[$day]++;
        }
        return $daily;
    }

    public function getHourlyDistribution(): array {
        $start = date('Y-m-d', strtotime('-30 days')) . 'T00:00:00' . date('P');

        $rows = $this->db->select('rides', [
            'select'     => 'created_at',
            'created_at' => 'gte.' . $start,
        ]);

        $hourly = array_fill(0, 24, 0);
        foreach ($rows as $r) {
            $hour = (int)date('G', strtotime($r['created_at']));
            $hourly[$hour]++;
        }
        return $hourly;
    }

    public function getDayOfWeekDistribution(): array {
        $start = date('Y-m-d', strtotime('-30 days')) . 'T00:00:00' . date('P');

        $rows = $this->db->select('rides', [
            'select'     => 'created_at',
            'created_at' => 'gte.' . $start,
        ]);

        $days = ['Mon'=>0,'Tue'=>0,'Wed'=>0,'Thu'=>0,'Fri'=>0,'Sat'=>0,'Sun'=>0];
        $dayMap = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',0=>'Sun'];
        foreach ($rows as $r) {
            $dow = (int)date('w', strtotime($r['created_at']));
            $days[$dayMap[$dow]]++;
        }
        return $days;
    }

    public function getTopDrivers(int $limit = 10): array {
        return $this->db->select('admin_driver_stats', [
            'select'     => 'id,full_name,total_rides,total_earnings,status',
            'deleted_at' => 'is.null',
            'order'      => 'total_rides.desc',
            'limit'      => $limit,
        ]);
    }

    public function getRevenueByStatus(): array {
        $rows = $this->db->select('rides', [
            'select' => 'status,fare_eur,final_fare',
        ]);

        $byStatus = [];
        foreach ($rows as $r) {
            $s = $r['status'] ?? 'unknown';
            $byStatus[$s] = ($byStatus[$s] ?? 0) + (float)($r['final_fare'] ?? $r['fare_eur'] ?? 0);
        }
        return $byStatus;
    }
}
