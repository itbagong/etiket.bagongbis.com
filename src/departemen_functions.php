<?php
// src/departemen_function.php
require_once 'Database.php';

function getDb() {
    // Asumsi class Database ada dan memiliki method getInstance()
    return Database::getInstance();
}

function getDepartemenById($id) {
    $db = getDb();
    $id = intval($id);
    $res = $db->query("SELECT * FROM departemen WHERE departemen_id = $id");
    return $res ? $res->fetch_assoc() : null;
}

function saveDepartemen($data) {
    $db = getDb();
    $id = intval($data['departemen_id'] ?? 0);
    $name = $db->real_escape_string($data['departemen_name']);
    $abb = $db->real_escape_string($data['departemen_abb']);
    $status = intval($data['departemen_status']);

    if (empty($name) || empty($abb)) {
         // Tambahkan validasi dasar
        return false;
    }

    if ($id > 0) {
        // UPDATE query
        return $db->query("UPDATE departemen SET departemen_name='$name', departemen_abb='$abb', departemen_status=$status WHERE departemen_id=$id");
    } else {
        // INSERT query
        return $db->query("INSERT INTO departemen (departemen_name, departemen_abb, departemen_status) VALUES ('$name', '$abb', $status)");
    }
}

function deleteDepartemen($id) {
    $db = getDb();
    return $db->query("DELETE FROM departemen WHERE departemen_id = " . intval($id));
}
// Tambahkan fungsi lain yang dibutuhkan untuk Ajax DataTables jika perlu