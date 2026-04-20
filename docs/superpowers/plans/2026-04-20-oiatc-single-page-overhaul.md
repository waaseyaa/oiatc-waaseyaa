# OIATC Single-Page Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace oiatc.ca with a single honest homepage, upgrade Waaseyaa to the latest alpha, and add SEO fundamentals (JSON-LD, sitemap, robots).

**Architecture:** The site already uses Waaseyaa's routing + SSR + Twig stack cleanly. No entities, no Access policies, no queue jobs; the only concrete Waaseyaa touchpoint is `SiteServiceProvider::routes()` + a thin `PageController`. The overhaul collapses 7 routes into 1 public route with 301 redirects from the old paths, trims the template set to `base.html.twig` + `home.html.twig` + `404.html.twig`, and adds JSON-LD plus a tiny sitemap in `base.html.twig` and `public/`. The Waaseyaa bump is `alpha.101-103` → `alpha.151` (current `^0.1` constraint already permits this).

**Tech Stack:** PHP 8.4, Waaseyaa framework, Twig, Symfony HttpFoundation. Plain CSS (no build). No tests exist in this project, and the deltas are content + config; I'll verify via `php -S` smoke test rather than add a phpunit suite just for this.

**Decisions flagged:**

- **Old routes: 301 to `/` (not 410).** The site has not been widely indexed and the new single page is the replacement for *everything* previously served. 301 preserves whatever link equity exists and matches user expectation ("I clicked /about, I want the org info"). 410 would be right if the content was permanently meaningful *and* we didn't want users to see any replacement, which isn't the case here.
- **Cut content archive location.** The brief says the cut pages are preserved at `C:\Users\jones\Projects\OIATC\oiatc-cut-content.md` (Russell's local Windows path). That file isn't reachable from this WSL repo. I will additionally copy the deleted templates into `docs/archive/2026-04-20-cut-pages/` inside this repo as a version-controlled safety net, so nothing is lost even if the local Windows file moves.
- **Web Networks logo placement.** The existing `partner-card` component + `/images/partners/web-networks-logo.svg` asset are already in use. The new copy has a "council" section naming Web Networks. I'll render that member using the existing partner-card markup so the logo appears with the Web Networks paragraph, and the Russell paragraph uses a matching card (no portrait in the new copy's wording, so no image for him on the single page).
- **"We are looking for" list.** Plain `<ul>` directly under the paragraph, no card grid. The brief's tone is early and honest; a bulleted list matches.
- **No visual redesign.** Existing CSS tokens/components are reused. Only template markup changes.

---

## File Structure

**Modify:**
- `composer.lock` — via `composer update waaseyaa/*`
- `src/Provider/SiteServiceProvider.php` — collapse to one content route + redirect route
- `src/Controller/PageController.php` — keep `home`, `notFound`, and add `redirectToHome`; delete the rest
- `templates/home.html.twig` — full rewrite to the new copy
- `templates/base.html.twig` — remove nav to deleted pages, add JSON-LD + richer OG/Twitter tags

**Create:**
- `docs/archive/2026-04-20-cut-pages/` — copies of deleted templates
- `public/robots.txt`
- `public/sitemap.xml`

**Delete:**
- `templates/about.html.twig`
- `templates/waaseyaa.html.twig`
- `templates/minoo.html.twig`
- `templates/grants.html.twig`
- `templates/contact.html.twig`
- `templates/founding-charter.html.twig`
- `templates/page.html.twig` (stale "Hello Waaseyaa" stub, unused)

---

## Task 1: Archive Cut Content

**Files:**
- Create: `docs/archive/2026-04-20-cut-pages/README.md`
- Copy: all six deleted twig files into `docs/archive/2026-04-20-cut-pages/`

- [ ] **Step 1: Create archive directory and copy the templates**

```bash
mkdir -p docs/archive/2026-04-20-cut-pages
cp templates/about.html.twig \
   templates/waaseyaa.html.twig \
   templates/minoo.html.twig \
   templates/grants.html.twig \
   templates/contact.html.twig \
   templates/founding-charter.html.twig \
   docs/archive/2026-04-20-cut-pages/
```

- [ ] **Step 2: Add README explaining the archive**

Write `docs/archive/2026-04-20-cut-pages/README.md`:

```markdown
# Cut pages (2026-04-20)

These templates were removed when OIATC.ca collapsed to a single-page site.
Kept here in git history as a safety net. Not rendered.

See commit that introduced the single-page site for context.
```

- [ ] **Step 3: Commit**

```bash
git add docs/archive/2026-04-20-cut-pages
git commit -m "chore: archive cut pages before single-page collapse"
```

---

## Task 2: Upgrade Waaseyaa Framework

**Files:**
- Modify: `composer.lock`

- [ ] **Step 1: Verify current vs latest**

Run: `composer show 'waaseyaa/*' | awk '{print $1, $2}'`

Then compare against `composer show -a waaseyaa/foundation | grep '^versions'` — confirm latest is `v0.1.0-alpha.151` or newer. If any installed package is already at the latest, note it and skip.

- [ ] **Step 2: Run the update**

Run: `composer update 'waaseyaa/*' --with-all-dependencies`

Expected: packages bump from alpha.101-103 to alpha.151-range. If composer reports a conflict that requires bumping `composer.json` constraints (e.g. Waaseyaa introduced `^0.2`), STOP and summarize rather than edit constraints blind.

- [ ] **Step 3: Run the audit-site preflight**

Run: `./bin/waaseyaa-audit-site` if present, otherwise `composer validate`.

Expected: no drift errors. If the audit surfaces provenance/golden-SHA drift, note it and continue — that's a deploy-time concern, not a code concern.

- [ ] **Step 4: Grep for removed Waaseyaa APIs**

Run these checks in the project source:

```bash
grep -rn 'Waaseyaa\\SSR\\SsrResponse' src/ templates/
grep -rn 'Waaseyaa\\Routing\\WaaseyaaRouter\|RouteBuilder' src/
grep -rn 'Waaseyaa\\Foundation\\ServiceProvider' src/
```

Expected: each symbol still resolvable. If any was renamed/moved in the newer alphas, adjust imports in the affected file(s) in this step. If a rename implies semantic change beyond a namespace move, STOP and summarize.

- [ ] **Step 5: Commit**

```bash
git add composer.lock
git commit -m "chore: upgrade waaseyaa/* to latest v0.1 alphas"
```

---

## Task 3: Replace Home Template

**Files:**
- Modify: `templates/home.html.twig`

- [ ] **Step 1: Rewrite home.html.twig**

Replace the entire file with the new single-page content below. Note: uses existing CSS classes (`section`, `page-header`, `eyebrow`, `card`, `partner-card`, `cta-row`, `button`, `reveal`) so no CSS changes are needed.

```twig
{% extends 'base.html.twig' %}

{% block title %}Ontario Indigenous AI & Technology Council{% endblock %}

{% block description %}A council of two working to improve the digital systems that deliver services to First Nations. Governance framework, Waaseyaa technical framework, and the Minoo community platform.{% endblock %}

{% block og_title %}Ontario Indigenous AI & Technology Council{% endblock %}
{% block og_description %}A council of two working to improve the digital systems that deliver services to First Nations.{% endblock %}

{% block content %}
  <section class="page-header">
    <div class="eyebrow">Council</div>
    <h1>Ontario Indigenous AI &amp; Technology Council</h1>
    <p>A council of two, working to improve the digital systems that deliver services to First Nations.</p>
  </section>

  <section class="section">
    <div class="section-title reveal">
      <div class="eyebrow">Why we exist</div>
      <h2>The systems meant to serve First Nations are rarely chosen by them.</h2>
    </div>
    <p class="section-copy">
      First Nations are routinely handed digital systems, case management tools, data platforms, AI, that were chosen by vendors or administrators without the communities those systems are meant to serve. The result is software that doesn't fit, data that flows to the wrong places, and services that suffer for it.
    </p>
    <p class="section-copy">
      OIATC exists to change that. We are authoring a governance framework for Indigenous-led digital decisions and stewarding the platforms and practices that put it into use.
    </p>
  </section>

  <section class="section">
    <div class="section-title reveal">
      <div class="eyebrow">The council</div>
      <h2>Two members today.</h2>
    </div>

    <article class="card partner-card reveal">
      <div class="partner-card__body">
        <h3>Russell Jones</h3>
        <p class="partner-card__meta">Ojibwe from Sagamok Anishnawbek · Full-stack developer</p>
        <p>
          In 2023 Russell was contracted to implement a case management system at a First Nations Child and Family Advocacy Unit, saw within weeks that it would not fit the community, and made the case for a Canadian-built alternative already in use at neighbouring Nations. The recommendation was shelved and the contract ended. OIATC is how that lesson becomes a practice.
        </p>
      </div>
    </article>

    <article class="card partner-card reveal reveal--d1">
      <div class="partner-card__logo-wrap">
        <img src="/images/partners/web-networks-logo.svg" alt="Web Networks logo" class="partner-card__logo">
      </div>
      <div class="partner-card__body">
        <h3><a href="https://web.net" target="_blank" rel="noopener">Web Networks</a></h3>
        <p class="partner-card__meta">Founded 1987 · One of Canada's first ISPs · Non-profit worker co-op · All infrastructure on Canadian soil</p>
        <p>
          A non-profit worker co-op whose clients have included the Legislative Assembly of Nunavut and Nunavut Public Library Services. Web Networks provides OIATC's hosting foundation and sits on the council as its second member.
        </p>
      </div>
    </article>
  </section>

  <section class="section section--surface">
    <div class="section-title reveal">
      <div class="eyebrow">What we're building</div>
      <h2>One governance framework. Two platforms.</h2>
    </div>
    <div class="grid-3">
      <article class="pillar reveal reveal--d1">
        <span class="label">Governance</span>
        <h3>Governance framework</h3>
        <p>A framework for how First Nations can evaluate, govern, and steward the digital systems that run community services. Protocol before product. Consent before architecture.</p>
      </article>
      <article class="pillar reveal reveal--d2">
        <span class="label">Platform</span>
        <h3>Waaseyaa</h3>
        <p><a href="https://waaseyaa.org" target="_blank" rel="noopener">waaseyaa.org</a>. A modular system where governance and access control are structural, not bolted on.</p>
      </article>
      <article class="pillar reveal reveal--d3">
        <span class="label">Community</span>
        <h3>Minoo</h3>
        <p><a href="https://minoo.live" target="_blank" rel="noopener">minoo.live</a>. The first platform built on Waaseyaa, for Indigenous languages, teachings, and community continuity.</p>
      </article>
    </div>
  </section>

  <section class="section">
    <div class="section-title reveal">
      <div class="eyebrow">Where we are</div>
      <h2>Early. We are not pretending otherwise.</h2>
    </div>
    <p class="section-copy">
      OIATC is early. The council has two members today. The framework is in active drafting. The platforms are in active build.
    </p>
    <p class="section-copy">We are looking for:</p>
    <ul class="looking-for">
      <li>A Knowledge Keeper or Elder willing to sit on the council</li>
      <li>A Nation interested in being a first partner</li>
      <li>A legal or policy advisor with experience in Indigenous data governance</li>
    </ul>
  </section>

  <section class="section section--final-cta">
    <div class="section-title reveal">
      <div class="eyebrow">Contact</div>
      <h2>jonesrussell42@gmail.com</h2>
    </div>
    <div class="cta-row reveal reveal--d1">
      <a class="button" href="mailto:jonesrussell42@gmail.com">Email Russell</a>
    </div>
  </section>
{% endblock %}
```

- [ ] **Step 2: Add minimal CSS for the `.looking-for` list**

Append to `public/css/site.css` (just a spacing/typography tweak so the bullets don't look raw):

```css
/* Single-page homepage "looking for" list */
.looking-for {
  list-style: disc;
  padding-left: 1.25rem;
  margin: 0.5rem 0 0;
}
.looking-for li + li { margin-top: 0.35rem; }
```

- [ ] **Step 3: Render check**

Run: `php -l templates/home.html.twig` (well, twig isn't PHP — skip this; instead render via step 7 below). Mentally verify Twig syntax: all `{% block %}`/`{% endblock %}` paired, no unresolved variables beyond `path` (inherited from base).

- [ ] **Step 4: Commit (after Task 4 so routes + controller match template expectations)**

Defer commit to Task 4's commit.

---

## Task 4: Collapse Routes + Controller

**Files:**
- Modify: `src/Provider/SiteServiceProvider.php`
- Modify: `src/Controller/PageController.php`
- Delete: 6 twig files

- [ ] **Step 1: Rewrite `SiteServiceProvider::routes()`**

Replace the `routes` array and loop with one content route, a set of 301 redirect routes for each old path, and the existing 404 catch-all:

```php
public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
{
    $router->addRoute(
        'page.home',
        RouteBuilder::create('/')
            ->controller('App\\Controller\\PageController::home')
            ->render()
            ->methods('GET')
            ->build(),
    );

    $legacyPaths = ['/about', '/waaseyaa', '/minoo', '/grants', '/contact', '/founding-charter'];
    foreach ($legacyPaths as $legacyPath) {
        $routeName = 'legacy.redirect' . str_replace('/', '.', $legacyPath);
        $router->addRoute(
            $routeName,
            RouteBuilder::create($legacyPath)
                ->controller('App\\Controller\\PageController::redirectToHome')
                ->methods('GET')
                ->build(),
        );
    }

    $router->addRoute(
        'page.not_found',
        RouteBuilder::create('/{path}')
            ->controller('App\\Controller\\PageController::notFound')
            ->render()
            ->methods('GET')
            ->requirement('path', '.+')
            ->build(),
    );
}
```

Note: the redirect routes deliberately omit `->render()` — they return a RedirectResponse, not SSR-rendered HTML.

- [ ] **Step 2: Rewrite `PageController`**

Replace the full file with:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\SSR\SsrResponse;

final class PageController
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function home(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return new SsrResponse($this->twig->render('home.html.twig', ['path' => '/']));
    }

    public function redirectToHome(array $params, array $query, $account, HttpRequest $request): RedirectResponse
    {
        return new RedirectResponse('/', 301);
    }

    public function notFound(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return new SsrResponse(
            $this->twig->render('404.html.twig', ['path' => $request->getPathInfo()]),
            404,
        );
    }
}
```

- [ ] **Step 3: Delete the six cut templates and the stale `page.html.twig`**

```bash
rm templates/about.html.twig \
   templates/waaseyaa.html.twig \
   templates/minoo.html.twig \
   templates/grants.html.twig \
   templates/contact.html.twig \
   templates/founding-charter.html.twig \
   templates/page.html.twig
```

(page.html.twig is a stale "Hello Waaseyaa" stub not referenced by any controller — verified via grep earlier.)

- [ ] **Step 4: Verify no remaining references**

Run:
```bash
grep -rn 'about.html.twig\|waaseyaa.html.twig\|minoo.html.twig\|grants.html.twig\|contact.html.twig\|founding-charter.html.twig\|page.html.twig' src/ templates/
grep -rn 'href="/about\|href="/waaseyaa\|href="/minoo\|href="/grants\|href="/contact\|href="/founding-charter"' templates/
```

Expected: no hits. If the second grep finds links inside `base.html.twig` or elsewhere, they will be cleaned up in Task 5.

- [ ] **Step 5: PHP lint the two edited PHP files**

Run: `php -l src/Provider/SiteServiceProvider.php && php -l src/Controller/PageController.php`

Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Commit**

```bash
git add templates/ src/ public/css/site.css
git commit -m "feat: collapse oiatc.ca to single-page site with 301s for old routes"
```

---

## Task 5: Base Template Nav + SEO

**Files:**
- Modify: `templates/base.html.twig`

- [ ] **Step 1: Simplify `<nav>`**

In `templates/base.html.twig`, replace the entire `<nav class="site-nav" ...>` block (including its two `<div class="site-nav__group">` children) with a single minimal nav that shows only Home and email:

```twig
<nav class="site-nav" id="site-nav" aria-label="Primary">
  <div class="site-nav__group">
    <a href="/" aria-current="page">Home</a>
  </div>
  <div class="site-nav__group site-nav__group--secondary">
    <a class="site-nav__action" href="mailto:jonesrussell42@gmail.com">Email</a>
  </div>
</nav>
```

Also update the existing `site-footer` block (look for `<footer class="site-footer">` in base.html.twig) to remove any links pointing to `/about`, `/waaseyaa`, `/minoo`, `/grants`, `/contact`, `/founding-charter`. Inline the footer cleanup: keep OIATC brand + email + links out to waaseyaa.org and minoo.live. If the footer has no such links, leave it alone.

- [ ] **Step 2: Update canonical and OG tags**

Inside `<head>`, just before `<link rel="icon" ...>`, add:

```twig
  <link rel="canonical" href="https://oiatc.ca{{ path }}">
  <meta property="og:site_name" content="Ontario Indigenous AI &amp; Technology Council">
  <meta property="og:locale" content="en_CA">
  <meta name="twitter:title" content="{{ block('og_title') }}">
  <meta name="twitter:description" content="{{ block('og_description') }}">
  <meta name="twitter:image" content="{{ block('og_image') }}">
```

Also change the existing `og:url` line from `https://oiatc.waaseyaa.org{{ path }}` to `https://oiatc.ca{{ path }}` (the public canonical domain is oiatc.ca; oiatc.waaseyaa.org is the staging alias).

- [ ] **Step 3: Add JSON-LD Organization + Person**

Immediately before `</head>`, add:

```twig
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "@id": "https://oiatc.ca/#organization",
        "name": "Ontario Indigenous AI & Technology Council",
        "alternateName": "OIATC",
        "url": "https://oiatc.ca/",
        "email": "jonesrussell42@gmail.com",
        "logo": "https://oiatc.ca/apple-touch-icon.png",
        "areaServed": { "@type": "AdministrativeArea", "name": "Ontario, Canada" },
        "member": [
          { "@id": "https://oiatc.ca/#russell-jones" },
          {
            "@type": "Organization",
            "name": "Web Networks",
            "url": "https://web.net",
            "foundingDate": "1987"
          }
        ]
      },
      {
        "@type": "Person",
        "@id": "https://oiatc.ca/#russell-jones",
        "name": "Russell Jones",
        "jobTitle": "Founder, OIATC",
        "affiliation": { "@id": "https://oiatc.ca/#organization" },
        "email": "jonesrussell42@gmail.com",
        "sameAs": [
          "https://northops.ca",
          "https://waaseyaa.org",
          "https://minoo.live",
          "https://github.com/jonesrussell"
        ]
      }
    ]
  }
  </script>
```

(The GitHub `sameAs` entry matches the `jonesrussell` handle visible on northops; if that's wrong the user can correct it — flagged in summary.)

- [ ] **Step 4: Render smoke test**

Run: `php -S 127.0.0.1:8123 -t public >/tmp/oiatc.log 2>&1 &` then `sleep 1 && curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8123/` → expect `200`.

Then `curl -sI http://127.0.0.1:8123/about | head -5` → expect `HTTP/1.1 301` and `Location: /`.

Then stop the server: `kill %1 2>/dev/null || pkill -f 'php -S 127.0.0.1:8123'`.

- [ ] **Step 5: Commit**

```bash
git add templates/base.html.twig
git commit -m "feat: JSON-LD, canonical, twitter meta for single-page site"
```

---

## Task 6: robots.txt + sitemap.xml

**Files:**
- Create: `public/robots.txt`
- Create: `public/sitemap.xml`

- [ ] **Step 1: Write `public/robots.txt`**

```
User-agent: *
Allow: /
Disallow: /storage/

Sitemap: https://oiatc.ca/sitemap.xml
```

- [ ] **Step 2: Write `public/sitemap.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://oiatc.ca/</loc>
    <lastmod>2026-04-20</lastmod>
    <changefreq>monthly</changefreq>
    <priority>1.0</priority>
  </url>
</urlset>
```

- [ ] **Step 3: Verify they are served as static files (not swallowed by the catch-all)**

Start `php -S` as above and `curl -sS http://127.0.0.1:8123/robots.txt` — expect the raw text (not a 404 HTML page). Same for `/sitemap.xml`.

PHP's built-in server serves existing files before routing to `index.php` by default, so this should work. If the public `index.php` front controller intercepts, add early `return false;` guards in `public/index.php` for those two paths (flag in summary — but don't change without confirming it's needed).

- [ ] **Step 4: Commit**

```bash
git add public/robots.txt public/sitemap.xml
git commit -m "feat: add robots.txt and sitemap.xml for single-page site"
```

---

## Task 7: Final Smoke Test + Summary

- [ ] **Step 1: Full smoke**

Boot `php -S 127.0.0.1:8123 -t public &`, then:

- `curl -sS http://127.0.0.1:8123/ | grep -E '(<title>|Ontario Indigenous|Russell Jones|Web Networks|jonesrussell42)'` — expect all five strings present.
- `curl -sS http://127.0.0.1:8123/ | grep -c 'application/ld+json'` — expect `1`.
- `for p in /about /waaseyaa /minoo /grants /contact /founding-charter; do printf '%s -> ' "$p"; curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "http://127.0.0.1:8123$p"; done` — expect each prints `301 /`.
- `curl -sS http://127.0.0.1:8123/robots.txt` — expect robots content.
- `curl -sS http://127.0.0.1:8123/sitemap.xml | head -1` — expect XML declaration.
- `curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8123/definitely-not-a-page` — expect `404`.

Stop server.

- [ ] **Step 2: Summarize for user**

Report:

- Waaseyaa version before → after (actual numbers).
- Redirect choice: 301 (with one-line justification).
- Files created / modified / deleted (counts + names).
- Any ambiguity flagged (Web Networks logo placement, GitHub sameAs handle, cut content archive duplication).
- Any breaking-change surprises from the upgrade. If none: say so.

---

## Self-Review

- **Spec coverage:** (1) single-page content ✓ Task 3. (2) route collapse + redirect decision ✓ Task 4. (3) framework upgrade ✓ Task 2. (4) JSON-LD Org + Person + sameAs ✓ Task 5. (5) OG + Twitter ✓ Task 5. (6) favicon — already present, verified in preflight. (7) sitemap.xml ✓ Task 6. (8) robots.txt ✓ Task 6. (9) archive of cut content ✓ Task 1. (10) anti-pattern audit — addressed in Task 2 step 4 (grep current Waaseyaa symbol usage); the project is otherwise thin, no Entity/Access/Domain code exists, so there's nothing legacy to rewrite.

- **Placeholder scan:** None. All code blocks contain the final content.

- **Type consistency:** `redirectToHome` used in both provider + controller. `SsrResponse` and `RedirectResponse` both imported. `path` var passed to home template.
