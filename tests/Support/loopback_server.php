<?php
/**
 * Throwaway HTTP server for the loopback tests: php loopback_server.php <port> <logfile>
 *
 * It listens on 127.0.0.1 ONLY, and appends the raw head and body of every request it receives to <logfile>, as JSON
 * lines. It answers according to the path: /ok, /status/<code>, /nobody, /redirect, /slow (3 seconds), /bad-json,
 * /html502, /big, /token-ok, /token-401, /flaky, /flaky-reset, /always-401, /ra/<code>/<url encoded Retry-After value>, /ra-flaky,
 * /ra-flaky-reset, /token-flaky, /token-flaky-reset, /quit and, for any other path, an echo of
 * the request. It handles one connection at a time.
 *
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

[$self, $port, $log] = $argv;

$server = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);

if (!$server) {
    fwrite(STDERR, "cannot listen on 127.0.0.1:$port: $errstr\n");
    exit(1);
}

$counters = [];

/**
 * Answer a request and let the connection go
 * @param resource $conn The connection
 * @param int $status HTTP status
 * @param string $body Response body
 * @param array $extra Extra header lines
 * @return void
 */
function respond($conn, int $status, string $body, array $extra = []): void
{
    $reason = [200 => 'OK', 204 => 'No Content', 302 => 'Found', 401 => 'Unauthorized', 400 => 'Bad Request', 403 => 'Forbidden', 404 => 'Not Found', 429 => 'Too Many Requests', 500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable'][$status] ?? 'Status';
    $head = "HTTP/1.1 $status $reason\r\nConnection: close\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n";

    foreach ($extra as $line) {
        $head .= $line . "\r\n";
    }

    @fwrite($conn, $head . "\r\n" . $body);
}

while ($conn = @stream_socket_accept($server, 120)) {
    stream_set_timeout($conn, 5);
    $raw = '';

    while (strpos($raw, "\r\n\r\n") === false) {
        $chunk = fread($conn, 8192);

        if ($chunk === '' || $chunk === false) {
            break;
        }

        $raw .= $chunk;
    }

    $pos = strpos($raw, "\r\n\r\n");

    if ($pos === false) {
        fclose($conn);
        continue;
    }

    $head = substr($raw, 0, $pos);
    $body = substr($raw, $pos + 4);
    $need = preg_match('/^Content-Length:\s*(\d+)/mi', $head, $m) ? (int) $m[1] : 0;

    if (preg_match('/^Expect:\s*100-continue/mi', $head) && strlen($body) < $need) {
        @fwrite($conn, "HTTP/1.1 100 Continue\r\nX-Interim: yes\r\n\r\n");
    }

    while (strlen($body) < $need) {
        $chunk = fread($conn, 8192);

        if ($chunk === '' || $chunk === false) {
            break;
        }

        $body .= $chunk;
    }

    file_put_contents($log, json_encode(['head' => $head, 'body' => base64_encode($body)]) . "\n", FILE_APPEND);

    [$method, $target] = explode(' ', strtok($head, "\r\n")) + [1 => '/'];
    $path = parse_url($target, PHP_URL_PATH);

    if ($path === '/ok') {
        respond($conn, 200, '{"ok":true}');
    } elseif (preg_match('#^/status/(\d+)$#', $path, $s)) {
        respond($conn, (int) $s[1], json_encode(['transactionId' => 'T-' . $s[1], 'customerTransactionId' => 'C-' . $s[1], 'errors' => [['code' => 'ERR.' . $s[1], 'message' => 'status ' . $s[1]]]]));
    } elseif ($path === '/nobody') {
        respond($conn, 204, '');
    } elseif ($path === '/redirect') {
        respond($conn, 302, '', ['Location: /ok']);
    } elseif ($path === '/slow') {
        sleep(3);
        respond($conn, 200, '{"slow":true}');
    } elseif ($path === '/bad-json') {
        respond($conn, 200, 'this is not json');
    } elseif ($path === '/html502') {
        respond($conn, 502, '<html><body>Bad Gateway</body></html>');
    } elseif ($path === '/big') {
        respond($conn, 200, json_encode(['data' => str_repeat('0123456789abcdef', 20000)]));
    } elseif ($path === '/token-ok') {
        respond($conn, 200, '{"access_token":"TOK","token_type":"bearer","expires_in":3600,"scope":"CXS"}');
    } elseif ($path === '/token-401') {
        respond($conn, 401, '{"transactionId":"624deea6","errors":[{"code":"NOT.AUTHORIZED.ERROR","message":"The given client credentials were not valid."}]}');
    } elseif ($path === '/flaky') {
        // 401 on the first request, 200 afterwards (until /flaky-reset)
        $counters['flaky'] = ($counters['flaky'] ?? 0) + 1;

        if ($counters['flaky'] === 1) {
            respond($conn, 401, '{"transactionId":"T","errors":[{"code":"NOT.AUTHORIZED.ERROR","message":"Access token expired."}]}');
        } else {
            respond($conn, 200, '{"ok":true,"attempt":' . $counters['flaky'] . '}');
        }
    } elseif ($path === '/flaky-reset') {
        $counters['flaky'] = 0;
        respond($conn, 200, '{"reset":true}');
    } elseif (preg_match('#^/ra/(\d+)/(.*)$#', $path, $s)) {
        // Answers with the status and a Retry-After header with the value given, and with two headers of the same name
        respond($conn, (int) $s[1], json_encode(['transactionId' => 'T-' . $s[1], 'errors' => [['code' => 'ERR.' . $s[1], 'message' => 'status ' . $s[1]]]]), ['Retry-After: ' . rawurldecode($s[2]), 'X-Twice: one', 'X-Twice: two', 'x-mixed: a', 'X-MIXED: b']);
    } elseif ($path === '/ra-flaky') {
        // 429 with Retry-After: 1 on the first request, 200 afterwards (until /ra-flaky-reset)
        $counters['ra'] = ($counters['ra'] ?? 0) + 1;

        if ($counters['ra'] === 1) {
            respond($conn, 429, '{"transactionId":"T","errors":[{"code":"TOO.MANY.REQUESTS","message":"Slow down."}]}', ['Retry-After: 1']);
        } else {
            respond($conn, 200, '{"ok":true,"attempt":' . $counters['ra'] . '}');
        }
    } elseif ($path === '/token-flaky') {
        // 503 with Retry-After: 1 on the first token request, a token afterwards (until /token-flaky-reset)
        $counters['token'] = ($counters['token'] ?? 0) + 1;

        if ($counters['token'] === 1) {
            respond($conn, 503, '{"transactionId":"T","errors":[{"code":"SERVICE.UNAVAILABLE","message":"Try later."}]}', ['Retry-After: 1']);
        } else {
            respond($conn, 200, '{"access_token":"TOK-' . $counters['token'] . '","token_type":"bearer","expires_in":3600,"scope":"CXS"}');
        }
    } elseif ($path === '/token-flaky-reset') {
        $counters['token'] = 0;
        respond($conn, 200, '{"reset":true}');
    } elseif ($path === '/ra-flaky-reset') {
        $counters['ra'] = 0;
        respond($conn, 200, '{"reset":true}');
    } elseif ($path === '/always-401') {
        respond($conn, 401, '{"transactionId":"T","errors":[{"code":"NOT.AUTHORIZED.ERROR","message":"Access token expired."}]}');
    } elseif ($path === '/quit') {
        respond($conn, 200, '{"bye":true}');
        fclose($conn);
        break;
    } else {
        respond($conn, 200, json_encode(['method' => $method, 'target' => $target, 'head' => $head, 'body' => $body]));
    }

    fclose($conn);
}
