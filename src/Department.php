<?php
// src/Department.php - Model PHP OOP untuk tabel 'departemen' (REVISI: Soft Delete)

class Department {
    private $db;

    // Mapping properti objek ke kolom tabel:
    public $id = null;             // maps to departemen_id
    public $name = '';             // maps to departemen_name
    public $abbreviation = '';     // maps to departemen_abb
    public $status = 1;            // maps to departemen_status

    public function __construct($data = null) {
        $this->db = Database::getInstance();
        if ($data) {
            $this->populateObject((object)$data);
        }
    }

    // Mengisi properti objek dari hasil query atau POST data
    public function populateObject($object) {
        // 1. Ambil data dari hasil Query DB (nama kolom departemen_XXX)
        if (isset($object->departemen_id)) $this->id = (int)$object->departemen_id;
        if (isset($object->departemen_name)) $this->name = $object->departemen_name;
        if (isset($object->departemen_abb)) $this->abbreviation = $object->departemen_abb;
        if (isset($object->departemen_status)) $this->status = (int)$object->departemen_status;

        // 2. AMBIL DATA DARI POST FORM (nama input HTML department_XXX)
        if (isset($object->department_id) && $object->department_id > 0) $this->id = (int)$object->department_id;
        if (isset($object->department_name)) $this->name = $object->department_name;
        if (isset($object->department_abb)) $this->abbreviation = $object->department_abb;
        if (isset($object->department_status)) $this->status = (int)$object->department_status;
        
        // Agar kompatibel dengan kode lama:
        if (isset($object->id) && $object->id > 0) $this->id = (int)$object->id;
    }

    // --- CREATE / UPDATE (Menggunakan Prepared Statement) ---
    public function save() : Department
    {
        // Pastikan nilai status adalah integer
        $status_int = (int)$this->status;

        if ($this->id) {
            // UPDATE
            $sql = "UPDATE departemen SET departemen_name = ?, departemen_abb = ?, departemen_status = ? WHERE departemen_id = ?";
            $stmt = $this->db->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Gagal menyiapkan query UPDATE Departemen: " . $this->db->error);
            }
            // s: name (string), s: abbreviation (string), i: status (integer), i: id (integer)
            $stmt->bind_param("ssii", $this->name, $this->abbreviation, $status_int, $this->id);
        } else {
            // CREATE
            $sql = "INSERT INTO departemen (departemen_name, departemen_abb, departemen_status) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Gagal menyiapkan query CREATE Departemen: " . $this->db->error);
            }
            // s: name (string), s: abbreviation (string), i: status (integer)
            $stmt->bind_param("ssi", $this->name, $this->abbreviation, $status_int);
        }
        
        if($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Error saat menyimpan data. DB Error: " . $error);
        }

        if (!$this->id) {
            $this->id = $stmt->insert_id;
        }
        $stmt->close();
        
        return self::find($this->id);
    }

    // --- FIND ONE (Menggunakan Prepared Statement) ---
    public static function find($id)
    {
        $db = Database::getInstance();
        $sql ="SELECT * FROM departemen WHERE departemen_id = ?";
        
        $stmt = $db->prepare($sql);
        if ($stmt === false) { return false; }
        
        $id_int = (int)$id;
        $stmt->bind_param("i", $id_int);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if(!$res || $res->num_rows < 1) {
            $stmt->close();
            return false;
        }
        
        $department = new static;
        $department->populateObject($res->fetch_object());
        $stmt->close();
        return $department;
    }

    // --- FIND ALL ---
    public static function findAll() : array
    {
        $departments = [];
        $db = Database::getInstance();
        // Hanya menampilkan departemen yang statusnya AKTIF (0)
        $sql = "SELECT * FROM departemen WHERE departemen_status = 0 ORDER BY departemen_id DESC"; 
        $res = $db->query($sql);
        
        if(!$res || $res->num_rows < 1) return []; 

        while($row = $res->fetch_object()){
            $department = new static;
            $department->populateObject($row);
            $departments[] = $department;
        }
        return $departments;
    } 

    // --- SOFT DELETE (Mengubah status menjadi 1 / Nonaktif) ---
    public static function softDelete($id) : bool 
    {
        $db = Database::getInstance();
        // Mengubah status menjadi 1 (Nonaktif)
        $sql = "UPDATE departemen SET departemen_status = 1 WHERE departemen_id = ?"; 
        
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Gagal menyiapkan query SOFT DELETE Departemen: " . $db->error);
        }

        $id_int = (int)$id;
        $stmt->bind_param("i", $id_int);
        
        if($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Gagal melakukan soft delete Departemen. DB Error: " . $error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }
}