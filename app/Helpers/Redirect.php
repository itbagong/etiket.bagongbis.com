<?php
namespace App\Helpers;

class Redirect
{
    public static function byRole(string $role): void
    {
        $target = ($role === 'admin' || $role === 'teknisi')
            ? 'dashboard.php'
            : 'user_dashboard.php';

        header("Location: {$target}");
        exit();
    }
}
