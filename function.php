<?php
require_once __DIR__ . '/src/Database.php';
$db = Database::getInstance();

// === Fungsi ambil statistik utama ===
function getStatusStats($filter = null) {
    $conn = getConnection();
    $where = "";

    if ($filter === "weekly") {
        $where = "WHERE YEARWEEK(date_request, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($filter === "monthly") {
        $where = "WHERE MONTH(date_request) = MONTH(CURDATE()) AND YEAR(date_request) = YEAR(CURDATE())";
    }

    $sql = "SELECT 
                SUM(status='open') AS open_count,
                SUM(status='progress') AS progress_count,
                SUM(status='done') AS done_count,
                SUM(status='closed') AS closed_count,
                COUNT(*) AS total
            FROM work_order $where";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// === Statistik per kategori ===
function getCategoryStats($filter = null) {
    $conn = getConnection();
    $where = "";

    if ($filter === "weekly") {
        $where = "WHERE YEARWEEK(w.date_request, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($filter === "monthly") {
        $where = "WHERE MONTH(w.date_request) = MONTH(CURDATE()) AND YEAR(w.date_request) = YEAR(CURDATE())";
    }

    $sql = "SELECT c.name AS category, COUNT(w.wo_id) AS total
            FROM work_order w
            JOIN ticket_category c ON c.id = w.work_type
            $where
            GROUP BY c.name
            ORDER BY total DESC";
    $res = $conn->query($sql);

    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    return $data;
}

// === Statistik per lokasi ===
function getLocationStats($filter = null) {
    $conn = getConnection();
    $where = "";

    if ($filter === "weekly") {
        $where = "WHERE YEARWEEK(date_request, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($filter === "monthly") {
        $where = "WHERE MONTH(date_request) = MONTH(CURDATE()) AND YEAR(date_request) = YEAR(CURDATE())";
    }

    $sql = "SELECT l.name AS location, COUNT(w.wo_id) AS total
            FROM work_order w
            JOIN location l ON l.id = w.location
            $where
            GROUP BY l.name
            ORDER BY total DESC";
    $res = $conn->query($sql);

    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    return $data;
}

// === Statistik per subkategori ===
function getSubcategoryStats($categoryId = null, $filter = null) {
    $conn = getConnection();
    $where = "";

    if ($filter === "weekly") {
        $where = "AND YEARWEEK(w.date_request, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($filter === "monthly") {
        $where = "AND MONTH(w.date_request) = MONTH(CURDATE()) AND YEAR(w.date_request) = YEAR(CURDATE())";
    }

    $categoryFilter = "";
    if ($categoryId) {
        $categoryFilter = "AND c.id = " . intval($categoryId);
    }

    $sql = "SELECT s.name AS subcategory, COUNT(w.wo_id) AS total
            FROM work_order w
            JOIN ticket_category c ON c.id = w.work_type
            JOIN ticket_subcategory s ON s.id = w.team
            WHERE 1=1 $categoryFilter $where
            GROUP BY s.name
            ORDER BY total DESC";
    $res = $conn->query($sql);

    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    return $data;
}

// === Ambil daftar kategori untuk dropdown ===
function getCategories() {
    $conn = getConnection();
    $res = $conn->query("SELECT id, name FROM ticket_category ORDER BY name ASC");
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    return $data;
}

// === 5 Work Order Terakhir ===
function getRecentWorkOrders() {
    $conn = getConnection();
    $sql = "SELECT wo_id, requester, status, date_request 
            FROM work_order 
            ORDER BY created_at DESC 
            LIMIT 5";
    $res = $conn->query($sql);
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    return $data;
}
