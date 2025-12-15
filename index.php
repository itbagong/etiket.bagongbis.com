<?php

session_start();

require_once __DIR__ . '/src/Database.php';

require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Services/AuthService.php';
require_once __DIR__ . '/app/Helpers/Redirect.php';

use App\Controllers\AuthController;
use app\Helpers\Redirect;

// Jika sudah login → redirect sesuai role
if (!empty($_SESSION['logged-in']) && $_SESSION['logged-in'] === true) {
    Redirect::byRole($_SESSION['user']->role ?? '');
}

// Proses login
$controller = new AuthController();
$result = $controller->login();

// Render view
require __DIR__ . '/views/auth/login.php';