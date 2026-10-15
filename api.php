<?php
// =====================================================================
// Zugangsdaten anpassen, BEVOR du diese Datei auf den Server hochlädst.
// Zu finden im Strato-Kundenlogin unter "Datenbanken" (MySQL).
// =====================================================================
define('DB_HOST', 'DEIN_DB_HOST');
define('DB_NAME', 'DEIN_DB_NAME');
define('DB_USER', 'DEIN_DB_USER');
define('DB_PASS', 'DEIN_DB_PASSWORT');
// =====================================================================

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS app_state (
        id INT PRIMARY KEY,
        data LONGTEXT NOT NULL,
        updated_at BIGINT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT data, updated_at FROM app_state WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $data = json_decode($row['data'], true);
        if (!is_array($data) || !isset($data['courses']) || !is_array($data['courses'])) {
            $data = ['courses' => []];
        }
    } else {
        $data = ['courses' => []];
    }

    echo json_encode(['updated_at' => $row ? (int) $row['updated_at'] : 0, 'data' => $data]);
    exit;
}

if ($method === 'POST') {
    $body = file_get_contents('php://input');
    $decoded = json_decode($body, true);

    if (!is_array($decoded) || !isset($decoded['courses']) || !is_array($decoded['courses'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        exit;
    }

    $cleanJson = json_encode($decoded);
    $now = (int) round(microtime(true) * 1000);

    $stmt = $pdo->prepare(
        'INSERT INTO app_state (id, data, updated_at) VALUES (1, :data, :updated_at)
         ON DUPLICATE KEY UPDATE data = :data2, updated_at = :updated_at2'
    );
    $stmt->execute([
        ':data' => $cleanJson,
        ':updated_at' => $now,
        ':data2' => $cleanJson,
        ':updated_at2' => $now,
    ]);

    echo json_encode(['ok' => true, 'updated_at' => $now]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
