<?php
declare(strict_types=1);

namespace App;

use PDO;

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0755, true)) {
        throw new \RuntimeException('Ο φάκελος data/ δεν υπάρχει και δεν μπορεί να δημιουργηθεί.');
    }
    protect_dir(DATA_DIR);
    $fresh = !is_file(DB_FILE);
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    migrate($pdo);
    if ($fresh || (int) $pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn() === 0) {
        seed($pdo);
    }
    return $pdo;
}

/** Writes a deny-all .htaccess into private directories (defence in depth). */
function protect_dir(string $dir): void
{
    $f = $dir . '/.htaccess';
    if (!is_file($f) && is_writable($dir)) {
        @file_put_contents($f, "Require all denied\nDeny from all\n");
    }
}

function migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
    $done = $pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    $files = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($files);
    foreach ($files as $file) {
        $v = basename($file, '.sql');
        if (in_array($v, $done, true)) {
            continue;
        }
        $pdo->beginTransaction();
        try {
            $pdo->exec((string) file_get_contents($file));
            $pdo->prepare('INSERT INTO migrations(version, applied_at) VALUES(?, ?)')->execute([$v, now()]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

/* ---------------- page queries ---------------- */

/** Publishes scheduled pages whose time has come (cheap, runs per request). */
function publish_due(): void
{
    db()->prepare("UPDATE pages SET status='published' WHERE status='scheduled' AND publish_at IS NOT NULL AND publish_at <= ?")
        ->execute([now()]);
}

function page_by_path(string $path): ?array
{
    $st = db()->prepare('SELECT * FROM pages WHERE path = ?');
    $st->execute([$path]);
    return $st->fetch() ?: null;
}

function page_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM pages WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function page_by_type(string $type): ?array
{
    $st = db()->prepare("SELECT * FROM pages WHERE type = ? AND status='published' ORDER BY sort, id LIMIT 1");
    $st->execute([$type]);
    return $st->fetch() ?: null;
}

function published_children(int $parentId): array
{
    $st = db()->prepare("SELECT * FROM pages WHERE parent_id = ? AND status='published' ORDER BY sort, title");
    $st->execute([$parentId]);
    return $st->fetchAll();
}

function published_pages(): array
{
    return db()->query("SELECT * FROM pages WHERE status='published' ORDER BY sort, id")->fetchAll();
}

function services(): array
{
    return db()->query("SELECT * FROM pages WHERE type='service' AND status='published' ORDER BY sort, id")->fetchAll();
}

function nav_pages(): array
{
    return db()->query("SELECT * FROM pages WHERE in_nav=1 AND status='published' ORDER BY sort, id")->fetchAll();
}

function build_path(?int $parentId, string $slug, string $type): string
{
    if ($type === 'home') {
        return '/';
    }
    $prefix = '/';
    if ($parentId) {
        $parent = page_by_id($parentId);
        if ($parent && $parent['path'] !== '/') {
            $prefix = $parent['path'];
        }
    }
    return $prefix . trim($slug, '/') . '/';
}

/** After a path change, rewrite descendant paths and leave 301s behind for every moved URL. */
function cascade_path_change(int $id, string $oldPath, string $newPath): void
{
    if ($oldPath === $newPath) {
        return;
    }
    add_redirect($oldPath, $newPath, 301, 'Αυτόματο: αλλαγή slug');
    $st = db()->prepare('SELECT id, path FROM pages WHERE path LIKE ? AND id != ?');
    $st->execute([$oldPath . '%', $id]);
    foreach ($st->fetchAll() as $child) {
        $np = $newPath . substr($child['path'], strlen($oldPath));
        db()->prepare('UPDATE pages SET path = ?, updated_at = ? WHERE id = ?')->execute([$np, now(), $child['id']]);
        add_redirect($child['path'], $np, 301, 'Αυτόματο: αλλαγή γονικού slug');
    }
}

/**
 * Creates an exact redirect and collapses chains: any redirect that pointed at $source now points at $target.
 * Removes a redirect whose source is now a live page again.
 */
function add_redirect(string $source, string $target, int $code = 301, string $note = ''): void
{
    $pdo = db();
    $pdo->prepare("DELETE FROM redirects WHERE match_type='exact' AND source = ?")->execute([$target]);
    $pdo->prepare("UPDATE redirects SET target = ? WHERE match_type='exact' AND target = ?")->execute([$target, $source]);
    $pdo->prepare("DELETE FROM redirects WHERE match_type='exact' AND source = target")->execute();
    $pdo->prepare("DELETE FROM redirects WHERE match_type='exact' AND source = ?")->execute([$source]);
    $pdo->prepare('INSERT INTO redirects(source, target, code, match_type, active, note, created_at) VALUES(?,?,?,?,1,?,?)')
        ->execute([$source, $target, $code, 'exact', $note, now()]);
}
