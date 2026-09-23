<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Fonctions utilitaires
 * ============================================================================
 *
 * Helpers utilises dans tout le projet : nettoyage de chaines,
 * validation, reponses JSON, audit, etc.
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------------
// NETTOYAGE ET VALIDATION
// ---------------------------------------------------------------------------

/**
 * Nettoie une chaine de caracteres (trim + htmlspecialchars).
 *
 * @param string $value
 * @return string
 */
function clean(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Nettoie une chaine sans HTML encoding (pour stockage brut, ex: email).
 *
 * @param string $value
 * @return string
 */
function cleanRaw(string $value): string
{
    return trim($value);
}

/**
 * Verifie si un numero de telephone est valide.
 * Accepte chiffres, espaces, +, -, parentheses. Longueur 8 a 20.
 *
 * @param string $phone
 * @return bool
 */
function isValidPhone(string $phone): bool
{
    return (bool) preg_match('/^[0-9+\-\s()]{8,20}$/', $phone);
}

/**
 * Verifie si une adresse email est valide.
 *
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ---------------------------------------------------------------------------
// REPONSES JSON
// ---------------------------------------------------------------------------

/**
 * Envoie une reponse JSON avec le code HTTP specifie.
 *
 * @param int    $statusCode
 * @param array  $data
 * @return never
 */
function jsonResponse(int $statusCode, array $data): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $json === false ? '{}' : $json;
    exit;
}

/**
 * Reponse de succes rapide.
 *
 * @param string $message
 * @param array  $extra
 * @return never
 */
function jsonSuccess(string $message, array $extra = []): void
{
    jsonResponse(200, array_merge(['success' => true, 'message' => $message], $extra));
}

/**
 * Reponse d'erreur rapide.
 *
 * @param int    $code
 * @param string $message
 * @param array  $extra
 * @return never
 */
function jsonError(int $code, string $message, array $extra = []): void
{
    jsonResponse($code, array_merge(['success' => false, 'message' => $message], $extra));
}

// ---------------------------------------------------------------------------
// JOURNALISATION DES ERREURS
// ---------------------------------------------------------------------------

/**
 * Enregistre une erreur dans error_logs + error_log() PHP.
 *
 * @param string      $niveau   ERROR | WARNING | INFO
 * @param string      $message  Description de l'erreur
 * @param string      $fichier  Fichier source
 * @param int         $ligne    Numero de ligne
 * @param string|null $url      URL de la requete (auto si possible)
 * @param int|null    $userId   ID de l'utilisateur connecte (auto si possible)
 */
function logError(
    string $niveau,
    string $message,
    string $fichier = '',
    int $ligne = 0,
    ?string $url = null,
    ?int $userId = null
): void {
    ensureSession();

    if ($url === null) {
        $url = ($_SERVER['REQUEST_URI'] ?? '');
    }
    if ($userId === null) {
        $userId = $_SESSION['admin_id'] ?? null;
    }

    error_log("[ATLANTIS {$niveau}] {$message} ({$fichier}:{$ligne})");

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO error_logs (level, message, `file`, `line`, url, user_id)
             VALUES (:niveau, :message, :fichier, :ligne, :url, :user_id)'
        );
        $stmt->execute([
            ':niveau'   => $niveau,
            ':message'  => $message,
            ':fichier'  => $fichier,
            ':ligne'    => $ligne,
            ':url'      => $url,
            ':user_id'  => $userId,
        ]);
    } catch (PDOException $e) {
        error_log('Error log insert failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// JOURNAL D'AUDIT
// ---------------------------------------------------------------------------

/**
 * Enregistre une action dans le journal d'audit.
 *
 * @param string      $action         Description de l'action
 * @param string|null $cibleType      Type d'entite ('application', 'user', 'settings', etc.)
 * @param int|null    $cibleId        ID de l'entite concernee
 * @param string|null $details        Description detaillee
 */
function logAudit(
    string $action,
    ?string $cibleType = null,
    ?int $cibleId = null,
    ?string $details = null
): void {
    ensureSession();

    // Si pas connecte (formulaire public par ex.), on enregistre quand meme
    // avec user_id = NULL.
    $userId       = $_SESSION['admin_id'] ?? null;
    $identifiant  = $_SESSION['admin_username'] ?? 'systeme';
    $role         = $_SESSION['admin_role'] ?? 'systeme';
    $ip           = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log
                (user_id, identifiant_snapshot, role_snapshot, action, cible_type, cible_id, details, adresse_ip)
             VALUES
                (:user_id, :identifiant, :role, :action, :cible_type, :cible_id, :details, :ip)'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':identifiant' => $identifiant,
            ':role'        => $role,
            ':action'      => $action,
            ':cible_type'  => $cibleType,
            ':cible_id'    => $cibleId,
            ':details'     => $details,
            ':ip'          => $ip,
        ]);
    } catch (PDOException $e) {
        // L'audit ne doit jamais faire echouer l'operation principale.
        logError('ERROR', 'Audit log failed: ' . $e->getMessage(), 'includes/functions.php', 0, null, $userId);
    }
}

// ---------------------------------------------------------------------------
// NOTIFICATIONS
// ---------------------------------------------------------------------------

function getAdminDisplayName(): string
{
    ensureSession();
    return $_SESSION['admin_nom_complet'] ?? ($_SESSION['admin_username'] ?? 'systeme');
}

/**
 * Cree une notification destinee a des roles.
 * L'auteur de l'action est stocke (auteur_id) ; il est exclu a la lecture,
 * uniquement pour son propre compte, pas pour les autres membres de son role.
 *
 * @param array $destRoles ex. ['admin','gestionnaire']
 * @param bool  $recordAuthor false si l'action vient du public (pas d'auteur admin)
 */
function notify(
    string $type,
    string $message,
    array $destRoles,
    ?string $cibleType = null,
    ?int $cibleId = null,
    bool $recordAuthor = true
): void {
    ensureSession();
    $authorId = $recordAuthor ? ($_SESSION['admin_id'] ?? null) : null;

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO notifications
                (type_notification, message, destinataires, auteur_id, auteur_identifiant, cible_type, cible_id)
             VALUES
                (:type, :message, :dest, :auteur_id, :auteur_identifiant, :cible_type, :cible_id)'
        );
        $stmt->execute([
            ':type'               => $type,
            ':message'            => $message,
            ':dest'               => implode(',', $destRoles),
            ':auteur_id'          => $authorId,
            ':auteur_identifiant' => $authorId !== null ? getAdminDisplayName() : null,
            ':cible_type'         => $cibleType,
            ':cible_id'           => $cibleId,
        ]);
    } catch (PDOException $e) {
        // La notification ne doit jamais faire echouer l'operation principale.
        logError('ERROR', 'Notification insert failed: ' . $e->getMessage(), 'includes/functions.php', 0, null, $authorId);
    }
}

/**
 * Nombre de notifications non lues pour l'utilisateur connecte.
 */
function countUnreadNotifications(): int
{
    ensureSession();
    $userId = $_SESSION['admin_id'] ?? null;
    $role   = getAdminRole();
    if ($userId === null || $role === null) return 0;
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
               FROM notifications n
               LEFT JOIN notification_reads r
                      ON r.notification_id = n.id AND r.user_id = :uid1
              WHERE r.user_id IS NULL
                AND FIND_IN_SET(:role, n.destinataires)
                AND (n.auteur_id IS NULL OR n.auteur_id <> :uid2)'
        );
        $stmt->execute([':uid1' => $userId, ':uid2' => $userId, ':role' => $role]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        logError('ERROR', 'Notification count failed: ' . $e->getMessage(), 'includes/functions.php', 0, null, $userId);
        return 0;
    }
}

/**
 * Liste des notifications visibles par l'utilisateur connecte.
 * @return array
 */
function listNotifications(int $limit = 30): array
{
    ensureSession();
    $userId = $_SESSION['admin_id'] ?? null;
    $role   = getAdminRole();
    if ($userId === null || $role === null) return [];
    $limit = max(1, min(100, $limit));
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT n.id, n.type_notification, n.message, n.cible_type, n.cible_id,
                    n.auteur_identifiant, n.cree_le,
                    (r.user_id IS NOT NULL) AS lu
               FROM notifications n
               LEFT JOIN notification_reads r
                      ON r.notification_id = n.id AND r.user_id = :uid1
              WHERE FIND_IN_SET(:role, n.destinataires)
                AND (n.auteur_id IS NULL OR n.auteur_id <> :uid2)
              ORDER BY n.id DESC
              LIMIT :lim'
        );
        $stmt->bindValue(':uid1', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':role', $role, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError('ERROR', 'Notification list failed: ' . $e->getMessage(), 'includes/functions.php', 0, null, $userId);
        return [];
    }
}

/**
 * Marque une notification comme lue (si visible par l'utilisateur).
 */
function markNotificationRead(int $id): void
{
    ensureSession();
    $userId = $_SESSION['admin_id'] ?? null;
    $role   = getAdminRole();
    if ($userId === null || $role === null) return;
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO notification_reads (user_id, notification_id)
             SELECT :uid1, n.id FROM notifications n
              WHERE n.id = :nid AND FIND_IN_SET(:role, n.destinataires)
                AND (n.auteur_id IS NULL OR n.auteur_id <> :uid2)'
        );
        $stmt->execute([':uid1' => $userId, ':uid2' => $userId, ':nid' => $id, ':role' => $role]);
    } catch (PDOException $e) {
        logError('ERROR', 'Notification mark read failed: ' . $e->getMessage(), 'includes/functions.php', 0, null, $userId);
    }
}

/**
 * Marque toutes les notifications visibles comme lues.
 */
function markAllNotificationsRead(): void
{
    ensureSession();
    $userId = $_SESSION['admin_id'] ?? null;
    $role   = getAdminRole();
    if ($userId === null || $role === null) return;
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO notification_reads (user_id, notification_id)
             SELECT :uid1, n.id FROM notifications n
              WHERE FIND_IN_SET(:role, n.destinataires)
                AND (n.auteur_id IS NULL OR n.auteur_id <> :uid2)'
        );
        $stmt->execute([':uid1' => $userId, ':uid2' => $userId, ':role' => $role]);
    } catch (PDOException $e) {
        logError('ERROR', 'Notification mark all read failed: ' . $e->getMessage(), 'includes/functions.php', 0, null, $userId);
    }
}

/**
 * HTML de la cloche de notifications (panneau rempli en JS).
 */
function renderNotificationBellHtml(): string
{
    $count = countUnreadNotifications();
    $badge = $count > 0
        ? '<span class="notif-badge">' . $count . '</span>'
        : '<span class="notif-badge" style="display:none;">0</span>';

    return '<div class="notif-bell">'
        . '<button type="button" class="notif-trigger" aria-label="Notifications">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>'
        . $badge
        . '</button>'
        . '<div class="notif-panel">'
        . '<div class="notif-header"><span>Notifications</span>'
        . '<button type="button" class="notif-mark-all">Tout marquer comme lu</button></div>'
        . '<div class="notif-list"></div>'
        . '</div>'
        . '</div>';
}

// ---------------------------------------------------------------------------
// SESSIONS
// ---------------------------------------------------------------------------

/**
 * Demarre la session si elle n'est pas encore active.
 * Les reglages de securite du cookie doivent etre appliques AVANT session_start().
 */
function ensureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.cookie_secure', isSecureConnection() ? '1' : '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

        set_error_handler(function (int $severity, string $message): bool {
            return str_contains($message, 'session_start');
        });
        try {
            session_start();
        } finally {
            restore_error_handler();
        }
    }
}

// ---------------------------------------------------------------------------
// SECURITE SESSIONS
// ---------------------------------------------------------------------------

/**
 * Demarre une session admin avec les parametres de securite.
 * Doit etre appele apres une connexion reussie.
 */
function startAdminSession(): void
{
    ensureSession();

    session_regenerate_id(true);
}

// ---------------------------------------------------------------------------
// POLITIQUE DE MOT DE PASSE (source unique de verite)
// ---------------------------------------------------------------------------

/**
 * Longueur minimale imposee pour les mots de passe.
 */
const PASSWORD_MIN_LENGTH = 8;

/**
 * Valide un mot de passe selon la politique du site :
 *   - 8 caracteres minimum
 *   - au moins une majuscule
 *   - au moins un chiffre
 *
 * @param string $pwd Mot de passe a valider.
 * @return string|null Message d'erreur, ou null si le mot de passe est valide.
 */
function validatePasswordPolicy(string $pwd): ?string
{
    if (mb_strlen($pwd) < PASSWORD_MIN_LENGTH) {
        return 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caracteres.';
    }

    if (!preg_match('/[A-Z]/', $pwd)) {
        return 'Le mot de passe doit contenir au moins une majuscule.';
    }

    if (!preg_match('/[0-9]/', $pwd)) {
        return 'Le mot de passe doit contenir au moins un chiffre.';
    }

    return null;
}

// ---------------------------------------------------------------------------
// "SE SOUVENIR DE MOI" (cookie signe HMAC, 30 jours)
// ---------------------------------------------------------------------------

/**
 * Chemin du cookie (scope au sous-dossier du projet).
 *
 * @return string
 */
function rememberCookiePath(): string
{
    $path = parse_url(BASE_URL, PHP_URL_PATH) ?: '/';
    return rtrim($path, '/') . '/';
}

/**
 * Encode une chaine en base64url.
 *
 * @param string $data
 * @return string
 */
function b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Decode une chaine base64url.
 *
 * @param string $data
 * @return string
 */
function b64urlDecode(string $data): string
{
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    return $decoded === false ? '' : $decoded;
}

/**
 * Detecte si la connexion est securisee (HTTPS direct ou via proxy).
 *
 * @return bool
 */
function isSecureConnection(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on') {
        return true;
    }
    return defined('FORCE_SSL') && FORCE_SSL;
}

/**
 * Emet le cookie "se souvenir de moi" (signe par HMAC).
 *
 * @param int $userId
 * @return void
 */
function issueRememberCookie(int $userId): void
{
    $expiry = time() + REMEMBER_COOKIE_LIFETIME;
    $payload = $userId . '|' . $expiry;
    $signature = hash_hmac('sha256', $payload, APP_SECRET);
    $token = b64url($payload) . '.' . $signature;

    setcookie(REMEMBER_COOKIE, $token, [
        'expires' => $expiry,
        'path' => rememberCookiePath(),
        'secure' => isSecureConnection(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Supprime le cookie "se souvenir de moi".
 */
function clearRememberCookie(): void
{
    if (isset($_COOKIE[REMEMBER_COOKIE])) {
        setcookie(REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => rememberCookiePath(),
            'secure' => isSecureConnection(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        unset($_COOKIE[REMEMBER_COOKIE]);
    }
}

/**
 * Reconnecte automatiquement un utilisateur via le cookie "se souvenir de moi".
 * A appeler avant les controles d'authentification (login.php, auth_check.php).
 */
function maybeAutoLogin(): void
{
    if (isLoggedIn()) return;

    $token = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($token === '') return;

    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        clearRememberCookie();
        return;
    }

    [$encodedPayload, $signature] = $parts;
    $payload = b64urlDecode((string) $encodedPayload);
    if ($payload === '' || !hash_equals(hash_hmac('sha256', $payload, APP_SECRET), (string) $signature)) {
        clearRememberCookie();
        return;
    }

    $segments = explode('|', $payload);
    if (count($segments) !== 2) {
        clearRememberCookie();
        return;
    }

    [$userId, $expiry] = $segments;
    if (!ctype_digit((string) $userId) || !ctype_digit((string) $expiry) || (int) $expiry < time()) {
        clearRememberCookie();
        return;
    }

    try {
        $stmt = getDB()->prepare(
            'SELECT id, identifiant, nom_complet, role, is_super_admin, must_change_password, statut_compte
             FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => (int) $userId]);
        $user = $stmt->fetch();

        if (!$user || $user['statut_compte'] === 'desactive') {
            clearRememberCookie();
            return;
        }

        $_SESSION['admin_id']          = (int) $user['id'];
        $_SESSION['admin_username']    = $user['identifiant'];
        $_SESSION['admin_role']        = $user['role'];
        $_SESSION['admin_nom_complet'] = $user['nom_complet'];
        $_SESSION['admin_is_super']    = (bool) $user['is_super_admin'];

        session_regenerate_id(true);

        // Rotation du cookie (limite le rejeu)
        issueRememberCookie((int) $user['id']);

        logAudit('auto_login', 'user', (int) $user['id'], 'Connexion automatique (se souvenir de moi)');
    } catch (PDOException $e) {
        logError('ERROR', 'Auto login error: ' . $e->getMessage(), 'includes/functions.php', 0, null, (int) $userId);
        clearRememberCookie();
    }
}

/**
 * Verifie si l'utilisateur est connecte en tant qu'admin.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    ensureSession();
    return isset($_SESSION['admin_id']);
}

/**
 * Redirige vers login.php si non connecte.
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Redirige vers le dashboard si le role n'est pas autorise.
 *
 * @param string|array $roles
 */
function requireRole(string|array $roles): void
{
    if (!hasPermission($roles)) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

/**
 * Retourne l'ID de l'admin connecte.
 *
 * @return int|null
 */
function getAdminId(): ?int
{
    ensureSession();
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Retourne le nom d'utilisateur de l'admin connecte.
 *
 * @return string
 */
function getAdminUsername(): string
{
    ensureSession();
    return $_SESSION['admin_username'] ?? '';
}

/**
 * Retourne le role de l'admin connecte.
 *
 * @return string|null
 */
function getAdminRole(): ?string
{
    ensureSession();
    return $_SESSION['admin_role'] ?? null;
}

/**
 * Verifie si l'admin connecte a l'un des roles donnes.
 *
 * @param string|array $roles
 * @return bool
 */
function hasPermission(string|array $roles): bool
{
    $role = getAdminRole();
    if ($role === null) return false;

    $allowed = is_array($roles) ? $roles : [$roles];
    return in_array($role, $allowed, true);
}

/**
 * Verifie si l'admin connecte est le super admin.
 *
 * @return bool
 */
function isSuperAdmin(): bool
{
    ensureSession();
    return isset($_SESSION['admin_is_super']) && $_SESSION['admin_is_super'] === true;
}


/**
 * Garde standardisee pour un endpoint API admin (JSON) : verifie la methode,
 * la connexion, les permissions et le token CSRF. En cas d'echec, repond
 * en JSON et termine l'execution.
 *
 * @param string          $method      Methode HTTP attendue (ex: 'POST').
 * @param string|array    $permissions Role(s) autorise(s).
 * @return void
 */
function adminApiGuard(string $method, string|array $permissions): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission($permissions)) jsonError(403, 'Permissions insuffisantes.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }
}

/**
 * Verifie les tentatives de connexion echouees.
 * Retourne true si le compte est bloque.
 *
 * @param string $identifiant
 * @return bool
 */
function isLoginLocked(string $identifiant): bool
{
    ensureSession();

    $lockKey = 'login_lock_' . md5($identifiant);

    if (!isset($_SESSION[$lockKey])) {
        return false;
    }

    $lockData = $_SESSION[$lockKey];

    // Verifier si le delai de blocage est passe
    if (time() - $lockData['time'] > LOGIN_LOCKOUT_TIME) {
        unset($_SESSION[$lockKey]);
        return false;
    }

    return $lockData['attempts'] >= MAX_LOGIN_ATTEMPTS;
}

/**
 * Enregistre une tentative de connexion echouee.
 *
 * @param string $identifiant
 */
function recordFailedLogin(string $identifiant): void
{
    ensureSession();

    $lockKey = 'login_lock_' . md5($identifiant);

    if (!isset($_SESSION[$lockKey])) {
        $_SESSION[$lockKey] = ['attempts' => 0, 'time' => time()];
    }

    $_SESSION[$lockKey]['attempts']++;
    $_SESSION[$lockKey]['time'] = time();
}

/**
 * Reinitialise le compteur de tentatives apres connexion reussie.
 *
 * @param string $identifiant
 */
function resetLoginAttempts(string $identifiant): void
{
    ensureSession();
    unset($_SESSION['login_lock_' . md5($identifiant)]);
}

// ---------------------------------------------------------------------------
// FORMATAGE
// ---------------------------------------------------------------------------

/**
 * Formate une date en format lisible (francais).
 *
 * @param string $date
 * @return string
 */
function formatDate(string $date): string
{
    try {
        $dt = new DateTime($date);
        return $dt->format('d/m/Y \à H:i');
    } catch (\Exception $e) {
        return $date;
    }
}

/**
 * Retourne le libelle francais d'un statut.
 *
 * @param string $statut
 * @return string
 */
function statutLabel(string $statut): string
{
    $labels = [
        'en_attente' => 'En attente',
        'en_cours'   => 'En cours',
        'valide'     => 'Acceptée',
        'refuse'     => 'Refusée',
        'archive'    => 'Archivée',
    ];
    return $labels[$statut] ?? $statut;
}

/**
 * Retourne la classe CSS d'un statut (pour les badges).
 *
 * @param string $statut
 * @return string
 */
function statutClass(string $statut): string
{
    $classes = [
        'en_attente' => 'badge-warning',
        'en_cours'   => 'badge-info',
        'valide'     => 'badge-success',
        'refuse'     => 'badge-danger',
        'archive'    => 'badge-secondary',
    ];
    return $classes[$statut] ?? 'badge-secondary';
}

/**
 * Retourne le libelle francais d'un type de demande.
 *
 * @param string $type
 * @return string
 */
function typeLabel(string $type): string
{
    $labels = [
        'partenariat' => 'Partenariat',
        'recrutement' => 'Recrutement',
    ];
    return $labels[$type] ?? $type;
}

/**
 * Retourne l'icone d'un type de demande.
 *
 * @param string $type
 * @return string
 */
function typeIcon(string $type): string
{
    $icons = [
        'partenariat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'recrutement' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>',
    ];
    return $icons[$type] ?? '';
}

/**
 * Nettoie l'identifiant pour un usage URL-safe.
 *
 * @param string $str
 * @return string
 */
function slugify(string $str): string
{
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9-]/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

// ---------------------------------------------------------------------------
// CHAMPS DU FORMULAIRE DE CANDIDATURE (configurable)
// ---------------------------------------------------------------------------

/**
 * Retourne le champ type d'un champ de formulaire (nettoie).
 *
 * @param array $field
 * @return string
 */
function fieldType(array $field): string
{
    $t = (string)($field['type'] ?? 'text');
    if (!in_array($t, ['text', 'tel', 'email', 'textarea', 'select', 'file'], true)) {
        return 'text';
    }
    return $t;
}

/**
 * Liste des champs du formulaire de contact (defaut = comportement actuel).
 * Le reglage site_settings.landing_form_fields peut les surcharger.
 *
 * Chaque champ :
 *   cle        : colonne applications (nom, prenom, telephone, email,
 *                entreprise, type, message) ou "pieces" (dossier), ou "custom_N".
 *   type       : text | tel | email | textarea | select | file
 *   libelle    : label affiche
 *   placeholder: texte indicatif
 *   obligatoire: bool
 *   visible    : bool
 *   options    : liste (select)
 *
 * @return array
 */
function getLandingFormFields(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $defaults = [
        ['cle' => 'nom',        'type' => 'text',     'libelle' => 'Nom',                'placeholder' => 'Votre nom',                        'obligatoire' => true,  'visible' => true,  'options' => []],
        ['cle' => 'prenom',     'type' => 'text',     'libelle' => 'Prenom',             'placeholder' => 'Votre prenom',                     'obligatoire' => false, 'visible' => true,  'options' => []],
        ['cle' => 'telephone',  'type' => 'tel',      'libelle' => 'Telephone',          'placeholder' => '+237 6 XX XX XX XX',                'obligatoire' => true,  'visible' => true,  'options' => []],
        ['cle' => 'email',      'type' => 'email',    'libelle' => 'Email',              'placeholder' => 'vous@exemple.com',                  'obligatoire' => false, 'visible' => true,  'options' => []],
        ['cle' => 'entreprise', 'type' => 'text',     'libelle' => 'Entreprise',         'placeholder' => 'Nom de votre entreprise',           'obligatoire' => false, 'visible' => true,  'options' => []],
        ['cle' => 'type',       'type' => 'select',   'libelle' => 'Type de demande',    'placeholder' => '',                                   'obligatoire' => true,  'visible' => true,  'options' => ['partenariat', 'recrutement']],
        ['cle' => 'pieces',     'type' => 'file',     'libelle' => 'Dossier de candidature', 'placeholder' => '',                             'obligatoire' => true,  'visible' => true,  'options' => []],
        ['cle' => 'message',    'type' => 'textarea', 'libelle' => 'Message',            'placeholder' => 'Decrivez brievement votre projet ou votre profil...', 'obligatoire' => false, 'visible' => true, 'options' => []],
    ];

    try {
        $stmt = getDB()->prepare('SELECT valeur FROM site_settings WHERE cle = :cle LIMIT 1');
        $stmt->execute([':cle' => 'landing_form_fields']);
        $row = $stmt->fetch();
        if ($row) {
            $decoded = json_decode($row['valeur'], true);
            if (is_array($decoded)) {
                $cache = $decoded;
                return $cache;
            }
        }
    } catch (PDOException $e) {
        // On reste sur les valeurs par defaut
    }

    $cache = $defaults;
    return $cache;
}
