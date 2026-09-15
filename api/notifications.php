<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Notifications
 * ============================================================================
 *
 * Endpoints (authentification requise) :
 *   GET  ?action=count          - Nb de notifications non lues
 *   GET  ?action=list&limit=30  - Liste des notifications visibles
 *   POST ?action=mark_read&id=X       - Marquer une notification comme lue
 *   POST ?action=mark_all_read        - Tout marquer comme lu
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'count':
        handleCount();
        break;
    case 'list':
        handleList();
        break;
    case 'mark_read':
        handleMarkRead();
        break;
    case 'mark_all_read':
        handleMarkAllRead();
        break;
    default:
        jsonError(400, 'Action inconnue.');
}

// ---------------------------------------------------------------------------
// COUNT
// ---------------------------------------------------------------------------
function handleCount(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');

    jsonSuccess('Compteur recupere.', ['count' => countUnreadNotifications()]);
}

// ---------------------------------------------------------------------------
// LIST
// ---------------------------------------------------------------------------
function handleList(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');

    $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
    jsonSuccess('Notifications recuperees.', ['items' => listNotifications($limit)]);
}

// ---------------------------------------------------------------------------
// MARK READ
// ---------------------------------------------------------------------------
function handleMarkRead(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) jsonError(400, 'Identifiant invalide.');

    markNotificationRead($id);
    jsonSuccess('Notification marquee comme lue.');
}

// ---------------------------------------------------------------------------
// MARK ALL READ
// ---------------------------------------------------------------------------
function handleMarkAllRead(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    markAllNotificationsRead();
    jsonSuccess('Toutes les notifications marquees comme lues.');
}