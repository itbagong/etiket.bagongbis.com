<?php
// src/work_order_view_helpers.php - Kumpulan Fungsi Pembantu Khusus untuk VIEWING Work Order

// ASUMSI: Fungsi getDb() harus tersedia atau di-require dari file lain.
// ASUMSI: Fungsi getWorkOrderStatusText() dan getWorkOrderStatusClass() DIBIARKAN.

// =================================================================
// V. FUNGSI VIEWING WORK ORDER (Untuk Tampilan User/Admin)
// =================================================================

/**
* Mendapatkan catatan/komentar terbaru untuk ditampilkan sebagai 'Latest Notes' di list view.
* Hanya mengambil komentar yang BUKAN private.
* @param int $wo_id ID Work Order
* @return string Catatan terbaru atau string kosong.
*/
function getLatestNoteByWoId(int $wo_id): string {
  $db = getDb();
  $stmt = $db->prepare("
    SELECT body
    FROM comments
    WHERE ticket = ? AND private = 0
    ORDER BY created_at DESC
    LIMIT 1
  ");
  $stmt->bind_param('i', $wo_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $note = $result->fetch_assoc()['body'] ?? '';
  $stmt->close();
  return $note;
}

/**
* Mengambil semua Work Order berdasarkan ID Requestor (integer).
* PENTING: Menggunakan requester_id (INT) sebagai filter.
* @param int $requesterId ID pengguna yang melakukan request (Requester ID)
* @param bool $includeInactive Jika true, sertakan WO dengan status_wo = 1
* @return array Array of Work Orders
*/
function getWorkOrdersByUserId(int $requesterId, bool $includeInactive = false): array {
  $db = getDb();
  // Menggunakan JOIN ke users untuk mendapatkan nama requester, dan JOIN ke work_type
  $sql = "
    SELECT
      wo.wo_id AS id,
      wo.updated_at,
      wt.type_name AS request_type,
      wo.description AS request_detail,
      wo.status AS status_text,
      wo.status_wo,
      wo.priority,
      u.name AS requestor_name -- Ambil nama dari tabel users
    FROM work_order wo
    JOIN work_type wt ON wo.work_type = wt.type_id
    JOIN users u ON wo.requester_id = u.id -- PERUBAHAN KRITIKAL: JOIN menggunakan requester_id
    WHERE wo.requester_id = ? -- PERUBAHAN KRITIKAL: Filter menggunakan requester_id
  ";
 
  if (!$includeInactive) {
    $sql .= " AND wo.status_wo = 0";
  }
 
  $sql .= " ORDER BY wo.updated_at DESC";

  $stmt = $db->prepare($sql);
  // PERUBAHAN KRITIKAL: Ubah binding ke "i" (integer)
  $stmt->bind_param("i", $requesterId);
  $stmt->execute();
  $result = $stmt->get_result();
  $workOrders = [];
 
  while ($row = $result->fetch_assoc()) {
    $row['latest_notes'] = getLatestNoteByWoId($row['id']);
    $workOrders[] = $row;
  }
 
  $stmt->close();
  return $workOrders;
}

/**
* Mengambil satu Work Order berdasarkan ID utama (wo_id).
* Digunakan untuk validasi hak akses sebelum delete.
* PENTING: Sekarang mengambil requester_id (INT) dan nama (JOIN users).
* @param int $wo_id ID Work Order
* @return array|null Detail Work Order atau null jika tidak ditemukan
*/
function getWorkOrderById(int $wo_id): ?array {
  $db = getDb();
  // PERBAIKAN KRITIKAL: Ambil requester_id dan nama dari JOIN users
  $stmt = $db->prepare("
    SELECT
      wo.wo_id AS id,
      wo.requester_id,
      u.name AS requestor_name,
      wo.status_wo
    FROM work_order wo
    LEFT JOIN users u ON wo.requester_id = u.id -- Tambahkan JOIN untuk nama
    WHERE wo.wo_id = ?
  ");
  $stmt->bind_param("i", $wo_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $wo = $result->fetch_assoc();
  $stmt->close();
  return $wo;
}

/**
* Mengambil SEMUA detail Work Order beserta data itemnya.
* @param int $wo_id ID Work Order
* @return array|null Semua detail Work Order, termasuk array 'items', atau null jika WO tidak ditemukan.
*/
function getFullWorkOrderDetails(int $wo_id): ?array {
  $db = getDb();

  // 1. Ambil Detail Header Work Order dengan JOIN ke tabel lookup
  $sql_header = "
    SELECT
      wo.wo_id AS id,
      wo.requester_id, -- Tambahkan requester_id
      u.name AS requester_name, -- PERBAIKAN: Ambil nama dari JOIN
      wo.date_request,
      wo.updated_at,
      wo.SN,
      wo.description,
      wo.priority,
      wo.status AS status_enum,
      wo.status_wo,
      d.departemen_name AS department_name,
      l.location_name AS location_name,
      wt.type_name AS work_type_name,
      t.name AS team_name
    FROM work_order wo
    LEFT JOIN users u ON wo.requester_id = u.id -- PERBAIKAN: Join menggunakan requester_id
    LEFT JOIN departemen d ON wo.department = d.departemen_id
    LEFT JOIN location l ON wo.location = l.location_id
    LEFT JOIN work_type wt ON wo.work_type = wt.type_id
    LEFT JOIN team t ON wo.team = t.id
    WHERE wo.wo_id = ?
  ";

  $stmt_header = $db->prepare($sql_header);
  $stmt_header->bind_param("i", $wo_id);
  $stmt_header->execute();
  $result_header = $stmt_header->get_result();
  $wo_details = $result_header->fetch_assoc();
  $stmt_header->close();

  if (!$wo_details) {
    return null;
  }
 
  // Tambahkan helper status/badge
  $wo_details['status_text'] = getWorkOrderStatusText($wo_details['status_enum']);
  $wo_details['status_badge'] = getWorkOrderStatusClass($wo_details['status_enum']);


  // 2. Ambil Item Work Order (Asumsi hanya ada satu item)
  $sql_item = "
    SELECT
      woi.item_id,
      woi.qty,
      woi.cost, -- *** PERUBAHAN DI SINI: Mengganti 'reason' dengan 'cost' ***
      tc.name AS category_name,
      tsc.name AS subcategory_name
    FROM work_order_items woi
    JOIN ticket_category tc ON woi.category = tc.id
    JOIN ticket_subcategory tsc ON woi.subcategory = tsc.id
    WHERE woi.wo_id = ?
    ORDER BY woi.item_id ASC
  ";

  $stmt_item = $db->prepare($sql_item);
  $stmt_item->bind_param("i", $wo_id);
  $stmt_item->execute();
  $result_item = $stmt_item->get_result();
  $wo_details['items'] = $result_item->fetch_all(MYSQLI_ASSOC);
  $stmt_item->close();

  return $wo_details;
}

// =================================================================
// VI. FUNGSI BADGE & HELPER STATUS
// =================================================================

/**
* Mengonversi teks status ENUM menjadi teks yang ramah pengguna.
* @param string $status Teks status ENUM ('open', 'progress', 'closed', 'deleted', 'nonaktif')
* @return string Teks Status
*/
function getWorkOrderStatusText(string $status): string {
  $status = strtolower($status);
  switch ($status) {
    case 'open': return 'Terbuka';
    case 'progress': return 'Dalam Proses';
    case 'closed': return 'Selesai/Ditutup';
    case 'deleted':
    case 'nonaktif':
      return 'Dihapus/Nonaktif';
    default: return 'Tidak Diketahui';
  }
}

/**
* Mendapatkan class badge Bootstrap yang sesuai berdasarkan status WO (ENUM).
* @param string $status Teks status ENUM ('open', 'progress', 'closed', 'deleted', 'nonaktif')
* @return string Class Bootstrap (tanpa 'badge-')
*/
function getWorkOrderStatusClass(string $status): string {
  $status = strtolower($status);
  switch ($status) {
    case 'open':
      return 'warning text-dark';
    case 'progress':
      return 'primary';
    case 'closed':
      return 'success';
    case 'deleted':
    case 'nonaktif':
      return 'secondary';
    default:
      return 'secondary';
  }
}