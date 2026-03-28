<?php
// ============================================================
// KOLOID NABAVKA — Autentikacija i upravljanje korisnicima
// ============================================================
session_start();
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Javni endpoint — login
if ($action === 'login') {
    if ($method !== 'POST') jsonError('Metoda nije dozvoljena', 405);
    $data = getInput();
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if (!$username || !$password) jsonError('Korisnicko ime i lozinka su obavezni');

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND aktivan = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonError('Pogresno korisnicko ime ili lozinka', 401);
    }

    $_SESSION['user'] = [
        'id'       => $user['id'],
        'username' => $user['username'],
        'ime'      => $user['ime'],
        'uloga'    => $user['uloga'],
    ];

    // Log login
    $pdo->prepare("INSERT INTO activity_log (korisnik, akcija, entitet, detalji) VALUES (?, 'LOGIN', 'auth', 'Prijava na sistem')")
        ->execute([$user['ime']]);

    jsonResponse([
        'id'       => $user['id'],
        'username' => $user['username'],
        'ime'      => $user['ime'],
        'uloga'    => $user['uloga'],
    ]);
}

// Sve ostalo zahteva autentikaciju
if (!isset($_SESSION['user'])) {
    jsonError('Neautorizovan pristup', 401);
}
$sessionUser = $_SESSION['user'];

switch ($action) {
    case 'me':
        jsonResponse($sessionUser);
        break;

    case 'logout':
        $pdo = getDB();
        $pdo->prepare("INSERT INTO activity_log (korisnik, akcija, entitet, detalji) VALUES (?, 'LOGOUT', 'auth', 'Odjava sa sistema')")
            ->execute([$sessionUser['ime']]);
        session_destroy();
        jsonResponse(['success' => true]);
        break;

    case 'change-password':
        if ($method !== 'POST') jsonError('Metoda nije dozvoljena', 405);
        $data = getInput();
        $old  = $data['oldPassword'] ?? '';
        $new  = $data['newPassword'] ?? '';

        if (!$old || !$new) jsonError('Stara i nova lozinka su obavezne');
        if (strlen($new) < 6) jsonError('Nova lozinka mora imati najmanje 6 karaktera');

        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$sessionUser['id']]);
        $row = $stmt->fetch();

        if (!password_verify($old, $row['password_hash'])) {
            jsonError('Stara lozinka nije ispravna', 400);
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $sessionUser['id']]);
        jsonResponse(['success' => true]);
        break;

    case 'users':
        if ($sessionUser['uloga'] !== 'admin') jsonError('Pristup odbijen', 403);
        $pdo = getDB();
        $users = $pdo->query("SELECT id, username, ime, uloga, aktivan, created_at FROM users ORDER BY ime")->fetchAll();
        jsonResponse($users);
        break;

    case 'create-user':
        if ($sessionUser['uloga'] !== 'admin') jsonError('Pristup odbijen', 403);
        if ($method !== 'POST') jsonError('Metoda nije dozvoljena', 405);

        $data     = getInput();
        $username = trim($data['username'] ?? '');
        $ime      = trim($data['ime'] ?? '');
        $uloga    = $data['uloga'] ?? 'viewer';
        $password = $data['password'] ?? 'koloid2026';

        if (!$username || !$ime) jsonError('Korisnicko ime i ime su obavezni');
        if (!in_array($uloga, ['admin', 'nabavka', 'viewer'])) jsonError('Neispravna uloga');

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo  = getDB();

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, ime, uloga) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $ime, $uloga]);
            $newId = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO activity_log (korisnik, akcija, entitet, entitet_id, detalji) VALUES (?, 'CREATE', 'users', ?, ?)")
                ->execute([$sessionUser['ime'], $newId, "Kreiran korisnik: $username"]);
            jsonResponse(['id' => $newId, 'username' => $username, 'ime' => $ime, 'uloga' => $uloga, 'aktivan' => 1], 201);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) jsonError('Korisnicko ime vec postoji', 409);
            throw $e;
        }
        break;

    case 'update-user':
        if ($sessionUser['uloga'] !== 'admin') jsonError('Pristup odbijen', 403);
        if ($method !== 'PUT') jsonError('Metoda nije dozvoljena', 405);

        $id   = (int)($_GET['id'] ?? 0);
        if (!$id) jsonError('ID je obavezan');

        $data  = getInput();
        $ime   = trim($data['ime'] ?? '');
        $uloga = $data['uloga'] ?? '';
        $aktivan = isset($data['aktivan']) ? (int)$data['aktivan'] : 1;

        if (!$ime) jsonError('Ime je obavezno');
        if (!in_array($uloga, ['admin', 'nabavka', 'viewer'])) jsonError('Neispravna uloga');

        $pdo = getDB();
        $pdo->prepare("UPDATE users SET ime = ?, uloga = ?, aktivan = ? WHERE id = ?")
            ->execute([$ime, $uloga, $aktivan, $id]);

        // Reset password ako je prosledjen
        if (!empty($data['newPassword'])) {
            if (strlen($data['newPassword']) < 6) jsonError('Lozinka mora imati najmanje 6 karaktera');
            $hash = password_hash($data['newPassword'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
        }

        $pdo->prepare("INSERT INTO activity_log (korisnik, akcija, entitet, entitet_id, detalji) VALUES (?, 'UPDATE', 'users', ?, ?)")
            ->execute([$sessionUser['ime'], $id, "Izmenjen korisnik ID: $id"]);

        jsonResponse(['success' => true]);
        break;

    case 'delete-user':
        if ($sessionUser['uloga'] !== 'admin') jsonError('Pristup odbijen', 403);
        if ($method !== 'DELETE') jsonError('Metoda nije dozvoljena', 405);

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonError('ID je obavezan');
        if ($id === $sessionUser['id']) jsonError('Ne mozete obrisati sopstveni nalog', 400);

        $pdo = getDB();
        $pdo->prepare("UPDATE users SET aktivan = 0 WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO activity_log (korisnik, akcija, entitet, entitet_id, detalji) VALUES (?, 'DELETE', 'users', ?, ?)")
            ->execute([$sessionUser['ime'], $id, "Deaktiviran korisnik ID: $id"]);

        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Nepoznata akcija', 400);
}
