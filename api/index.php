<?php
/**
 * SHIFFIN - API Entry Point
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Suppress HTML errors
ob_start(); // Buffer any stray output

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Project root
$root = dirname(__DIR__);

// Autoload core
require_once $root . '/app/config/config.php';
require_once $root . '/app/core/Database.php';
require_once $root . '/app/core/Response.php';
require_once $root . '/app/core/Auth.php';
require_once $root . '/app/core/Router.php';

// Load models
foreach (glob($root . '/app/models/*.php') as $model) {
    require_once $model;
}

// Load services
foreach (glob($root . '/app/services/*.php') as $service) {
    require_once $service;
}

// Clear any buffered output before routing
ob_clean();

// Route the request
try {
    Router::handle();
} catch (PDOException $e) {
    ob_clean();
    Response::error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ob_clean();
    $code = $e->getCode();
    Response::error($e->getMessage(), ($code >= 100 && $code < 600) ? $code : 500);
}

