<?php
// ============================================
// KOLOID NABAVKA — Konfiguracija baze
// ============================================
// 1. Kopirajte ovaj fajl u config.php
// 2. Izmenite podatke ispod prema vašem hostingu
// 3. NIKAD ne komitujte config.php sa pravim podacima!

define('DB_HOST', 'localhost');
define('DB_NAME', 'IME_BAZE_OVDE');
define('DB_USER', 'KORISNIK_OVDE');
define('DB_PASS', 'LOZINKA_OVDE');

define('SECRET_KEY', 'promenite-ovo-u-nasumican-tekst');
define('SESSION_LIFETIME', 28800);

// ============================================
// NE MENJAJTE ISPOD OVE LINIJE
// ============================================
date_default_timezone_set('Europe/Belgrade');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Greška pri konekciji na bazu: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $code = 400) {
    jsonResponse(['error' => $message], $code);
}

function getInput() {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}
