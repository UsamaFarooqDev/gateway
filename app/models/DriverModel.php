<?php

class DriverModel {
    public function __construct(private SupabaseDB $db) {}

    private const DOC_COLUMNS = [
        'license'     => 'license_url',
        'vehicle_reg' => 'vehicle_reg_url',
        'insurance'   => 'insurance_url',
        'nct'         => 'nct_cert',
        'rt'          => 'rt_cert',
        'suitability' => 'suitability_cert',
    ];

    public function deleteDriver(string $id): array {
        $rows = $this->db->select('drivers', [
            'select'     => 'id,full_name,email',
            'id'         => 'eq.' . $id,
            'deleted_at' => 'is.null',
            'limit'      => 1,
        ]);
        if (empty($rows)) return ['success' => false, 'message' => 'Driver not found.'];

        $driver = $rows[0];
        $email  = $driver['email'] ?? '';
        $name   = $driver['full_name'] ?? 'Driver';

        $ok = $this->db->update('drivers', [
            'deleted_at' => date('c'),
            'updated_at' => date('c'),
            'status'     => 'inactive',
        ], ['id' => 'eq.' . $id]);

        if (!$ok) return ['success' => false, 'message' => 'Failed to delete driver record.'];

        $this->db->deleteAuthUser($id);

        $emailSent = false;
        if ($email) $emailSent = $this->sendDeletionEmail($email, $name);

        return [
            'success'    => true,
            'message'    => 'Driver account deleted.' . ($emailSent ? ' Email notification sent.' : ' Email could not be sent — check server SMTP.'),
            'email_sent' => $emailSent,
        ];
    }

    private function sendDeletionEmail(string $email, string $name): bool {
        $subject = 'Your PowerCabs Driver Account Has Been Removed';
        $html    = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden">'
            . '<div style="background:#1A1A2E;padding:24px;text-align:center">'
            . '<span style="font-size:26px;font-weight:800;color:#F37A20">PowerCabs</span>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<h2 style="color:#1A1A2E;margin-top:0">Account Removed</h2>'
            . '<p>Dear ' . htmlspecialchars($name) . ',</p>'
            . '<p>Your PowerCabs driver account has been removed due to incomplete or invalid documentation.</p>'
            . '<p>To reapply, please ensure all of the following documents are valid and ready to upload:</p>'
            . '<ul style="line-height:2">'
            . '<li>Valid Driving Licence</li>'
            . '<li>Vehicle Registration Certificate</li>'
            . '<li>Insurance Certificate</li>'
            . '<li>NCT Certificate</li>'
            . '<li>Road Tax Certificate</li>'
            . '<li>Suitability Certificate</li>'
            . '</ul>'
            . '<p>If you believe this was a mistake, please contact us at '
            . '<a href="mailto:support@powercabs.ie" style="color:#F37A20">support@powercabs.ie</a>.</p>'
            . '<p style="color:#888;font-size:13px;margin-top:32px">The PowerCabs Team</p>'
            . '</div></div></body></html>';

        $headers  = "From: PowerCabs <noreply@powercabs.ie>\r\n";
        $headers .= "Reply-To: support@powercabs.ie\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        return @mail($email, $subject, $html, $headers);
    }

    public function searchDrivers(string $query, string $status, int $limit = 20): array {
        $params = [
            'select'     => 'id,full_name,email,phone,no_seats,plate_no,profile_pic_url,vehicle_make,vehicle_model,vehicle_number,type,status,is_online,last_active,created_at,license_url,vehicle_reg_url,insurance_url,nct_cert,rt_cert,suitability_cert,license_expiry,total_rides,total_earnings',
            'deleted_at' => 'is.null',
            'order'      => 'created_at.desc',
            'limit'      => $limit,
        ];

        if ($query !== '') {
            $safe = str_replace(['(', ')', ',', '.', '*'], '', $query);
            $params['or'] = "(full_name.ilike.*{$safe}*,email.ilike.*{$safe}*,plate_no.ilike.*{$safe}*,phone.ilike.*{$safe}*)";
        }

        if ($status !== 'all') {
            if ($status === 'online') {
                $params['is_online'] = 'is.true';
                $params['or2']       = '(status.eq.active,status.eq.approved)';
            } elseif ($status === 'active') {
                $params['or'] = isset($params['or'])
                    ? $params['or']
                    : '(status.eq.active,status.eq.approved)';
                if (isset($params['or']) && $query !== '') {
                    // can't use two `or` keys — fall back to just name search
                    $params['status'] = 'in.(active,approved)';
                    unset($params['or2']);
                }
            } else {
                $params['status'] = 'eq.' . $status;
            }
        }

        return $this->db->select('admin_driver_stats', $params);
    }

    public function uploadDoc(string $driverId, string $docType, string $tmpPath, string $originalName): array {
        if (!isset(self::DOC_COLUMNS[$docType])) {
            return ['success' => false, 'message' => 'Invalid document type.'];
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'webp'], true)) {
            return ['success' => false, 'message' => 'File type not allowed. Use JPG, PNG or PDF.'];
        }

        $maxBytes = 10 * 1024 * 1024; // 10 MB
        if (filesize($tmpPath) > $maxBytes) {
            return ['success' => false, 'message' => 'File too large. Maximum size is 10 MB.'];
        }

        $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf', 'webp' => 'image/webp'];
        $mime    = $mimeMap[$ext];
        $path    = "admin_uploads/{$driverId}/{$docType}.{$ext}";

        $content = file_get_contents($tmpPath);
        if ($content === false) {
            return ['success' => false, 'message' => 'Failed to read uploaded file.'];
        }

        $url = $this->db->uploadFile('driver_documents', $path, $content, $mime);
        if (!$url) {
            return ['success' => false, 'message' => 'Storage upload failed. Check the driver_documents bucket exists in Supabase.'];
        }

        $column = self::DOC_COLUMNS[$docType];
        $ok     = $this->db->update('drivers', [$column => $url, 'updated_at' => date('c')], ['id' => 'eq.' . $driverId]);
        if (!$ok) {
            return ['success' => false, 'message' => 'File uploaded but failed to save URL to driver record.'];
        }

        return ['success' => true, 'url' => $url, 'message' => 'Document uploaded successfully.'];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array {
        $params = [
            'select'     => 'id,full_name,email,phone,no_seats,plate_no,profile_pic_url,vehicle_make,vehicle_model,vehicle_number,type,status,is_online,last_active,created_at,license_url,vehicle_reg_url,insurance_url,nct_cert,rt_cert,suitability_cert,license_expiry,total_rides,total_earnings',
            'deleted_at' => 'is.null',
            'order'      => 'created_at.desc',
            'limit'      => $perPage,
            'offset'     => ($page - 1) * $perPage,
        ];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'online') {
                $params['is_online'] = 'is.true';
                // Include both 'active' and 'approved' as online-eligible
                $params['or'] = '(status.eq.active,status.eq.approved)';
            } elseif ($filters['status'] === 'active') {
                // 'active' filter shows both 'active' and 'approved' drivers
                $params['or'] = '(status.eq.active,status.eq.approved)';
            } else {
                $params['status'] = 'eq.' . $filters['status'];
            }
        }

        if (!empty($filters['search'])) {
            $params['full_name'] = 'ilike.*' . $filters['search'] . '*';
        }

        return $this->db->select('admin_driver_stats', $params);
    }

    public function count(array $filters = []): int {
        $params = [
            'select'     => 'id',
            'deleted_at' => 'is.null',
        ];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'online') {
                $params['is_online'] = 'is.true';
                $params['or'] = '(status.eq.active,status.eq.approved)';
            } elseif ($filters['status'] === 'active') {
                $params['or'] = '(status.eq.active,status.eq.approved)';
            } else {
                $params['status'] = 'eq.' . $filters['status'];
            }
        }

        if (!empty($filters['search'])) {
            $params['full_name'] = 'ilike.*' . $filters['search'] . '*';
        }

        return $this->db->select('admin_driver_stats', $params, true)['count'];
    }

    public function getById(string $id): ?array {
        $rows = $this->db->select('admin_driver_stats', [
            'id'         => 'eq.' . $id,
            'deleted_at' => 'is.null',
            'limit'      => 1,
        ]);
        return $rows[0] ?? null;
    }

    public function updateStatus(string $id, string $status): bool {
        // 'approved' maps to 'active' for consistency; also accept 'approved' directly
        $allowed = ['active', 'approved', 'inactive', 'pending', 'suspended'];
        if (!in_array($status, $allowed, true)) return false;

        return $this->db->update(
            'drivers',
            ['status' => $status, 'updated_at' => date('c')],
            ['id' => 'eq.' . $id]
        );
    }

    /**
     * Load all driver page data (list + total + status counts) in one parallel batch.
     */
    public function loadPageData(array $filters, int $page, int $perPage): array {
        $listParams  = [
            'select'     => 'id,full_name,email,phone,no_seats,plate_no,profile_pic_url,vehicle_make,vehicle_model,vehicle_number,type,status,is_online,last_active,created_at,license_url,vehicle_reg_url,insurance_url,nct_cert,rt_cert,suitability_cert,license_expiry,total_rides,total_earnings',
            'deleted_at' => 'is.null',
            'order'      => 'created_at.desc',
            'limit'      => $perPage,
            'offset'     => ($page - 1) * $perPage,
        ];
        $countParams = ['select' => 'id', 'deleted_at' => 'is.null'];
        $base        = ['select' => 'id', 'deleted_at' => 'is.null'];

        foreach ([$listParams, $countParams] as &$p) {
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                if ($filters['status'] === 'online') {
                    $p['is_online'] = 'is.true';
                    $p['or']        = '(status.eq.active,status.eq.approved)';
                } elseif ($filters['status'] === 'active') {
                    $p['or'] = '(status.eq.active,status.eq.approved)';
                } else {
                    $p['status'] = 'eq.' . $filters['status'];
                }
            }
            if (!empty($filters['search'])) {
                $p['full_name'] = 'ilike.*' . $filters['search'] . '*';
            }
        }
        unset($p);

        $res = $this->db->selectParallel([
            0 => ['table' => 'admin_driver_stats', 'params' => $listParams],
            1 => ['table' => 'admin_driver_stats', 'params' => $countParams, 'withCount' => true],
            2 => ['table' => 'drivers', 'params' => $base, 'withCount' => true],
            3 => ['table' => 'drivers', 'params' => [...$base, 'status' => 'eq.active'],   'withCount' => true],
            4 => ['table' => 'drivers', 'params' => [...$base, 'status' => 'eq.approved'], 'withCount' => true],
            5 => ['table' => 'drivers', 'params' => [...$base, 'status' => 'eq.pending'],  'withCount' => true],
            6 => ['table' => 'drivers', 'params' => [...$base, 'status' => 'eq.suspended'],'withCount' => true],
            7 => ['table' => 'drivers', 'params' => [...$base, 'is_online' => 'is.true', 'status' => 'eq.active'],   'withCount' => true],
            8 => ['table' => 'drivers', 'params' => [...$base, 'is_online' => 'is.true', 'status' => 'eq.approved'], 'withCount' => true],
        ]);

        return [
            'drivers' => $res[0],
            'total'   => $res[1]['count'],
            'counts'  => [
                'total'     => $res[2]['count'],
                'active'    => $res[3]['count'] + $res[4]['count'],
                'pending'   => $res[5]['count'],
                'suspended' => $res[6]['count'],
                'online'    => $res[7]['count'] + $res[8]['count'],
            ],
        ];
    }

    public function getStatusCounts(): array {
        $base = ['select' => 'id', 'deleted_at' => 'is.null'];
        $res  = $this->db->selectParallel([
            ['table' => 'drivers', 'params' => $base, 'withCount' => true],
            ['table' => 'drivers', 'params' => [...$base, 'status' => 'eq.active'],   'withCount' => true],
            ['table' => 'drivers', 'params' => [...$base, 'status' => 'eq.approved'], 'withCount' => true],
            ['table' => 'drivers', 'params' => [...$base, 'status' => 'eq.pending'],  'withCount' => true],
            ['table' => 'drivers', 'params' => [...$base, 'status' => 'eq.suspended'],'withCount' => true],
            ['table' => 'drivers', 'params' => [...$base, 'is_online' => 'is.true', 'status' => 'eq.active'],   'withCount' => true],
            ['table' => 'drivers', 'params' => [...$base, 'is_online' => 'is.true', 'status' => 'eq.approved'], 'withCount' => true],
        ]);

        return [
            'total'     => $res[0]['count'],
            'active'    => $res[1]['count'] + $res[2]['count'],
            'pending'   => $res[3]['count'],
            'suspended' => $res[4]['count'],
            'online'    => $res[5]['count'] + $res[6]['count'],
        ];
    }

    public function getRideTypes(): array {
        return $this->db->select('ride_types', [
            'select'    => 'id,name,description,icon_emoji,seats,multiplier,sort_order',
            'is_active' => 'eq.true',
            'order'     => 'sort_order.asc',
        ]);
    }

    public function assignRideTypes(string $driverId, array $selectedNames, array $allRideTypes): bool {
        $rtMap = array_column($allRideTypes, null, 'name');
        $types = [];
        foreach ($selectedNames as $name) {
            $name = (string)$name;
            if (!isset($rtMap[$name])) continue;
            $rt      = $rtMap[$name];
            $types[] = [
                'type'           => $rt['name'],
                'label'          => ucwords(str_replace('_', ' ', $rt['name'])),
                'description'    => $rt['description'] ?? '',
                'max_passengers' => (int)($rt['seats'] ?? 4),
                'multiplier'     => (float)($rt['multiplier'] ?? 1.0),
                'icon_emoji'     => $rt['icon_emoji'] ?? null,
            ];
        }
        return $this->db->update(
            'drivers',
            ['type' => $types, 'updated_at' => date('c')],
            ['id'   => 'eq.' . $driverId]
        );
    }

    public function getRecentRides(string $driverId, int $limit = 5): array {
        $rows = $this->db->select('rides', [
            'select'    => 'id,status,fare_eur,final_fare,created_at,pickup_addr,dest_addr,user_id',
            'driver_id' => 'eq.' . $driverId,
            'order'     => 'created_at.desc',
            'limit'     => $limit,
        ]);

        $userIds = array_values(array_unique(array_filter(array_column($rows, 'user_id'))));
        $pMap    = [];
        if (!empty($userIds)) {
            $pRows = $this->db->select('passengers', [
                'select' => 'id,name',
                'id'     => 'in.(' . implode(',', $userIds) . ')',
            ]);
            foreach ($pRows as $p) $pMap[$p['id']] = $p['name'];
        }

        return array_map(fn($r) => [
            ...$r,
            'fare'           => $r['final_fare'] ?? $r['fare_eur'],
            'passenger_name' => $pMap[$r['user_id']] ?? null,
        ], $rows);
    }
}
