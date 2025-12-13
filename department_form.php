<?php
// department_form.php - Logika Create/Update (REVISI: RBAC & Cleanup)
ob_start();

include './header.php'; 

require_once 'src/Database.php';
require_once 'src/Department.php';

$err = '';
$msg = '';

if (isset($_POST['save_department'])) {
    
    $id = intval($_POST['department_id'] ?? 0);
    $name = trim($_POST['department_name'] ?? '');
    $is_editing = $id > 0;

    // =========================================================================
    // RBAC CHECK: Server-Side Otorisasi untuk Create/Update
    // =========================================================================
    try {
        if ($is_editing) {
            // Jika EDIT
            $auth->authorize('department_list.php', 'edit');
        } else {
            // Jika ADD
            $auth->authorize('department_list.php', 'add');
        }
    } catch (Exception $e) {
        $err = "Akses Ditolak: Anda tidak memiliki izin untuk menyimpan departemen.";
        // Langsung redirect dengan error
        header('Location: department_list.php?err=' . urlencode($err));
        exit();
    }
    // =========================================================================
    
    // Validasi input
    if (strlen($name) < 2 || strlen($_POST['department_abb'] ?? '') < 1) { 
        $err = "Nama departemen dan singkatan wajib diisi.";
    } 
    
    if (empty($err)) {
        try {
            // Membuat objek Departemen dari data POST
            $department = new Department($_POST);
            
            // Memanggil fungsi save() yang sudah menggunakan Prepared Statements
            $department->save();
            
            $msg = $is_editing ? "Departemen **" . htmlspecialchars($name) . "** berhasil diperbarui." : "Departemen **" . htmlspecialchars($name) . "** berhasil ditambahkan.";
            
            header('Location: department_list.php?msg=' . urlencode($msg));
            exit();

        } catch(Exception $e) {
            // Tangkap Error dari Model Department
            $err = "Gagal menyimpan data: " . $e->getMessage();
            header('Location: department_list.php?err=' . urlencode($err));
            exit();
        }
    }
}

// Redirect fallback (jika diakses tanpa POST) atau jika ada error validasi di atas
header('Location: department_list.php?err=' . urlencode($err));
exit();