<?php
// src/team-member.php - Model Anggota Tim (Menggunakan Prepared Statements)
require_once './src/team.php';
require_once './src/Database.php';
require_once './src/user.php'; 
require_once './src/team-member.php'; 
require_once './src/security.php';

class TeamMember{
    
    public $id = null;
    public $user = null; // ID user
    public $team = null; // ID team
    public $created_at;
    protected $db; 

    public function __construct($data = null) 
    {
        $this->db = Database::getInstance();
        
        if ($data) {
            $this->id = (int)($data['id'] ?? 0);
            $this->user = (int)($data['user'] ?? $data['user_id'] ?? 0); 
            $this->team = (int)($data['team'] ?? $data['team_id'] ?? 0); 
            
            if (isset($data['created_at'])) $this->created_at = $data['created_at'];
        }
    }

    // --- CREATE/SAVE (Menggunakan Prepared Statement) ---
    // Dipanggil di team_form.php untuk menambahkan anggota baru.
    public function save() : TeamMember
    {
        // Pastikan tidak ada duplikat entri user-team
        $sql = "INSERT INTO team_member (user, team, created_at) VALUES (?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Gagal menyiapkan query TeamMember::save(): " . $this->db->error);
        }

        $user_int = (int)$this->user;
        $team_int = (int)$this->team;

        // Bind parameter: ii (user, team)
        $stmt->bind_param("ii", $user_int, $team_int);

        if($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            // Jika terjadi error, kembalikan ke kode di team_form.php
            throw new Exception("Gagal menyimpan anggota tim. DB Error: " . $error);
        }
        
        $this->id = $stmt->insert_id;
        $stmt->close();
        
        return $this; 
    }
    
    // --- UTILITY: Mengambil semua anggota untuk sebuah Tim (Menggunakan Prepared Statement) ---
    // Dipanggil di team_form.php untuk menampilkan anggota yang sudah ada.
    public static function findMembersByTeamId(int $teamId) : array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM team_member WHERE team = ? ORDER BY id DESC";
        $members = [];
        
        $stmt = $db->prepare($sql);
        if (!$stmt) return []; 

        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if($res->num_rows < 1) {
            $stmt->close();
            return [];
        }

        while($row = $res->fetch_object()){
            $members[] = new self((array)$row); 
        }
        
        $stmt->close();
        return $members;
    }
    
    // --- UTILITY: Menghapus semua anggota dari sebuah Tim (Menggunakan Prepared Statement) ---
    // Dipanggil di team_form.php sebelum menyimpan daftar anggota baru (logika "timpa").
    public static function deleteByTeamId(int $teamId)
    {
        $db = Database::getInstance();
        
        if ($teamId <= 0) {
            throw new Exception("ID Tim tidak valid (<= 0) untuk penghapusan anggota."); 
        }
        
        $sql = "DELETE FROM team_member WHERE team = ?";
        
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Gagal menyiapkan query deleteByTeamId: " . $db->error); 
        }

        $stmt->bind_param("i", $teamId);
        
        if ($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Gagal menghapus anggota tim lama: " . $error);
        }
        $stmt->close();
        return true;
    }
    
    // --- Helper function untuk mengisi data objek (tidak digunakan di static methods) ---
    private function populateObject($object) : void 
    {
        foreach($object as $key => $property){
            $this->$key = $property;
        }
    }
}