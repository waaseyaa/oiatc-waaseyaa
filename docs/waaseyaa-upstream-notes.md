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

## 010 — ai-vector ships no service provider; embedding is not wired even when installed

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Phase 2 AI-layer assessment — checking which AI packages actually boot in this app.
- **Symptom:** `ai-agent`, `ai-tools`, `ai-observability`, and `ai-pipeline` each declare `extra.waaseyaa.providers` and auto-boot (the `agent_run` table exists, the 8 stock tools register, etc.). **`ai-vector` and `ai-schema` declare no providers at all**, so nothing is wired by installing them: the `EntityEmbeddingListener` is not subscribed, no `EmbeddingStorageInterface` / `EmbeddingProviderInterface` is bound, and the `embeddings` table is never created. The package looks "installed and ready" but is inert until the app writes its own service provider to subscribe the listener and bind a storage + provider. Easy to assume vector search works because the package is present.
- **Workaround:** App-owned service provider that binds `EmbeddingStorageInterface` (e.g. `SqliteEmbeddingStorage`), an `EmbeddingProviderInterface` (via `EmbeddingProviderFactory::fromConfig`), and subscribes `EntityEmbeddingListener` to `EntityEvent::POST_SAVE`.
- **Likely upstream fix:** Ship an `AiVectorServiceProvider` (declared in `ai-vector/composer.json` `extra.waaseyaa.providers`) that wires storage + provider + listeners from the existing `ai.embedding_provider` config, no-opping cleanly when the provider is empty — matching how the other ai-* packages self-wire.

## 011 — The vector store keeps only vectors, and embedding is entity-driven; RAG over non-entity content needs synthesized chunk records

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Scoping RAG over the app's resource/explainer content.
- **Symptom:** Two coupled constraints. (1) `EmbeddingStorageInterface::store(entityType, id, vector)` persists only the float vector keyed by `(entity_type, entity_id)`; `findSimilar()` returns `{id, score}`. The store is **not** a document store — it holds no source text, so grounding a model requires the app to keep the chunk text itself elsewhere and join on the returned id. (2) Embedding generation is **entity-driven**: `EntityEmbeddingListener` embeds configured `embedding_fields` on `EntityEvent::POST_SAVE`. Content that is not an entity — here the resource/explainer pages are Twig templates, and the only entity (`news_post`) isn't even in `embedding_fields` — cannot be embedded without first turning it into entity/chunk records.
- **Workaround:** Introduce a `doc_chunk` record (entity or a `DatabaseInterface` table) holding `{id, source_url, title, text}`; embed each chunk into the vector store under a synthetic `entity_type='doc_chunk'`; at query time, search → ids → load chunk text → ground the model.
- **Likely upstream fix:** Offer a first-class "passage/chunk" source (text + metadata + vector in one record) and a non-entity ingestion path, plus optional text-chunking, so RAG over static/site content doesn't require every app to reinvent a chunk table.

## 012 — Tool-calling works only with AnthropicProvider; the OpenAI-compatible provider is text-only

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Designing a retrieval-tool RAG agent.
- **Symptom:** `AgentExecutor`'s tool loop (provider returns tool-use blocks → executor invokes `AgentToolInterface` → feeds results back) is implemented for `AnthropicProvider` (SSE, tool blocks). `OpenAiCompatibleProvider` is text-in/text-out with no tool-call support, so an `#[AsAgentDefinition]` that lists `tools:` will not actually call them under OpenAI. A retrieval-**tool** agent therefore requires Anthropic; with an OpenAI-compatible model you must inline retrieved passages into the prompt instead of exposing a tool.
- **Workaround:** Use `AnthropicProvider` for tool-using agents, or skip the tool loop and build a retrieve-then-prompt RAG controller that puts passages in the system/user message.
- **Likely upstream fix:** Implement OpenAI function-calling in `OpenAiCompatibleProvider` (the Chat Completions `tools`/`tool_calls` shape), or document clearly that tool loops are Anthropic-only at alpha.188.

## 013 — No chat ProviderInterface binding; NullLlmProvider returns a placeholder that looks like success

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Tracing what the shipped `/api/ai/agent/run` endpoint actually does out of the box.
- **Symptom:** `MessagingServiceProvider` binds `ProviderInterface` to `NullLlmProvider` by default, and the framework never reads an `ANTHROPIC_API_KEY` (Anthropic is entirely app-wired; only `OPENAI_API_KEY` is read, and only for embeddings). So a fresh app's agent runs "succeed" but return the literal placeholder `"[LLM unavailable in this environment ...]"`. Combined with the agent run being async (dispatched to a Messenger handler, needs a worker/sync transport) and `BroadcastStorage` being unbound ([#006]), the shipped agent HTTP/SSE surface returns no real, streamed answer until the app binds a provider, a worker, and broadcast storage.
- **Workaround:** App service provider binds `ProviderInterface` to `AnthropicProvider(getenv('ANTHROPIC_API_KEY'), model)`, binds `BroadcastStorage` (see [#006]), and runs a queue worker (or a sync transport for the `RunAgent` message).
- **Likely upstream fix:** Keep `NullLlmProvider` as the default but make it loud — log a `warning` once at boot when the active chat provider is Null, and/or have the agent endpoint surface a clear "no model configured" error rather than a success-shaped placeholder. Document the provider + worker + broadcast prerequisites for the agent endpoint in one place.

## 014 — No CLI or queue handler to build embeddings; the index must be warmed by hand

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Looking for how to populate the vector index.
- **Symptom:** `SemanticIndexWarmer` (batch embed) is a service with **no `bin/waaseyaa` command** exposed, and `EntityEmbeddingListener` can dispatch a `GenericMessage(type: 'ai_vector.embed_entity')` for which **no handler ships**. So there is no out-of-the-box way to (re)build embeddings; the app must wire its own CLI command or message handler.
- **Workaround:** App-owned CLI command (or a one-off script) that calls `SemanticIndexWarmer::warm([...types])`, or a registered handler for the `ai_vector.embed_entity` message.
- **Likely upstream fix:** Ship a `bin/waaseyaa ai:embed[:warm]` command and a default handler for the `ai_vector.embed_entity` message in `ai-vector`, so populating the index is a documented one-liner.

## 015 — `#[ContentEntityType]` does not auto-register an entity; you must also list it in config/entity-types.php

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Adding a `doc_chunk` content entity, following the attribute-first pattern the docs/UPGRADING.md promote (`#[ContentEntityType]` + `#[Field]` on typed properties).
- **Symptom:** The `#[ContentEntityType(id: 'doc_chunk')]` attribute on the entity class is **not** what registers the type. `optimize:manifest` reports `0 attribute entity types` even with `news_post` and `doc_chunk` both carrying the attribute; registration happens only because `config/entity-types.php` returns `new EntityType(id, label, class, keys)` for each. So the attribute drives field discovery/metadata, but the type is invisible to `EntityTypeManager` (and `getRepository('doc_chunk')` throws / the table is never created) until you also hand-register it in config. The two-place requirement is easy to miss given the docs lead with attribute-first.
- **Workaround:** Define the entity attribute-first **and** add a matching `new EntityType(...)` row to `config/entity-types.php`.
- **Likely upstream fix:** Either scan the app's `src/` for `#[ContentEntityType]` and auto-register (so the attribute is sufficient), or document clearly that attribute-first defines *shape* while `config/entity-types.php` (or `EntityType::fromClass()` in a provider) still does the *registration*. At minimum, make `optimize:manifest`'s "0 attribute entity types" line explain that app entities register via config.

## 016 — findBy() criteria only match key columns, not custom `#[Field]`s (which live in the _data blob)

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188
- **Doing:** Trying to upsert `doc_chunk` rows by a stable `source_url` / `chunk_key` field.
- **Symptom:** Custom `#[Field]` values are stored as JSON inside the `_data` column (only the entity-key fields — id, uuid, bundle, label, langcode — get real columns). `SqlStorageDriver::findBy(['source_url' => $url])` builds a SQL condition on a column that doesn't exist for blob fields, so filtering by a custom field does not work as written (the `NewsController` already sidesteps this by loading all rows and filtering in PHP). There's no error that says "you can't filter on a blob field" — it just won't behave like a normal query.
- **Workaround:** Load all rows (`findBy([])`) and index/filter in PHP (fine at our scale), as `IngestDocsCommand::syncChunks` does; or promote a field to a key/indexed column if it must be queryable.
- **Likely upstream fix:** Support `findBy()` predicates against `_data` fields via SQLite `json_extract` (and document which fields are query-capable), or make the storage reject/raise on criteria that reference non-column fields instead of silently not filtering.

## 017 — (infra, waaseyaa-infra) No memory limits on any compose service; Pi RAM size undocumented

- **Date / version:** 2026-06-01 · waaseyaa-infra (compose/docker-compose.yml), Pi rung-1.
- **Doing:** Assessing whether a local Ollama embedding container can run on the Pi without starving the web stacks (Phase 2 Stage 0).
- **Symptom:** (1) **No `mem_limit`/`deploy.resources` on any of the 13 compose services** — every container (oiatc-app, giiken-app, spot-the-ai, caddy, the two nginx static sites, the waitlist service, uptime-kuma, cloudflared) shares host RAM with no cap, so a new memory-hungry service (or a leak in any existing one) can pressure the whole box and OOM a neighbour. There's no per-service budget to reason against. (2) **The Pi's actual RAM is not recorded anywhere** — `docs/03-hardware.md` only says "4 GB minimum, 8 GB preferred"; the inventory says "Raspberry Pi 4 (rung 1)" with no size. The single most important capacity number for this decision isn't written down.
- **Workaround:** Determine RAM out-of-band (`free -h` on the box) and add `mem_limit` to any new service (Ollama) plus, ideally, backfill limits on the existing ones.
- **Likely upstream fix (infra repo, not framework):** Record the real RAM (and SSD model) in `docs/03-hardware.md` / `docs/06-infrastructure-inventory.md`; add explicit `mem_limit` (or `deploy.resources.limits.memory`) to each compose service so the box has a memory budget and one service can't starve the rest. (Logged here per the standing "framework or infra quirks" instruction; properly belongs in waaseyaa-infra docs.)

## 018 — No persistent rate-limiter, and resolve(DatabaseInterface) at route-build time is ephemeral

- **Date / version:** 2026-06-01 · waaseyaa/framework alpha.188
- **Doing:** Adding a per-client rate limit to the public `/api/chat` (cost guardrail on a paid model).
- **Symptom:** Two coupled gaps. (1) The shipped `RateLimiterInterface`/`InMemoryRateLimiter` and the default cache backend (`Backend\MemoryBackend`, the `CacheConfiguration` default) are both **per-request** under php-fpm (the kernel rebuilds each request), so neither actually limits across requests — a rate limiter built on them is a no-op in production. (2) More surprising: building a controller in `AppServiceProvider::routes()` with `$this->resolve(DatabaseInterface::class)` got an **ephemeral** database connection, not the persistent file DB. A `SqliteRateLimiter` wired that way created its table and wrote rows to a connection that never persisted (the table never appeared in `storage/waaseyaa.sqlite`), so the limit reset every request. The same `SqliteRateLimiter` pointed at the real file path works perfectly (verified: 12×200 then 429, rows persist). The route/controller closure appears to be built once rather than per-request, so the resolved DB is captured at build time and isn't the request-time file connection — and there's no error, the writes just go nowhere observable.
- **Workaround:** Pin the limiter to the SQLite file explicitly: `DBALDatabase::createSqlite($projectRoot.'/storage/waaseyaa.sqlite')` (honoring `WAASEYAA_DB`), independent of the container. See `AppServiceProvider::databasePath()` + `SqliteRateLimiter`.
- **Likely upstream fix:** (a) Ship a persistent rate-limiter backend (DB- or file-cache-backed) so `RateLimiterInterface` actually limits across requests; (b) clarify/guarantee that `resolve(DatabaseInterface)` returns the kernel's primary persistent connection in every phase (including route building), or document that route controllers are built once and must not capture request-scoped services. The silent "writes go to an ephemeral DB" behaviour is the dangerous part — analytics writes wired the same way in `routes()` may have the same latent issue and deserve a check.
- **Update (2026-05-31):** The analytics check is done — **same bug, confirmed live.** `CollectController`/`AnalyticsRecorder` and `AnalyticsReport` were wired in `routes()` via `resolve(DatabaseInterface)` (`tryResolveDatabase()`), and `boot()` ensured the schema the same way. A valid (non-bot UA) `POST /api/collect` returned `204` but persisted **0 rows** to `storage/waaseyaa.sqlite` — no `-wal`/`-shm` file was even created, so the resolved connection never touched the file. (Watch the bot filter when reproducing: a default `curl` User-Agent matches `AnalyticsRecorder::BOT_PATTERN` and is dropped before any write, which masks the DB issue.) Fixed identically to the limiter: a memoised `AppServiceProvider::persistentDatabase()` (`DBALDatabase::createSqlite($this->databasePath())`) now backs the schema-ensure, the recorder, the report, and the rate limiter; `tryResolveDatabase()` is kept only as a "kernel present?" gate. After the fix the same beacons persist and the dashboard reads them. Regression test: `tests/Integration/AnalyticsPersistenceTest.php` (write on one file connection, read on a separate one).

## 019 — `AnthropicProvider::httpPostStreaming()` swallows cURL transport errors; a failed stream looks like an empty (successful) answer

- **Date / version:** 2026-05-31 · waaseyaa/framework alpha.188 · `ai-agent/src/Provider/AnthropicProvider.php`
- **Doing:** Bringing the grounded `/api/chat` online with a real key for the demo. Retrieval, provider selection, sources, and the off-corpus refusal all worked, but every grounded question streamed back **zero text** — a `done` event with correct `sources` but no `delta`s ("(no text)").
- **Symptom:** The streaming path silently produced an empty `MessageResponse`. The non-streaming `httpPost()` (line ~192) captures `curl_exec()`'s return and throws `TransportException` when it's `false`; but `httpPostStreaming()` (line ~282) calls `\curl_exec($ch);` and **discards the return**, then only branches on `curl_getinfo(...HTTP_CODE)`. On a transport-level failure cURL never receives an HTTP status, so `$httpCode === 0`, which is **not** `>= 400` → no exception, no chunks, empty response. The controller's `try/catch` therefore never fires (it would have emitted `NO_ANSWER`), and the `done` event ships with empty content. Root cause here was local: PHP cURL on the Windows dev box had **no CA bundle** (`curl.cainfo`/`openssl.cafile` unset), so the TLS handshake to `api.anthropic.com` failed with errno 60 (`unable to get local issuer certificate`). The shell `curl` worked (Git's bundled CA), which masked it; and the non-streaming probe would have surfaced it as an exception — only the streaming path hid it.
- **Workaround:** (dev-env) Point PHP at a CA bundle — copied Git's `ca-bundle.crt` to `C:\tools\php85\extras\ssl\cacert.pem` and set `curl.cainfo` + `openssl.cafile` in `php.ini`. After that, streaming yields `text_delta`s normally (verified: housing/business/per-capita stream grounded, cited answers; off-corpus refuses). The Pi's Docker image already has system CA certs, so prod is unaffected.
- **Likely upstream fix:** In `httpPostStreaming()`, capture `$ok = \curl_exec($ch)` and, mirroring `httpPost()`, throw `TransportException` on `$ok === false` / `\curl_errno($ch) !== 0` (and ideally when `$httpCode === 0`) — so a broken stream surfaces as an error instead of an empty-but-"successful" answer. A silent empty completion is the dangerous part: callers can't tell "model returned nothing" from "the request never reached Anthropic."

## 020 — A `getEntityType()` getter on a ContentEntityBase subclass collides with `TranslatableEntityTrait::getEntityType()` (non-ignorable phpstan error)

- **Date / version:** 2026-06-01 · waaseyaa/framework alpha.188 · `entity` package
- **Doing:** Building the Anokii graph. Added a `doc_chunk` field `entity_type` (the kind of source entity a chunk links to) and, by reflex, a matching getter `getEntityType(): string`.
- **Symptom:** PHPStan level 5 fails with a **non-ignorable** error: *"Return type string of method `App\Entity\DocChunk::getEntityType()` is not covariant with return type `Waaseyaa\Entity\EntityTypeInterface` of method `Waaseyaa\Entity\TranslatableEntityTrait::getEntityType()`."* `ContentEntityBase` mixes in `TranslatableEntityTrait`, which already defines `getEntityType(): EntityTypeInterface` — so `getEntityType` is effectively a reserved accessor on every content entity, and a field literally named `entity_type` can't have the obvious getter. (Field accessors that don't collide, e.g. `getSourceUrl()`, are fine.)
- **Workaround:** Don't add the colliding getter. We read the field via the value bag where needed (`get('entity_type')`) and via raw SQL `_data` in the retriever, so no getter was required at all; the typed `#[Field] public string $entity_type` property is enough for field discovery. If a getter is genuinely needed, name it something non-colliding (e.g. `getSourceEntityType()`).
- **Likely upstream fix:** Document the reserved entity accessors (`getEntityType`, `getEntityTypeId`, etc.) that subclasses must not shadow, or rename the trait's accessor (e.g. `entityTypeDefinition()`) so the natural `getEntityType()` name is free for a field. At minimum, a clearer error/hint would help — the collision is surprising when you're just adding a field called `entity_type`.
