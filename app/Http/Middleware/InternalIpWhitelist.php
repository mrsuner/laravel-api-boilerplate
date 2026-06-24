<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the internal admin surface to a configured set of CIDR ranges
 * (Tailscale CGNAT by default). Rejected requests are recorded to the audit
 * trail. The check is skipped entirely when disabled in config, which is
 * useful for local development behind a different network.
 */
final class InternalIpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('boilerplate.admin.ip_whitelist.enabled', true)) {
            return $next($request);
        }

        $clientIp = $request->ip();
        $allowed = (array) config('boilerplate.admin.ip_whitelist.cidrs', []);

        foreach ($allowed as $cidr) {
            if ($this->matches($clientIp, trim((string) $cidr))) {
                return $next($request);
            }
        }

        audit_log('admin.ip_rejected', null, [
            'metadata' => [
                'ip' => $clientIp,
                'path' => $request->path(),
            ],
        ]);

        return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
    }

    private function matches(string $ip, string $cidr): bool
    {
        if ($cidr === '') {
            return false;
        }

        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$network, $bits] = explode('/', $cidr, 2);

        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);

        if ($ipLong === false || $networkLong === false) {
            return false;
        }

        $mask = ~((1 << (32 - (int) $bits)) - 1);

        return ($ipLong & $mask) === ($networkLong & $mask);
    }
}
