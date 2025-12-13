<?php

function cleanInput($input)
{
    return filter_var($input, FILTER_SANITIZE_SPECIAL_CHARS);
}

function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}


function isValidPhone($phone) {
    // 1. Bersihkan string dari karakter non-angka yang diizinkan (spasi, tanda kurung, tanda hubung).
    $cleaned_phone = preg_replace('/[^\d\+]/', '', $phone);

    // 2. Jika nomor telepon dikosongkan setelah dibersihkan, itu tidak valid.
    if (empty($cleaned_phone)) {
        return false;
    }

    // 3. Regex untuk memeriksa angka, opsional tanda '+' di awal.
    // Memastikan formatnya adalah angka, dengan panjang antara 8 hingga 15 digit.
    // Ini adalah pemeriksaan yang cukup longgar (fleksibel) untuk umum.
    $pattern = '/^\+?[\d\s\-\(\)]{8,15}$/';
    if (!preg_match($pattern, $phone)) {
        return false;
    }
    
    // 4. Periksa panjang string setelah dibersihkan (hanya angka, tanpa '+')
    // Jika Anda ingin validasi yang sangat ketat (hanya angka dan +)
    $pure_digits = preg_replace('/[^\d]/', '', $phone);
    
    // Kita cek panjang pure digits (misal 10 digit)
    if (strlen($pure_digits) < 8 || strlen($pure_digits) > 15) {
        return false;
    }
    
    return true;
}

function dnd($variable)
{
    echo '<pre>';
    var_dump($variable);
    echo '</pre>';
    die;
}