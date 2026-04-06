<?php
/** Auth - Login */
$method = Router::getMethod();
$input = Router::getInput();

if ($method !== 'POST') Response::error('Method not allowed', 405);

if (empty($input['username']) || empty($input['password'])) {
    Response::error('Username dan password harus diisi');
}

$user = Auth::login($input['username'], $input['password']);
if (!$user) {
    Response::error('Username atau password salah', 401);
}

Response::success($user, 'Login berhasil');
