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

    public function createDriver(string $authId, array $data): array {
        $now = date('c');

        $row = [
            'id'            => $authId,
            'full_name'     => $data['full_name'],
            'email'         => $data['email'],
            'phone'         => $data['phone']          ?? null,
            'vehicle_make'  => $data['vehicle_make']   ?? null,
            'vehicle_model' => $data['vehicle_model']  ?? null,
            'plate_no'      => $data['plate_no']       ?? null,
            'vehicle_number'=> $data['vehicle_number'] ?? null,
            'no_seats'      => isset($data['no_seats']) ? (int)$data['no_seats'] : 4,
            'status'        => in_array($data['status'] ?? '', ['approved', 'pending'], true) ? $data['status'] : 'pending',
            'is_online'     => false,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        $inserted = $this->db->insert('drivers', $row);
        if (!$inserted) return ['success' => false, 'message' => 'Auth user created but failed to insert driver profile. The user exists in Supabase Auth — delete them manually if needed.'];

        return ['success' => true, 'id' => $authId, 'message' => 'Driver account created successfully.'];
    }

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
        $subject  = 'Your PowerCabs Driver Account Has Been Removed';
        $safeName = htmlspecialchars($name);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Poppins',Helvetica,Arial,sans-serif;color:#333">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)">

  <!-- Header -->
  <tr>
    <td style="background:#1A1A2E;padding:28px 24px;text-align:center">
      <span style="font-size:28px;font-weight:800;color:#F37A20;letter-spacing:1px">PowerCabs</span>
    </td>
  </tr>

  <!-- Warning icon + title -->
  <tr>
    <td style="padding:36px 40px 0;text-align:center">
      <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 16px"><tr><td style="width:56px;height:56px;border-radius:50%;border:2px solid #d0d0d0;text-align:center;vertical-align:middle;font-size:30px;font-weight:700;color:#aaaaaa">!</td></tr></table>
      <h1 style="margin:0 0 4px;font-size:24px;font-weight:700;color:#1A1A2E">Account Removed</h1>
      <p style="margin:0;font-size:15px;font-weight:600;color:#F37A20">Restrictions for 30 Days</p>
    </td>
  </tr>

  <!-- Divider -->
  <tr>
    <td style="padding:20px 40px 0">
      <hr style="border:none;border-top:1px solid #e8e8e8;margin:0">
    </td>
  </tr>

  <!-- Body text -->
  <tr>
    <td style="padding:24px 40px 0;font-size:14px;line-height:1.7;color:#444">
      <p style="margin:0 0 12px">Dear {$safeName},</p>
      <p style="margin:0">Your account has been removed due to incomplete or invalid documentation following previous notifications. We welcome you to reapply after <strong style="color:#F37A20">30 days</strong> with all required documents ready for review.</p>
    </td>
  </tr>

  <!-- Info card 1: Restrictions -->
  <tr>
    <td style="padding:24px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F2;border-radius:10px;padding:20px">
        <tr>
          <td width="54" valign="top" style="padding:0 14px 0 0">
            <div style="width:44px;height:44px;background:#FFF0E0;border-radius:10px;text-align:center;line-height:0;padding-top:8px">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F37A20" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><rect x="7" y="14" width="3" height="3" fill="#F37A20" stroke="none"/><rect x="14" y="14" width="3" height="3" fill="#F37A20" stroke="none"/></svg>
            </div>
          </td>
          <td valign="top" style="font-size:13px;line-height:1.6;color:#555">
            <strong style="font-size:14px;color:#1A1A2E">Restrictions for 30 Days</strong><br>
            Please note that any application submitted within 30 days of account removal may be placed on hold and will be reviewed when 30 days have passed.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Info card 2: Additional Verification -->
  <tr>
    <td style="padding:12px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F2;border-radius:10px;padding:20px">
        <tr>
          <td width="54" valign="top" style="padding:0 14px 0 0">
            <div style="width:44px;height:44px;background:#FFF0E0;border-radius:10px;text-align:center;line-height:0;padding-top:8px">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F37A20" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="10" r="3" fill="#F37A20" stroke="none"/><path d="M12 13c-2.5 0-4.5 1.5-4.5 3.5h9c0-2-2-3.5-4.5-3.5z" fill="#F37A20" stroke="none"/></svg>
            </div>
          </td>
          <td valign="top" style="font-size:13px;line-height:1.6;color:#555">
            <strong style="font-size:14px;color:#1A1A2E">Additional Verification</strong><br>
            Future applications may also be subject to additional verification checks.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Info card 3: Repeated Incomplete Applications -->
  <tr>
    <td style="padding:12px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F2;border-radius:10px;padding:20px">
        <tr>
          <td width="54" valign="top" style="padding:0 14px 0 0">
            <div style="width:44px;height:44px;background:#FFF0E0;border-radius:10px;text-align:center;line-height:0;padding-top:8px">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F37A20" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="9" y1="12" x2="15" y2="18"/><line x1="15" y1="12" x2="9" y2="18"/></svg>
            </div>
          </td>
          <td valign="top" style="font-size:13px;line-height:1.6;color:#555">
            <strong style="font-size:14px;color:#1A1A2E">Repeated Incomplete Applications</strong><br>
            Repeated applications submitted without the required documentation or a complete profile may be considered misuse of the onboarding process and may result in a restriction on submitting further applications for up to <strong style="color:#F37A20">6 months</strong>.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Disclaimer box -->
  <tr>
    <td style="padding:20px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F5F5;border-radius:10px;padding:16px 20px">
        <tr>
          <td width="32" valign="top" style="padding:0 10px 0 0">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="width:28px;height:28px;border-radius:50%;border:2px solid #ccc;text-align:center;vertical-align:middle;font-size:14px;color:#999">&#10003;</td></tr></table>
          </td>
          <td style="font-size:12px;line-height:1.6;color:#777">
            PowerCabs reserves the right to accept or reject any future application based on the completeness and accuracy of the information provided.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="padding:28px 40px 36px;text-align:center;font-size:14px;color:#444">
      <p style="margin:0 0 2px"><strong>Thank you,</strong></p>
      <p style="margin:0;color:#F37A20;font-weight:600">PowerCabs Team</p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

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
