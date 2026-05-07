<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!cms_is_installed()) {
    http_response_code(403);
    exit;
}
cms_require_login();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Nieprawidłowy token bezpieczeństwa']);
    exit;
}

$file = $_FILES['image'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Brak pliku lub błąd przesyłania (kod: ' . ($file['error'] ?? 'brak') . ')']);
    exit;
}

if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Plik za duży (maksymalnie 10 MB)']);
    exit;
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file((string)($file['tmp_name'] ?? ''));
$allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'Niedozwolony typ pliku. Dozwolone: JPG, PNG, GIF, WEBP']);
    exit;
}

$uploadDir = dirname(__DIR__) . '/storage/uploads/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Nie można utworzyć katalogu do przechowywania plików']);
    exit;
}

$filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
$dest     = $uploadDir . $filename;

if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $dest)) {
    http_response_code(500);
    echo json_encode(['error' => 'Nie można zapisać pliku']);
    exit;
}

echo json_encode(['url' => cms_url('storage/uploads/' . $filename), 'filename' => $filename]);
