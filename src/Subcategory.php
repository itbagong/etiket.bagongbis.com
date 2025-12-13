<?php
// src/Subcategory.php (Asumsi - Wajibkan Anda untuk membuat/memverifikasi file ini)

class Subcategory {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // CREATE / UPDATE
    public function save($name, $category_id, $id = 0) {
        $id = intval($id);
        $category_id = intval($category_id);
        
        if ($id > 0) {
            // Update
            $stmt = $this->db->prepare("UPDATE ticket_subcategory SET name=?, category_id=? WHERE id=?");
            if (!$stmt) throw new Exception("Prepare failed: " . $this->db->error);
            $stmt->bind_param("sii", $name, $category_id, $id);
            if ($stmt->execute()) return true;
            throw new Exception("Gagal memperbarui subkategori: " . $stmt->error);
        } else {
            // Insert (status default 0/Aktif)
            $stmt = $this->db->prepare("INSERT INTO ticket_subcategory(name, category_id, status) VALUES(?, ?, 0)");
            if (!$stmt) throw new Exception("Prepare failed: " . $this->db->error);
            $stmt->bind_param("si", $name, $category_id);
            if ($stmt->execute()) return $this->db->insert_id;
            throw new Exception("Gagal menambahkan subkategori: " . $stmt->error);
        }
    }

    // DELETE (Soft Delete: Mengubah status menjadi 1/Tidak Aktif)
    public static function delete($id) {
        $db = Database::getInstance();
        $id = intval($id);
        
        $stmt = $db->prepare("UPDATE ticket_subcategory SET status = 1 WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $db->error);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
             return $stmt->affected_rows > 0;
        }
        throw new Exception("Soft delete subkategori gagal: " . $stmt->error);
    }
    
    // FIND ALL Joined (Hanya yang Aktif/status=0)
    public static function findAllJoined() {
        $db = Database::getInstance();
        $result = $db->query("
            SELECT 
                s.*, 
                c.name AS category_name
            FROM ticket_subcategory s
            JOIN ticket_category c ON s.category_id = c.id
            WHERE s.status = 0
            ORDER BY s.id DESC
        ");
        if (!$result) throw new Exception("Query failed: " . $db->error);
        return $result;
    }
}