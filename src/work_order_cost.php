<?php
// src/work_order_cost.php - API Endpoint KHUSUS CHART BIAYA

// ===============================================
// 1. AKTIFKAN PELAPORAN KESALAHAN (DEBUGGING)
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ===============================================

// SESUAIKAN PATH INI
require_once '../function.php';

// --- START RBAC IMPLEMENTATION FOR API ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set header JSON (harus sebelum exit)
header('Content-Type: application/json');

// Cek Otentikasi dan Otorisasi (Hanya Admin & Teknisi)
if (!isset($_SESSION['logged-in']) || $_SESSION['logged-in'] !== true) {
    // Pengguna belum login
    echo json_encode([
        'error' => true,
        'message' => 'Unauthorized access. Please login.',
        'http_status' => 401
    ]);
    http_response_code(401);
    exit;
}

$user_role = $_SESSION['user']->role ?? 'guest';
$allowed_roles = ['admin', 'teknisi']; // Hanya role ini yang boleh melihat data biaya

if (!in_array($user_role, $allowed_roles)) {
    // Pengguna login tapi tidak memiliki izin (misalnya role 'user')
    echo json_encode([
        'error' => true,
        'message' => 'Forbidden. Access denied for this role.',
        'http_status' => 403
    ]);
    http_response_code(403);
    exit;
}
// --- END RBAC IMPLEMENTATION FOR API ---


// ===============================================
// 2. CEK KONEKSI DATABASE
try {
  $conn = Database::getInstance();
  if (!$conn) {
    throw new Exception("Gagal membuat instance koneksi database.");
  }
} catch (\Throwable $e) {
  echo json_encode([
    'error' => 'Database connection failed or class not found.',
    'message' => $e->getMessage()
  ]);
  exit;
}
// ===============================================

// =================================================================
// FUNGSI HELPER: Perbaikan Query Tanggal
// =================================================================

/**
* FUNGSI UTAMA: Menghitung total biaya dalam rentang tanggal tertentu.
* Diperbaiki: Menggunakan >= dan <= di query untuk mengatasi masalah DATETIME.
*/
function calculatePeriodCost($db, $startDate, $endDate, $locationId = 'all'): float {
  if (empty($startDate) || empty($endDate) || strtotime($startDate) > strtotime($endDate)) {
    return 0.0;
  }

  $location_condition = "";
  if ($locationId !== 'all' && is_numeric($locationId)) {
    $location_condition = "AND wo.location = " . intval($locationId);
  }
 
  // Menggunakan >= startDate dan <= endDate
  $sql = "
    SELECT SUM(woi.cost * woi.qty) AS total_cost
    FROM work_order_items woi
    JOIN work_order wo ON woi.wo_id = wo.wo_id
    WHERE wo.status_wo = 0
     AND wo.date_request >= ? AND wo.date_request <= ?
     {$location_condition}
  ";
 
  $stmt = $db->prepare($sql);
  $stmt->bind_param('ss', $startDate, $endDate);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();

  return (float) ($row['total_cost'] ?? 0);
}

/**
* Mengambil Data Biaya Harian dalam satu Periode
* Diperbaiki: Menggunakan >= dan <= di query dan GROUP BY DATE()
*/
function getDailyCostInPeriod($db, $startDate, $endDate, $locationId = 'all'): array {
  $location_condition = "";
  if ($locationId !== 'all' && is_numeric($locationId)) {
    $location_condition = "AND wo.location = " . intval($locationId);
  }
 
  $sql = "
    SELECT DATE(wo.date_request) AS cost_date, SUM(woi.cost * woi.qty) AS daily_total
    FROM work_order_items woi
    JOIN work_order wo ON woi.wo_id = wo.wo_id
    WHERE wo.status_wo = 0
     AND wo.date_request >= ? AND wo.date_request <= ?
     {$location_condition}
    GROUP BY DATE(wo.date_request)
    ORDER BY DATE(wo.date_request) ASC
  ";
 
  $stmt = $db->prepare($sql);
  $stmt->bind_param('ss', $startDate, $endDate);
  $stmt->execute();
  $result = $stmt->get_result();
  $daily_costs = [];
  while ($row = $result->fetch_assoc()) {
    $daily_costs[$row['cost_date']] = (float) $row['daily_total'];
  }
  $stmt->close();
 
  return $daily_costs;
}

// =================================================================
// 3. PENGAMBILAN FILTER KHUSUS BIAYA
// =================================================================

$location_id = $_GET['location_id'] ?? 'all';

$default_end_A = date('Y-m-t', strtotime('today'));
$default_start_A = date('Y-m-01', strtotime('today'));
$default_end_B = date('Y-m-t', strtotime('last month'));
$default_start_B = date('Y-m-01', strtotime('last month'));

$start_A = $_GET['start_A'] ?? $default_start_A;
$end_A = $_GET['end_A'] ?? $default_end_A;
$label_A = date('d M Y', strtotime($start_A)) . ' - ' . date('d M Y', strtotime($end_A));

$start_B = $_GET['start_B'] ?? $default_start_B;
$end_B = $_GET['end_B'] ?? $default_end_B;
$label_B = date('d M Y', strtotime($start_B)) . ' - ' . date('d M Y', strtotime($end_B));


// ----------------------------------------------------------------------------------------------------------------------
// === A. DATA LOKASI (Dibutuhkan oleh dropdown di client) - Fix: Hapus status = 0
// ----------------------------------------------------------------------------------------------------------------------
$locations_list = ['all' => 'All Area'];
// Query diperbaiki dengan menghilangkan WHERE status=0
$resLoc = $conn->query("SELECT location_id, location_name FROM location ORDER BY location_name ASC");

if ($resLoc) {
  while ($r = $resLoc->fetch_assoc()) {
    $locations_list[$r['location_id']] = $r['location_name'];
  }
}

// ----------------------------------------------------------------------------------------------------------------------
// === B. DATA BIAYA WORK ORDER (Cost Chart)
// ----------------------------------------------------------------------------------------------------------------------

// 1. Total Biaya
$cost_A = calculatePeriodCost($conn, $start_A, $end_A, $location_id);
$cost_B = calculatePeriodCost($conn, $start_B, $end_B, $location_id);

// 2. Data Chart Tren Bulanan
$monthly_costs = [];
$location_condition_cost_trend = is_numeric($location_id) ? "AND wo.location = " . intval($location_id) : "";
$sql_chart_cost_trend = "
  SELECT DATE_FORMAT(wo.date_request, '%Y-%m') AS month_year, SUM(woi.cost * woi.qty) AS total_cost
  FROM work_order_items woi JOIN work_order wo ON woi.wo_id = wo.wo_id
  WHERE wo.status_wo = 0 {$location_condition_cost_trend}
  AND wo.date_request >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
  GROUP BY month_year ORDER BY month_year ASC
";

$result_chart_cost_trend = $conn->query($sql_chart_cost_trend);
if ($result_chart_cost_trend) {
  while ($row = $result_chart_cost_trend->fetch_assoc()) {
    $monthly_costs[] = [
      'month_year' => $row['month_year'],
      'total_cost' => (float) $row['total_cost']
    ];
  }
}

// 3. Data Chart Perbandingan Harian
$daily_costs_A_raw = getDailyCostInPeriod($conn, $start_A, $end_A, $location_id);
$daily_costs_B_raw = getDailyCostInPeriod($conn, $start_B, $end_B, $location_id);

$all_dates = array_unique(array_merge(array_keys($daily_costs_A_raw), array_keys($daily_costs_B_raw)));
sort($all_dates);

$daily_comparison_data = [];
foreach ($all_dates as $date) {
  $daily_comparison_data[] = [
    'date' => $date,
    'cost_A' => $daily_costs_A_raw[$date] ?? 0.0,
    'cost_B' => $daily_costs_B_raw[$date] ?? 0.0,
  ];
}


// ===============================================
// OUTPUT AKHIR JSON KHUSUS BIAYA
// ===============================================
echo json_encode([
  'params' => [
    'start_A' => $start_A, 'end_A' => $end_A, 'label_A' => $label_A,
    'start_B' => $start_B, 'end_B' => $end_B, 'label_B' => $label_B,
    'location_filter_id' => $location_id,
  ],
  'locations' => $locations_list,
  'totals' => ['cost_A' => $cost_A, 'cost_B' => $cost_B],
  'daily_comparison_data' => $daily_comparison_data,
  'monthly_trend_data' => $monthly_costs,
]);