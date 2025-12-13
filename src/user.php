<?php
// src/User.php (Diperbarui: Menambahkan fungsi updateStatus, delete, dan activate)

require_once 'Database.php';

class User{
 
  public $id = null;
  public $name = '';
  public $email = '';
  public $phone = '';
  public $password = '';
  public $role_id; // BERUBAH: Sekarang int (Foreign Key)
  public $role_name; // BARU: Untuk menyimpan hasil JOIN
  public $avatar = '';
  public $last_password = '';
  public $created_at = '';
  public $updated_at = '';
  public $status = 0;

  private $db;

  public function __construct($data = null) {
    if ($data) {
      $this->name = $data['name'] ?? null;
      $this->email = $data['email'] ?? null;
      $this->phone = $data['phone'] ?? null;
      $this->password = $data['password'] ?? null;
      $this->role_id = $data['role_id'] ?? null;
      $this->last_password = $data['last_password'] ?? $this->password;
    }
   
    $this->db = Database::getInstance();
  }

  public function populateObject($object){
    foreach($object as $key => $property){
      if (property_exists($this, $key)) {
       $this->$key = $property;
      }
    }
  }

  // --- QUERY UTAMA SELECT DENGAN JOIN ---
  private static function getSelectQuery() {
    // Ambil semua kolom user (u.*) dan role_name dari tabel role (r.role_name)
    return "SELECT
          u.*, r.role_name
        FROM users u
        JOIN role r ON u.role_id = r.role_id";
  }

  // --- OPERASI CREATE (Save) ---
    public function save(){
        
        // Tentukan nilai status berdasarkan properti is_from_register
        // Jika $this->is_from_register ada dan bernilai TRUE, status = 1 (Non-aktif)
        // Jika properti tidak ada (misal dari user_form.php), status = 0 (Aktif)
        $status_to_save = isset($this->is_from_register) && $this->is_from_register ? 1 : 0;
        
        // Pastikan properti status juga diisi, walau diabaikan di sini
        $this->status = $status_to_save;

        $sql = "INSERT INTO users (name, email, phone, password, role_id, last_password, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)"; // Tambahkan placeholder untuk status
        
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) { throw new Exception("Gagal Prepare Statement INSERT: " . $this->db->error); }

        // Perubahan: Tambahkan 'i' untuk tipe data integer (status)
        $stmt->bind_param("ssssisi", // ssss untuk string, i untuk role_id, s untuk last_password, i untuk status
            $this->name,
            $this->email,
            $this->phone,
            $this->password,
            $this->role_id,
            $this->last_password,
            $status_to_save // Binding nilai status yang sudah ditentukan
        );
        
        if(!$stmt->execute()) { throw new Exception("Gagal Execute Statement INSERT: " . $stmt->error); }
        
        $id = $stmt->insert_id;
        $stmt->close();
        return self::find($id);
    }

  // --- OPERASI READ (Find) ---
  public static function find($id){
    $self = new static;
    $sql = self::getSelectQuery() . " WHERE u.id = ?";
   
    $stmt = $self->db->prepare($sql);
    if ($stmt === false) { throw new Exception("Gagal Prepare Statement FIND: " . $self->db->error); }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if($res === false || $res->num_rows < 1) return false;
   
    $user_data = $res->fetch_object();
    $self->populateObject($user_data); // Memuat role_name ke objek
    $stmt->close();
    return $self;
  }

  // --- OPERASI READ (Find All) ---
  public static function findAll(){
    $self = new static;
    $sql = self::getSelectQuery() . " ORDER BY u.id DESC";
    $users = [];
   
    $res = $self->db->query($sql);

    if($res === false || $res->num_rows < 1) return [];

    while($row = $res->fetch_object()){
      $user = new static;
      $user->populateObject($row); // Memuat role_name ke objek
      $users[] = $user;
    }

    return $users;
  }

  // --- OPERASI UPDATE ---
  public function update(){
    if (!$this->id) { throw new Exception("Tidak dapat memperbarui pengguna yang belum tersimpan."); }
   
    $sql = "UPDATE users SET
          name = ?,
          email = ?,
          phone = ?,
          role_id = ?
        WHERE id = ?";

    $stmt = $this->db->prepare($sql);
    if ($stmt === false) { throw new Exception("Gagal Prepare Statement UPDATE: " . $this->db->error); }
   
    $stmt->bind_param("sssii", // sss untuk string, i untuk role_id, i untuk id
      $this->name,
      $this->email,
      $this->phone,
      $this->role_id,
      $this->id
    );

    if($stmt->execute() === false) { throw new Exception("Gagal Execute Statement UPDATE: " . $stmt->error); }
    $stmt->close();
    return self::find($this->id);
  }
    
    // =====================================================================
    // --- FUNGSI BARU: UPDATE STATUS ---
    // =====================================================================
    /**
     * Memperbarui kolom status (soft delete/aktivasi)
     * @param int $new_status Nilai status baru (0=Aktif, 1=Nonaktif)
     * @return bool True jika berhasil.
     */
    private function updateStatus(int $new_status){
        if (!$this->id) { throw new Exception("Tidak dapat memperbarui status pengguna yang belum tersimpan."); }
   
    $sql = "UPDATE users SET status = ? WHERE id = ?";
   
    $stmt = $this->db->prepare($sql);
    if ($stmt === false) { throw new Exception("Gagal Prepare Statement UPDATE STATUS: " . $this->db->error); }
   
    $stmt->bind_param("ii", $new_status, $this->id);
   
    if($stmt->execute() === false) { throw new Exception("Gagal Execute Statement UPDATE STATUS: " . $stmt->error); }
    $stmt->close();
        
        // Perbarui properti status objek setelah sukses
        $this->status = $new_status;
    return true;
    }
    
    // --- OPERASI SOFT DELETE (Memanggil updateStatus(1)) ---
  public function delete(){
        return $this->updateStatus(1); // 1 = Tidak Aktif / Soft Delete
  }
    
    // --- OPERASI AKTIVASI (BARU - Memanggil updateStatus(0)) ---
    public function activate(){
        return $this->updateStatus(0); // 0 = Aktif
    }

	// --- OPERASI UPDATE PASSWORD BARU (Ditambahkan Kembali) ---
  /**
  * Memperbarui password dan last_password pengguna.
  * @param string $new_hashed_password Password baru yang sudah di-hash.
  * @return bool True jika berhasil, throw Exception jika gagal.
  */
  public function updatePassword($new_hashed_password) {
    if (!$this->id) {
      throw new Exception("Tidak dapat memperbarui password pengguna yang belum tersimpan.");
    }

    // Memperbarui password dan last_password
    $sql = "UPDATE users SET
          password = ?,
          last_password = ?
        WHERE id = ?";

    $stmt = $this->db->prepare($sql);
    if ($stmt === false) {
      throw new Exception("Gagal Prepare Statement UPDATE PASSWORD: " . $this->db->error);
    }

    // Binding parameter: s (password), s (last_password), i (id)
    $stmt->bind_param("ssi",
      $new_hashed_password,
      $new_hashed_password,
      $this->id
    );

    if($stmt->execute() === false) {
      throw new Exception("Gagal Execute Statement UPDATE PASSWORD: " . $stmt->error);
    }
   
    // Memperbarui properti objek saat ini setelah sukses
    $this->password = $new_hashed_password;
    $this->last_password = $new_hashed_password;
   
    $stmt->close();
    return true;
  }
	
}