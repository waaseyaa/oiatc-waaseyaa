# First-party analytics — design

Date: 2026-05-24
Status: approved (build)

## Goal

Replace the external `analytics.minoo.live` (umami) script with first-party,
privacy-respecting analytics owned entirely on OIATC infrastructure. Capture
pageviews, unique-ish visitors, and engagement (read-depth + dwell). View it
on a protected dashboard.

## Principles

- First-party only. No third-party JS, no cookies, no raw IP stored.
- Data sovereignty: everything lives in the app's own SQLite DB.
- Respect Do Not Track; skip known bots.
- Lean core, extensible later.

## Architecture (umami-style beacon)

```
browser ──(JSON beacon)──> POST /api/collect ──> AnalyticsRecorder ──> analytics_event (SQLite)
                                                                              │
Caddy basic_auth /admin/* ──> GET /admin/analytics ──> AnalyticsReport ──(aggregate)─┘ ──> Twig dashboard
```

Rejected alternatives: server-side request logging (counts bots/prefetch,
needs a middleware hook); queue pipeline (overkill for content-site write
volume — a single INSERT per beacon is fine).

## Data contract — beacon (client → `POST /api/collect`, `application/json`)

Short keys to keep the payload tiny. CSRF-exempt automatically (JSON content type).

```json
// pageview, sent on load
{ "t": "pageview", "p": "/explainers/massey-solar-project", "r": "https://www.facebook.com/", "v": "0b9f...uuid" }

// engagement, sent on page hide via navigator.sendBeacon
{ "t": "engagement", "v": "0b9f...uuid", "s": 75, "d": 42000 }
```

| key | meaning | rules |
|-----|---------|-------|
| `t` | event type | `pageview` or `engagement` |
| `p` | path | required for pageview; string, capped 255 |
| `r` | referrer (full URL) | optional; server reduces to host only |
| `v` | view id (client `crypto.randomUUID()`) | required; capped 64 |
| `s` | max scroll percent | engagement; int 0–100 |
| `d` | dwell ms | engagement; int 0–86,400,000 (capped) |

Server derives and stores (never trusts client for these):
- `visitor_hash` = `sha256(daily_salt . '|' . ip . '|' . ua)`, where
  `daily_salt = hash_hmac('sha256', gmdate('Y-m-d'), SECRET)`.
  `SECRET = getenv('WAASEYAA_ANALYTICS_SECRET') ?: getenv('WAASEYAA_JWT_SECRET')`.
  Rotates daily; raw IP/UA never persisted.
- `device` = coarse `mobile` | `tablet` | `desktop` from UA.
- `referrer_host` = `parse_url($r, PHP_URL_HOST)` or null (null if same host or absent).
- `created_at` = `gmdate('Y-m-d H:i:s')` (UTC).

Recorder rejects (returns false, endpoint still answers 204): unknown `t`,
missing/oversized fields, out-of-range ints, or bot UA.

## Table `analytics_event`

Created on boot in a provider `boot()` guarded by `schema()->tableExists()`
(no migration CLI in the framework).

| field | type | notes |
|-------|------|-------|
| id | serial | pk |
| event_type | varchar(20) | |
| path | varchar(255) | null for engagement rows |
| referrer_host | varchar(255) | null |
| view_id | varchar(64) | ties pageview+engagement |
| visitor_hash | varchar(64) | null for engagement rows |
| device | varchar(20) | null |
| scroll_pct | int | null |
| dwell_ms | int | null |
| created_at | text | UTC `Y-m-d H:i:s` |

Indexes: `created_at`, `event_type`, `view_id`, `(visitor_hash, created_at)`.

## PHP API (built in main session — agents code against these, do not modify)

```php
namespace App\Analytics;

final class AnalyticsRecorder {
    public function __construct(\Waaseyaa\Database\DatabaseInterface $db, string $secret) {}
    /** @param array<string,mixed> $beacon decoded JSON */
    public function record(array $beacon, ?string $ip, ?string $userAgent): bool;
}

final class AnalyticsReport {
    public function __construct(\Waaseyaa\Database\DatabaseInterface $db) {}
    /**
     * @return array{
     *   totals: array{views:int, visitors:int},
     *   pages: list<array{path:string, views:int, visitors:int, avg_scroll:float, avg_dwell_ms:float}>,
     *   referrers: list<array{host:string, count:int}>,
     *   devices: list<array{device:string, count:int}>,
     *   from:string, to:string
     * }
     */
    public function summary(string $fromDate, string $toDate): array;
}
```

Controllers (built in main session):
- `App\Controller\CollectController::collect(Request $r): Response` — decode JSON, call recorder, return 204.
- `App\Controller\AnalyticsDashboardController::index(Request $r): Response` — read `?from`/`?to` (default last 30 days), call `AnalyticsReport::summary`, render Twig with `report` + `range`.

## Routes (provider)

- `analytics.collect` — `POST /api/collect`, `allowAll()`, `methods('POST')`.
- `admin.analytics` — `GET /admin/analytics`, `allowAll()` at app layer (Caddy gates it), `methods('GET')`.

## Dashboard view — `templates/admin/analytics.html.twig`

Real Twig (env from `Waaseyaa\SSR\SsrServiceProvider::getTwigEnvironment()`).
Receives `report` (the summary array) and `range`. Site-styled (reuse the
explainer palette/fonts). Renders: totals (views, visitors), per-page table
(views, visitors, avg scroll %, avg dwell), top referrers, device split, and a
from/to date range form.

## Client script — `public/js/oiatc-analytics.js`

Vanilla, no deps, served static at `/js/oiatc-analytics.js`. On load: bail if
`navigator.doNotTrack === '1'`. Generate view id. POST pageview (`fetch`,
`keepalive:true`). Track max scroll % (throttled) and start time. On
`visibilitychange→hidden` and `pagehide`, send engagement once via
`navigator.sendBeacon` (Blob, type `application/json`).

## Template swap (×9)

Replace in every template currently loading minoo:
`<script defer src="https://analytics.minoo.live/script.js" data-website-id="..."></script>`
→ `<script defer src="/js/oiatc-analytics.js"></script>`

Files: home, design-system, positions/counter-disinformation,
practice/ai-in-coursework, explainers/robinson-huron-treaty(+-distribution-models),
explainers/massey-solar-project(+-what-youve-heard, +-voices).

## Infra — Caddy basic auth (waaseyaa-infra)

Add `basic_auth` for `/admin/*` in the OIATC site block. Password hash via
`caddy hash-password` (manual step — Russell sets the credential).

## Build sequence

1. Core (main session): table bootstrap, AnalyticsRecorder, AnalyticsReport, both controllers, provider wiring. Verify it loads.
2. Fan out (parallel, independent files): client JS · template swap ×9 · dashboard Twig view · PHPUnit tests · infra Caddy basic auth.
3. Integrate, run tests, manual smoke, present. Deploy on Russell's say.

## Testing

PHPUnit: recorder stores valid pageview/engagement rows; rejects bad input;
visitor_hash stable within a day and rotates across days; bot UA skipped;
report aggregation (views/visitors/avg scroll/dwell, referrers, devices) over a range.
