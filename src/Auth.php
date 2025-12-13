<?php
// src/Auth.php - Class untuk Autentikasi dan Cek Izin (RBAC) - VERSI KOREKSI

class Auth {
    
    private $db;
    public $role_id;

    private $permissions = [];
    private $url_mapping = []; 

    public function __construct($role_id) 
    {
        global $db;
        $this->db = $db; 
        
        $this->role_id = (int)$role_id;
        
        if ($this->role_id > 0) {
            $this->loadPermissions(); 
        }
        $this->loadUrlMapping(); 
    }

    private function loadPermissions() 
    {
        if (!$this->db) return;
        
        $sql = "SELECT m.menu_url, a.can_view, a.can_add, a.can_edit, a.can_delete 
                FROM access a
                JOIN menu m ON a.menu_id = m.menu_id
                WHERE a.role_id = ?";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return;
        
        $stmt->bind_param("i", $this->role_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $this->permissions[$row['menu_url']] = [
                    'view' => (bool)$row['can_view'],
                    'add' => (bool)$row['can_add'],
                    'edit' => (bool)$row['can_edit'],
                    'delete' => (bool)$row['can_delete'],
                ];
            }
        }
        $stmt->close();
    }

    private function loadUrlMapping()
    {
        if (!$this->db) return;
        
        $sql = "SELECT menu_url, menu_form_url FROM menu 
                WHERE menu_form_url IS NOT NULL AND menu_form_url != ''";
        
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $form_url = str_replace('./', '', $row['menu_form_url']);
                $main_url = str_replace('./', '', $row['menu_url']);
                
                if (!empty($form_url)) {
                    $this->url_mapping[$form_url] = $main_url;
                }
            }
        }
        $this->url_mapping['access_form.php'] = 'role.php'; 
    }

    public function can(string $menu_url, string $action) : bool
    {
        if ($this->role_id <= 0) {
            return false;
        }

        if ($this->role_id === 1) {
            return true; 
        }

        $menu_url = str_replace('./', '', $menu_url);
        $check_url = $menu_url;
        
        if (isset($this->url_mapping[$menu_url])) {
            $check_url = $this->url_mapping[$menu_url];
        }
        
        // 1. Cek dari $this->permissions
        if (isset($this->permissions[$check_url])) {
            if (isset($this->permissions[$check_url][$action])) {
                return $this->permissions[$check_url][$action];
            }
        }
        
        // 2. Fallback: Query langsung ke DB (Jika permissions belum dimuat)
        if ($check_url === $menu_url) {
            
            $allowed_actions = ['view', 'add', 'edit', 'delete'];
            if (!in_array($action, $allowed_actions)) {
                return false; 
            }
            
            // Kolom dinamis harus dimasukkan secara aman
            $column_name = 'can_' . $action;

            $sql = "SELECT a.{$column_name} 
                    FROM access a
                    JOIN menu m ON a.menu_id = m.menu_id
                    WHERE m.menu_url = ? AND a.role_id = ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return false;
            
            $stmt->bind_param("si", $menu_url, $this->role_id);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $stmt->close();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (isset($row[$column_name])) {
                    return (bool)$row[$column_name]; 
                }
            }
        }

        return false;
    }

    public function authorize(string $menu_url, string $permission = 'view') {
        if (!$this->can($menu_url, $permission)) {
            // Tampilkan pesan "Akses Ditolak" langsung di halaman
            if (ob_get_level()) {
                ob_clean(); 
            }

            $message = "Akses Ditolak. Anda tidak memiliki izin '{$permission}' untuk menu ini.";
            
            echo '<!DOCTYPE html>';
            echo '<html lang="id">';
            echo '<head>';
            echo '    <meta charset="UTF-8">';
            echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
            echo '    <title>Akses Ditolak</title>';
            echo '    <style>';
            echo '        body { font-family: Arial, sans-serif; background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }';
            echo '        .alert { padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }';
            echo '        .alert h1 { margin-top: 0; }';
            echo '        .alert a { color: #007bff; text-decoration: none; margin-top: 10px; display: inline-block; }';
            echo '    </style>';
            echo '</head>';
            echo '<body>';
            echo '    <div class="alert">';
            echo '        <h1>🛑 Akses Ditolak</h1>';
            echo '        <p><strong>' . htmlspecialchars($message) . '</strong></p>';
            echo '        <p>Silakan kembali ke halaman utama.</p>';
            echo '        <a href="./dashboard.php">Kembali ke Dashboard</a>';
            echo '    </div>';
            echo '</body>';
            echo '</html>';
            
            exit(); 
        }
        return true; 
    }

    public function getAllowedMenus() : array
    {
        // 1. Tentukan SQL (mengambil parent_id dan menu_order)
        $sql = "SELECT m.menu_id, m.menu_url, m.menu_name, m.menu_icon, m.parent_id, m.menu_order 
                FROM menu m";

        // Pengguna biasa: HANYA menu yang ada di tabel ACCESS dengan can_view = 1
        if ($this->role_id !== 1) {
             $sql .= " JOIN access a ON m.menu_id = a.menu_id "; 
             $sql .= " WHERE a.role_id = ? AND a.can_view = 1 "; 
        }

        // 2. Pengurutan awal (hanya untuk membantu grouping)
        $sql .= " ORDER BY m.menu_order ASC";

        // 3. Eksekusi
        if ($this->role_id === 1) {
             $result = $this->db->query($sql);
        } else {
             $stmt = $this->db->prepare($sql);
             if (!$stmt) return [];
             
             $stmt->bind_param("i", $this->role_id);
             $stmt->execute();
             $result = $stmt->get_result();
             $stmt->close();
        }
            
        // 4. Proses dan Kelompokkan (Grouping Logic)
        $menus_list = [];
        if ($result) {
            while ($row = $result->fetch_object()) {
                $menus_list[] = $row;
            }
        }
        
        // --- Ambil Parent dari Submenu yang Diizinkan (Agar menu grup muncul) ---
        if ($this->role_id !== 1) {
            $parent_ids_needed = [];
            foreach ($menus_list as $menu) {
                if (!empty($menu->parent_id)) {
                    $parent_ids_needed[] = (int)$menu->parent_id;
                }
            }
            
            if (!empty($parent_ids_needed)) {
                $parent_ids_needed = array_unique($parent_ids_needed);
                $placeholders = implode(',', array_fill(0, count($parent_ids_needed), '?'));
                
                $sql_parent = "SELECT menu_id, menu_url, menu_name, menu_icon, parent_id, menu_order 
                               FROM menu 
                               WHERE menu_id IN ({$placeholders}) AND parent_id IS NULL";
                
                $stmt_parent = $this->db->prepare($sql_parent);
                if ($stmt_parent) {
                    $types = str_repeat('i', count($parent_ids_needed));
                    $stmt_parent->bind_param($types, ...$parent_ids_needed);
                    $stmt_parent->execute();
                    $result_parent = $stmt_parent->get_result();
                    
                    if ($result_parent) {
                        while ($row_parent = $result_parent->fetch_object()) {
                            $is_duplicate = false;
                            foreach ($menus_list as $existing_menu) {
                                if ($existing_menu->menu_id == $row_parent->menu_id) {
                                    $is_duplicate = true;
                                    break;
                                }
                            }
                            if (!$is_duplicate) {
                                $menus_list[] = $row_parent;
                            }
                        }
                    }
                    $stmt_parent->close();
                }
            }
        }
        
        // 5. Kelompokkan Menu dan Pastikan Kunci Array Lengkap
        $final_menus = [];
        $temp_groups = []; 

        // 1. Inisialisasi/kumpulkan semua children ke parent yang sesuai
        foreach ($menus_list as $menu) {
            $menu_id = (int)$menu->menu_id;
            
            if (!empty($menu->parent_id)) {
                $parent_id = (int)$menu->parent_id;
                
                if (!isset($temp_groups[$parent_id])) {
                    $temp_groups[$parent_id] = ['children' => []];
                }
                $temp_groups[$parent_id]['children'][] = $menu;
            }
        }
        
        // 2. Isi data PARENT secara lengkap
        foreach ($menus_list as $menu) {
            if (empty($menu->parent_id)) {
                $menu_id = (int)$menu->menu_id;
                
                // Pastikan item utama memiliki semua kunci yang dibutuhkan (termasuk menu_order)
                $temp_groups[$menu_id]['menu_id'] = $menu->menu_id;
                $temp_groups[$menu_id]['menu_url'] = $menu->menu_url;
                $temp_groups[$menu_id]['menu_name'] = $menu->menu_name;
                $temp_groups[$menu_id]['menu_icon'] = $menu->menu_icon;
                $temp_groups[$menu_id]['menu_order'] = $menu->menu_order; // <-- PENTING: DITAMBAHKAN DI SINI
                
                // Ambil children yang mungkin sudah ditambahkan
                $temp_groups[$menu_id]['children'] = $temp_groups[$menu_id]['children'] ?? [];
            }
        }
        
        // 3. Filter dan Finalisasi
        foreach ($temp_groups as $menu_item) {
            // Hanya masukkan menu utama yang memiliki CHILDREN atau menu utama yang URL-nya bukan '#' (menu tunggal)
            // Dan pastikan menu_id (kunci parent) terdefinisi, menandakan ini adalah PARENT/STANDALONE
            if (isset($menu_item['menu_id']) && (!empty($menu_item['children']) || $menu_item['menu_url'] !== '#')) {
                $final_menus[] = $menu_item;
            }
        }
        
        // 4. Urutkan berdasarkan menu_order (Baris 303 di kode Anda sebelumnya)
        usort($final_menus, function($a, $b) {
            // Kita sudah yakin menu_order ada di sini
            return $a['menu_order'] <=> $b['menu_order'];
        });

        return $final_menus;
    }
}