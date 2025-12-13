<?php
// ajax/ajax_get_subcategories.php - MEMUAT SUBKATEGORI (Tidak Ada Perubahan)

require_once '../src/Database.php';
$db = Database::getInstance();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

$catId = intval($_GET['category_id'] ?? 0);
$out = [];

if ($catId > 0) {
    $stmt = $db->prepare("SELECT id, name FROM ticket_subcategory WHERE category_id = ? ORDER BY name ASC");
    $stmt->bind_param('i', $catId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;