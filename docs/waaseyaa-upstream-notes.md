# Waaseyaa upstream notes

A running log of framework quirks, bugs, breakages, missing pieces, and
workarounds hit while building this app on an **alpha** release of
[`waaseyaa/framework`](https://github.com/waaseyaa/framework). The goal is to
fix these **upstream** later rather than carrying app-level hacks indefinitely.

When you hit a Waaseyaa quirk, add an entry. Keep app-level issues (our own
code) out of here — those belong in the audit / punch-list, not in upstream
notes.

**Entry format:**

```
## NNN — short title

- **Date / version:** YYYY-MM-DD · waaseyaa/framework alpha.NNN
- **Doing:** what we were doing when we hit it
- **Symptom:** the observable problem (error text, wrong output, etc.)
- **Workaround:** what we did to get unblocked (or "none needed — informational")
- **Likely upstream fix:** the proper change in waaseyaa/framework
```

---

## 001 — Framework `VERSION` file is stale (reads alpha.4)

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Reporting the installed framework version for the upgrade assessment.
- **Symptom:** `vendor/waaseyaa/framework/VERSION` contains `0.1.0-alpha.4`, while the installed package (git tag / `composer.lock`) is `v0.1.0-alpha.188`. The two disagree by 184 releases. Anything trusting the `VERSION` file for provenance/drift checks would read a wildly wrong value.
- **Workaround:** Treat the git tag / `composer.lock` `version` as the source of truth; ignore the `VERSION` file. (`bin/waaseyaa-version` should be the canonical provenance tool.)
- **Likely upstream fix:** Have the release-cut process (`scripts/release.sh` / `release-cut.yml`) rewrite `VERSION` from the tag at publish time — the same mechanism that already resolves `self.version` for sibling packages. Or delete the file and make `bin/waaseyaa-version` read the lockfile/tag exclusively, so there is no second, stale source.

## 002 — "Ambiguous class resolution" warnings: metapackage vendors the same classes as the split mirrors

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** `composer install` / `composer update` (autoloader generation).
- **Symptom:** ~10 Composer warnings like `Ambiguous class resolution, "Waaseyaa\GitHub\GitHubClient" was found in both vendor/waaseyaa/framework/packages/github/src and vendor/waaseyaa/github/src, the first will be used.` (also `Waaseyaa\Engagement\*`). The monolithic `waaseyaa/framework` package vendors `packages/github`, `packages/engagement`, etc., **and** the standalone split-mirror packages (`waaseyaa/github`, `waaseyaa/engagement`) ship the identical classes — both end up in the classmap.
- **Workaround:** None needed — informational. "first will be used" (the framework copy wins) and behavior is correct. Just noise.
- **Likely upstream fix:** The framework metapackage's `autoload` should `exclude-from-classmap` the `packages/*/src` dirs that are also published as standalone packages, or the split mirrors should be `replace`d by `waaseyaa/framework` in its `composer.json` so Composer never loads both. Cleanest: declare `"replace": { "waaseyaa/github": "self.version", "waaseyaa/engagement": "self.version", ... }` in the framework root manifest.

## 003 — `composer.lock` drift after `php: >=8.5` added post-hash

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** First `composer install` on a fresh clone.
- **Symptom:** `Warning: The lock file is not up to date with the latest changes in composer.json … run composer update`. Root cause: the alpha.152→alpha.188 bump regenerated the lock, then a later commit added `php: >=8.5` to `composer.json` (an alpha.188 platform requirement) **without** regenerating the lock, so the content-hash no longer matched. Harmless (the constraint is satisfied) but it nags on every install and would block `--no-update` workflows.
- **Workaround:** Ran `composer update` on a branch (`chore/refresh-lock`) to regenerate the lock; only transitive Symfony/Twig point releases moved, framework stayed at alpha.188.
- **Likely upstream fix:** This is partly an app discipline issue (always regenerate the lock when editing `composer.json`), but the framework could help: when alpha.188 raised the **PHP floor to 8.5**, the skeleton's `composer.json`/`composer.lock` (`skeleton/` in the framework repo) should have been bumped together in the same release so downstream `composer create-project` users start consistent. Add a release gate that fails if `skeleton/composer.json` platform reqs and `skeleton/composer.lock` content-hash disagree.

## 004 — Hard `ext-sodium` dependency via `oidc → lcobucci/jwt`

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** `composer install` on a stock Windows PHP 8.5 build with `ext-sodium` disabled.
- **Symptom:** Install **fails**: `lcobucci/jwt 5.6.0 requires ext-sodium * -> it is missing from your system` (pulled in by `waaseyaa/oidc → lcobucci/jwt ^5.3`). The dependency is transitive and non-obvious — nothing in the app uses OIDC/JWT directly, yet a fresh clone can't install without sodium.
- **Workaround:** Enabled `extension=sodium` in the machine's `php.ini` (the DLL shipped, just commented out). Interim: `php -d extension=sodium composer install`.
- **Likely upstream fix:** (a) Document `ext-sodium` in the framework's platform requirements / install docs and add it to root `composer.json` `require` (`"ext-sodium": "*"`) so Composer reports it up front as a clear platform error instead of a deep transitive surprise. (b) Consider whether `waaseyaa/oidc` belongs in the default `waaseyaa/framework` metapackage dependency graph at all — apps that don't issue/verify OIDC tokens shouldn't transitively require a sodium-backed JWT lib. Splitting oidc into an opt-in package would remove the requirement for non-OIDC apps like this one.

## 005 — No migration CLI; schema must be created at runtime (boot)

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Reviewing how the app's `analytics_event` (non-entity) table gets created (`src/Analytics/AnalyticsSchema.php`).
- **Symptom:** The framework has no schema-migration CLI for non-entity / supporting tables, so the app creates its table inside `AppServiceProvider::boot()` via `$db->schema()->createTable(...)`, guarded by `tableExists()`. This runs a schema check on **every request** and couples DDL to the request lifecycle (the code comment says as much: "The framework has no migration CLI, so the table is ensured at boot").
- **Workaround:** Boot-time `ensure()` with a `tableExists()` short-circuit. Works, but is not how schema should be managed in production.
- **Likely upstream fix:** Provide a first-class migration mechanism in `waaseyaa/migration` (or a `bin/waaseyaa migrate`-style CLI) that covers raw/supporting tables, not just entity-storage schema, so apps can declare migrations and run them out-of-band instead of on every boot.

## 006 — `BroadcastStorageScheduleEntries` warns on every boot when `BroadcastStorage` is unbound

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Smoke-testing public pages under `php -S` (dev server log).
- **Symptom:** Every request emits `warning … BroadcastStorageScheduleEntries: BroadcastStorage not bound; broadcast_log_prune task will not be registered. Bind Waaseyaa\Api\Controller\BroadcastStorage in a ServiceProvider to enable pruning.` This app doesn't use SSE broadcasting, so the warning is pure noise and repeats on every boot/request.
- **Workaround:** None — informational; the page renders fine (200). Ignored.
- **Likely upstream fix:** Downgrade to `debug` level (or emit once per process, not per request) when the broadcast feature is simply not wired. A schedule-entries class whose optional dependency is absent should no-op quietly, not `warning` — reserve `warning` for misconfiguration, not for a feature the consumer never opted into.

## 007 — No public way to set the SSR Twig environment for tests

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Writing an integration test that renders SSR Twig templates through a controller (`SsrServiceProvider::getTwigEnvironment()`).
- **Symptom:** `getTwigEnvironment()` reads a `private static ?Environment $twigEnvironment` that is only assigned inside `SsrServiceProvider::boot()`. There is a public `SsrServiceProvider::createTwigEnvironment($projectRoot, $config)` factory (its docblock even says "For static factory usage (tests)…"), but it **returns** an env without storing it — so calling it does nothing for `getTwigEnvironment()`. There is no public setter and no documented test-render path. A test that just news up a controller and calls a render method gets a 500 ("Twig is not initialised").
- **Workaround:** Boot a real provider the way the kernel does, using the base `ServiceProvider::setKernelContext()`: `(new SsrServiceProvider())->setKernelContext($appRoot, [], []); $provider->boot();` in `setUpBeforeClass()`. This populates the static so `getTwigEnvironment()` returns a real env. Works, but it's non-obvious and couples the test to internal boot ordering.
- **Likely upstream fix:** Either (a) add a public `SsrServiceProvider::setTwigEnvironment(Environment $env): void` (+ a `reset()` for test isolation) so the public `createTwigEnvironment()` factory is actually usable, or (b) ship a small testing helper in `packages/ssr/testing/` (e.g. `SsrTestKit::bootTwig($projectRoot): Environment`) and document the render-in-test pattern. The factory's "for tests" docblock implies an intended path that isn't wired up.

## 008 — PHPStan needs a raised memory limit because of the framework's autoload footprint

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Adding a PHPStan level-5 gate to this small app (only ~14 source/test files).
- **Symptom:** `phpstan analyse` crashes with `PHPStan process crashed because it reached configured PHP memory limit: 128M` (the PHP CLI default). The app's own code is tiny; the memory goes to autoloading/reflecting the `waaseyaa/framework` metapackage and its ~60 sibling packages that the app types against.
- **Workaround:** Run PHPStan with `--memory-limit=1G` (baked into the `composer phpstan` script). Resolves it, but 1G for a 14-file app is a lot.
- **Likely upstream fix:** Framework-footprint friction rather than a bug. Lighter, purpose-scoped metapackages (e.g. a `waaseyaa/framework-runtime` without the full AI/CLI/admin surface) would shrink the type-graph a consumer must load. At minimum, document a recommended PHPStan `--memory-limit` for downstream apps. Tracks with the monolith-vs-split tension noted in [#002].

## 009 — A new content entity is silently invisible over JSON:API until a view-granting AccessPolicy exists

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Auditing `/api/news_post` (an app entity with no AccessPolicy), then adding one.
- **Symptom:** With no policy registered for an entity type, `EntityAccessHandler::check($entity, 'view', $account)` aggregates to `Neutral`, and `JsonApiController` requires `isAllowed()`. For the **single-entity GET** that means `403`, but for the **collection GET** (`/api/{type}`) the controller filters each row by `isAllowed()` (`JsonApiController:85`), so the endpoint returns **`200` with `data: []`** — an empty list, no error, no warning. A freshly-registered content type therefore looks "publicly readable but empty" rather than "not yet authorized," which reads as a data bug, not an access decision. (This is what made the read surface look "open but empty" during the audit.)
- **Workaround:** Add an explicit `AccessPolicy` that returns `allowed()` for `view` (here: published rows public, drafts gated). See `src/Access/NewsPostAccessPolicy.php`.
- **Likely upstream fix:** Fail-closed is the right default, but the **silent** empty collection is a footgun. Options: (a) when a list is filtered down by access, include a JSON:API `meta` note or emit a `debug`-level log ("N rows hidden by access policy"); (b) document prominently that a new content entity needs a view-granting policy to be API-readable; (c) optionally surface a boot-time/dev-mode notice when a registered entity type has no policy at all. Any of these turns a confusing empty list into an obvious "you haven't authorized this yet."
