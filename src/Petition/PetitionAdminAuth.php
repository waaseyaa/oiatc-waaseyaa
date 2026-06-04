<?php

declare(strict_types=1);

namespace App\Petition;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP Basic gate for the petition admin (signature export holds PII).
 *
 * Credentials come from env (server-side only): PETITION_ADMIN_USER and
 * PETITION_ADMIN_PASS. This FAILS CLOSED — if either is unset, the admin is
 * unavailable (503) rather than open. That matters here because /admin/* on
 * this site currently has no edge auth, so the gate must live in the app.
 *
 * This is a deliberate stopgap; see the deploy notes about moving the petition
 * admin behind Caddy basic_auth or the Tailscale-only interface as well.
 */
final class PetitionAdminAuth
{
    public function __construct(
        private readonly string $user,
        private readonly string $pass,
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            getenv('PETITION_ADMIN_USER') ?: '',
            getenv('PETITION_ADMIN_PASS') ?: '',
        );
    }

    /**
     * Returns null when the request is authorised; otherwise the Response to
     * send back (503 if unconfigured, 401 challenge if missing/wrong).
     */
    public function guard(Request $request): ?Response
    {
        if ($this->user === '' || $this->pass === '') {
            return new Response(
                'Petition admin is not configured. Set PETITION_ADMIN_USER and PETITION_ADMIN_PASS.',
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $givenUser = (string) ($request->getUser() ?? '');
        $givenPass = (string) ($request->getPassword() ?? '');

        $ok = hash_equals($this->user, $givenUser) && hash_equals($this->pass, $givenPass);
        if (!$ok) {
            return new Response('Authentication required.', 401, [
                'WWW-Authenticate' => 'Basic realm="OIATC petition admin", charset="UTF-8"',
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return null;
    }
}
