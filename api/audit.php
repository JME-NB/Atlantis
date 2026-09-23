<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Journal d'audit
 * ============================================================================
 *
 * Endpoints :
 *   GET ?action=list (admin/super-admin)
 *   GET ?action=details&id=X (admin/super-admin)
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'details':
        handleDetails();
        break;
    default:
        jsonError(400, 'Action inconnue.');
}

// ---------------------------------------------------------------------------
// LIST
// ---------------------------------------------------------------------------
function handleList(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission('admin')) jsonError(403, 'Permissions insuffisantes.');

    $pdo      = getDB();
    $search   = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
    $action   = is_string($_GET['action_filter'] ?? null) ? $_GET['action_filter'] : '';
    $userId   = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    $role     = is_string($_GET['role'] ?? null) ? $_GET['role'] : '';
    $dateDeb  = is_string($_GET['date_debut'] ?? null) ? $_GET['date_debut'] : '';
    $dateFin  = is_string($_GET['date_fin'] ?? null) ? $_GET['date_fin'] : '';
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = 50;
    $offset   = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(identifiant_snapshot LIKE :search OR action LIKE :search OR details LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    if ($action !== '') {
        $where[]       = 'action = :action';
        $params[':action'] = $action;
    }

    if ($userId && $userId > 0) {
        $where[]       = 'user_id = :user_id';
        $params[':user_id'] = $userId;
    }

    if ($role !== '') {
        $where[]       = 'role_snapshot = :role';
        $params[':role'] = $role;
    }

    if ($dateDeb !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDeb)) jsonError(400, 'Date de debut invalide.');
        $where[] = 'created_at >= :date_debut';
        $params[':date_debut'] = $dateDeb . ' 00:00:00';
    }

    if ($dateFin !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) jsonError(400, 'Date de fin invalide.');
        $where[] = 'created_at <= :date_fin';
        $params[':date_fin'] = $dateFin . ' 23:59:59';
    }

    // Tri par liste blanche (anti-injection SQL)
    $sortCols = [
        'created_at'          => 'created_at',
        'user_id'             => 'user_id',
        'identifiant_snapshot' => 'identifiant_snapshot',
        'role_snapshot'       => 'role_snapshot',
        'action'              => 'action',
        'details'             => 'details',
        'adresse_ip'          => 'adresse_ip',
    ];
    $sort = (is_string($_GET['sort'] ?? null) && isset($sortCols[$_GET['sort']]))
        ? $sortCols[$_GET['sort']]
        : 'created_at';
    $dir = (is_string($_GET['dir'] ?? null) && strtoupper($_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';

    $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $orderSQL = 'ORDER BY ' . $sort . ' ' . $dir;

    $countSQL = "SELECT COUNT(*) as total FROM audit_log $whereSQL";
    $stmt     = $pdo->prepare($countSQL);
    $stmt->execute($params);
    $total    = (int) $stmt->fetch()['total'];

    $dataSQL = "SELECT * FROM audit_log $whereSQL $orderSQL LIMIT :limit OFFSET :offset";
    $stmt    = $pdo->prepare($dataSQL);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

    jsonSuccess('Journal recuper.', [
        'items' => $logs,
        'total' => $total,
        'page'  => $page,
        'pages' => (int) ceil($total / $limit),
    ]);
}

// ---------------------------------------------------------------------------
// DETAILS (log + donnees de l'entite cible)
// ---------------------------------------------------------------------------
function handleDetails(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission('admin')) jsonError(403, 'Permissions insuffisantes.');

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) jsonError(400, 'Identifiant invalide.');

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM audit_log WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $log = $stmt->fetch();

        if (!$log) jsonError(404, 'Entree inconnue.');

        $entity = null;
        $pieces = [];

        if ($log['cible_type'] === 'application' && $log['cible_id'] !== null) {
            $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $log['cible_id']]);
            $entity = $stmt->fetch();

            if ($entity) {
                $stmt = $pdo->prepare('SELECT * FROM application_champs_personnalises WHERE application_id = :id ORDER BY id ASC');
                $stmt->execute([':id' => $entity['id']]);
                $cps = $stmt->fetchAll();

                $stmt = $pdo->prepare('SELECT * FROM application_pieces WHERE application_id = :id ORDER BY id ASC');
                $stmt->execute([':id' => $entity['id']]);
                $pieces = $stmt->fetchAll();
            }
        } elseif ($log['cible_type'] === 'user' && $log['cible_id'] !== null) {
            $stmt = $pdo->prepare('SELECT id, identifiant, nom_complet, role, is_super_admin, statut_compte, must_change_password, created_at FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $log['cible_id']]);
            $entity = $stmt->fetch();
        } elseif ($log['cible_type'] === 'settings') {
            $stmt = $pdo->query('SELECT cle, valeur FROM site_settings');
            $entity = [];
            while ($row = $stmt->fetch()) {
                $entity[] = ['cle' => $row['cle'], 'valeur' => $row['valeur']];
            }
        }

        jsonSuccess('Details recuperes.', [
            'log'       => $log,
            'entity'    => $entity,
            'pieces'    => $pieces,
            'champs'    => $cps ?? null,
        ]);
    } catch (PDOException $e) {
        logError('ERROR', 'Audit details error: ' . $e->getMessage(), 'api/audit.php', 145);
        jsonError(500, 'Erreur interne.');
    }
}
