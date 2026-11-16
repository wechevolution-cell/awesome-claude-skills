<?php
declare(strict_types=1);

/**
 * Sends a plain-text + HTML email. Uses SMTP when configured, PHP mail() otherwise.
 * In dev every message is also written to data/mail.log so flows can be tested without a mail server.
 */
function send_mail(string $to, string $subject, string $text, ?string $html = null): bool
{
    $smtp = config('smtp', []);
    $from = $smtp['from'] ?: (setting('contact_email') ?: 'no-reply@' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost'));
    $fromName = $smtp['from_name'] ?: site_name();

    if (is_dev()) {
        @file_put_contents(ROOT . '/data/mail.log', "---- " . now() . "\nTo: $to\nSubject: $subject\n\n$text\n\n", FILE_APPEND | LOCK_EX);
        if (empty($smtp['host'])) {
            return true;
        }
    }

    $boundary = 'b' . bin2hex(random_bytes(8));
    $html = $html ?? '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5">' . nl2br(e($text)) . '</div>';
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
    $headers = [
        'From' => $encFrom,
        'Reply-To' => $from,
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
        'Date' => date('r'),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost') . '>',
    ];

    if (empty($smtp['host'])) {
        $h = '';
        foreach ($headers as $k => $v) {
            $h .= "$k: $v\r\n";
        }
        return @mail($to, $encSubject, $body, $h);
    }

    try {
        return smtp_send($smtp, $from, $to, $encSubject, $headers, $body);
    } catch (Throwable $e) {
        error_log('SMTP error: ' . $e->getMessage());
        return false;
    }
}

function smtp_send(array $c, string $from, string $to, string $subject, array $headers, string $body): bool
{
    $host = ($c['secure'] === 'ssl' ? 'ssl://' : '') . $c['host'];
    $fp = @stream_socket_client($host . ':' . (int)$c['port'], $errno, $errstr, 15);
    if (!$fp) {
        throw new RuntimeException("connect: $errstr");
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };
    $cmd = function (string $c, array $expect) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $expect, true)) {
            throw new RuntimeException(trim($r));
        }
        return $r;
    };
    $read();
    $ehloHost = parse_url(base_url(), PHP_URL_HOST) ?: 'localhost';
    $cmd('EHLO ' . $ehloHost, [250]);
    if ($c['secure'] === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('TLS failed');
        }
        $cmd('EHLO ' . $ehloHost, [250]);
    }
    if (!empty($c['user'])) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user']), [334]);
        $cmd(base64_encode($c['pass']), [235]);
    }
    $cmd('MAIL FROM:<' . $from . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $h = "To: <$to>\r\nSubject: $subject\r\n";
    foreach ($headers as $k => $v) {
        $h .= "$k: $v\r\n";
    }
    $data = $h . "\r\n" . preg_replace('/^\./m', '..', $body);
    $cmd($data . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}
