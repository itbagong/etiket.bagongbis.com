<?php
// src/Menu.php - SOLUSI FINAL UNTUK BINDING parent_id = NULL

// Asumsi Database.php sudah ada dan memiliki static method getInstance()

class Menu {
    
    public $menu_id = null;
    public $menu_name = '';
    public $menu_url = '';
    public $menu_form_url = null;
    public $menu_icon = '';
    public $parent_id = null;
    public $menu_order = 0;
    public $status = 0; 
    
    private $db;

    public function __construct($data = null) {
        $this->db = Database::getInstance();
        if ($data) {
            $this->populateObject((object)$data);
        }
    }
    
    public function populateObject($object) : void {
        foreach($object as $key => $property){
            if (property_exists($this, $key)) {
                $this->$key = $property;
            }
        }
        // Pastikan nilai parent_id yang kosong dari database atau POST menjadi NULL (bukan 0 atau string kosong)
        if (empty($this->parent_id)) {
            $this->parent_id = null;
        }
    }

    // --- 💾 CREATE (SAVE) - MENGGUNAKAN PREPARED STATEMENT ---
    public function save() : Menu {
        // Query INSERT pertama: Menggunakan 0 sebagai placeholder untuk parent_id jika NULL
        // Ini akan melanggar FK, tapi kita perbaiki dengan query kedua di bawah.
        $sql = "INSERT INTO menu (menu_name, menu_url, menu_icon, parent_id, menu_order, status)
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $this->db->error);
        
        $parent_id_param = $this->parent_id ?? 0; // Ganti NULL menjadi 0 untuk binding 'i'
        
        // sssiii = 3 string, 3 integer (Total 6 parameter)
        // Perbaikan: Sebelumnya sssiis, tapi status dan order adalah integer.
        $stmt->bind_param("sssiii", // <-- Perbaikan di sini
            $this->menu_name, 
            $this->menu_url, 
            $this->menu_icon, 
            $parent_id_param, // Bind 0 jika NULL
            $this->menu_order,
            $this->status
        );
        
        if ($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Execute failed: " . $error);
        }
        
        $insert_id = $stmt->insert_id;
        $stmt->close();
        
        if ($insert_id <= 0) throw new Exception("Gagal mendapatkan ID Menu baru.");
        
        // *PERBAIKAN*: Jalankan query update terpisah untuk mengatur parent_id = NULL
        if ($this->parent_id === null && $parent_id_param === 0) {
            // Gunakan prepared statement juga untuk update NULL ini agar lebih aman
            $sql_null_fix = "UPDATE menu SET parent_id = NULL WHERE menu_id = ?";
            $stmt_fix = $this->db->prepare($sql_null_fix);
            if (!$stmt_fix) throw new Exception("Prepare fix failed: " . $this->db->error);
            
            $stmt_fix->bind_param("i", $insert_id);
            $stmt_fix->execute();
            $stmt_fix->close();
        }

        return self::find($insert_id);
    }
    
    // --- 🔄 UPDATE - MENGGUNAKAN PREPARED STATEMENT (FIX BINDING NULL) ---
    public function update() : void {
        if (!$this->menu_id) throw new Exception("ID Menu harus ada untuk update.");

        $parent_id_is_null = $this->parent_id === null;

        if ($parent_id_is_null) {
            // JALUR 1: Jika parent_id adalah NULL
            $sql_null = "UPDATE menu SET 
                            menu_name = ?, menu_url = ?, menu_icon = ?, parent_id = NULL,
                            menu_order = ?, status = ?
                        WHERE menu_id = ?";
            
            $stmt = $this->db->prepare($sql_null);
            if (!$stmt) throw new Exception("Prepare NULL failed: " . $this->db->error);

            // Perbaikan: Tambahkan 'i' keenam untuk $this->menu_id di klausa WHERE
            // SSSIII = 3 string, 3 integer (Total 6 parameter)
            $stmt->bind_param("sssiii", // <-- Perbaikan di sini (Baris 109)
                $this->menu_name, 
                $this->menu_url, 
                $this->menu_icon, 
                $this->menu_order, 
                $this->status, 
                $this->menu_id // Parameter ke-6
            );

            if ($stmt->execute() === false) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception("Execute NULL failed: " . $error);
            }
            $stmt->close();

        } else {
            // JALUR 2: Jika parent_id adalah ID yang valid
            $sql = "UPDATE menu SET 
                        menu_name = ?, menu_url = ?, menu_icon = ?, parent_id = ?,
                        menu_order = ?, status = ?
                    WHERE menu_id = ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new Exception("Prepare ID failed: " . $this->db->error);

            $parent_id_param = (int)$this->parent_id;

            // Tipe data binding: SSSIIII (7 parameter)
            $stmt->bind_param("sssiiii", 
                $this->menu_name, 
                $this->menu_url, 
                $this->menu_icon, 
                $parent_id_param, 
                $this->menu_order, 
                $this->status, 
                $this->menu_id 
            );

            if ($stmt->execute() === false) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception("Execute ID failed: " . $error);
            }
            $stmt->close();
        }
    }

    // --- ❌ SOFT DELETE ---
    public function softDelete() : bool {
        if (!$this->menu_id) throw new Exception("Cannot soft-delete unsaved Menu.");
        
        $sql = "UPDATE menu SET status = 1 WHERE menu_id = ?";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $this->db->error);
        
        $stmt->bind_param("i", $this->menu_id);

        if ($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Execute failed: " . $error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        $this->status = 1; 
        return $affected > 0;
    }
    
    public static function delete($id) : bool {
        $menu = self::find($id, true);
        if ($menu) {
            return $menu->softDelete();
        }
        return false;
    }


    // --- 🔎 FIND (READ) ---
    public static function find($id, $include_inactive = false) {
        $self = new static;
        $sql = "SELECT * FROM menu WHERE menu_id = ?";
        
        if (!$include_inactive) {
            $sql .= " AND status = 0"; 
        }
        
        $stmt = $self->db->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $stmt->close();

        if ($result === false || $result->num_rows < 1) return false;
        
        $self->populateObject($result->fetch_object());
        return $self;
    }

    // --- 📚 FIND ALL (READ) ---
    public static function findAll($include_inactive = false) : array {
        $menus = [];
        $self = new static;
        $sql = "SELECT * FROM menu";
        
        if (!$include_inactive) {
            $sql .= " WHERE status = 0"; 
        }
        
        // Urutkan berdasarkan parent_id (parent dulu baru child)
        $sql .= " ORDER BY parent_id ASC, menu_order ASC"; 
        
        $res = $self->db->query($sql);
        
        if(!$res || $res->num_rows < 1) return []; 
        
        while($row = $res->fetch_object()){
            $menu = new static;
            $menu->populateObject($row);
            $menus[] = $menu;
        }
        return $menus;
    }
}