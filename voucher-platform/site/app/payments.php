<?php
declare(strict_types=1);

/**
 * Payment gateway layer. Phase 1 ships the "test" gateway only, so the full flow
 * (pending → paid → codes) works end to end before a provider is chosen.
 *
 * To add a marketplace provider (e.g. Viva Wallet Marketplace or Stripe Connect):
 *   1. implement {provider}_start(array $order): string  — returns the hosted checkout URL
 *   2. implement {provider}_webhook(): void              — verifies the signature, then calls finalize_order($id, 'paid', '{provider}', $ref)
 *   3. register it in payment_providers()
 * The split between platform fee and business share is stored on each order (platform_fee_cents)
 * and on each code (paid_cents), so payouts can be calculated whichever provider is used.
 */
function payment_providers(): array
{
    return ['test' => 'Δοκιμαστική πληρωμή (χωρίς χρέωση)'];
}

function payment_start(array $order): string
{
    $p = setting('payments_provider', 'test');
    if ($p === 'test') {
        return url('/pay/test/' . $order['number']);
    }
    $fn = $p . '_start';
    if (!function_exists($fn)) {
        throw new RuntimeException('Ο πάροχος πληρωμών δεν έχει ρυθμιστεί.');
    }
    return $fn($order);
}

function test_payments_allowed(): bool
{
    return setting('payments_mode', 'test') === 'test';
}
