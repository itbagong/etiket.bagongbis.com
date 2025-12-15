<?php
namespace App\Services;

use Database;
use App\Helpers\Redirect;

class AuthService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function authenticate(string $email, string $password): string
    {
        $sql = "SELECT id, name, email, password, role_id FROM users WHERE email = ?";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return 'Database error';
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows < 1) {
            return 'No user found';
        }

        $user = $res->fetch_object();

        if (!password_verify($password, $user->password)) {
            return 'Wrong username or password';
        }

        session_regenerate_id(true);

        $roleName = $this->getRoleName($user->role_id);

        $_SESSION['logged-in'] = true;
        $_SESSION['user'] = (object)[
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $roleName
        ];

        Redirect::byRole($roleName);
        return '';
    }

    private function getRoleName(int $roleId): string
    {
        $stmt = $this->db->prepare("SELECT role_name FROM role WHERE role_id = ?");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $res = $stmt->get_result();

        return $res->num_rows > 0
            ? $res->fetch_assoc()['role_name']
            : 'guest';
    }
}
