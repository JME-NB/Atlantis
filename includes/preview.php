<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Apercu de designs (lien signe)
 * ============================================================================
 *
 * Permet de previsualiser un design sauvegarde SANS l'activer :
 *   - designPreviewUrl(int $id, string $cible)  : genere l'URL signee
 *   - previewDesign(int $id, string $sig)       : valide la signature et
 *                                                  retourne le design (row)
 *                                                  ou null si invalide/inconnu
 *
 * Les pages rendues (public/index.php, admin/login.php, admin/dashboard.php)
 * appliquent ensuite leur configuration en memoire uniquement.
 * ============================================================================
 */

function designPreviewSignature(int $id, string $cible): string
{
    return hash_hmac('sha256', $id . '|' . $cible, defined('APP_PREVIEW_KEY') ? APP_PREVIEW_KEY : '');
}

function designPreviewUrl(int $id, string $cible): string
{
    $sig = designPreviewSignature($id, $cible);
    if ($cible === 'login') {
        return BASE_URL . '/admin/login.php?preview=' . $id . '&sig=' . $sig;
    }
    if ($cible === 'admin') {
        return BASE_URL . '/admin/dashboard.php?preview=' . $id . '&sig=' . $sig;
    }
    return BASE_URL . '/public/index.php?preview=' . $id . '&sig=' . $sig;
}

function previewDesign(int $id, string $sig): ?array
{
    $id = (int) $id;
    if ($id <= 0 || $sig === '') {
        return null;
    }
    try {
        $stmt = getDB()->prepare('SELECT * FROM designs WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $design = $stmt->fetch();
        if (!$design) {
            return null;
        }
        if (!hash_equals(designPreviewSignature($id, (string) $design['cible']), (string) $sig)) {
            return null;
        }
        $design['configuration'] = json_decode($design['configuration'], true);
        if (!is_array($design['configuration'])) {
            return null;
        }
        return $design;
    } catch (PDOException $e) {
        return null;
    }
}