<?php

class FinanceModel {
    private const COMMISSION_PCT = 10.0;
    private const STATS_SCAN_LIMIT = 5000;

    public function __construct(private SupabaseDB $db) {}

    public function getDriversForFilter(): array {
        return $this->db->select('drivers', [
            'select'     => 'id,full_name',
            'deleted_at' => 'is.null',
            'order'      => 'full_name.asc',
            'limit'      => 1000,
        ]);
    }

    public function getPassengersForFilter(): array {
        return $this->db->select('passengers', [
            'select'     => 'id,name',
            'deleted_at' => 'is.null',
            'order'      => 'name.asc',
            'limit'      => 1000,
        ]);
    }

    // ── Revenue Overview ────────────────────────────────────────────

    public function getKpis(): array {
        $now        = date('Y-m-01');
        $todayStart = date('Y-m-d');
        $offset     = date('P');

        $res = $this->db->selectParallel([
            0 => ['table' => 'rides', 'params' => ['select' => 'fare_eur,final_fare', 'status' => 'eq.completed', 'limit' => self::STATS_SCAN_LIMIT]],
            1 => ['table' => 'rides', 'params' => ['select' => 'fare_eur,final_fare', 'status' => 'eq.completed', 'created_at' => 'gte.' . $now . 'T00:00:00' . $offset, 'limit' => self::STATS_SCAN_LIMIT]],
            2 => ['table' => 'rides', 'params' => ['select' => 'id', 'status' => 'eq.completed', 'created_at' => 'gte.' . $todayStart . 'T00:00:00' . $offset], 'withCount' => true],
            3 => ['table' => 'pricing_config', 'params' => ['select' => 'driver_commission_pct', 'is_active' => 'eq.true']],
        ]);

        $sum = fn($rows) => array_sum(array_map(fn($r) => (float)($r['final_fare'] ?? $r['fare_eur'] ?? 0), $rows));

        $totalRevenue = $sum($res[0]);
        $mtdRevenue   = $sum($res[1]);

        $pcts          = array_column($res[3], 'driver_commission_pct');
        $avgCommission = $pcts ? array_sum($pcts) / count($pcts) : self::COMMISSION_PCT;

        return [
            'total_revenue'       => round($totalRevenue, 2),
            'revenue_mtd'         => round($mtdRevenue, 2),
            'driver_earnings_mtd' => round($mtdRevenue * (100 - $avgCommission) / 100, 2),
            'avg_commission_pct'  => round($avgCommission, 1),
            'transactions_today'  => $res[2]['count'],
        ];
    }

    public function getDailyRevenueSeries(int $days = 30): array {
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

    // ── Driver Payouts (wallet ledger) ──────────────────────────────

    public function getDriverWallets(string $search = ''): array {
        $params = [
            'select'     => 'id,full_name,email,phone',
            'deleted_at' => 'is.null',
            'order'      => 'full_name.asc',
            'limit'      => 500,
        ];
        if ($search !== '') {
            $safe                = str_replace(['(', ')', ',', '*'], '', $search);
            $params['full_name'] = 'ilike.*' . $safe . '*';
        }

        $drivers = $this->db->select('drivers', $params);
        if (empty($drivers)) return [];

        $ids     = array_column($drivers, 'id');
        $wallets = $this->db->select('driver_wallet', [
            'select'    => 'driver_id,balance,total_topped,total_deducted,updated_at',
            'driver_id' => 'in.(' . implode(',', $ids) . ')',
        ]);

        $walletMap = [];
        foreach ($wallets as $w) $walletMap[$w['driver_id']] = $w;

        return array_map(function ($d) use ($walletMap) {
            $w = $walletMap[$d['id']] ?? ['balance' => 0, 'total_topped' => 0, 'total_deducted' => 0, 'updated_at' => null];
            return [
                'id'             => $d['id'],
                'full_name'      => $d['full_name'],
                'email'          => $d['email'],
                'phone'          => $d['phone'],
                'balance'        => (float)$w['balance'],
                'total_topped'   => (float)$w['total_topped'],
                'total_deducted' => (float)$w['total_deducted'],
                'updated_at'     => $w['updated_at'],
            ];
        }, $drivers);
    }

    // ── Refunds & Adjustments (real wallet credits) ─────────────────

    public function issueDriverCredit(string $driverId, float $amount, string $reason): bool {
        $res = $this->db->rpcWithStatus('credit_driver_wallet', [
            'p_driver_id'   => $driverId,
            'p_amount'      => $amount,
            'p_payment_id'  => 'admin-' . uniqid(),
            'p_description' => $reason,
        ]);
        return $res['code'] >= 200 && $res['code'] < 300;
    }

    public function issuePassengerCredit(string $passengerId, float $amount, string $reason): bool {
        $res = $this->db->rpcWithStatus('credit_wallet', [
            'p_passenger_id' => $passengerId,
            'p_amount'       => $amount,
            'p_description'  => $reason,
        ]);
        return $res['code'] >= 200 && $res['code'] < 300;
    }

    // ── Payment Transactions ─────────────────────────────────────────

    public function getTransactions(array $filters, int $page, int $perPage): array {
        $params            = $this->buildTransactionParams($filters);
        $params['select']  = 'id,status,fare_eur,final_fare,total_charged,payment_method,stripe_payment_intent_id,stripe_charge_status,charged_at,created_at,user_id';
        $params['order']   = 'created_at.desc';
        $params['limit']   = $perPage;
        $params['offset']  = ($page - 1) * $perPage;

        return $this->joinPassengerNames($this->db->select('rides', $params));
    }

    public function countTransactions(array $filters): int {
        $params           = $this->buildTransactionParams($filters);
        $params['select'] = 'id';
        return $this->db->select('rides', $params, true)['count'];
    }

    private function buildTransactionParams(array $filters): array {
        $params = ['status' => 'eq.completed'];

        if (!empty($filters['date_from'])) $params['created_at'][] = 'gte.' . $filters['date_from'] . 'T00:00:00' . date('P');
        if (!empty($filters['date_to']))   $params['created_at'][] = 'lte.' . $filters['date_to']   . 'T23:59:59' . date('P');
        if (!empty($filters['method']) && $filters['method'] !== 'all') {
            $params['payment_method'] = 'ilike.' . $filters['method'];
        }
        if (!empty($filters['search'])) {
            $safe         = str_replace(['(', ')', ',', '*'], '', $filters['search']);
            $params['or'] = "(pickup_addr.ilike.*{$safe}*,dest_addr.ilike.*{$safe}*)";
        }

        return $params;
    }

    private function joinPassengerNames(array $rows): array {
        if (empty($rows)) return [];

        $userIds = array_values(array_unique(array_filter(array_column($rows, 'user_id'))));
        $map     = [];
        if (!empty($userIds)) {
            $passengers = $this->db->select('passengers', ['select' => 'id,name', 'id' => 'in.(' . implode(',', $userIds) . ')']);
            foreach ($passengers as $p) $map[$p['id']] = $p['name'];
        }

        return array_map(fn($r) => [...$r, 'passenger_name' => $map[$r['user_id']] ?? null], $rows);
    }

    // ── Commission Settings (read-only — edit via Promotions & Pricing) ──

    public function getCommissionSettings(): array {
        return $this->db->select('pricing_config', [
            'select' => 'id,ride_type,time_period,driver_commission_pct,is_active',
            'order'  => 'ride_type.asc,time_period.asc',
        ]);
    }

    // ── Corporate Invoices ───────────────────────────────────────────

    public function getCorporateInvoices(array $filters): array {
        $params = [
            'select' => 'cid,company,fare_eur,final_fare',
            'cid'    => 'not.is.null',
            'status' => 'ilike.completed',
            'limit'  => self::STATS_SCAN_LIMIT,
        ];
        if (!empty($filters['date_from'])) $params['created_at'][] = 'gte.' . $filters['date_from'] . 'T00:00:00' . date('P');
        if (!empty($filters['date_to']))   $params['created_at'][] = 'lte.' . $filters['date_to']   . 'T23:59:59' . date('P');

        $rows = $this->db->select('rides', $params);
        if (empty($rows)) return [];

        $agg = [];
        foreach ($rows as $r) {
            $cid = $r['cid'];
            $agg[$cid] ??= ['cid' => $cid, 'company' => $r['company'], 'rides' => 0, 'total_fare' => 0.0];
            $agg[$cid]['rides']++;
            $agg[$cid]['total_fare'] += (float)($r['final_fare'] ?? $r['fare_eur'] ?? 0);
        }

        $corp = $this->db->select('corporate', [
            'select' => 'cid,name,email,invoice_email',
            'cid'    => 'in.(' . implode(',', array_keys($agg)) . ')',
        ]);
        $corpMap = [];
        foreach ($corp as $c) $corpMap[$c['cid']] = $c;

        $clean = fn($v) => (is_string($v) && strtoupper(trim($v)) === 'NULL') ? null : $v;

        $result = array_map(function ($a) use ($corpMap, $clean) {
            $c = $corpMap[$a['cid']] ?? null;
            return [
                'cid'           => $a['cid'],
                'company'       => $clean($c['name'] ?? null) ?: ($a['company'] ?: $a['cid']),
                'email'         => $clean($c['email'] ?? null),
                'invoice_email' => $clean($c['invoice_email'] ?? null),
                'rides'         => $a['rides'],
                'total_fare'    => round($a['total_fare'], 2),
            ];
        }, array_values($agg));

        usort($result, fn($a, $b) => $b['total_fare'] <=> $a['total_fare']);
        return $result;
    }

    public function getCorporateInvoiceDetail(string $cid, array $filters): array {
        $params = [
            'select' => 'id,created_at,employee,pickup_addr,dest_addr,fare_eur,final_fare',
            'cid'    => 'eq.' . $cid,
            'status' => 'ilike.completed',
            'order'  => 'created_at.desc',
            'limit'  => self::STATS_SCAN_LIMIT,
        ];
        if (!empty($filters['date_from'])) $params['created_at'][] = 'gte.' . $filters['date_from'] . 'T00:00:00' . date('P');
        if (!empty($filters['date_to']))   $params['created_at'][] = 'lte.' . $filters['date_to']   . 'T23:59:59' . date('P');

        return $this->db->select('rides', $params);
    }

    public function getInvoiceRides(array $filters, int $page, int $perPage): array {
        $params            = $this->buildInvoiceParams($filters);
        $params['select']  = 'id,status,fare_eur,final_fare,created_at,pickup_addr,dest_addr,user_id,driver_id,distance_km,duration_min';
        $params['order']   = $this->sortClause($filters['sort'] ?? 'date_desc');
        $params['limit']   = $perPage;
        $params['offset']  = ($page - 1) * $perPage;

        return $this->joinNames($this->db->select('rides', $params));
    }

    public function countInvoiceRides(array $filters): int {
        $params           = $this->buildInvoiceParams($filters);
        $params['select'] = 'id';
        return $this->db->select('rides', $params, true)['count'];
    }

    public function getInvoiceStats(array $filters): array {
        $params           = $this->buildInvoiceParams($filters);
        $params['select'] = 'fare_eur,final_fare';
        $params['limit']  = self::STATS_SCAN_LIMIT;

        $rows = $this->db->select('rides', $params);

        $totalRevenue = 0.0;
        foreach ($rows as $r) {
            $totalRevenue += (float)($r['final_fare'] ?? $r['fare_eur'] ?? 0);
        }
        $commission = round($totalRevenue * self::COMMISSION_PCT / 100, 2);

        return [
            'count'            => count($rows),
            'total_revenue'    => round($totalRevenue, 2),
            'total_commission' => $commission,
            'total_payout'     => round($totalRevenue - $commission, 2),
        ];
    }

    public function getAllForExport(array $filters): array {
        $params            = $this->buildInvoiceParams($filters);
        $params['select']  = 'id,status,fare_eur,final_fare,created_at,pickup_addr,dest_addr,user_id,driver_id,distance_km,duration_min';
        $params['order']   = $this->sortClause($filters['sort'] ?? 'date_desc');
        $params['limit']   = self::STATS_SCAN_LIMIT;

        return $this->joinNames($this->db->select('rides', $params));
    }

    private function buildInvoiceParams(array $filters): array {
        $params = ['status' => 'eq.completed'];

        if (!empty($filters['date_from'])) {
            $params['created_at'][] = 'gte.' . $filters['date_from'] . 'T00:00:00' . date('P');
        }
        if (!empty($filters['date_to'])) {
            $params['created_at'][] = 'lte.' . $filters['date_to'] . 'T23:59:59' . date('P');
        }
        if (!empty($filters['driver_id'])) {
            $params['driver_id'] = 'eq.' . $filters['driver_id'];
        }
        if (!empty($filters['search'])) {
            $safe         = str_replace(['(', ')', ',', '*'], '', $filters['search']);
            $params['or'] = "(pickup_addr.ilike.*{$safe}*,dest_addr.ilike.*{$safe}*)";
        }
        if (isset($filters['fare_min']) && $filters['fare_min'] !== '') {
            $params['fare_eur'][] = 'gte.' . (float)$filters['fare_min'];
        }
        if (isset($filters['fare_max']) && $filters['fare_max'] !== '') {
            $params['fare_eur'][] = 'lte.' . (float)$filters['fare_max'];
        }

        return $params;
    }

    private function sortClause(string $sort): string {
        return match($sort) {
            'date_asc'  => 'created_at.asc',
            'fare_desc' => 'fare_eur.desc',
            'fare_asc'  => 'fare_eur.asc',
            default     => 'created_at.desc',
        };
    }

    private function joinNames(array $rows): array {
        if (empty($rows)) return [];

        $driverIds = array_values(array_unique(array_filter(array_column($rows, 'driver_id'))));
        $userIds   = array_values(array_unique(array_filter(array_column($rows, 'user_id'))));

        $lookups = [];
        if (!empty($driverIds)) $lookups['d'] = ['table'=>'drivers',    'params'=>['select'=>'id,full_name,phone,email','id'=>'in.('.implode(',', $driverIds).')']];
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
            $driver    = $driverMap[$r['driver_id']] ?? null;
            $passenger = $passengerMap[$r['user_id']] ?? null;
            return [
                ...$r,
                'fare'            => $r['final_fare'] ?? $r['fare_eur'],
                'driver_name'     => $driver['full_name'] ?? null,
                'driver_phone'    => $driver['phone'] ?? null,
                'driver_email'    => $driver['email'] ?? null,
                'passenger_name'  => $passenger['name'] ?? null,
                'passenger_phone' => $passenger['phone'] ?? null,
                'passenger_email' => $passenger['email'] ?? null,
            ];
        }, $rows);
    }
}
