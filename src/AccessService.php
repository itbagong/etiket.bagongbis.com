<?php
// src/AccessService.php - Class untuk CRUD Izin Akses menggunakan Prepared Statements

class AccessService {
    private $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    /**
     * Mengambil semua izin akses yang sudah ada untuk role tertentu.
     * @param int $role_id ID Role
     * @return array Array asosiatif [menu_id => [can_view, can_add, ...]]
     */
    public function getCurrentAccess(int $role_id): array {
        $current_access = [];
        
        $sql = "SELECT menu_id, can_view, can_add, can_edit, can_delete 
                FROM access 
                WHERE role_id = ?";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $current_access[$row['menu_id']] = $row;
            }
        }
        $stmt->close();
        
        return $current_access;
    }

    /**
     * Melakukan UPSERT (Update/Insert) semua izin untuk setiap menu.
     * Dijalankan dalam transaksi.
     * @param int $role_id ID Role
     * @param array $menus Array of Menu objects
     * @param array $postData Data dari $_POST
     * @return bool True jika sukses
     * @throws Exception Jika terjadi kegagalan DB
     */
    public function saveAccess(int $role_id, array $menus, array $postData): bool {
        
        $this->db->begin_transaction();
        
        try {
            // Prepared statement untuk UPSERT (INSERT OR REPLACE/DUPLICATE KEY UPDATE)
            $sql_upsert = "INSERT INTO access (role_id, menu_id, can_view, can_add, can_edit, can_delete) 
                           VALUES (?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE 
                           can_view = VALUES(can_view), 
                           can_add = VALUES(can_add), 
                           can_edit = VALUES(can_edit), 
                           can_delete = VALUES(can_delete)";
            
            $stmt_upsert = $this->db->prepare($sql_upsert);
            if (!$stmt_upsert) {
                throw new Exception("Gagal menyiapkan statement UPSERT: " . $this->db->error);
            }

            // Bind parameter sekali (i = int, i = int, iiii = 4 integers)
            $stmt_upsert->bind_param("iiiiii", $role_id, $menu_id, $can_view, $can_add, $can_edit, $can_delete);

            foreach ($menus as $menu) {
                $menu_id = $menu->menu_id;
                
                // Ambil nilai dari POST (1 atau 0)
                // Menggunakan intval untuk memastikan nilai numerik, meskipun isset sudah cukup aman di sini.
                $can_view = isset($postData['view'][$menu_id]) ? 1 : 0;
                $can_add = isset($postData['add'][$menu_id]) ? 1 : 0;
                $can_edit = isset($postData['edit'][$menu_id]) ? 1 : 0;
                $can_delete = isset($postData['delete'][$menu_id]) ? 1 : 0;

                if (!$stmt_upsert->execute()) {
                    throw new Exception("Gagal eksekusi UPSERT untuk Menu ID $menu_id: " . $stmt_upsert->error);
                }
            }
            $stmt_upsert->close();
            
            // --- LOGIKA PEMBERSIHAN (DELETE jika semua izin 0) ---
            $sql_clean = "DELETE FROM access 
                          WHERE role_id = ? 
                          AND can_view = 0 AND can_add = 0 AND can_edit = 0 AND can_delete = 0";
            
            $stmt_clean = $this->db->prepare($sql_clean);
            if (!$stmt_clean) {
                throw new Exception("Gagal menyiapkan statement CLEAN: " . $this->db->error);
            }
            $stmt_clean->bind_param("i", $role_id);
            if (!$stmt_clean->execute()) {
                throw new Exception("Gagal eksekusi CLEAN: " . $stmt_clean->error);
            }
            $stmt_clean->close();

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e; // Lempar kembali exception
        }
    }
}