<?php
// ajax/ajax_get_notifications.php - FINAL DENGAN ID MENTAH DAN ENKRIPSI

session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['logged-in']) || $_SESSION['logged-in'] == false){
  echo json_encode(['open_count' => 0, 'progress_count' => 0, 'data' => []]);
  exit();
}

require_once '../src/Database.php';
require_once '../src/security.php';

$db = Database::getInstance();

$response = [
  'open_count' => 0,
  'progress_count' => 0,
  'data' => []
];

try {
  $sql_data = "
    SELECT
      wo.wo_id AS id_raw,     /* ID mentah (TAMPILAN) */
      wo.wo_id,          /* ID mentah (ENKRIPSI) */
      u.name AS requester,    /* DIGANTI: Ambil nama dari tabel users */
      d.departemen_name AS department,
      DATE_FORMAT(wo.date_request, '%d %b %Y') AS date,
      wo.status AS status_wo
    FROM work_order wo
    JOIN departemen d
      ON wo.department = d.departemen_id
    JOIN users u          /* JOIN ke tabel users */
      ON wo.requester_id = u.id  /* Kunci JOIN */
    WHERE wo.status IN ('open', 'progress')
    ORDER BY FIELD(wo.status, 'open', 'progress') ASC, wo.date_request DESC, wo.wo_id DESC
    LIMIT 7";

  $result = $db->query($sql_data);
 
  if ($result === false) {
    throw new Exception("SQL Data Query Failed.");
  }
 
  $wo_data_encrypted = [];
  while ($row = $result->fetch_assoc()) {
    $row['id'] = encrypt_id($row['wo_id']); // ID terenkripsi untuk URL
    unset($row['wo_id']);
   
    $wo_data_encrypted[] = $row;
  }

  $count_open = $db->query("SELECT COUNT(wo_id) AS total FROM work_order WHERE status = 'open'")->fetch_assoc()['total'];
  $count_progress = $db->query("SELECT COUNT(wo_id) AS total FROM work_order WHERE status = 'progress'")->fetch_assoc()['total'];

  $response['open_count'] = (int)$count_open;
  $response['progress_count'] = (int)$count_progress;
  $response['data'] = $wo_data_encrypted;

  echo json_encode($response);

} catch (Exception $e) {
  error_log("Notification DB Error: " . $e->getMessage());
  echo json_encode(['open_count' => 0, 'progress_count' => 0, 'data' => [], 'error' => 'Server Error']);
}
?>