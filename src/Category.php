<?php
// src/Category.php - Model untuk ticket_category (Updated: Soft Delete & Prepared Statements)

class Category {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // CREATE / UPDATE
    // Menggunakan Prepared Statement dan mengabaikan status=0 untuk insert/update
    public function save($name, $id = 0) {
        $id = intval($id);
        
        if ($id > 0) {
            // Update
            $stmt = $this->db->prepare("UPDATE ticket_category SET name=? WHERE id=?");
            if (!$stmt) throw new Exception("Prepare failed: " . $this->db->error);
            $stmt->bind_param("si", $name, $id);
            if ($stmt->execute()) return true;
            throw new Exception("Gagal memperbarui kategori: " . $stmt->error);
        } else {
            // Insert (status default 0/Aktif)
            $stmt = $this->db->prepare("INSERT INTO ticket_category(name, status) VALUES(?, 0)");
            if (!$stmt) throw new Exception("Prepare failed: " . $this->db->error);
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) return $this->db->insert_id;
            throw new Exception("Gagal menambahkan kategori: " . $stmt->error);
        }
    }

    // DELETE (Soft Delete: Mengubah status menjadi 1/Tidak Aktif)
    // Static method tetap dipertahankan agar bisa dipanggil dari category_list.php
    public static function delete($id) {
        $db = Database::getInstance();
        $id = intval($id);
        
        $stmt = $db->prepare("UPDATE ticket_category SET status = 1 WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $db->error);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
             // Periksa jika ada baris yang terpengaruh (artinya kategori ditemukan)
             return $stmt->affected_rows > 0;
        }
        throw new Exception("Soft delete kategori gagal: " . $stmt->error);
    }
    
    // FIND ALL (Hanya yang Aktif/status=0)
    public static function findAll() {
        $db = Database::getInstance();
        // Hanya tampilkan yang statusnya 0 (Aktif)
        $result = $db->query("SELECT * FROM ticket_category WHERE status = 0 ORDER BY id DESC");
        if (!$result) throw new Exception("Query failed: " . $db->error);
        return $result;
    }

    // FIND ONE
    public static function find($id) {
        $db = Database::getInstance();
        $id = intval($id);
        $stmt = $db->prepare("SELECT * FROM ticket_category WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $db->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : false;
    }
}