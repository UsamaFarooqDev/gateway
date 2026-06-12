<?php

class AlertsModel {
    public function __construct(private SupabaseDB $db) {}

    public function getAllAlerts(): array {
        $today   = date('Y-m-d') . 'T00:00:00' . date('P');
        $in7days = date('Y-m-d', strtotime('+7 days')) . 'T23:59:59' . date('P');
        $ago24h  = date('c', strtotime('-24 hours'));
        $ago10m  = date('c', strtotime('-10 minutes'));

        $res = $this->db->selectParallel([
            // 0: Drivers with license expiring within 7 days
            ['table' => 'drivers', 'params' => [
                'select'         => 'id,full_name,license_expiry,status',
                'license_expiry' => ["gte.{$today}", "lte.{$in7days}"],
                'deleted_at'     => 'is.null',
                'order'          => 'license_expiry.asc',
                'limit'          => 50,
            ]],
            // 1: Drivers pending approval for >24 hours
            ['table' => 'drivers', 'params' => [
                'select'     => 'id,full_name,created_at',
                'status'     => 'eq.pending',
                'created_at' => "lte.{$ago24h}",
                'deleted_at' => 'is.null',
                'order'      => 'created_at.asc',
                'limit'      => 50,
            ]],
            // 2: Rides stuck searching for >10 minutes
            ['table' => 'rides', 'params' => [
                'select'     => 'id,created_at,pickup_addr,user_id',
                'status'     => 'eq.searching',
                'created_at' => "lte.{$ago10m}",
                'order'      => 'created_at.asc',
                'limit'      => 50,
            ]],
        ]);

        return [
            'license_expiring' => $res[0],
            'pending_drivers'  => $res[1],
            'stale_searching'  => $res[2],
        ];
    }
}
