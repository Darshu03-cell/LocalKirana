<?php
// Streams a delivery partner's ID proof. The files live in data/id_docs (blocked
// from the web), so this page is the only way to see one — and only the partner
// themselves or an admin may.
require_once __DIR__ . '/includes/init.php';

$user = current_user();
$p    = delivery_partner_find((int) ($_GET['id'] ?? 0));

$isAdmin = $user && in_array($user['role'] ?? '', ['admin', 'superadmin'], true);
$isOwner = $user && ($user['role'] ?? '') === 'delivery' && $p
        && strtolower((string) $p['email']) === strtolower((string) ($user['email'] ?? ''));

$path = ($p && ($isAdmin || $isOwner)) ? private_document_path((string) ($p['idDoc'] ?? '')) : null;
if (!$path) {
  http_response_code(404);
  exit('Document not found.');
}

$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
header('Content-Type: ' . ($types[pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="id-proof.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
