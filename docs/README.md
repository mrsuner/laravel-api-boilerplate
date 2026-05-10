# Documentation Index

Per-feature docs for the Laravel API Boilerplate. Start with the area you're touching; each doc is self-contained and links to related modules where relevant.

## Authentication

| Doc | Covers |
|---|---|
| [authentication.md](authentication.md) | High-level overview, dual app/web routes, password auth flows, security model |
| [otp.md](otp.md) | Email-based one-time passwords, database vs cache driver |
| [social-auth.md](social-auth.md) | OAuth (Google / GitHub / Facebook / Twitter), account linking |
| [email-verification.md](email-verification.md) | Signed-URL verification, resend, login enforcement, OTP auto-verify |
| [password-policy.md](password-policy.md) | `Password::defaults()` chain composed from config |

## API Surface

| Doc | Covers |
|---|---|
| [api-responses.md](api-responses.md) | Base controller helpers (`respondOk`, `respondNotFound`, …) and the global exception → JSON envelope renderer |
| [rate-limiting.md](rate-limiting.md) | Per-endpoint named throttles for the auth surface |

## Cross-Cutting

| Doc | Covers |
|---|---|
| [notifications.md](notifications.md) | Auth event listeners (welcome / login / logout / reset) and customizing email templates |

## Devlog

| Doc | Covers |
|---|---|
| [devlog/2026-05-10-boilerplate-roadmap.md](devlog/2026-05-10-boilerplate-roadmap.md) | Feature roadmap memo — what's planned, configurability requirements, prioritization |

## Conventions

- **Configurability first.** Every feature exposes its knobs through `config/boilerplate.php` with matching env vars in `.env.example`. If you can't toggle it from config, treat that as a bug.
- **Standard response envelope.** Both intentional responses and thrown exceptions go through the [API responses module](api-responses.md). Don't construct raw `response()->json([...], $code)` for errors — use the helpers or throw, so the shape stays consistent.
- **Tests live alongside features.** Look for `tests/Feature/<Area>/...` matching each doc above. Tests are the canonical source of truth for behavior.
- **Boilerplate, not blueprint.** These features are starting points. Edit them — the controllers, mailables, Blade views, and config defaults are all expected to be customized per project.
