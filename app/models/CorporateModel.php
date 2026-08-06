<?php

class CorporateModel {
    public function __construct(private SupabaseDB $db) {}

    public function getStats(): array {
        $res = $this->db->selectParallel([
            ['table' => 'corporate', 'params' => ['select' => 'id'],                                   'withCount' => true],
            ['table' => 'corporate', 'params' => ['select' => 'id', 'status' => 'eq.active'],          'withCount' => true],
            ['table' => 'corporate_employees', 'params' => ['select' => 'id'],                          'withCount' => true],
            ['table' => 'corporate', 'params' => ['select' => 'id', 'billing_model' => 'eq.revenue'],  'withCount' => true],
        ]);
        return [
            'total_clients'    => $res[0]['count'] ?? 0,
            'active_clients'   => $res[1]['count'] ?? 0,
            'total_employees'  => $res[2]['count'] ?? 0,
            'revenue_accounts' => $res[3]['count'] ?? 0,
        ];
    }

    public function getAll(): array {
        return $this->db->select('corporate', [
            'select' => 'id,cid,company_name,tax_number,appointed_person,designation,name,email,phone,billing_model,payment_cycle,status,created_at',
            'order'  => 'created_at.desc',
        ]);
    }

    public function getById(string $cid): ?array {
        $rows = $this->db->select('corporate', ['select' => '*', 'cid' => 'eq.' . $cid, 'limit' => 1]);
        return $rows[0] ?? null;
    }

    public function create(array $data): ?array {
        return $this->db->insert('corporate', $this->sanitize($data));
    }

    public function update(string $cid, array $data): bool {
        unset($data['cid']); // PK must not change
        return $this->db->update('corporate', $this->sanitize($data), ['cid' => 'eq.' . $cid]);
    }

    public function delete(string $cid): bool {
        return $this->db->delete('corporate', ['cid' => 'eq.' . $cid]);
    }

    public function toggleStatus(string $cid, string $status): bool {
        return $this->db->update('corporate', ['status' => $status], ['cid' => 'eq.' . $cid]);
    }

    public function getEmployees(string $cid, string $search = ''): array {
        $params = ['select' => 'id,name,email,department,phone,cid,company', 'cid' => 'eq.' . $cid];
        if ($search) $params['name'] = 'ilike.*' . $search . '*';
        return $this->db->select('corporate_employees', $params);
    }

    public function getAllEmployees(string $search = ''): array {
        $params = ['select' => 'id,name,email,department,phone,cid,company'];
        if ($search) $params['name'] = 'ilike.*' . $search . '*';
        return $this->db->select('corporate_employees', $params);
    }

    public function getRides(string $cid, array $filters = []): array {
        // Corporate rides are linked via the cid column directly on the ride (user_id is null for corporate)
        $params = [
            'select' => 'id,created_at,pickup_addr,dest_addr,fare_eur,final_fare,total_charged,payment_method,status,distance_km,duration_min,employee,employee_id',
            'cid'    => 'eq.' . $cid,
            'order'  => 'created_at.desc',
            'limit'  => 200,
        ];
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $params['status'] = 'eq.' . $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $params['created_at'][] = 'gte.' . $filters['date_from'] . 'T00:00:00' . date('P');
        }
        if (!empty($filters['date_to'])) {
            $params['created_at'][] = 'lte.' . $filters['date_to'] . 'T23:59:59' . date('P');
        }

        $rides = $this->db->select('rides', $params);
        return array_map(fn($r) => [
            ...$r,
            'employee_name'  => trim($r['employee']    ?? '—') ?: '—',
            'employee_email' => trim($r['employee_id'] ?? '—') ?: '—',
            'fare'           => (float)($r['total_charged'] ?? $r['final_fare'] ?? $r['fare_eur'] ?? 0),
        ], $rides);
    }

    public function computeInvoice(string $cid, string $dateFrom, string $dateTo): array {
        $corporate = $this->getById($cid);
        if (!$corporate) return ['success' => false, 'message' => 'Corporate account not found.'];

        // Rides carry cid, employee, employee_id directly — no join to corporate_employees needed
        $rides = $this->db->select('rides', [
            'select'     => 'id,created_at,pickup_addr,dest_addr,fare_eur,final_fare,total_charged,payment_method,status,employee,employee_id',
            'cid'        => 'eq.' . $cid,
            'status'     => 'eq.completed',
            'created_at' => ['gte.' . $dateFrom . 'T00:00:00' . date('P'), 'lte.' . $dateTo . 'T23:59:59' . date('P')],
            'order'      => 'created_at.asc',
        ]);

        if (empty($rides)) {
            return ['success' => false, 'message' => 'No completed rides found for this period.'];
        }

        $billingModel = $corporate['billing_model'] ?? 'regular';
        $fixedFee     = (float)($corporate['revenue_fixed_fee'] ?? 0);
        $rawTiers     = $corporate['revenue_tiers'] ?? null;
        $tiers        = is_string($rawTiers) ? (json_decode($rawTiers, true) ?: []) : (is_array($rawTiers) ? $rawTiers : []);

        $computedRides  = [];
        $totalRideFare  = 0.0;  // sum of total_charged (fixed fee already baked in)
        $totalCredits   = 0.0;  // sum of credits earned by corporate
        $totalCharge    = 0.0;  // what corporate actually pays (ride_fare - credit)
        $totalPcProfit  = 0.0;  // PowerCabs net from revenue model (fixed_fee - credit)

        foreach ($rides as $i => $ride) {
            $rideNumber = $i + 1;
            // total_charged already contains the fixed fee — it is the ride fare as billed to corporate
            $rideFare = (float)($ride['total_charged'] ?? $ride['final_fare'] ?? $ride['fare_eur'] ?? 0);
            $credit   = 0.0;
            $pcProfit = 0.0;

            if ($billingModel === 'revenue') {
                foreach ($tiers as $tier) {
                    $from = (int)($tier['from'] ?? 1);
                    $to   = (isset($tier['to']) && $tier['to'] !== null) ? (int)$tier['to'] : PHP_INT_MAX;
                    if ($rideNumber >= $from && $rideNumber <= $to) {
                        $credit = (float)($tier['discount'] ?? 0);
                        break;
                    }
                }
                // Corporate is always billed the full fare; credits are a separate loyalty balance
                $charge   = $rideFare;
                $pcProfit = $rideFare - $credit; // PC effective revenue (net of credit liability)
            } else {
                $charge   = $rideFare;
                $pcProfit = $rideFare;
            }

            $totalRideFare += $rideFare;
            $totalCredits  += $credit;
            $totalCharge   += $charge;
            $totalPcProfit += $pcProfit;

            $computedRides[] = [
                'id'             => $ride['id'],
                'ride_number'    => $rideNumber,
                'date'           => $ride['created_at'],
                'employee_name'  => trim($ride['employee']    ?? '—') ?: '—',
                'employee_email' => trim($ride['employee_id'] ?? '—') ?: '—',
                'pickup_addr'    => $ride['pickup_addr'] ?? '—',
                'dest_addr'      => $ride['dest_addr']   ?? '—',
                'payment_method' => $ride['payment_method'] ?? 'Cash',
                'ride_fare'      => round($rideFare, 2),
                'credit'         => round($credit, 2),   // earned credit (informational)
                'charge'         => round($charge, 2),   // = ride_fare always
                'pc_profit'      => round($pcProfit, 2),
            ];
        }

        return [
            'success'          => true,
            'corporate'        => $corporate,
            'billing_model'    => $billingModel,
            'period_from'      => $dateFrom,
            'period_to'        => $dateTo,
            'rides'            => $computedRides,
            'total_rides'      => count($computedRides),
            'total_ride_fare'  => round($totalRideFare, 2),
            'total_credits'    => round($totalCredits, 2),
            'total_charge'     => round($totalCharge, 2),
            'total_pc_profit'  => round($totalPcProfit, 2),
            'fixed_fee'        => $fixedFee,
            'tiers'            => $tiers,
        ];
    }

    public function getRevenueHistory(string $cid, string $dateFrom, string $dateTo): array {
        $corporate = $this->getById($cid);
        if (!$corporate) return ['success' => false, 'message' => 'Corporate account not found.'];

        $rides = $this->db->select('rides', [
            'select'     => 'id,created_at,total_charged,fare_eur,final_fare',
            'cid'        => 'eq.' . $cid,
            'status'     => 'eq.completed',
            'created_at' => ['gte.' . $dateFrom . 'T00:00:00' . date('P'), 'lte.' . $dateTo . 'T23:59:59' . date('P')],
            'order'      => 'created_at.asc',
        ]);

        $billingModel = $corporate['billing_model'] ?? 'regular';
        $fixedFee     = (float)($corporate['revenue_fixed_fee'] ?? 0);
        $rawTiers     = $corporate['revenue_tiers'] ?? null;
        $tiers        = is_string($rawTiers) ? (json_decode($rawTiers, true) ?: []) : (is_array($rawTiers) ? $rawTiers : []);

        $byMonth = [];
        foreach ($rides as $ride) {
            $month = substr($ride['created_at'], 0, 7);
            $byMonth[$month][] = $ride;
        }
        krsort($byMonth);

        $months = [];
        foreach ($byMonth as $month => $monthRides) {
            $totalRideFare = 0.0;
            $totalCredits  = 0.0;
            $totalCharge   = 0.0;
            $totalPcProfit = 0.0;

            foreach ($monthRides as $i => $ride) {
                $rideNumber = $i + 1;
                $rideFare   = (float)($ride['total_charged'] ?? $ride['final_fare'] ?? $ride['fare_eur'] ?? 0);
                $credit     = 0.0;

                if ($billingModel === 'revenue') {
                    foreach ($tiers as $tier) {
                        $from = (int)($tier['from'] ?? 1);
                        $to   = (isset($tier['to']) && $tier['to'] !== null) ? (int)$tier['to'] : PHP_INT_MAX;
                        if ($rideNumber >= $from && $rideNumber <= $to) {
                            $credit = (float)($tier['discount'] ?? 0);
                            break;
                        }
                    }
                    // Corporate is always billed full fare; credits are loyalty balance only
                    $charge   = $rideFare;
                    $pcProfit = $rideFare - $credit; // effective PC revenue net of credit liability
                } else {
                    $charge   = $rideFare;
                    $pcProfit = $rideFare;
                }

                $totalRideFare += $rideFare;
                $totalCredits  += $credit;
                $totalCharge   += $charge;
                $totalPcProfit += $pcProfit;
            }

            $y = (int)substr($month, 0, 4);
            $m = (int)substr($month, 5, 2);
            $months[] = [
                'month'           => $month,
                'month_label'     => date('F Y', mktime(0, 0, 0, $m, 1, $y)),
                'date_from'       => $month . '-01',
                'date_to'         => date('Y-m-t', mktime(0, 0, 0, $m, 1, $y)),
                'total_rides'     => count($monthRides),
                'total_ride_fare' => round($totalRideFare, 2),
                'total_credits'   => round($totalCredits, 2),
                'total_charge'    => round($totalCharge, 2),
                'total_pc_profit' => round($totalPcProfit, 2),
            ];
        }

        return [
            'success'           => true,
            'corporate'         => $corporate,
            'billing_model'     => $billingModel,
            'fixed_fee'         => $fixedFee,
            'months'            => $months,
            'grand_total'       => round(array_sum(array_column($months, 'total_charge')), 2),
            'grand_credits'     => round(array_sum(array_column($months, 'total_credits')), 2),
            'grand_pc_profit'   => round(array_sum(array_column($months, 'total_pc_profit')), 2),
            'total_rides'       => (int)array_sum(array_column($months, 'total_rides')),
        ];
    }

    private function sanitize(array $d): array {
        $out = [];

        $clean = static function ($v): ?string {
            if ($v === null || $v === '') return null;
            if (strtoupper(trim((string)$v)) === 'NULL') return null;
            return (string)$v;
        };

        foreach (['cid','company_name','tax_number','appointed_person','designation','name','email','phone',
                  'office_address','billing_iban','payment_cycle','invoice_email','address','billing_model','status'] as $k) {
            if (array_key_exists($k, $d)) {
                $out[$k] = $clean($d[$k]);
            }
        }
        foreach (['special_notes_company','special_notes_powercabs'] as $k) {
            if (array_key_exists($k, $d)) {
                $out[$k] = $clean($d[$k]);
            }
        }

        if (array_key_exists('revenue_fixed_fee', $d)) {
            $out['revenue_fixed_fee'] = ($d['revenue_fixed_fee'] !== '' && $d['revenue_fixed_fee'] !== null)
                ? (float)$d['revenue_fixed_fee'] : null;
        }

        if (array_key_exists('revenue_tiers', $d)) {
            $raw = $d['revenue_tiers'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $out['revenue_tiers'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
            } elseif (is_array($raw)) {
                $out['revenue_tiers'] = $raw;
            } else {
                $out['revenue_tiers'] = null;
            }
        }

        return $out;
    }
}
