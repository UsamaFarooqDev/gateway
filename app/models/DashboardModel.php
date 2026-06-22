<?php

class DashboardModel {
    public function __construct(private SupabaseDB $db) {}

    /**
     * Load all dashboard data in 2 network round-trips:
     *   Round 1 — 22 parallel queries (stats + driver counts + weekly + ride status + recent + alerts)
     *   Round 2 — parallel name join (drivers + passengers for recent rides table)
     */
    public function getAllData(): array {
        $offset    = date('P');
        $today     = date('Y-m-d') . 'T00:00:00' . $offset;
        $tomorrow  = date('Y-m-d', strtotime('+1 day')) . 'T00:00:00' . $offset;
        $yesterday = date('Y-m-d', strtotime('-1 day')) . 'T00:00:00' . $offset;
        $weekStart = date('Y-m-d', strtotime('-6 days')) . 'T00:00:00' . $offset;
        $in7days   = date('Y-m-d', strtotime('+7 days')) . 'T23:59:59' . $offset;

        // ── Round 1: fire everything at once ────────────────────────────────
        $res = $this->db->selectParallel([
            //  0  rides today count
            ['table'=>'rides',      'params'=>['select'=>'id','created_at'=>["gte.{$today}","lt.{$tomorrow}"]], 'withCount'=>true],
            //  1  rides yesterday count
            ['table'=>'rides',      'params'=>['select'=>'id','created_at'=>["gte.{$yesterday}","lt.{$today}"]], 'withCount'=>true],
            //  2  online active drivers
            ['table'=>'drivers',    'params'=>['select'=>'id','is_online'=>'is.true','status'=>'eq.active',   'deleted_at'=>'is.null'], 'withCount'=>true],
            //  3  online approved drivers
            ['table'=>'drivers',    'params'=>['select'=>'id','is_online'=>'is.true','status'=>'eq.approved', 'deleted_at'=>'is.null'], 'withCount'=>true],
            //  4  total active drivers
            ['table'=>'drivers',    'params'=>['select'=>'id','status'=>'eq.active',   'deleted_at'=>'is.null'], 'withCount'=>true],
            //  5  total approved drivers
            ['table'=>'drivers',    'params'=>['select'=>'id','status'=>'eq.approved', 'deleted_at'=>'is.null'], 'withCount'=>true],
            //  6  total passengers
            ['table'=>'passengers', 'params'=>['select'=>'id','status'=>'eq.active','deleted_at'=>'is.null'], 'withCount'=>true],
            //  7  new passengers today
            ['table'=>'passengers', 'params'=>['select'=>'id','created_at'=>["gte.{$today}","lt.{$tomorrow}"],'deleted_at'=>'is.null'], 'withCount'=>true],
            //  8  revenue today (fare rows)
            ['table'=>'rides',      'params'=>['select'=>'fare_eur,final_fare','status'=>'eq.completed','created_at'=>["gte.{$today}","lt.{$tomorrow}"]]],
            //  9  revenue yesterday
            ['table'=>'rides',      'params'=>['select'=>'fare_eur,final_fare','status'=>'eq.completed','created_at'=>["gte.{$yesterday}","lt.{$today}"]]],
            // 10  pending driver applications count
            ['table'=>'drivers',    'params'=>['select'=>'id','status'=>'eq.pending','deleted_at'=>'is.null'], 'withCount'=>true],
            // 11  driver donut: online+active
            ['table'=>'drivers',    'params'=>['select'=>'id','is_online'=>'is.true', 'status'=>'eq.active',   'deleted_at'=>'is.null'], 'withCount'=>true],
            // 12  driver donut: online+approved
            ['table'=>'drivers',    'params'=>['select'=>'id','is_online'=>'is.true', 'status'=>'eq.approved', 'deleted_at'=>'is.null'], 'withCount'=>true],
            // 13  driver donut: offline+active
            ['table'=>'drivers',    'params'=>['select'=>'id','is_online'=>'is.false','status'=>'eq.active',   'deleted_at'=>'is.null'], 'withCount'=>true],
            // 14  driver donut: offline+approved
            ['table'=>'drivers',    'params'=>['select'=>'id','is_online'=>'is.false','status'=>'eq.approved', 'deleted_at'=>'is.null'], 'withCount'=>true],
            // 15  driver donut: pending
            ['table'=>'drivers',    'params'=>['select'=>'id','status'=>'eq.pending',   'deleted_at'=>'is.null'], 'withCount'=>true],
            // 16  driver donut: suspended
            ['table'=>'drivers',    'params'=>['select'=>'id','status'=>'eq.suspended', 'deleted_at'=>'is.null'], 'withCount'=>true],
            // 17  weekly rides: created_at + status for by-status line chart
            ['table'=>'rides',      'params'=>['select'=>'created_at,status','created_at'=>["gte.{$weekStart}","lt.{$tomorrow}"]]],
            // 18  today ride statuses
            ['table'=>'rides',      'params'=>['select'=>'status','created_at'=>["gte.{$today}","lt.{$tomorrow}"]]],
            // 19  recent 10 rides
            ['table'=>'rides',      'params'=>['select'=>'id,status,fare_eur,final_fare,created_at,updated_at,pickup_addr,dest_addr,user_id,driver_id','order'=>'created_at.desc','limit'=>10]],
            // 20  drivers with license expiring or already expired (up to today+7)
            ['table'=>'drivers',    'params'=>['select'=>'id,full_name,license_expiry','license_expiry'=>['not.is.null',"lte.{$in7days}"],'deleted_at'=>'is.null','order'=>'license_expiry.asc','limit'=>10]],
            // 21  pending drivers list for dashboard alerts
            ['table'=>'drivers',    'params'=>['select'=>'id,full_name,created_at','status'=>'eq.pending','deleted_at'=>'is.null','order'=>'created_at.asc','limit'=>10]],
        ]);

        // ── Process stats ─────────────────────────────────────────────────
        $sumFare = fn($rows) => array_sum(array_map(
            fn($r) => (float)($r['final_fare'] ?? $r['fare_eur'] ?? 0), $rows
        ));

        $stats = [
            'rides_today'          => $res[0]['count'],
            'rides_yesterday'      => $res[1]['count'],
            'active_drivers'       => $res[2]['count'] + $res[3]['count'],
            'total_drivers'        => $res[4]['count'] + $res[5]['count'],
            'total_passengers'     => $res[6]['count'],
            'new_passengers_today' => $res[7]['count'],
            'revenue_today'        => $sumFare($res[8]),
            'revenue_yesterday'    => $sumFare($res[9]),
            'pending_drivers'      => $res[10]['count'],
        ];

        $driverCounts = [
            'online'    => $res[11]['count'] + $res[12]['count'],
            'offline'   => $res[13]['count'] + $res[14]['count'],
            'pending'   => $res[15]['count'],
            'suspended' => $res[16]['count'],
        ];

        // Weekly rides grouped by date AND by status for multi-line chart
        $weeklyStatuses = ['searching', 'assigned', 'enroute', 'completed', 'cancelled', 'scheduled'];
        $weeklyRides    = [];
        $weeklyByStatus = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $weeklyRides[$day]    = 0;
            $weeklyByStatus[$day] = array_fill_keys($weeklyStatuses, 0);
        }
        foreach ($res[17] as $row) {
            $day = substr($row['created_at'], 0, 10);
            if (!isset($weeklyRides[$day])) continue;
            $weeklyRides[$day]++;
            $s = $row['status'] ?? '';
            if (in_array($s, $weeklyStatuses, true)) $weeklyByStatus[$day][$s]++;
        }

        // Ride status counts for today
        $rideCounts = ['searching'=>0,'assigned'=>0,'enroute'=>0,'scheduled'=>0,'completed'=>0,'cancelled'=>0];
        foreach ($res[18] as $row) {
            if (array_key_exists($row['status'], $rideCounts)) $rideCounts[$row['status']]++;
        }

        // Dashboard alerts
        $dashboardAlerts = [
            'license_expiring' => $res[20],
            'pending_drivers'  => $res[21],
        ];

        // ── Round 2: parallel name join for recent rides ──────────────────
        $rideRows  = $res[19];
        $driverIds = array_values(array_unique(array_filter(array_column($rideRows, 'driver_id'))));
        $userIds   = array_values(array_unique(array_filter(array_column($rideRows, 'user_id'))));

        $lookups = [];
        if (!empty($driverIds)) $lookups['d'] = ['table'=>'drivers',    'params'=>['select'=>'id,full_name','id'=>'in.('.implode(',', $driverIds).')']];
        if (!empty($userIds))   $lookups['p'] = ['table'=>'passengers', 'params'=>['select'=>'id,name',    'id'=>'in.('.implode(',', $userIds).')']];

        $driverMap = $passengerMap = [];
        if (!empty($lookups)) {
            $names = $this->db->selectParallel(array_values($lookups));
            foreach (array_keys($lookups) as $idx => $key) {
                foreach ($names[$idx] as $row) {
                    if ($key === 'd') $driverMap[$row['id']]    = $row['full_name'];
                    if ($key === 'p') $passengerMap[$row['id']] = $row['name'];
                }
            }
        }

        $recentRides = array_map(fn($r) => [
            'id'             => $r['id'],
            'status'         => $r['status'],
            'fare'           => $r['final_fare'] ?? $r['fare_eur'],
            'created_at'     => $r['created_at'],
            'updated_at'     => $r['updated_at'] ?? null,
            'pickup_addr'    => $r['pickup_addr'],
            'dest_addr'      => $r['dest_addr'],
            'driver_name'    => isset($r['driver_id']) ? ($driverMap[$r['driver_id']] ?? null) : null,
            'passenger_name' => isset($r['user_id']) ? ($passengerMap[$r['user_id']] ?? null) : null,
        ], $rideRows);

        return compact('stats', 'driverCounts', 'weeklyRides', 'weeklyByStatus', 'rideCounts', 'recentRides', 'dashboardAlerts');
    }
}
