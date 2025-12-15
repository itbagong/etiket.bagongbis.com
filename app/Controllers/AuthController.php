<?php
namespace App\Controllers;

use App\Services\AuthService;

class AuthController
{
    private AuthService $service;
    public string $error = '';

    public function __construct()
    {
        $this->service = new AuthService();
    }

    public function login(): array
    {
        if (!isset($_POST['submit'])) {
            return ['error' => ''];
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '') {
            $this->error = 'Please enter email address';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error = 'Please enter a valid email address';
        } elseif ($password === '') {
            $this->error = 'Please enter your password';
        } else {
            $this->error = $this->service->authenticate($email, $password);
        }

        return ['error' => $this->error];
    }
}
