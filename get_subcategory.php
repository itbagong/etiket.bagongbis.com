<?php
require_once './src/Subcategory.php';
header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['category_id'])) {
    $subs = Subcategory::findByCategory($_GET['category_id']);
    $data = [];

    foreach ($subs as $s) {
        $data[] = [
            'id' => $s->id,
            'name' => $s->name
        ];
    }

    echo json_encode($data);
    exit;
}

echo json_encode([]);
?>
