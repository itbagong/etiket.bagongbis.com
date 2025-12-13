<?php
// src/location.php - REVISI DENGAN PREPARED STATEMENTS & SOFT DELETE

class Location {
    
    public $location_id = null;
    public $location_name = '';
    public $location_address = '';
    public $location_status = 1; 
    
    private $db;

    public function __construct($data = null) {
        if ($data) {
            $this->location_id = $data['location_id'] ?? null;
            $this->location_name = $data['location_name'] ?? '';
            $this->location_address = $data['location_address'] ?? '';
            $this->location_status = $data['location_status'] ?? 0;
        }
        $this->db = Database::getInstance(); 
    }

    // --- CREATE (SAVE) - Tetap menggunakan Prepared Statement ---
    public function save() {
        $sql = "INSERT INTO location (location_name, location_address, location_status)
                VALUES (?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->db->error);
        }
        
        // ssi = string, string, integer
        $stmt->bind_param("ssi", $this->location_name, $this->location_address, $this->location_status);
        
        if ($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Execute failed: " . $error);
        }
        
        $this->location_id = $stmt->insert_id;
        $stmt->close();
        return $this;
    }

    // --- UPDATE - Tetap menggunakan Prepared Statement ---
    public function update() {
        if (!$this->location_id) {
            throw new Exception("Cannot update unsaved location.");
        }
        
        $sql = "UPDATE location SET 
                    location_name = ?,
                    location_address = ?,
                    location_status = ?
                WHERE location_id = ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->db->error);
        }

        // ssii = string, string, integer, integer (untuk location_id)
        $stmt->bind_param("ssii", 
            $this->location_name, 
            $this->location_address, 
            $this->location_status, 
            $this->location_id
        );

        if ($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Execute failed: " . $error);
        }
        
        $stmt->close();
        return self::find($this->location_id); 
    }
    
    // --- 🚨 REVISI KRUSIAL: SOFT DELETE 🚨 ---
    /**
     * Melakukan soft delete (menetapkan status = 1) pada lokasi. (Prepared Statement)
     */
    public function softDelete() {
        if (!$this->location_id) {
            throw new Exception("Cannot soft-delete unsaved location.");
        }
        
        // Query UPDATE, bukan DELETE
        $sql = "UPDATE location SET location_status = 1 WHERE location_id = ?";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->db->error);
        }
        
        // i = integer (location_id)
        $stmt->bind_param("i", $this->location_id);

        if ($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Execute failed: " . $error);
        }
        
        $stmt->close();
        // Update status objek lokal
        $this->location_status = 1; 
        return true;
    }
    // Mengganti nama fungsi agar lebih jelas
    public function delete() {
        return $this->softDelete();
    }


    // --- FIND (READ) - Tetap menggunakan Prepared Statement ---
    public static function find($id) {
        $self = new static;
        
        $sql = "SELECT * FROM location WHERE location_id = ?";
        
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

    // --- FIND ALL (READ) - Tetap tanpa input user, tidak perlu Prepared Statement ---
    public static function findAll() {
        $self = new static;
        // Opsional: Anda mungkin ingin hanya menampilkan yang aktif (status=1) secara default
        $sql = "SELECT * FROM location where location_status='0' ORDER BY location_name ASC";
        $locations = [];
        $res = $self->db->query($sql);
        
        if ($res === false || $res->num_rows < 1) return [];

        while ($row = $res->fetch_object()) {
            $location = new static;
            $location->populateObject($row);
            $locations[] = $location;
        }
        return $locations;
    } 
    
    public function populateObject($object) {
        foreach ($object as $key => $property) {
            if (property_exists($this, $key)) {
                 $this->$key = $property;
            }
        }
    }
}