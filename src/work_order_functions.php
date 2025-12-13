<?php
// src/work_order_functions.php - Kumpulan Fungsi Work Order dan Helper (FINAL)

require_once __DIR__ . '/Database.php';

function getDb() {
  // ASUMSI: Database class menyediakan instance mysqli
  return Database::getInstance();
}

// =================================================================
// I. FUNGSI HELPER DROPDOWN 
// =================================================================

function getCategories() {
  $db = getDb();
  $res = $db->query("SELECT id, name FROM ticket_category ORDER BY name ASC");
  return $res->fetch_all(MYSQLI_ASSOC);
}

function getDepartemen() {
  $db = getDb();
  $sql = "SELECT departemen_id, departemen_name FROM departemen WHERE departemen_status = 0 ORDER BY departemen_name ASC";
  $res = $db->query($sql);
  return $res->fetch_all(MYSQLI_ASSOC);
}

function getLocations() {
  $db = getDb();
  $sql = "SELECT location_id, location_name FROM location WHERE location_status = 0 ORDER BY location_name ASC";
  $res = $db->query($sql);
  return $res->fetch_all(MYSQLI_ASSOC);
}

function getWorkTypes() {
  $db = getDb();
  $sql = "SELECT type_id, type_name FROM work_type WHERE status = 0 ORDER BY type_name ASC";
  $res = $db->query($sql);
  return $res->fetch_all(MYSQLI_ASSOC);
}

function getTeams() {
  $db = getDb();
  $res = $db->query("SELECT id, name FROM team WHERE status=0 ORDER BY name ASC");
  return $res->fetch_all(MYSQLI_ASSOC);
}

// =================================================================
// II. FUNGSI BADGE (Visualisasi Status & Prioritas)
// =================================================================

function getStatusBadge(string $status) {
  $status = strtolower($status);
  $class = '';
  switch ($status) {
    case 'closed':
      $class = 'secondary';
      break;
    case 'progress':
      $class = 'primary';
      break;
    case 'open':
      $class = 'warning text-dark';
      break;
    case 'deleted': 
      $class = 'danger';
      break;
    default:
      $class = 'secondary';
  }
  return '<span class="badge bg-' . $class . '">' . htmlspecialchars(ucwords($status)) . '</span>';
}

function getPriorityBadge(string $priority) {
  $priority = strtolower($priority);
  $class = '';
  switch ($priority) {
    case 'high':
      $class = 'danger'; 
      break;
    case 'medium':
      $class = 'warning text-dark'; 
      break;
    case 'low':
      $class = 'success'; 
      break;
    default:
      $class = 'secondary';
  }
  return '<span class="badge bg-' . $class . '">' . htmlspecialchars(ucwords($priority)) . '</span>';
}

// =================================================================
// III. FUNGSI TRANSAKSI UTAMA (Work Order Creation & Soft Delete)
// =================================================================

function saveWorkOrder($data) {
  $db = getDb();
  
  // --- Data WO ---
  $requester_id = intval($data['user_id'] ?? 0);
  
  $department = intval($data['department'] ?? 0);
  $location = intval($data['location'] ?? 0);
  $work_type = intval($data['work_type'] ?? 0);
  $team = intval($data['team'] ?? 0);
  $priority = trim($data['priority'] ?? 'Medium');
  $SN = trim($data['SN'] ?? '');
  $description = trim($data['description'] ?? '');
  
  // --- Data Item ---
  $category = intval($data['main_category'] ?? 0);
  $subcategory = intval($data['subcategory'] ?? 0);
  $quantity = max(1, intval($data['quantity'] ?? 1)); 
  // MENGAMBIL COST SEBAGAI INTEGER
  $cost = intval($data['cost'] ?? 0);
  
  // --- Status Default ---
  $status_enum_default = 'open'; // Sesuai ENUM di DB: 'open', 'progress', 'closed'
  $status_wo_active = 0; // Sesuai TINYINT di DB: 0 (Aktif)
  $status_item_active = 0; // ASUMSI status item default (misal: "Requested")

  // Validasi
  if (!$requester_id || !$department || !$location || !$work_type || !$team)
    return ['error' => "Semua field wajib diisi (kecuali SN), termasuk ID Pemohon (Masalah otentikasi)."];

  // VALIDASI UNTUK ITEM, SEKARANG HANYA MENGANDALKAN $cost
  if (!$category || !$subcategory || $quantity < 1 || $cost = 0)
    return ['error' => "Data barang (Kategori, Subkategori, Quantity, dan Estimasi Biaya) harus diisi dengan benar. Quantity minimal 1, Estimasi Biaya minimal 0."];

  $db->begin_transaction();
  try {
    // 1. INSERT ke work_order
    $stmt = $db->prepare("INSERT INTO work_order
      (requester_id, department, location, work_type, team, priority, SN, description, date_request, status, status_wo)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)");
      
    // Type definition (10 parameter):
    $stmt->bind_param('iiiiissssi', 
      $requester_id, 
      $department, 
      $location, 
      $work_type, 
      $team, 
      $priority, 
      $SN, 
      $description, 
      $status_enum_default, // status ENUM 'open'
      $status_wo_active   // status_wo TINYINT 0
    );
    $stmt->execute();
    $wo_id = $db->insert_id;
    $stmt->close();

    // 2. INSERT ke work_order_items
        // Kolom yang di-INSERT: wo_id, category, subcategory, qty, cost, status (5 kolom data)
    $stmtItem = $db->prepare("INSERT INTO work_order_items
                  (wo_id, category, subcategory, qty, cost, status)
                  VALUES (?, ?, ?, ?, ?, ?)");
                  
    // Type definition: i (wo_id), i (category), i (subcategory), i (qty), i (cost), i (status)
    $stmtItem->bind_param('iiiiii', 
            $wo_id, 
            $category, 
            $subcategory, 
            $quantity, 
            $cost, // Sekarang kolom cost (integer)
            $status_item_active
        );
    $stmtItem->execute();
    $stmtItem->close();

    $db->commit();
    return ['success' => "✅ Work Order berhasil disimpan. ID: $wo_id"];
  } catch (Exception $e) {
    $db->rollback();
    // Ambil pesan error MySQL
    $db_error = $db->error;
    error_log("Gagal menyimpan WO: " . $e->getMessage() . " | DB Error: " . $db_error);
    
    // Kembalikan pesan error yang lebih detail HANYA di lingkungan development (sebaiknya diatur)
    return ['error' => "❌ Gagal menyimpan Work Order. Detail: " . htmlspecialchars($e->getMessage()) . " | SQL Error: " . htmlspecialchars($db_error)];
  }
}

/**
* Melakukan Soft Delete pada Work Order.
* status_wo = 1 berarti Soft Delete.
* @param int $wo_id ID Work Order
* @return array Hasil operasi
*/
function softDeleteWorkOrder(int $wo_id): array {
  $db = getDb();
  $deleted_status_db = 1; // status_wo = 1 (Soft Delete)
  $closed_enum = 'closed'; // Ganti status WO menjadi 'closed' sesuai ENUM Anda

  try {
    // Cek apakah WO sudah dinonaktifkan (status_wo = 1)
    $checkStmt = $db->prepare("SELECT status_wo FROM work_order WHERE wo_id = ?");
    $checkStmt->bind_param('i', $wo_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $data = $result->fetch_assoc();
    $checkStmt->close();

    if (!$data) {
      return ['error' => "Work Order ID #$wo_id tidak ditemukan."];
    }

    if ($data['status_wo'] == $deleted_status_db) {
      return ['error' => "Work Order ID #$wo_id sudah dinonaktifkan sebelumnya (status_wo = 1)."];
    }

    // Lakukan Soft Delete: Set status_wo = 1 dan set status ENUM ke 'closed'
    $stmt = $db->prepare("UPDATE work_order SET status = ?, status_wo = ? WHERE wo_id = ? AND status_wo = 0");
    $stmt->bind_param('sii', $closed_enum, $deleted_status_db, $wo_id);
    $success = $stmt->execute();
    
    if (!$success) {
      $error_message = $stmt->error;
      $stmt->close();
      return ['error' => "⚠️ SQL ERROR saat soft delete: " . htmlspecialchars($error_message)];
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    if ($affected_rows > 0) {
      return ['success' => "Work Order ID #$wo_id berhasil dinonaktifkan (Soft Delete). Status WO di DB diubah menjadi 'closed', **status_wo menjadi 1**."];
    } else {
      return ['error' => "Gagal menonaktifkan Work Order. WO tidak ditemukan atau WO sudah berstatus soft delete."];
    }
  } catch (Exception $e) {
    error_log("Gagal Soft Delete WO: " . $e->getMessage());
    return ['error' => "Gagal menonaktifkan WO: " . $e->getMessage()];
  }
}


// =================================================================
// IV. FUNGSI KOMENTAR/STATUS
// =================================================================

function getWorkOrderComments(int $wo_id) {
  $db = getDb();
  $stmt = $db->prepare("
    SELECT
      c.*,
      u.name AS team_member_name
    FROM comments c
    LEFT JOIN users u ON c.team_member = u.id
    WHERE c.ticket = ?
    ORDER BY c.created_at ASC
  ");
  $stmt->bind_param('i', $wo_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $stmt->close();
  return $res->fetch_all(MYSQLI_ASSOC);
}

function addWorkOrderComment(array $data) {
  $db = getDb();
  $wo_id = intval($data['wo_id'] ?? 0);
  $team_member_id = intval($data['team_member_id'] ?? 0);
  $body = trim($data['comment_body'] ?? '');
  $private = isset($data['private']) ? 1 : 0;
  
  // Tentukan nilai default untuk kolom 'status' (sesuai dump Anda, status=0 adalah default)
  $comment_status = 0; 

  if (!$wo_id || !$team_member_id || empty($body)) {
    return ['error' => "Data komentar tidak lengkap."];
  }

  try {
    // PERBAIKAN: Tambahkan kolom status ke dalam INSERT dan bind param
    $stmt = $db->prepare("
      INSERT INTO comments (ticket, team_member, private, body, created_at, status)
      VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    
    // Perubahan pada bind_param: Menambahkan 'i' untuk status
    $stmt->bind_param('iiisi', $wo_id, $team_member_id, $private, $body, $comment_status); 
    $stmt->execute();
    $stmt->close();
    return ['success' => "Komentar berhasil ditambahkan."];
  } catch (Exception $e) {
    error_log("Gagal menyimpan komentar: " . $e->getMessage());
    return ['error' => "Gagal menyimpan komentar: " . $e->getMessage()];
  }
}

/**
* Update status WO hanya menerima nilai ENUM ('open', 'progress', 'closed').
*/
function updateWorkOrderStatus(int $wo_id, string $status) {
  $db = getDb();
  
  $allowed_statuses = ['open', 'progress', 'closed'];
  $status = strtolower($status);
  $status_wo_active = 0; // Filter: hanya WO aktif (status_wo = 0) yang bisa diupdate status ENUM-nya.
  
  if (!in_array($status, $allowed_statuses)) {
    return ['error' => "Status '$status' tidak valid. Hanya boleh: " . implode(', ', $allowed_statuses)];
  }
  
  try {
    // Hanya update jika status_wo = 0 (Aktif)
    $stmt = $db->prepare("UPDATE work_order SET status = ?, updated_at = NOW() WHERE wo_id = ? AND status_wo = ?"); 
    $stmt->bind_param('sii', $status, $wo_id, $status_wo_active);
    $success = $stmt->execute();
    
    if (!$success) {
      $error_message = $stmt->error;
      $stmt->close();
      return ['error' => "⚠️ SQL ERROR saat update status: " . htmlspecialchars($error_message)];
    }
    
    if ($stmt->affected_rows > 0) {
      $stmt->close();
      return ['success' => "Status WO berhasil diubah menjadi **" . htmlspecialchars($status) . "**."];
    } else {
      $stmt->close();
      return ['error' => "Status WO tidak berubah. Mungkin WO sudah dinonaktifkan (**status_wo = 1**) atau status sudah sama."];
    }
  } catch (Exception $e) {
    error_log("Gagal mengubah status WO: " . $e->getMessage());
    return ['error' => "Gagal mengubah status: " . $e->getMessage()];
  }
}