<?php

namespace KawayanMedia\Attendance\Devices\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal HTTP Digest authentication for the Hikvision and Dahua APIs.
 *
 * Laravel's HTTP client only sends Basic auth, so the challenge is answered
 * manually: send once, read `WWW-Authenticate`, compute the RFC 2617 response
 * and retry with the Authorization header.
 */
final class DigestAuth
{
    /**
     * @param  array<string, mixed>  $options  Guzzle-style options (`json`, `body`, `query`, `headers`).
     */
    public static function send(
        string $method,
        string $url,
        array $options = [],
        ?string $username = null,
        ?string $password = null,
        int $timeout = 15,
        bool $verify = true,
    ): Response {
        $request = Http::timeout($timeout)->withOptions(['verify' => $verify]);

        $first = self::dispatch($request, $method, $url, $options);

        if ($username === null || $password === null || $first->status() !== 401) {
            return $first;
        }

        $challenge = (string) $first->header('WWW-Authenticate');

        if (! str_contains(strtolower($challenge), 'digest')) {
            return $first;
        }

        $authorization = self::authorizationHeader($challenge, $method, $url, $username, $password);

        $retry = $request->withHeaders(['Authorization' => $authorization]);

        return self::dispatch($retry, $method, $url, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function dispatch(PendingRequest $request, string $method, string $url, array $options): Response
    {
        return $request->send(strtoupper($method), $url, $options);
    }

    public static function authorizationHeader(
        string $challenge,
        string $method,
        string $url,
        string $username,
        string $password,
    ): string {
        $params = self::parseChallenge($challenge);

        $realm = (string) ($params['realm'] ?? '');
        $nonce = (string) ($params['nonce'] ?? '');
        $opaque = $params['opaque'] ?? null;
        $algorithm = strtoupper((string) ($params['algorithm'] ?? 'MD5'));
        $qops = array_map('trim', explode(',', (string) ($params['qop'] ?? '')));
        $qop = $qops[0] !== '' ? $qops[0] : null;

        $uri = self::requestUri($url);
        $nc = '00000001';
        $cnonce = bin2hex(random_bytes(8));

        $ha1 = md5("{$username}:{$realm}:{$password}");

        if ($algorithm === 'MD5-SESS') {
            $ha1 = md5("{$ha1}:{$nonce}:{$cnonce}");
        }

        $ha2 = md5(strtoupper($method).":{$uri}");

        $response = $qop === null
            ? md5("{$ha1}:{$nonce}:{$ha2}")
            : md5("{$ha1}:{$nonce}:{$nc}:{$cnonce}:{$qop}:{$ha2}");

        $parts = [
            'username="'.$username.'"',
            'realm="'.$realm.'"',
            'nonce="'.$nonce.'"',
            'uri="'.$uri.'"',
            'algorithm='.$algorithm,
        ];

        if ($qop !== null) {
            $parts[] = 'qop='.$qop;
            $parts[] = 'nc='.$nc;
            $parts[] = 'cnonce="'.$cnonce.'"';
        }

        if (is_string($opaque) && $opaque !== '') {
            $parts[] = 'opaque="'.$opaque.'"';
        }

        $parts[] = 'response="'.$response.'"';

        return 'Digest '.implode(', ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private static function parseChallenge(string $challenge): array
    {
        $challenge = preg_replace('/^Digest\s+/i', '', $challenge) ?? $challenge;

        preg_match_all('/(\w+)=(?:"([^"]*)"|([^,\s]+))/', $challenge, $matches, PREG_SET_ORDER);

        $params = [];

        foreach ($matches as $match) {
            $quoted = $match[2] ?? '';
            $bare = $match[3] ?? '';

            $params[strtolower($match[1])] = $quoted !== '' ? $quoted : $bare;
        }

        return $params;
    }

    private static function requestUri(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return $query === null ? $path : $path.'?'.$query;
    }
}
