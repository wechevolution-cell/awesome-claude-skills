<?php
declare(strict_types=1);

/** Characters that cannot be confused when read aloud or typed (no 0/O, 1/I/L). */
const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

function generate_code(): string
{
    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)setting('code_prefix', 'CD'))) ?: 'CD';
    $prefix = substr($prefix, 0, 4);
    do {
        $chars = '';
        for ($i = 0; $i < 8; $i++) {
            $chars .= CODE_ALPHABET[random_int(0, strlen(CODE_ALPHABET) - 1)];
        }
        $code = $prefix . '-' . substr($chars, 0, 4) . '-' . substr($chars, 4, 4);
    } while (val('SELECT COUNT(*) FROM codes WHERE code = ?', [$code]));
    return $code;
}

/** Normalises what a cashier types or what a QR contains (a full URL or a bare code). */
function normalize_code(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('~[?&]code=([A-Za-z0-9\-]+)~', $raw, $m)) {
        $raw = $m[1];
    }
    $raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
    // Re-insert dashes: PREFIX + 8 chars
    if (strlen($raw) >= 9) {
        $body = substr($raw, -8);
        $prefix = substr($raw, 0, -8);
        return $prefix . '-' . substr($body, 0, 4) . '-' . substr($body, 4, 4);
    }
    return $raw;
}

function code_redeem_url(string $code): string
{
    return abs_url('/business/redeem') . '?code=' . rawurlencode($code);
}

/** Expiry: end of day, N days after issue (N is set per service, default from settings). Campaign dates limit when codes can be issued, not used. */
function code_expiry(array $service): ?string
{
    $days = (int)($service['code_validity_days'] ?: setting('default_validity', 30));
    $exp = time() + max(1, $days) * 86400;
    return date('Y-m-d 23:59:59', $exp);
}

function issue_code(array $orderItem, array $service, int $userId): array
{
    $code = generate_code();
    $id = insert('codes', [
        'code' => $code,
        'order_item_id' => $orderItem['id'] ?? null,
        'user_id' => $userId,
        'service_id' => (int)$service['id'],
        'variant_id' => $orderItem['variant_id'] ?? null,
        'business_id' => (int)$service['business_id'],
        'paid_cents' => (int)($orderItem['unit_cents'] ?? 0),
        'status' => 'active',
        'expires_at' => code_expiry($service),
        'created_at' => now(),
    ]);
    return one('SELECT * FROM codes WHERE id = ?', [$id]);
}

/** Read-only lookup for the redemption screen. */
function lookup_code(string $raw, int $businessId): array
{
    $code = normalize_code($raw);
    $row = one('SELECT c.*, s.title AS service_title, v.label AS variant_label, u.name AS customer_name
                FROM codes c JOIN services s ON s.id = c.service_id JOIN users u ON u.id = c.user_id
                LEFT JOIN service_variants v ON v.id = c.variant_id WHERE c.code = ?', [$code]);
    if (!$row) {
        return ['status' => 'not_found', 'code' => $code];
    }
    if ((int)$row['business_id'] !== $businessId) {
        return ['status' => 'wrong_business', 'code' => $code];
    }
    if ($row['status'] === 'active' && $row['expires_at'] && $row['expires_at'] < now()) {
        q("UPDATE codes SET status = 'expired' WHERE id = ? AND status = 'active'", [$row['id']]);
        $row['status'] = 'expired';
    }
    return ['status' => $row['status'], 'code' => $code, 'row' => $row];
}

/**
 * Atomic redemption: the conditional UPDATE guarantees a code can be used once,
 * even if two cashiers press the button at the same moment.
 */
function redeem_code(string $raw, int $businessId, ?int $storeId, int $staffUserId): array
{
    $look = lookup_code($raw, $businessId);
    if ($look['status'] !== 'active') {
        return $look;
    }
    $row = $look['row'];
    return tx(function () use ($row, $businessId, $storeId, $staffUserId, $look) {
        $n = q("UPDATE codes SET status = 'redeemed', redeemed_at = ?, redeemed_store_id = ?, redeemed_by = ?
                WHERE id = ? AND status = 'active' AND business_id = ?", [now(), $storeId, $staffUserId, $row['id'], $businessId])->rowCount();
        if ($n !== 1) {
            return ['status' => 'redeemed', 'code' => $look['code'], 'row' => $row];
        }
        record_commission((int)$row['id'], $businessId, (int)$row['paid_cents']);
        audit('code.redeem', 'code', (int)$row['id'], ['store' => $storeId]);
        $row['status'] = 'redeemed';
        $row['redeemed_at'] = now();
        return ['status' => 'ok', 'code' => $look['code'], 'row' => $row];
    });
}

/** Agent commission on each redemption: percent of the paid amount + a fixed amount, as set on the agent. */
function record_commission(int $codeId, int $businessId, int $paidCents): void
{
    $b = one('SELECT agent_id FROM businesses WHERE id = ?', [$businessId]);
    if (!$b || !$b['agent_id']) {
        return;
    }
    $a = one("SELECT * FROM agents WHERE id = ? AND status = 'active'", [$b['agent_id']]);
    if (!$a) {
        return;
    }
    $amount = (int)round($paidCents * ((int)$a['commission_percent']) / 100) + (int)$a['commission_fixed_cents'];
    if ($amount <= 0) {
        return;
    }
    insert('commissions', [
        'agent_id' => (int)$a['id'], 'business_id' => $businessId, 'code_id' => $codeId, 'amount_cents' => $amount,
        'basis' => $a['commission_percent'] . '% + ' . money((int)$a['commission_fixed_cents']), 'status' => 'pending', 'created_at' => now(),
    ]);
}

function code_status_label(string $s): string
{
    return ['active' => 'Ενεργός', 'redeemed' => 'Εξαργυρώθηκε', 'expired' => 'Έληξε', 'void' => 'Ακυρώθηκε'][$s] ?? $s;
}

function redeem_message(array $r): array
{
    return match ($r['status']) {
        'ok' => ['ok', 'Η εξαργύρωση επιβεβαιώθηκε.'],
        'not_found' => ['err', 'Ο κωδικός δεν βρέθηκε. Έλεγξε τους χαρακτήρες και δοκίμασε ξανά.'],
        'wrong_business' => ['err', 'Αυτός ο κωδικός ανήκει σε άλλη επιχείρηση.'],
        'redeemed' => ['err', 'Ο κωδικός έχει ήδη εξαργυρωθεί' . (!empty($r['row']['redeemed_at']) ? ' στις ' . date_gr($r['row']['redeemed_at'], true) : '') . '.'],
        'expired' => ['err', 'Ο κωδικός έχει λήξει.'],
        'void' => ['err', 'Ο κωδικός έχει ακυρωθεί.'],
        'active' => ['info', 'Ο κωδικός είναι ενεργός. Επιβεβαίωσε για να τον εξαργυρώσεις.'],
        default => ['err', 'Ο κωδικός δεν μπορεί να εξαργυρωθεί.'],
    };
}
