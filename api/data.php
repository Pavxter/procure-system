<?php
// ============================================================
// KOLOID NABAVKA — CRUD za sve entitete
// ============================================================
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    jsonError('Neautorizovan pristup', 401);
}

$user   = $_SESSION['user'];
$entity = $_GET['entity'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$method = $_SERVER['REQUEST_METHOD'];

// Viewer moze samo GET
if ($user['uloga'] === 'viewer' && $method !== 'GET') {
    jsonError('Nemate dozvolu za izmenu podataka', 403);
}

function logActivity($pdo, $user, $akcija, $entitet, $entitet_id, $detalji = '') {
    $stmt = $pdo->prepare("INSERT INTO activity_log (korisnik, akcija, entitet, entitet_id, detalji) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user['ime'], $akcija, $entitet, $entitet_id, $detalji]);
}

switch ($entity) {
    case 'stats':         handleStats();                         break;
    case 'sirovine':      handleSirovine($method, $id, $user);  break;
    case 'dobavljaci':    handleDobavljaci($method, $id, $user); break;
    case 'narudzbine':    handleNarudzbine($method, $id, $user); break;
    case 'kontroling':    handleKontroling($method, $user);      break;
    case 'plan_nabavke':  handlePlanNabavke($method, $id, $user);break;
    case 'plan_ugovora':  handlePlanUgovora($method, $id, $user);break;
    case 'kategorije':    handleKategorije($method, $id, $user);  break;
    case 'activity_log':  handleActivityLog();                   break;
    default: jsonError('Nepoznat entitet', 400);
}

// ============================================================
// STATS
// ============================================================
function handleStats() {
    $pdo = getDB();
    $stats = [
        'totalSirovine'     => (int)$pdo->query("SELECT COUNT(*) FROM sirovine WHERE aktivan=1")->fetchColumn(),
        'kriticneSirovine'  => (int)$pdo->query("SELECT COUNT(*) FROM sirovine WHERE aktivan=1 AND stanje < minimum")->fetchColumn(),
        'aktivneNarudzbine' => (int)$pdo->query("SELECT COUNT(*) FROM narudzbine WHERE status IN ('naruceno','cekanje')")->fetchColumn(),
        'totalDobavljaci'   => (int)$pdo->query("SELECT COUNT(*) FROM dobavljaci WHERE aktivan=1")->fetchColumn(),
        'potpisaniUgovori'  => (int)$pdo->query("SELECT COUNT(*) FROM plan_ugovora WHERE status='potpisan'")->fetchColumn(),
        'totalUgovori'      => (int)$pdo->query("SELECT COUNT(*) FROM plan_ugovora")->fetchColumn(),
    ];

    // Kriticne sirovine (detalji)
    $stats['kriticneDetalji'] = $pdo->query("
        SELECT s.id, s.naziv, s.kategorija, s.jm, s.stanje, s.minimum,
               CASE WHEN s.stanje = 0 THEN 'kriticno'
                    WHEN s.stanje < s.minimum * 0.5 THEN 'kriticno'
                    ELSE 'nisko'
               END as nivo,
               d.naziv as dobavljac_naziv
        FROM sirovine s
        LEFT JOIN dobavljaci d ON s.dobavljac_id = d.id
        WHERE s.aktivan=1 AND s.stanje < s.minimum
        ORDER BY s.stanje / NULLIF(s.minimum, 0) ASC
        LIMIT 10
    ")->fetchAll();

    jsonResponse($stats);
}

// ============================================================
// SIROVINE
// ============================================================
function handleSirovine($method, $id, $user) {
    $pdo = getDB();

    if ($method === 'GET') {
        if ($id) {
            $stmt = $pdo->prepare("SELECT s.*, d.naziv as dobavljac_naziv FROM sirovine s LEFT JOIN dobavljaci d ON s.dobavljac_id=d.id WHERE s.id=? AND s.aktivan=1");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonError('Sirovina nije pronadjena', 404);
            jsonResponse($row);
        }
        $search    = $_GET['search'] ?? '';
        $kategorija = $_GET['kategorija'] ?? '';
        $sql = "SELECT s.*, d.naziv as dobavljac_naziv FROM sirovine s LEFT JOIN dobavljaci d ON s.dobavljac_id=d.id WHERE s.aktivan=1";
        $params = [];
        if ($search) {
            $sql .= " AND (s.naziv LIKE ? OR s.kategorija LIKE ?)";
            $params[] = "%{$search}%"; $params[] = "%{$search}%";
        }
        if ($kategorija) {
            $sql .= " AND s.kategorija = ?";
            $params[] = $kategorija;
        }
        $sql .= " ORDER BY s.naziv ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data  = getInput();
        $naziv = trim($data['naziv'] ?? '');
        if (!$naziv) jsonError('Naziv je obavezan');

        $stmt = $pdo->prepare("INSERT INTO sirovine (naziv, kategorija, jm, stanje, minimum, cena, dobavljac_id, napomena) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $naziv,
            $data['kategorija'] ?? null,
            $data['jm'] ?? 'kg',
            (float)($data['stanje'] ?? 0),
            (float)($data['minimum'] ?? 0),
            (float)($data['cena'] ?? 0),
            $data['dobavljacId'] ?? $data['dobavljac_id'] ?? null,
            $data['napomena'] ?? null,
        ]);
        $newId = $pdo->lastInsertId();
        logActivity($pdo, $user, 'CREATE', 'sirovine', $newId, "Dodato: {$naziv}");

        $stmt = $pdo->prepare("SELECT s.*, d.naziv as dobavljac_naziv FROM sirovine s LEFT JOIN dobavljaci d ON s.dobavljac_id=d.id WHERE s.id=?");
        $stmt->execute([$newId]);
        jsonResponse($stmt->fetch(), 201);
    }

    if ($method === 'PUT') {
        if (!$id) jsonError('ID je obavezan');
        $data  = getInput();
        $naziv = trim($data['naziv'] ?? '');
        if (!$naziv) jsonError('Naziv je obavezan');

        $stmt = $pdo->prepare("UPDATE sirovine SET naziv=?, kategorija=?, jm=?, stanje=?, minimum=?, cena=?, dobavljac_id=?, napomena=?, updated_at=NOW() WHERE id=? AND aktivan=1");
        $stmt->execute([
            $naziv,
            $data['kategorija'] ?? null,
            $data['jm'] ?? 'kg',
            (float)($data['stanje'] ?? 0),
            (float)($data['minimum'] ?? 0),
            (float)($data['cena'] ?? 0),
            $data['dobavljacId'] ?? $data['dobavljac_id'] ?? null,
            $data['napomena'] ?? null,
            $id,
        ]);
        logActivity($pdo, $user, 'UPDATE', 'sirovine', $id, "Izmenjeno: {$naziv}");

        $stmt = $pdo->prepare("SELECT s.*, d.naziv as dobavljac_naziv FROM sirovine s LEFT JOIN dobavljaci d ON s.dobavljac_id=d.id WHERE s.id=?");
        $stmt->execute([$id]);
        jsonResponse($stmt->fetch());
    }

    if ($method === 'DELETE') {
        if (!$id) jsonError('ID je obavezan');
        $stmt = $pdo->prepare("SELECT naziv FROM sirovine WHERE id=? AND aktivan=1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonError('Sirovina nije pronadjena', 404);

        $pdo->prepare("UPDATE sirovine SET aktivan=0 WHERE id=?")->execute([$id]);
        logActivity($pdo, $user, 'DELETE', 'sirovine', $id, "Obrisano: {$row['naziv']}");
        jsonResponse(['success' => true]);
    }
}

// ============================================================
// DOBAVLJACI
// ============================================================
function handleDobavljaci($method, $id, $user) {
    $pdo = getDB();

    if ($method === 'GET') {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM dobavljaci WHERE id=? AND aktivan=1");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonError('Dobavljac nije pronadjen', 404);
            jsonResponse($row);
        }
        $search = $_GET['search'] ?? '';
        $sql = "SELECT * FROM dobavljaci WHERE aktivan=1";
        $params = [];
        if ($search) {
            $sql .= " AND (naziv LIKE ? OR kontakt_osoba LIKE ? OR email LIKE ?)";
            $params[] = "%{$search}%"; $params[] = "%{$search}%"; $params[] = "%{$search}%";
        }
        $sql .= " ORDER BY naziv ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data  = getInput();
        $naziv = trim($data['naziv'] ?? '');
        if (!$naziv) jsonError('Naziv je obavezan');

        $ocena = (int)($data['ocena'] ?? 3);
        $stmt  = $pdo->prepare("INSERT INTO dobavljaci (naziv, kontakt_osoba, telefon, email, adresa, ocena, ocena_kvalitet, ocena_cena, ocena_rokovi, ocena_placanje, ocena_reklamacije, ugovor, napomena) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $naziv,
            $data['kontakt_osoba'] ?? null,
            $data['telefon'] ?? null,
            $data['email'] ?? null,
            $data['adresa'] ?? null,
            $ocena,
            (int)($data['ocena_kvalitet'] ?? $ocena),
            (int)($data['ocena_cena'] ?? $ocena),
            (int)($data['ocena_rokovi'] ?? $ocena),
            (int)($data['ocena_placanje'] ?? $ocena),
            (int)($data['ocena_reklamacije'] ?? $ocena),
            (int)($data['ugovor'] ?? 0),
            $data['napomena'] ?? null,
        ]);
        $newId = $pdo->lastInsertId();
        logActivity($pdo, $user, 'CREATE', 'dobavljaci', $newId, "Dodat: {$naziv}");

        $stmt = $pdo->prepare("SELECT * FROM dobavljaci WHERE id=?");
        $stmt->execute([$newId]);
        jsonResponse($stmt->fetch(), 201);
    }

    if ($method === 'PUT') {
        if (!$id) jsonError('ID je obavezan');
        $data  = getInput();
        $naziv = trim($data['naziv'] ?? '');
        if (!$naziv) jsonError('Naziv je obavezan');

        $ocena = (int)($data['ocena'] ?? 3);
        $stmt  = $pdo->prepare("UPDATE dobavljaci SET naziv=?, kontakt_osoba=?, telefon=?, email=?, adresa=?, ocena=?, ocena_kvalitet=?, ocena_cena=?, ocena_rokovi=?, ocena_placanje=?, ocena_reklamacije=?, ugovor=?, napomena=?, updated_at=NOW() WHERE id=? AND aktivan=1");
        $stmt->execute([
            $naziv,
            $data['kontakt_osoba'] ?? null,
            $data['telefon'] ?? null,
            $data['email'] ?? null,
            $data['adresa'] ?? null,
            $ocena,
            (int)($data['ocena_kvalitet'] ?? $ocena),
            (int)($data['ocena_cena'] ?? $ocena),
            (int)($data['ocena_rokovi'] ?? $ocena),
            (int)($data['ocena_placanje'] ?? $ocena),
            (int)($data['ocena_reklamacije'] ?? $ocena),
            (int)($data['ugovor'] ?? 0),
            $data['napomena'] ?? null,
            $id,
        ]);
        logActivity($pdo, $user, 'UPDATE', 'dobavljaci', $id, "Izmenjeno: {$naziv}");

        $stmt = $pdo->prepare("SELECT * FROM dobavljaci WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse($stmt->fetch());
    }

    if ($method === 'DELETE') {
        if (!$id) jsonError('ID je obavezan');
        $stmt = $pdo->prepare("SELECT naziv FROM dobavljaci WHERE id=? AND aktivan=1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonError('Dobavljac nije pronadjen', 404);

        $pdo->prepare("UPDATE dobavljaci SET aktivan=0 WHERE id=?")->execute([$id]);
        logActivity($pdo, $user, 'DELETE', 'dobavljaci', $id, "Obrisano: {$row['naziv']}");
        jsonResponse(['success' => true]);
    }
}

// ============================================================
// NARUDZBINE
// ============================================================
function handleNarudzbine($method, $id, $user) {
    $pdo = getDB();

    if ($method === 'GET') {
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT n.*, s.naziv as sirovina_naziv, s.jm, d.naziv as dobavljac_naziv
                FROM narudzbine n
                LEFT JOIN sirovine s ON n.sirovina_id=s.id
                LEFT JOIN dobavljaci d ON n.dobavljac_id=d.id
                WHERE n.id=?
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonError('Narudzbina nije pronadjena', 404);
            jsonResponse($row);
        }

        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        $sql = "SELECT n.*, s.naziv as sirovina_naziv, s.jm, d.naziv as dobavljac_naziv
                FROM narudzbine n
                LEFT JOIN sirovine s ON n.sirovina_id=s.id
                LEFT JOIN dobavljaci d ON n.dobavljac_id=d.id
                WHERE 1=1";
        $params = [];
        if ($status) { $sql .= " AND n.status = ?"; $params[] = $status; }
        if ($search) {
            $sql .= " AND (s.naziv LIKE ? OR d.naziv LIKE ?)";
            $params[] = "%{$search}%"; $params[] = "%{$search}%";
        }
        $sql .= " ORDER BY n.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data     = getInput();
        $kolicina = (float)($data['kolicina'] ?? 0);
        if ($kolicina <= 0) jsonError('Kolicina mora biti veca od 0');

        $cena_jm    = (float)($data['cena_po_jm'] ?? 0);
        $ukupna_cena = $kolicina * $cena_jm;

        $stmt = $pdo->prepare("INSERT INTO narudzbine (sirovina_id, dobavljac_id, kolicina, cena_po_jm, ukupna_cena, status, datum_narucivanja, datum_isporuke, napomena, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['sirovinaId'] ?? $data['sirovina_id'] ?? null,
            $data['dobavljacId'] ?? $data['dobavljac_id'] ?? null,
            $kolicina,
            $cena_jm,
            $ukupna_cena,
            $data['status'] ?? 'naruceno',
            $data['datum_narucivanja'] ?? date('Y-m-d'),
            $data['datum_isporuke'] ?? null,
            $data['napomena'] ?? null,
            $user['ime'],
        ]);
        $newId = $pdo->lastInsertId();
        logActivity($pdo, $user, 'CREATE', 'narudzbine', $newId, "Kreirana narudzbina #{$newId}");

        $stmt = $pdo->prepare("SELECT n.*, s.naziv as sirovina_naziv, s.jm, d.naziv as dobavljac_naziv FROM narudzbine n LEFT JOIN sirovine s ON n.sirovina_id=s.id LEFT JOIN dobavljaci d ON n.dobavljac_id=d.id WHERE n.id=?");
        $stmt->execute([$newId]);
        jsonResponse($stmt->fetch(), 201);
    }

    if ($method === 'PUT') {
        if (!$id) jsonError('ID je obavezan');
        $data     = getInput();
        $kolicina = (float)($data['kolicina'] ?? 0);
        if ($kolicina <= 0) jsonError('Kolicina mora biti veca od 0');

        $cena_jm     = (float)($data['cena_po_jm'] ?? 0);
        $ukupna_cena = $kolicina * $cena_jm;

        // Ako je status -> isporuceno, azuriraj stanje sirovine
        $oldStatus = $pdo->prepare("SELECT status, sirovina_id FROM narudzbine WHERE id=?");
        $oldStatus->execute([$id]);
        $old = $oldStatus->fetch();

        $stmt = $pdo->prepare("UPDATE narudzbine SET sirovina_id=?, dobavljac_id=?, kolicina=?, cena_po_jm=?, ukupna_cena=?, status=?, datum_narucivanja=?, datum_isporuke=?, napomena=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([
            $data['sirovinaId'] ?? $data['sirovina_id'] ?? null,
            $data['dobavljacId'] ?? $data['dobavljac_id'] ?? null,
            $kolicina,
            $cena_jm,
            $ukupna_cena,
            $data['status'] ?? 'naruceno',
            $data['datum_narucivanja'] ?? date('Y-m-d'),
            $data['datum_isporuke'] ?? null,
            $data['napomena'] ?? null,
            $id,
        ]);

        // Automatski povecaj stanje sirovine kad je isporuceno
        $newStatus = $data['status'] ?? 'naruceno';
        if ($old && $old['status'] !== 'isporuceno' && $newStatus === 'isporuceno' && $old['sirovina_id']) {
            $pdo->prepare("UPDATE sirovine SET stanje = stanje + ? WHERE id=?")
                ->execute([$kolicina, $old['sirovina_id']]);
        }

        logActivity($pdo, $user, 'UPDATE', 'narudzbine', $id, "Status: {$newStatus}");

        $stmt = $pdo->prepare("SELECT n.*, s.naziv as sirovina_naziv, s.jm, d.naziv as dobavljac_naziv FROM narudzbine n LEFT JOIN sirovine s ON n.sirovina_id=s.id LEFT JOIN dobavljaci d ON n.dobavljac_id=d.id WHERE n.id=?");
        $stmt->execute([$id]);
        jsonResponse($stmt->fetch());
    }

    if ($method === 'DELETE') {
        if (!$id) jsonError('ID je obavezan');
        $pdo->prepare("DELETE FROM narudzbine WHERE id=?")->execute([$id]);
        logActivity($pdo, $user, 'DELETE', 'narudzbine', $id, "Obrisana narudzbina #{$id}");
        jsonResponse(['success' => true]);
    }
}

// ============================================================
// KONTROLING
// ============================================================
function handleKontroling($method, $user) {
    $pdo = getDB();

    if ($method === 'GET') {
        $rows = $pdo->query("SELECT oblast, polje, vrednost FROM kontroling ORDER BY oblast, polje")->fetchAll();
        // Grupisi po oblasti
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['oblast']][$r['polje']] = $r['vrednost'];
        }
        jsonResponse($grouped);
    }

    if ($method === 'POST' || $method === 'PUT') {
        $data   = getInput();
        $oblast = trim($data['oblast'] ?? '');
        $polje  = trim($data['polje'] ?? '');

        if (!$oblast || !$polje) jsonError('Oblast i polje su obavezni');

        $vrednost = $data['vrednost'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO kontroling (oblast, polje, vrednost, updated_by) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE vrednost=VALUES(vrednost), updated_by=VALUES(updated_by), updated_at=NOW()");
        $stmt->execute([$oblast, $polje, $vrednost, $user['ime']]);

        jsonResponse(['success' => true, 'oblast' => $oblast, 'polje' => $polje]);
    }
}

// ============================================================
// PLAN NABAVKE
// ============================================================
function handlePlanNabavke($method, $id, $user) {
    $pdo = getDB();

    if ($method === 'GET') {
        $godina    = (int)($_GET['godina'] ?? date('Y'));
        $kategorija = $_GET['kategorija'] ?? '';

        $sql = "SELECT p.*, s.naziv as sirovina_naziv, s.jm, s.kategorija
                FROM plan_nabavke p
                JOIN sirovine s ON p.sirovina_id=s.id
                WHERE p.godina=? AND s.aktivan=1";
        $params = [$godina];
        if ($kategorija) { $sql .= " AND s.kategorija=?"; $params[] = $kategorija; }
        $sql .= " ORDER BY s.kategorija, s.naziv";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Dodaj realizovano iz narudzbina (isporuceno u toj godini)
        foreach ($rows as &$row) {
            for ($m = 1; $m <= 12; $m++) {
                $mesecStr = str_pad($m, 2, '0', STR_PAD_LEFT);
                $stmt2 = $pdo->prepare("
                    SELECT COALESCE(SUM(kolicina), 0) FROM narudzbine
                    WHERE sirovina_id=? AND status='isporuceno'
                    AND YEAR(datum_isporuke)=? AND MONTH(datum_isporuke)=?
                ");
                $stmt2->execute([$row['sirovina_id'], $godina, $m]);
                $row["real_{$m}"] = (float)$stmt2->fetchColumn();
            }
        }
        jsonResponse($rows);
    }

    if ($method === 'POST' || $method === 'PUT') {
        $data       = getInput();
        $sirovinaId = (int)($data['sirovinaId'] ?? $data['sirovina_id'] ?? 0);
        $godina     = (int)($data['godina'] ?? date('Y'));
        if (!$sirovinaId || !$godina) jsonError('sirovina_id i godina su obavezni');

        $fields = [];
        $params = [];
        for ($m = 1; $m <= 12; $m++) {
            $fields[] = "mesec_{$m}";
            $fields[] = "budzet_{$m}";
            $params[] = (float)($data["mesec_{$m}"] ?? 0);
            $params[] = (float)($data["budzet_{$m}"] ?? 0);
        }

        $cols   = implode(', ', $fields);
        $vals   = implode(', ', array_fill(0, count($fields), '?'));
        $update = implode(', ', array_map(fn($f) => "{$f}=VALUES({$f})", $fields));

        $sql = "INSERT INTO plan_nabavke (sirovina_id, godina, {$cols}, created_by)
                VALUES (?, ?, {$vals}, ?)
                ON DUPLICATE KEY UPDATE {$update}, updated_at=NOW()";

        $allParams = array_merge([$sirovinaId, $godina], $params, [$user['ime']]);
        $pdo->prepare($sql)->execute($allParams);

        jsonResponse(['success' => true]);
    }
}

// ============================================================
// PLAN UGOVORA
// ============================================================
function handlePlanUgovora($method, $id, $user) {
    $pdo = getDB();

    if ($method === 'GET') {
        if ($id) {
            $stmt = $pdo->prepare("SELECT p.*, d.naziv as dobavljac_naziv, d.kontakt_osoba, d.telefon, d.email, d.ocena FROM plan_ugovora p JOIN dobavljaci d ON p.dobavljac_id=d.id WHERE p.id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonError('Ugovor nije pronadjen', 404);
            jsonResponse($row);
        }

        $status = $_GET['status'] ?? '';
        $sql = "SELECT p.*, d.naziv as dobavljac_naziv, d.kontakt_osoba, d.telefon, d.email, d.ocena
                FROM plan_ugovora p
                JOIN dobavljaci d ON p.dobavljac_id=d.id
                WHERE d.aktivan=1";
        $params = [];
        if ($status) { $sql .= " AND p.status=?"; $params[] = $status; }
        $sql .= " ORDER BY p.prioritet DESC, d.naziv ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data       = getInput();
        $dobavljacId = (int)($data['dobavljacId'] ?? $data['dobavljac_id'] ?? 0);
        if (!$dobavljacId) jsonError('dobavljac_id je obavezan');

        try {
            $stmt = $pdo->prepare("INSERT INTO plan_ugovora (dobavljac_id, status, datum_potpisa, datum_isteka, rok_placanja, rabat, min_kolicina, prioritet, napomena, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $dobavljacId,
                $data['status'] ?? 'nema_ugovor',
                $data['datum_potpisa'] ?? null,
                $data['datum_isteka'] ?? null,
                $data['rok_placanja'] ?? null,
                $data['rabat'] ?? null,
                $data['min_kolicina'] ?? null,
                (int)($data['prioritet'] ?? 2),
                $data['napomena'] ?? null,
                $user['ime'],
            ]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $user, 'CREATE', 'plan_ugovora', $newId, "Dodat ugovor za dobavljaca ID:{$dobavljacId}");

            $stmt = $pdo->prepare("SELECT p.*, d.naziv as dobavljac_naziv, d.kontakt_osoba, d.telefon, d.email, d.ocena FROM plan_ugovora p JOIN dobavljaci d ON p.dobavljac_id=d.id WHERE p.id=?");
            $stmt->execute([$newId]);
            jsonResponse($stmt->fetch(), 201);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) jsonError('Ugovor za ovog dobavljaca vec postoji. Koristite PUT za izmenu.', 409);
            throw $e;
        }
    }

    if ($method === 'PUT') {
        if (!$id) jsonError('ID je obavezan');
        $data = getInput();

        $stmt = $pdo->prepare("UPDATE plan_ugovora SET status=?, datum_potpisa=?, datum_isteka=?, rok_placanja=?, rabat=?, min_kolicina=?, prioritet=?, napomena=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([
            $data['status'] ?? 'nema_ugovor',
            $data['datum_potpisa'] ?? null,
            $data['datum_isteka'] ?? null,
            $data['rok_placanja'] ?? null,
            $data['rabat'] ?? null,
            $data['min_kolicina'] ?? null,
            (int)($data['prioritet'] ?? 2),
            $data['napomena'] ?? null,
            $id,
        ]);
        logActivity($pdo, $user, 'UPDATE', 'plan_ugovora', $id, "Izmenjen ugovor #{$id}");

        $stmt = $pdo->prepare("SELECT p.*, d.naziv as dobavljac_naziv, d.kontakt_osoba, d.telefon, d.email, d.ocena FROM plan_ugovora p JOIN dobavljaci d ON p.dobavljac_id=d.id WHERE p.id=?");
        $stmt->execute([$id]);
        jsonResponse($stmt->fetch());
    }

    if ($method === 'DELETE') {
        if (!$id) jsonError('ID je obavezan');
        $pdo->prepare("DELETE FROM plan_ugovora WHERE id=?")->execute([$id]);
        logActivity($pdo, $user, 'DELETE', 'plan_ugovora', $id, "Obrisan ugovor #{$id}");
        jsonResponse(['success' => true]);
    }
}

// ============================================================
// KATEGORIJE
// ============================================================
function handleKategorije($method, $id, $user) {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS kategorije (
        id INT AUTO_INCREMENT PRIMARY KEY,
        naziv VARCHAR(100) NOT NULL,
        UNIQUE KEY naziv_unique (naziv)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($method === 'GET') {
        jsonResponse($pdo->query("SELECT * FROM kategorije ORDER BY naziv ASC")->fetchAll());
    }

    if ($method === 'POST') {
        if ($user['uloga'] === 'viewer') jsonError('Pristup odbijen', 403);
        $naziv = trim((getInput())['naziv'] ?? '');
        if (!$naziv) jsonError('Naziv je obavezan');
        try {
            $pdo->prepare("INSERT INTO kategorije (naziv) VALUES (?)")->execute([$naziv]);
            jsonResponse(['id' => $pdo->lastInsertId(), 'naziv' => $naziv], 201);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) jsonError('Kategorija vec postoji', 409);
            throw $e;
        }
    }

    if ($method === 'DELETE') {
        if ($user['uloga'] !== 'admin') jsonError('Pristup odbijen', 403);
        if (!$id) jsonError('ID je obavezan');
        $pdo->prepare("DELETE FROM kategorije WHERE id=?")->execute([$id]);
        jsonResponse(['success' => true]);
    }
}

// ============================================================
// ACTIVITY LOG
// ============================================================
function handleActivityLog() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Metoda nije dozvoljena', 405);
    $pdo   = getDB();
    $limit = (int)($_GET['limit'] ?? 20);
    $rows  = $pdo->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?");
    $rows->execute([$limit]);
    jsonResponse($rows->fetchAll());
}
