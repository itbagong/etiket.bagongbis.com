<?php
require_once __DIR__ . '/Database.php';

/**
 * 🔹 Statistik utama dashboard (Open, Progress, Closed)
 * Catatan: Fungsi ini menghitung SEMUA status (status_wo = 0 dan 1) untuk memberikan statistik dashboard lengkap.
 */
function getDashboardStats($filter = 'all') {
    $db = Database::getInstance();
    $stats = ['total' => 0, 'open' => 0, 'progress' => 0, 'closed' => 0]; // Menghapus 'done'

    // Filter waktu
    $where = '';
    if ($filter === 'month') {
        $where = "WHERE MONTH(date_request) = MONTH(CURDATE())";
    } elseif ($filter === 'week') {
        $where = "WHERE YEARWEEK(date_request, 1) = YEARWEEK(CURDATE(), 1)";
    }

    // Hitung total
    $res = $db->query("SELECT COUNT(*) AS total FROM work_order $where");
    $stats['total'] = (int)($res->fetch_assoc()['total'] ?? 0);

    // Hitung per status (Hanya status enum: open, progress, closed)
    $res = $db->query("SELECT LOWER(status) AS status, COUNT(*) AS total FROM work_order $where GROUP BY status");
    while ($r = $res->fetch_assoc()) {
        $status = strtolower($r['status']);
        // Hanya ambil status yang valid (open, progress, closed)
        if (isset($stats[$status])) {
            $stats[$status] = (int)$r['total'];
        }
    }

    // Hitung persentase
    $stats_keys = array_keys($stats);
    if ($stats['total'] > 0) {
        foreach ($stats_keys as $k) {
            if ($k !== 'total') {
                $stats["percent_$k"] = round(($stats[$k] / $stats['total']) * 100, 1);
            }
        }
    }

    return $stats;
}

/**
 * 🔹 Statistik kategori & subkategori
 */
function getCategoryBreakdown($filter = 'all') {
    $db = Database::getInstance();
    $where = 'WHERE w.status_wo = 0'; // Filter WO aktif
    if ($filter === 'month') {
        $where .= " AND MONTH(w.date_request) = MONTH(CURDATE())";
    } elseif ($filter === 'week') {
        $where .= " AND YEARWEEK(w.date_request, 1) = YEARWEEK(CURDATE(), 1)";
    }

    $sql = "
        SELECT 
            c.name AS category,
            s.name AS subcategory,
            COUNT(wi.id) AS total
        FROM work_order_items wi
        JOIN work_order w ON wi.wo_id = w.wo_id
        LEFT JOIN ticket_category c ON wi.category = c.id
        LEFT JOIN ticket_subcategory s ON wi.subcategory = s.id
        $where
        GROUP BY c.name, s.name
        ORDER BY c.name ASC
    ";

    $res = $db->query($sql);
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[$r['category']]['total'] = ($data[$r['category']]['total'] ?? 0) + $r['total'];
        $data[$r['category']]['sub'][$r['subcategory']] = $r['total'];
    }
    return $data;
}

/**
 * 🔹 Statistik per lokasi
 */
function getLocationBreakdown($filter = 'all') {
    $db = Database::getInstance();
    $where = 'WHERE w.status_wo = 0'; // Filter WO aktif
    if ($filter === 'month') {
        $where .= " AND MONTH(date_request) = MONTH(CURDATE())";
    } elseif ($filter === 'week') {
        $where .= " AND YEARWEEK(date_request, 1) = YEARWEEK(CURDATE(), 1)";
    }

    $sql = "
        SELECT l.location_name AS location, COUNT(w.wo_id) AS total
        FROM work_order w
        LEFT JOIN location l ON w.location = l.location_id
        $where
        GROUP BY l.location_name
        ORDER BY l.location_name ASC
    ";
    $res = $db->query($sql);
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[$r['location']] = (int)$r['total'];
    }
    return $data;
}

/**
 * 🔹 5 Work Order Terakhir
 */
function getRecentWorkOrders($filter = 'all') {
    $db = Database::getInstance();
    $where = 'WHERE w.status_wo = 0'; // Filter WO aktif

    if ($filter === 'month') {
        $where .= " AND MONTH(w.date_request) = MONTH(CURDATE())";
    } elseif ($filter === 'week') {
        $where .= " AND YEARWEEK(w.date_request, 1) = YEARWEEK(CURDATE(), 1)";
    }

    $sql = "
        SELECT w.wo_id, u.name AS requester_name, w.priority, w.status, w.date_request,
               d.departemen_name, l.location_name
        FROM work_order w
        LEFT JOIN users u ON w.requester_id = u.id -- PERBAIKAN: Join untuk mendapatkan nama requester
        LEFT JOIN departemen d ON w.department = d.departemen_id
        LEFT JOIN location l ON w.location = l.location_id
        $where
        ORDER BY w.created_at DESC
        LIMIT 5
    ";
    $res = $db->query($sql);
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

function getCategoryPercentage() {
    $db = Database::getInstance();
    $data = [];

    $q = $db->query("
        SELECT c.name AS category, COUNT(wi.id) AS total
        FROM work_order_items wi
        JOIN work_order w ON wi.wo_id = w.wo_id
        LEFT JOIN ticket_category c ON wi.category = c.id
        WHERE w.status_wo = 0 -- Filter WO aktif
        GROUP BY wi.category
    ");

    $total_all = 0;
    while ($r = $q->fetch_assoc()) {
        $total_all += $r['total'];
        $data[] = $r;
    }

    // Hitung persentase
    foreach ($data as &$row) {
        $row['percentage'] = $total_all > 0 ? round(($row['total'] / $total_all) * 100, 2) : 0;
    }

    return $data;
}
?>