<?php
// src/team.php - Class Model untuk Tim (REVISI: 0=AKTIF, 1=TIDAK AKTIF/HAPUS)

class Team{
    
    public $id = null;
    public $name = '';
    public $created_at;
    public $status = 0; // Default aktif (0)
    private $db; 

    public function __construct($data = null) 
    {
        $this->db = Database::getInstance(); 
        if (isset($data['name'])) {
            $this->name = $data['name'];
        }
        // Pastikan status selalu integer 0 atau 1
        if (isset($data['status'])) {
            $this->status = (int)$data['status'];
        }
    }
    
    // --- CREATE/SAVE ---
    public function save() : Team
    {
        $sql = "INSERT INTO team (name, status, created_at) VALUES (?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Gagal menyiapkan query CREATE Team: " . $this->db->error);
        }

        // AMBIL NILAI STATUS SEBAGAI INTEGER
        $status_to_bind = (int)$this->status; 
        
        // Bind parameter: s (string name), i (integer status)
        $stmt->bind_param("si", $this->name, $status_to_bind); 
        
        if($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            // Jika error terjadi, error SQL dari sini akan ditangkap di team_form.php
            throw new Exception("Error SQL saat menyimpan Tim: " . $error); 
        }
        
        $insert_id = $stmt->insert_id;
        $stmt->close();

        if ($insert_id <= 0) {
            throw new Exception("ID Tim tidak terdeteksi (0) setelah penyimpanan.");
        }

        $this->id = $insert_id;
        return $this; 
    }
    
    // --- UPDATE ---
    public function update() : void
    {
        if (!$this->id) {
            throw new Exception("ID Tim harus ada untuk melakukan update.");
        }
        
        $sql = "UPDATE team SET name = ?, status = ? WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Gagal menyiapkan query UPDATE Team: " . $this->db->error);
        }
        
        // AMBIL NILAI STATUS SEBAGAI INTEGER UNTUK UPDATE
        $status_to_bind = (int)$this->status;
        $id_to_bind = (int)$this->id;

        // Bind parameter: s (string name), i (integer status), i (integer id)
        $stmt->bind_param("sii", $this->name, $status_to_bind, $id_to_bind);
        
        if($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Error saat update Tim: " . $error);
        }
        $stmt->close();
    }

    // --- FIND ONE (Hanya mencari yang status = 0) ---
    public static function find($id)
    {
        $self = new static;
        $sql ="SELECT * FROM team WHERE id = ? AND status = 0";
        
        $stmt = $self->db->prepare($sql);
        if ($stmt === false) { return false; }

        $id_int = (int)$id;
        $stmt->bind_param("i", $id_int);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if(!$res || $res->num_rows < 1) {
            $stmt->close();
            return false;
        }
        
        $self->populateObject($res->fetch_object());
        $stmt->close();
        return $self;
    }

    // --- FIND ALL (Hanya mengambil yang status = 0) ---
    public static function findAll() : array
    {
        $teams = [];
        $self = new static;
        $sql = "SELECT * FROM team WHERE status = 0 ORDER BY id DESC"; 
        
        $res = $self->db->query($sql); 
        
        if(!$res || $res->num_rows < 1) return []; 

        while($row = $res->fetch_object()){
            $team = new static;
            $team->populateObject($row);
            $teams[] = $team;
        }
        return $teams;
    } 

    // --- SOFT DELETE (Mengganti DELETE dengan UPDATE status = 1) ---
    public static function softDelete($id) : bool 
    {
        $self = new static;
        // Mengatur status = 1 (Tidak Aktif)
        $sql = "UPDATE team SET status = 1 WHERE id = ?";
        
        $stmt = $self->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Gagal menyiapkan query SOFT DELETE Team: " . $self->db->error);
        }

        $id_int = (int)$id;
        $stmt->bind_param("i", $id_int);

        if($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Gagal melakukan soft delete Tim. DB Error: " . $error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }

    // --- UTIL ---
    public function populateObject($object) : void 
    {
        foreach($object as $key => $property){
            $this->$key = $property;
        }
    }
}