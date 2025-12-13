<?php
// src/role.php - Class Model untuk Peran (Role)
// Note: File ini mengasumsikan class Database dan class Auth sudah didefinisikan

class Role{

public $role_id = null;
public $role_name = '';
public $role_desc = '';
public $created_at;
public $status = 0; // 0=Aktif, 1=Dihapus (Soft Delete)
private $db;
// Tentukan resource yang dikelola oleh class ini
private const RESOURCE_NAME = 'role.php';

public function __construct($data = null)
{
    // Asumsi Database::getInstance() mengembalikan koneksi mysqli
    $this->db = Database::getInstance();
    if (isset($data['role_name'])) {
        $this->role_name = $data['role_name'];
        $this->role_desc = $data['role_desc'] ?? '';
    }
}

// --- CREATE/SAVE ---
public function save(Auth $auth) : Role // Menerima objek Auth
{
    // Pengecekan RBAC: Izin 'add'
    if (!$auth->can(self::RESOURCE_NAME, 'add')) {
        throw new Exception("Akses Ditolak: Anda tidak memiliki izin untuk menambah peran.");
    }
    
    if (empty($this->role_name)) {
        throw new Exception("Nama Peran wajib diisi.");
    }
    
    // Default status adalah 0 (Aktif)
    $sql = "INSERT INTO role (role_name, role_desc, created_at, status) VALUES (?, ?, NOW(), 0)";
    
    // PREPARE STATEMENT
    $stmt = $this->db->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error saat menyiapkan query INSERT: " . $this->db->error);
    }
    
    // BIND PARAMETERS: 'ss' = dua string
    $stmt->bind_param("ss", $this->role_name, $this->role_desc);
    
    if($stmt->execute() === false) {
        // Cek error khusus (misalnya: duplikat key)
        if ($stmt->errno == 1062) {
             throw new Exception("Error saat menyimpan: Peran mungkin sudah ada.");
        }
        throw new Exception("Error saat menyimpan: " . $stmt->error);
    }
    
    $inserted_id = $stmt->insert_id;
    $stmt->close();
    
    return self::find($inserted_id);
}

// --- UPDATE ---
public function update(Auth $auth) : void // Menerima objek Auth
{
    // Pengecekan RBAC: Izin 'edit'
    if (!$auth->can(self::RESOURCE_NAME, 'edit')) {
        throw new Exception("Akses Ditolak: Anda tidak memiliki izin untuk mengedit peran.");
    }
    // Pengecekan: Tidak boleh mengedit Role ID 1 (Super Admin)
    if ((int)$this->role_id === 1) {
        throw new Exception("Peran 'Super Admin' tidak dapat diubah.");
    }

    if (!$this->role_id) {
        throw new Exception("ID Peran harus ada untuk melakukan update.");
    }
    
    $sql = "UPDATE role SET role_name = ?, role_desc = ? WHERE role_id = ?";
    
    // PREPARE STATEMENT
    $stmt = $this->db->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error saat menyiapkan query UPDATE: " . $this->db->error);
    }
    
    // BIND PARAMETERS: 'ssi' = dua string, satu integer
    $stmt->bind_param("ssi", $this->role_name, $this->role_desc, $this->role_id);
    
    if($stmt->execute() === false) {
        throw new Exception("Error saat update: " . $stmt->error);
    }
    
    $stmt->close();
}

// --- FIND ONE (Termasuk yang berstatus Dihapus) ---
public static function find($id)
{
    $self = new static;
    $sql = "SELECT * FROM role WHERE role_id = ?";
    
    // PREPARE STATEMENT
    $stmt = $self->db->prepare($sql);
    if ($stmt === false) return false;

    // BIND PARAMETERS: 'i' = satu integer
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $res = $stmt->get_result();
    $stmt->close();
    
    if($res->num_rows < 1) return false;
    
    $self->populateObject($res->fetch_object());
    return $self;
}

// --- FIND ALL (Hanya yang berstatus Aktif/status = 0) ---
// Note: Query ini tidak memerlukan Prepared Statement karena tidak ada input user.
public static function findAll() : array
{
    $roles = [];
    $self = new static;
    // HANYA ambil peran yang statusnya 0 (Aktif)
    $sql = "SELECT * FROM role WHERE status = 0 ORDER BY role_name ASC"; 
    $res = $self->db->query($sql);
    
    if(!$res || $res->num_rows < 1) return [];

    while($row = $res->fetch_object()){
        $role = new static;
        $role->populateObject($row);
        $roles[] = $role;
    }
    return $roles;
}

public static function findAllActive() {
        $self = new static();
        // Hanya ambil role yang status = 0 (Aktif)
        $sql = "SELECT role_id, role_name, role_desc FROM role WHERE status = 0 ORDER BY role_name ASC";
        
        $res = $self->db->query($sql);
        
        if ($res === false || $res->num_rows < 1) {
            return [];
        }

        $roles = [];
        while ($row = $res->fetch_object('Role')) {
            $roles[] = $row;
        }

        return $roles;
    }

// --- SOFT DELETE ---
public static function delete(int $id, Auth $auth) : bool // Menerima ID dan objek Auth
{
    global $db; // Akses koneksi database dari luar scope static
    
    // 1. Pengecekan RBAC: Izin 'delete'
    if (!$auth->can(self::RESOURCE_NAME, 'delete')) {
        throw new Exception("Akses Ditolak: Anda tidak memiliki izin untuk menghapus peran.");
    }

    // 2. Pengecekan Khusus: Role ID 1 (Super Admin)
    if ($id === 1) {
        throw new Exception("Peran 'Super Admin' tidak dapat dihapus.");
    }
    
    // 3. Ambil Nama Role (Role Name) untuk Pengecekan Keterkaitan (Menggunakan find() yang sudah Prepared)
    $role_to_delete = self::find($id); 
    
    if (!$role_to_delete) {
        return false; 
    }

    $role_name_to_check = $role_to_delete->role_name; // Sudah aman karena berasal dari DB (select)
    
    // 4. Pengecekan Keterkaitan dengan Tabel `users`
    // Menggunakan Prepared Statement untuk $role_name_to_check
    $check_user_sql = "SELECT COUNT(*) AS total FROM users WHERE role = ? AND status = 0";
    
    $stmt_check = $db->prepare($check_user_sql);
    if ($stmt_check === false) {
         throw new Exception("Gagal menyiapkan pengecekan pengguna: " . $db->error);
    }
    
    $stmt_check->bind_param("s", $role_name_to_check);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $row = $result->fetch_assoc();
    $stmt_check->close();

    if ($row['total'] > 0) {
        throw new Exception("Gagal menghapus: Terdapat **{$row['total']}** pengguna aktif yang menggunakan role **'{$role_to_delete->role_name}'** ini.");
    }
    
    // 5. Melakukan Soft Delete (Update kolom status)
    $sql = "UPDATE role SET status = 1 WHERE role_id = ? AND status = 0"; 
    
    $stmt_delete = $db->prepare($sql);
    if ($stmt_delete === false) {
        throw new Exception("Gagal menyiapkan query DELETE: " . $db->error);
    }
    
    $stmt_delete->bind_param("i", $id);
    
    if($stmt_delete->execute() === false) {
        throw new Exception("Gagal menghapus Peran (Soft Delete). DB Error: " . $stmt_delete->error);
    }
    
    $affected_rows = $stmt_delete->affected_rows;
    $stmt_delete->close();
    
    return $affected_rows > 0;
}

// --- UTIL ---
public function populateObject($object) : void
{
    foreach($object as $key => $property){
        $this->$key = $property;
    }
}
}