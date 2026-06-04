# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this application.

## Overview

<!-- Replace with your app description -->
A Waaseyaa application built on the [Waaseyaa framework](https://github.com/waaseyaa/framework).

## Strategy folder & published-site inventory

This repo is the single source of truth for everything published on **oiatc.ca**. A separate OIATC strategy folder (Russell's `Projects/OIATC/` workspace, outside this repo) keeps a documentation/index layer:

- `oiatc-ca-inventory.md` maps every live page to its canonical Twig source here, its URL, and its last-updated date, plus the news/analytics systems, social-post drafts, and cut pages.
- That folder's `CLAUDE.md` points back to this repo as the source of truth.

When a page ships, is renamed, or is retired here, reconcile `oiatc-ca-inventory.md`. If the two disagree, this repo wins.

## Architecture

```
src/
├── Access/        Authorization policies
├── Controller/    HTTP controllers (thin orchestration)
├── Domain/        Domain logic grouped by bounded context
├── Entity/        Entity classes (extend ContentEntityBase)
├── Provider/      Service providers (DI, routing, entity registration)
└── Support/       Cross-cutting utilities
```

### Key Patterns

- **Entities** extend `ContentEntityBase` and register via `EntityTypeManager`
- **Persistence** uses `EntityRepository` + `SqlStorageDriver` (see `.claude/rules/waaseyaa-framework.md`)
- **Routes** defined in `ServiceProvider::routes()` via `WaaseyaaRouter`
- **Auth** via `Waaseyaa\Auth\AuthManager` (session-based)
- **Config** via `config/waaseyaa.php` — use `getenv()` or `env()` helper, NEVER `$_ENV`
- **PSR-4 one-class-per-file** — each PHP file declares exactly one class/interface/enum. Namespace matches directory path.

### ServiceProvider DI Methods

Service providers extend `Waaseyaa\Foundation\ServiceProvider\ServiceProvider`. Register bindings in `register()`, use `boot()` for event subscribers and cache warming.

```php
// In register():
$this->singleton(MyInterface::class, fn () => new MyService($this->resolve(Dependency::class)));
$this->bind(TransientService::class, TransientService::class);  // new instance each time
$myService = $this->resolve(MyInterface::class);  // resolve a registered binding
$this->tag(MyInterface::class, 'my_tag');  // tag for grouped resolution
$this->entityType(new EntityType(...));  // register an entity type
```

**Method signatures** (from `Waaseyaa\Foundation\ServiceProvider\ServiceProvider`):

| Method | Signature | Purpose |
|--------|-----------|---------|
| `singleton()` | `protected singleton(string $abstract, string\|callable $concrete): void` | Bind as shared instance (resolved once) |
| `bind()` | `protected bind(string $abstract, string\|callable $concrete): void` | Bind as transient (new instance each call) |
| `resolve()` | `public resolve(string $abstract): mixed` | Resolve a binding (falls back to kernel resolver) |
| `tag()` | `protected tag(string $abstract, string $tag): void` | Tag a binding for grouped resolution |
| `entityType()` | `protected entityType(EntityTypeInterface $entityType): void` | Register an entity type definition |

### Key Framework Namespaces

| Interface | Full Namespace | Purpose |
|-----------|---------------|---------|
| `EntityRepositoryInterface` | `Waaseyaa\Entity\Repository\EntityRepositoryInterface` | Entity CRUD (find, findBy, save, delete, saveMany, deleteMany) |
| `AccessPolicyInterface` | `Waaseyaa\Access\AccessPolicyInterface` | Entity access control (access, createAccess, appliesTo) |
| `FieldAccessPolicyInterface` | `Waaseyaa\Access\FieldAccessPolicyInterface` | Field-level access (open-by-default, Forbidden restricts) |
| `QueueInterface` | `Waaseyaa\Queue\QueueInterface` | Dispatch messages: `dispatch(object $message): void` |
| `Job` | `Waaseyaa\Queue\Job` | Abstract queue job base class |
| `DatabaseInterface` | `Waaseyaa\Database\DatabaseInterface` | Raw SQL via Doctrine DBAL (for non-entity tables) |
| `ServiceProvider` | `Waaseyaa\Foundation\ServiceProvider\ServiceProvider` | Base class for service providers |

### Queue Job Pattern

```php
use Waaseyaa\Queue\Job;
use Waaseyaa\Queue\QueueInterface;

final class SendWelcomeEmail extends Job
{
    public int $tries = 3;        // max attempts
    public int $timeout = 30;     // seconds before timeout
    public int $retryAfter = 10;  // seconds between retries

    public function __construct(private readonly string $userId) {}

    public function handle(): void
    {
        // Job logic here
    }

    public function failed(\Throwable $e): void
    {
        // Cleanup on final failure (optional override)
    }
}

// Dispatch via QueueInterface:
$queue->dispatch(new SendWelcomeEmail($userId));
```

## Frontend Design System

All frontend lives in `public/css/site.css` and `templates/`. No build step — plain CSS + Twig. Every page is rendered through the SSR Twig environment (`Waaseyaa\SSR\SsrServiceProvider::getTwigEnvironment()`); controllers never read template files directly.

### Template families

There are three distinct page families. Know which one you're editing:

1. **Site shell** — `templates/base.html.twig` is the shared layout for the marketing/site pages (`home`, `design-system`, `practice/*`). It owns the `<head>` (Source Serif 4 / Inter / JetBrains Mono via Google Fonts, `/css/site.css`, analytics beacon), the `header.top` masthead + theme toggle, the `site-foot` footer, and the theme-toggle + `.reveal` IntersectionObserver script. Child pages `{% extends 'base.html.twig' %}` and fill blocks: `title`, `description`, `head_meta` (per-page canonical/OG/Twitter/JSON-LD), `head_styles` (per-page `<style>`), `body_class`, `topnav`, `content`, `footer`, `scripts_extra`.
2. **Longform documents** — the explainers (`templates/explainers/*`), positions (`templates/positions/*`), and disclosures (`templates/disclosure/*`) are self-contained editorial documents, each with its own large inline `<style>` and masthead/footer. They do **not** extend `base.html.twig` and do **not** use `site.css`. (Follow-up: extract a shared `templates/_doc.html.twig` for them — they currently duplicate their `:root` tokens per file.)
3. **News** — `templates/news/*` extends `templates/news/_layout.html.twig` (its own "newsprint" theme, separate from the site shell).

### Design tokens (`site.css :root`)

`site.css` (~540 lines) defines tokens in **oklch**, with a default light theme on `:root` and a dark override under `[data-theme="dark"]` (toggled by the base-layout button, persisted to `localStorage`).

| Token | Role |
|-------|------|
| `--font-serif` `"Source Serif 4"` | Display + body serif |
| `--font-sans` `"Inter"` | UI / labels |
| `--font-mono` `"JetBrains Mono"` | Code, dates, eyebrows |
| `--accent` (oklch hue 55) | Amber accent; `--accent-deep` / `--accent-soft` / `--accent-wash` derived |
| `--paper` / `--paper-2` / `--paper-3` | Surfaces (warm in light, blue-grey in dark) |
| `--ink` / `--ink-2` / `--ink-3` | Text (primary → muted) |

When changing the palette, update both the `:root` (light) and `[data-theme="dark"]` token blocks.

### OG image

1200×630 social cards live in `public/images/og/` (per page, named `<slug>.png` where `slug` is the template path with `/`→`-`, no extension) plus the hand-crafted `og-default.png` / `og-massey-solar-project.png` / `og-sagamok-portal.png`. `HomeController::socialContext()` serves the per-page card if present, else falls back to `og-default.png`. `scripts/generate-og.js` auto-discovers any `base.html.twig`-extending page and renders a card from `scripts/og-template-auto.html`.

**A new page gets its card automatically.** The `.github/workflows/og-cards.yml` workflow runs on push to `main` (templates/generator changes), renders only the *missing* cards (`generate-og.js --only-missing`, no churn) on a hosted runner, and commits them back. Chromium never runs on the Pi.

> **Deploy note:** because the card is committed by a follow-up CI commit, bump `OIATC_REF` to the **main tip after the "OG cards" workflow finishes** (not your code commit), so the image build includes the card.

Manual use (refreshing an existing card after a title/palette change, which `--only-missing` won't do; `package.json` is gitignored so install Playwright directly):
```bash
npm install playwright && npx playwright install chromium
node scripts/generate-og.js                  # regenerate all
node scripts/generate-og.js --only-missing   # gaps only (what CI runs)
```
Colors in the `og-template*.html` files are hardcoded (not CSS variables). When updating the palette, sync them manually.

### Scroll reveal

Elements with `.reveal` start invisible and fade up via `IntersectionObserver` (threshold 0.1). Stagger with `.reveal--d1 / --d2 / --d3`. Respects `prefers-reduced-motion`. Wired in `templates/base.html.twig`.

### Orchestration Table

<!-- Map file patterns to skills and specs as you add them -->
| File Pattern | Skill | Spec |
|-------------|-------|------|
| `src/Entity/**` | `waaseyaa:entity-system` | entity-system.md |
| `src/Access/**` | `waaseyaa:access-control` | access-control.md |
| `src/Provider/**` | `feature-dev` | — |
| `.claude/rules/**` | `updating-codified-context` | — |
| `docs/specs/**` | `updating-codified-context` | — |

<!-- Note: waaseyaa:* skills are placeholders. They will not function
     until the skills are built. The entries document intended routing. -->

## MCP Federation

Register Waaseyaa's MCP server in `.claude/settings.json` for on-demand framework specs:

```json
{
  "mcpServers": {
    "waaseyaa": {
      "command": "node",
      "args": ["vendor/waaseyaa/mcp/server.js"],
      "cwd": "."
    }
  }
}
```

## Development

```bash
composer install                    # Install dependencies
php -S localhost:8080 -t public     # Dev server
./vendor/bin/phpunit                # Run tests
bin/waaseyaa                        # CLI
bin/waaseyaa-version                # Framework provenance (path SHA, lockfile, drift vs golden)
bin/waaseyaa-audit-site             # Mechanical convergence preflight (validate + bins + provenance)
bin/waaseyaa sync-rules             # Update framework rules from Waaseyaa
```

Set `WAASEYAA_GOLDEN_SHA` or add `.waaseyaa-golden-sha` for CI drift gates (see `docs/specs/version-provenance.md` in the framework repo).

**Per-site convergence audits:** follow [per-site-convergence-audit.md](https://github.com/waaseyaa/framework/blob/main/docs/specs/per-site-convergence-audit.md) in the Waaseyaa monorepo; record findings under `docs/audits/` per that spec.

## Operations: deploy, Pi access, secrets

oiatc.ca runs on a Raspberry Pi managed entirely through the **private**
`waaseyaa-infra` repo. **Do not re-derive any of this each session, and do not
ask the human to SSH or to run Pi commands** — they have never SSHed in and
won't. A Claude agent set the Pi up; agents administer it. You already have the
access; use it.

- **Deploy app code:** commit + push `main`, then bump `OIATC_REF` in
  `waaseyaa-infra` `compose/docker-compose.yml` to the new SHA and push — GitHub
  Actions rebuilds + restarts the container on the Pi over Tailscale. You do not
  build on the Pi by hand.
- **Pi access:** SSH as the `oiatc` user with the agent key on this workstation
  (the deploy also reaches the Pi from CI). Exact host/key are in the private
  runbook below — never put them or any secret in this (public) repo.
- **Secrets:** the **ansible vault** in `waaseyaa-infra` is the source of truth.
  `ANTHROPIC_API_KEY` is delivered to the Pi by the ansible playbook (or, when
  ansible isn't available, an SSH apply that pipes the value on stdin). It is
  **server-side only — never echo it into chat, logs, the transcript, or a
  rendered page.** Locally it lives in `.env` (gitignored); the vault password,
  when present, is `.vault-pass` (gitignored). Never commit or print either.
- **Anokii data:** after a deploy, `bin/waaseyaa app:ingest-docs` then
  `app:seed-graph` (idempotent) build the chunk + graph data on the Pi's storage
  volume; the curated region place-lists and coordinates live in
  `src/Command/SeedGraphCommand.php`.

> **Full, authoritative procedure (host, SSH key, vault, apply commands, gaps):**
> `waaseyaa-infra/runbooks/05-oiatc-deploy-and-secrets.md` (private repo). That
> runbook is the canonical reference — read it before any deploy or secret work.

## Codified Context

This app uses a three-tier codified context system inherited from Waaseyaa:

| Tier | Location | Purpose |
|------|----------|---------|
| **Constitution** | `CLAUDE.md` (this file) | Architecture, conventions, orchestration |
| **Rules** | `.claude/rules/waaseyaa-*.md` | Framework invariants (always active, never cited) |
| **Specs** | `docs/specs/*.md` | Domain contracts for each subsystem |

Framework rules are owned by Waaseyaa. Update them via `bin/waaseyaa sync-rules` after `composer update`.

When modifying a subsystem, update its spec in the same PR.

## Known Gaps

<!-- Track technical debt and migration items here -->

## Gotchas

- **Never use `$_ENV`** — Waaseyaa's `EnvLoader` only populates `putenv()`/`getenv()`. Use `getenv()` or the `env()` helper.
- **SQLite write access** — Both the `.sqlite` file AND its parent directory need write permissions for WAL/journal files.
