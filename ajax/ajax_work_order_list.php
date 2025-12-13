<?php
// ajax/ajax_work_order_list.php - KODE PRODUKSI FINAL (Disesuaikan untuk status_wo = 0 (Aktif) & Enkripsi ID)

// Pastikan error reporting dimatikan untuk produksi
ini_set('display_errors', 0);
error_reporting(0);

session_start();

// Cek path: Gunakan '../src/' karena file ini di dalam folder 'ajax'
require_once '../src/Database.php';
require_once '../src/work_order_functions.php';
require_once '../src/Auth.php';
require_once '../src/security.php'; // WAJIB: untuk fungsi encrypt_id

header('Content-Type: application/json; charset=utf-8');

// =========================================================
// 1. INI KONEKSI DATABASE
// =========================================================
$db = getDb();
if (!$db) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(["error" => "Koneksi database gagal."]);
    exit;
}
// Set SQL Mode (jika diperlukan oleh GROUP BY)
$db->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

$rbac_target = 'work_order_list.php';

// =========================================================
// 2. LOGIKA RBAC & AUTENTIKASI 
// =========================================================

$user_role_id = $_SESSION['user']->role_id ?? 0;

$can_access_view = false;
$can_delete = false;

try {
    $auth = new Auth($user_role_id, $db);
    
    // Cek izin (menggunakan izin 'edit' sebagai proxy untuk detail/view, dan 'delete')
    $can_access_view = $auth->can($rbac_target, 'edit');
    $can_delete = $auth->can($rbac_target, 'delete');

} catch (Throwable $e) {
    $can_access_view = false;
    $can_delete = false;
}

// =========================================================
// 3. QUERY DAN DATA TABLES (PENTING: Menambahkan Filter status_wo = 0)
// =========================================================
$draw = intval($_GET['draw'] ?? 0);
$start = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$search = trim($_GET['search']['value'] ?? '');
$statusFilter = $_GET['status'] ?? ''; // Jika ada filter status tambahan

// Klausa utama untuk filter Soft Delete (HANYA tampilkan yang AKTIF)
$softDeleteFilterClause = " wo.status_wo = 0 "; 

$baseQuerySelect = "FROM work_order wo
    LEFT JOIN departemen d ON wo.department = d.departemen_id
    LEFT JOIN (
        SELECT woi.wo_id, tc.name AS category_name, tsc.name AS subcategory_name
        FROM work_order_items woi LEFT JOIN ticket_category tc ON woi.category = tc.id LEFT JOIN ticket_subcategory tsc ON woi.subcategory = tsc.id GROUP BY woi.wo_id
    ) AS item ON wo.wo_id = item.wo_id
    LEFT JOIN (
        SELECT c1.ticket, c1.body AS latest_note, c1.created_at AS latest_update
        FROM comments c1 INNER JOIN ( SELECT ticket, MAX(created_at) AS max_created_at FROM comments GROUP BY ticket ) AS max_c ON c1.ticket = max_c.ticket AND c1.created_at = max_c.max_created_at GROUP BY c1.ticket
    ) AS latest_comment ON wo.wo_id = latest_comment.ticket";

// Hitung total records (semua WO, termasuk yang soft delete, untuk recordsTotal - Opsional)
$totalRes = $db->query("SELECT COUNT(wo_id) AS cnt FROM work_order");
$totalRecords = $totalRes ? $totalRes->fetch_assoc()['cnt'] : 0;

$where = " WHERE " . $softDeleteFilterClause; // Awali WHERE dengan filter soft delete

if ($search !== '') {
    $esc = $db->real_escape_string($search);
    // Tambahkan filter pencarian ke klausa WHERE yang sudah ada
    $where .= " AND (wo.requester LIKE '%$esc%' OR wo.description LIKE '%$esc%' OR d.departemen_name LIKE '%$esc%' OR item.category_name LIKE '%$esc%' OR item.subcategory_name LIKE '%$esc%' OR wo.wo_id = '$esc')";
}

if ($statusFilter !== '') {
    $escStatus = $db->real_escape_string($statusFilter);
    // Tambahkan filter status ke klausa WHERE
    $where .= " AND wo.status = '$escStatus'";
}

// Query Count untuk recordsFiltered (HANYA yang aktif dan tersaring)
$baseQueryCount = "FROM work_order wo LEFT JOIN departemen d ON wo.department = d.departemen_id LEFT JOIN (SELECT woi.wo_id FROM work_order_items woi LEFT JOIN ticket_category tc ON woi.category = tc.id LEFT JOIN ticket_subcategory tsc ON woi.subcategory = tsc.id GROUP BY woi.wo_id) AS item ON wo.wo_id = item.wo_id";
$filteredRes = $db->query("SELECT COUNT(DISTINCT wo.wo_id) AS cnt $baseQueryCount $where"); // Gunakan $where yang sudah lengkap
$filteredRecords = $filteredRes ? $filteredRes->fetch_assoc()['cnt'] : 0;

// Query Data Utama
$query = "
SELECT
    wo.wo_id, wo.description, wo.priority, wo.status, wo.date_request,
    d.departemen_name, item.category_name, item.subcategory_name,
    latest_comment.latest_note, latest_comment.latest_update 
$baseQuerySelect $where 
GROUP BY wo.wo_id
ORDER BY wo.wo_id DESC
LIMIT $start, $length
";

$result = $db->query($query);
if (!$result) {
    echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "SQL Error: " . $db->error]);
    exit;
}

// =========================================================
// 4. PEMBENTUKAN ARRAY DATA DENGAN TOMBOL AKSI
// =========================================================
$data = [];
while ($row = $result->fetch_assoc()) {
    $wo_id = (int)$row['wo_id'];
    $encrypted_id = encrypt_id($wo_id); // <<< ENKRIPSI ID ASLI

    $current_status = $row['status'] ?? 'Open';
    
    $formatted_priority = getPriorityBadge($row['priority'] ?? 'Low');
    $formatted_status = getStatusBadge($current_status);
    $req_type = '<b>' . htmlspecialchars($row['departemen_name'] ?? 'N/A') . '</b>';
    $req_type .= '<br>* ' . htmlspecialchars($row['category_name'] ?? 'N/A') . ' / ' . htmlspecialchars($row['subcategory_name'] ?? 'N/A');
    $req_detail = '<div style="max-width: 250px; white-space: normal;">' . htmlspecialchars($row['description'] ?? '') . '</div>';
    $updated_ts = $row['latest_update'] ? strtotime($row['latest_update']) : strtotime($row['date_request']);
    $updated_date = date('m/d/y h:i a', $updated_ts);
    $latest_note = '<div style="max-width: 200px; white-space: normal;">' . htmlspecialchars($row['latest_note'] ?? 'N/A') . '</div>';
    
    // --- Kontruksi Tombol Aksi ---
    $actions = '<div class="btn-group btn-group-sm" role="group" aria-label="Aksi Work Order">';
    
    // 1. Tombol VIEW/DETAIL
    if ($can_access_view) {
        $actions .= '<a href="work_order_view.php?id=' . $encrypted_id . '" class="btn btn-info" title="Lihat Detail/Akses"><i class="fas fa-eye"></i></a>';
    } else {
        $actions .= '<a href="#" class="btn btn-info disabled" title="Tidak ada akses Detail" style="pointer-events: none;"><i class="fas fa-eye"></i></a>';
    }
    
    // 2. Tombol PRINT/CETAK (Hanya muncul jika Status = 'Closed')
    if (strtolower($current_status) === 'closed') {
        // Mengarah ke export_pdf.php yang akan menampilkan HTML untuk dicetak manual (gunakan encrypted ID)
        $actions .= '<a href="export_pdf.php?id=' . $encrypted_id . '" target="_blank" class="btn btn-secondary" title="Cetak Work Order"><i class="fas fa-print"></i></a>';
    } else {
        $actions .= '<a href="#" class="btn btn-secondary disabled" title="Work Order Belum Ditutup" style="pointer-events: none;"><i class="fas fa-print"></i></a>';
    }

    // 3. Tombol DELETE/Soft Delete
    if ($can_delete) {
        // Panggil fungsi JS showDeleteModal dari work_order_list.php
        $actions .= '<button type="button" onclick="showDeleteModal(\'' . $encrypted_id . '\', ' . $wo_id . ')" class="btn btn-danger" title="Nonaktifkan (Soft Delete)"><i class="fas fa-trash"></i></button>';
    } else {
        $actions .= '<a href="#" class="btn btn-danger disabled" title="Tidak ada akses Hapus" style="pointer-events: none;"><i class="fas fa-trash"></i></a>';
    }
    $actions .= '</div>';

    $data[] = [
        $wo_id, 
        $updated_date, 
        $req_type, 
        $req_detail, 
        $latest_note, 
        $formatted_status, 
        $formatted_priority, 
        $actions 
    ];
}

// 5. KIRIM RESPONSE JSON
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => $data
]);
exit;