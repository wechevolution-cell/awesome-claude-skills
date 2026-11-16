<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $c = config('db');
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (($c['driver'] ?? 'sqlite') === 'mysql') {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)$c['port'], $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    } else {
        $path = $c['sqlite_path'];
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    return $pdo;
}

function is_mysql(): bool
{
    return (config('db')['driver'] ?? 'sqlite') === 'mysql';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function ident(string $name): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException('Bad identifier');
    }
    return $name;
}

function insert(string $table, array $data): int
{
    $cols = array_map('ident', array_keys($data));
    $sql = 'INSERT INTO ' . ident($table) . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function update(string $table, array $data, array $where): int
{
    $set = [];
    foreach (array_keys($data) as $k) {
        $set[] = ident($k) . ' = ?';
    }
    $w = [];
    foreach (array_keys($where) as $k) {
        $w[] = ident($k) . ' = ?';
    }
    $sql = 'UPDATE ' . ident($table) . ' SET ' . implode(', ', $set) . ' WHERE ' . implode(' AND ', $w);
    return q($sql, array_merge(array_values($data), array_values($where)))->rowCount();
}

function tx(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function now(): string
{
    return date('Y-m-d H:i:s');
}
