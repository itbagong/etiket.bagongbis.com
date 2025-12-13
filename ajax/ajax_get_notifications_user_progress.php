<?php
// ajax/ajax_get_notifications_user_progress.php - FIX OUTPUT DAN GABUNGAN HITUNGAN

session_start();
header('Content-Type: application/json');

$response = [
    'progress_count_user' => 0, 
    'open_count' => 0,          
    'progress_count' => 0,      
    'total_active' => 0,        
    'data' => [],               
    'debug_user_id' => 'INIT FAILED' 
];

// --- Pengecekan Sesi Mutlak ---
if(
    !isset($_SESSION['logged-in']) || 
    $_SESSION['logged-in'] == false || 
    !isset($_SESSION['user']) || 
    !isset($_SESSION['user']->id)
) {
    $response['debug_user_id'] = 'SESI GAGAL (No Login/No ID Object)';
    echo json_encode($response);
    exit(); // PENTING: Berhenti segera setelah output error sesi
}

require_once '../src/Database.php';
require_once '../src/security.php'; 

$db = Database::getInstance();

$user_object = $_SESSION['user'];
$user_id = (int)$user_object->id;

$response['debug_user_id'] = $user_id;

if ($user_id <= 0) {
    $response['debug_user_id'] = 'ID USER TIDAK VALID/NOL';
    echo json_encode($response);
    exit(); // PENTING: Berhenti segera setelah output error ID
}

try {
    $where_requester = " wo.requester_id = {$user_id} ";
    
    // =========================================================
    // A. HITUNGAN GLOBAL (OPEN & PROGRESS)
    // =========================================================
    
    // --- QUERY COUNT OPEN ---
    $sql_count_open = "SELECT COUNT(wo_id) AS total FROM work_order wo WHERE {$where_requester} AND wo.status = 'open'";
    $count_open = $db->query($sql_count_open)->fetch_assoc()['total'];
    
    // --- QUERY COUNT PROGRESS ---
    $sql_count_progress = "SELECT COUNT(wo_id) AS total FROM work_order wo WHERE {$where_requester} AND wo.status = 'progress'";
    $count_progress = $db->query($sql_count_progress)->fetch_assoc()['total'];
    
    $response['open_count'] = (int)$count_open;
    $response['progress_count'] = (int)$count_progress;
    $response['total_active'] = (int)$count_open + (int)$count_progress;
    
    // =========================================================
    // B. DATA NOTIFIKASI PROGRESS 
    // =========================================================
    
    $where_condition_progress = " {$where_requester} AND wo.status = 'progress' ";

    $sql_data = "
        SELECT
            wo.wo_id AS id_raw,     
            wo.wo_id,             
            wo.description,
            DATE_FORMAT(wo.date_request, '%d %b %Y') AS date_display,
            wo.status AS status_wo
        FROM work_order wo 
        WHERE {$where_condition_progress}
        ORDER BY wo.date_request DESC, wo.wo_id DESC
        LIMIT 7";

    $result = $db->query($sql_data);
    
    if ($result === false) {
        throw new Exception("SQL Data Query Failed. MySQL Error: " . $db->error);
    }
    
    $wo_data_encrypted = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = encrypt_id($row['wo_id']); 
        $row['status_wo'] = $row['status_wo'] ?? 'progress'; 
        $row['date_display'] = $row['date_display'] ?? ''; 
        unset($row['wo_id']);
        $wo_data_encrypted[] = $row;
    }

    $response['progress_count_user'] = $response['progress_count']; 
    $response['data'] = $wo_data_encrypted;

    // OUTPUT UTAMA YANG BERHASIL
    echo json_encode($response);
    exit(); // PENTING: Berhenti total setelah output JSON yang valid

} catch (Exception $e) {
    error_log("Progress Notification Error for User {$user_id}: " . $e->getMessage());
    echo json_encode([
        'progress_count_user' => 0, 
        'data' => [], 
        'error' => $e->getMessage(),
        'debug_user_id' => $user_id
    ]);
    exit(); // PENTING: Berhenti total setelah output JSON error
}