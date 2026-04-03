# OIATC MVP Tightening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tighten the OIATC prototype site into an MVP by eliminating content redundancy, expanding thin pages, restructuring navigation, and adding infrastructure (mobile nav, meta, 404, accessibility).

**Architecture:** Static Waaseyaa site with Twig templates, single CSS file, thin PageController. All changes are to templates and CSS — no entity/domain changes. Framework is Waaseyaa (Symfony-based, entity-first PHP), NOT Laravel.

**Tech Stack:** PHP 8.4, Waaseyaa framework, Twig 3, vanilla CSS, no JS build step

**Spec:** `docs/superpowers/specs/2026-04-03-mvp-tightening-design.md`

**GitHub repo:** `waaseyaa/oiatc-waaseyaa`

---

### Task 1: Create GitHub milestone and issues

**Files:**
- None (GitHub API only)

- [ ] **Step 1: Create the milestone**

```bash
gh milestone create "MVP Tightening" --repo waaseyaa/oiatc-waaseyaa --description "Prototype to MVP: nav restructure, content deduplication, page expansion, mobile nav, meta, accessibility, 404"
```

- [ ] **Step 2: Create issues**

```bash
# Get milestone number
milestone_num=$(gh milestone list --repo waaseyaa/oiatc-waaseyaa --json number,title --jq '.[] | select(.title=="MVP Tightening") | .number')

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Restructure navigation: 7 → 6 links, demote Charter to footer" \
  --label "enhancement" \
  --body "Remove Charter from top nav. Update footer links to include Waaseyaa and Minoo. Charter remains accessible from footer, home page CTA, and about page link."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Tighten home page: 8 → 6 sections" \
  --label "enhancement" \
  --body "Remove founder-band section (redundant with hero founder card). Collapse split-CTA into compact two-link band. Result: hero, why, pillars, lanes, grants+charter band, final CTA."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Remove redundant content from about page" \
  --label "enhancement" \
  --body "Remove founding principles and mandate domains cards (duplicate charter). Add charter link below purpose/relationship cards."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Expand Waaseyaa page with concrete content" \
  --label "enhancement" \
  --body "Replace 2 vague cards with substantive content: what it is, capabilities (entity system, access control, AI-native), why it matters for Indigenous governance, relationship to OIATC, link to waaseyaa.org."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Expand Minoo page with concrete content" \
  --label "enhancement" \
  --body "Replace 2 vague cards with substantive content: what it is, features (language/dictionary, teachings, community), who it serves, data sovereignty, relationship to OIATC, link to minoo.live."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Add mobile navigation hamburger toggle" \
  --label "enhancement" \
  --body "Add hamburger button visible below 768px. Toggle nav open/close with aria-expanded. Stack links vertically. Small inline script in base template."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Add head meta: OG tags, skip-to-content, favicon prep" \
  --label "enhancement" \
  --body "Add Open Graph meta tags with per-page Twig block overrides. Add skip-to-content link. Add favicon link tags (favicon files generated separately)."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Add 404 error page" \
  --label "enhancement" \
  --body "Create 404.html.twig template extending base. Simple page-header with message and home link. Wire up in PageController as notFound method."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Add general inquiry fallback to contact page" \
  --label "enhancement" \
  --body "Add fifth contact article for general inquiries (info@oiatc.ca). Adjust contact-grid CSS for 5 items."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Generate favicon and OG image assets" \
  --label "enhancement" \
  --body "Generate favicon.svg, favicon.ico (32x32), apple-touch-icon.png (180x180) from OIATC brand mark. Generate default OG image (1200x630)."

gh issue create --repo waaseyaa/oiatc-waaseyaa --milestone "$milestone_num" \
  --title "Clean up removed CSS" \
  --label "chore" \
  --body "Remove .founder-band styles after home page tightening. Clean up any orphaned selectors."
```

- [ ] **Step 3: Verify issues created**

```bash
gh issue list --repo waaseyaa/oiatc-waaseyaa --milestone "MVP Tightening" --state open
```

Expected: 11 open issues under the MVP Tightening milestone.

---

### Task 2: Restructure navigation and footer

**Files:**
- Modify: `templates/base.html.twig`

**Closes:** GitHub issue "Restructure navigation: 7 → 6 links, demote Charter to footer"

- [ ] **Step 1: Remove Charter from nav**

In `templates/base.html.twig`, replace the secondary nav group:

```html
          <div class="site-nav__group site-nav__group--secondary">
            <a href="/grants" {% if path == '/grants' %}aria-current="page"{% endif %}>Grants</a>
            <a href="/founding-charter" {% if path == '/founding-charter' %}aria-current="page"{% endif %}>Charter</a>
            <a class="site-nav__action" href="/contact" {% if path == '/contact' %}aria-current="page"{% endif %}>Contact</a>
          </div>
```

with:

```html
          <div class="site-nav__group site-nav__group--secondary">
            <a href="/grants" {% if path == '/grants' %}aria-current="page"{% endif %}>Grants</a>
            <a class="site-nav__action" href="/contact" {% if path == '/contact' %}aria-current="page"{% endif %}>Contact</a>
          </div>
```

- [ ] **Step 2: Update footer links**

In `templates/base.html.twig`, replace the footer links:

```html
        <div class="site-footer__links">
          <a href="/about">About OIATC</a>
          <a href="/founding-charter">Founding Charter</a>
          <a href="/grants">Grants &amp; Funding</a>
          <a href="/contact">Contact / Partner</a>
        </div>
```

with:

```html
        <div class="site-footer__links">
          <a href="/about">About</a>
          <a href="/waaseyaa">Waaseyaa</a>
          <a href="/minoo">Minoo</a>
          <a href="/grants">Grants</a>
          <a href="/founding-charter">Charter</a>
          <a href="/contact">Contact</a>
        </div>
```

- [ ] **Step 3: Verify in browser**

```bash
php -S localhost:8080 -t public &
sleep 1
curl -s http://localhost:8080/ | grep -c 'founding-charter'
# Expected: appears in footer and home page CTAs, NOT in nav
kill %1
```

- [ ] **Step 4: Commit**

```bash
git add templates/base.html.twig
git commit -m "feat: restructure nav from 7 to 6 links, demote Charter to footer

Closes #<nav-issue-number>"
```

---

### Task 3: Tighten home page

**Files:**
- Modify: `templates/home.html.twig`
- Modify: `public/css/site.css`

**Closes:** GitHub issue "Tighten home page: 8 → 6 sections" and "Clean up removed CSS"

- [ ] **Step 1: Remove founder-band section**

In `templates/home.html.twig`, delete the entire founder-band section (lines 104–118):

```html
  <section class="section founder-band">
    <div class="founder-band__content">
      <div class="eyebrow">Founding context</div>
      <h2>Built from the conviction that sovereignty must extend into digital space.</h2>
      <p>
        OIATC is being shaped in Ontario by Indigenous-led technical work that refuses black-box systems, extractive data practices, and governance that arrives after deployment.
      </p>
    </div>
    <div class="founder-band__note">
      <p>
        Russell Jones is Ojibwe from Sagamok Anishnawbek and is grounding the Council around governance, platform stewardship, and long-term institutional continuity.
      </p>
      <a class="text-link text-link--light" href="/about">View founder profile</a>
    </div>
  </section>
```

- [ ] **Step 2: Replace split-CTA with compact band**

In `templates/home.html.twig`, replace the split-cta section:

```html
  <section class="section split-cta">
    <article class="card card--feature">
      <div class="eyebrow">Funding and readiness</div>
      <h3>Building with grant discipline, not grant dependency.</h3>
      <p>
        OIATC is aligning platform development, training, governance research, and capital needs against federal and provincial funding programs that fit Indigenous-led institution building.
      </p>
      <div class="cta-row">
        <a class="button-secondary" href="/grants">View grants and funding priorities</a>
      </div>
    </article>
    <article class="card">
      <div class="eyebrow">Founding charter</div>
      <h3>Protocols are the architecture of the future.</h3>
      <p>
        The founding charter defines the Council's purpose, invariants, mandate, structure, and relationship to Nations. Founding-circle membership can remain structurally described until public naming is approved.
      </p>
      <div class="cta-row">
        <a class="button-secondary" href="/founding-charter">Read the charter</a>
      </div>
    </article>
  </section>
```

with:

```html
  <section class="section section--band">
    <div class="cta-band">
      <a class="button-secondary" href="/grants">Grants &amp; funding priorities</a>
      <a class="button-secondary" href="/founding-charter">Read the founding charter</a>
    </div>
  </section>
```

- [ ] **Step 3: Remove founder-band CSS**

In `public/css/site.css`, delete the `.founder-band` block (lines 582–612):

```css
.founder-band {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(18rem, 0.85fr);
    gap: 1.5rem;
    padding: 2rem;
    border-radius: calc(var(--radius) + 8px);
    background:
        linear-gradient(118deg, rgba(8, 25, 15, 0.98), rgba(15, 43, 31, 0.95) 42%, rgba(24, 122, 108, 0.7) 100%);
    color: var(--cream);
    box-shadow: var(--shadow);
}

.founder-band .eyebrow {
    color: rgba(247, 240, 228, 0.72);
}

.founder-band h2 {
    font-size: clamp(2.1rem, 3.5vw, 3.5rem);
    margin-top: 0.45rem;
}

.founder-band__content p,
.founder-band__note p {
    color: rgba(247, 240, 228, 0.88);
}

.founder-band__note {
    align-self: end;
    padding: 1.2rem 0 0 1.4rem;
    border-left: 1px solid rgba(247, 240, 228, 0.18);
}
```

- [ ] **Step 4: Remove `.founder-band` from shared selectors**

In `public/css/site.css`, remove `.founder-band h2` from the shared heading selector (line 243):

Change:
```css
.hero h1,
.page-header h1,
.founder-profile h2,
.founder-card h2,
.section-title h2,
.founder-band h2 {
```
to:
```css
.hero h1,
.page-header h1,
.founder-profile h2,
.founder-card h2,
.section-title h2 {
```

Remove `.founder-band p` from the shared paragraph selector (line 271):

Change:
```css
.founder-band p,
```
to nothing (delete the line).

Remove `.founder-band` from the 1024px responsive rule (lines 814–817):

```css
    .founder-band__note {
        border-left: 0;
        border-top: 1px solid rgba(247, 240, 228, 0.18);
        padding: 1.2rem 0 0;
    }
```

Remove `.founder-band` from the 640px responsive rules — delete from the border-radius selector and the padding selector.

- [ ] **Step 5: Replace split-cta CSS with cta-band**

In `public/css/site.css`, replace:

```css
.split-cta {
    grid-template-columns: 1.1fr 0.9fr;
}
```

with:

```css
.cta-band {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 1rem;
}
```

Remove `.split-cta` from the 1024px responsive rule (line 802):

```css
    .hero__grid,
    .founder-band,
    .split-cta {
        grid-template-columns: 1fr;
    }
```

becomes:

```css
    .hero__grid {
        grid-template-columns: 1fr;
    }
```

Remove `.split-cta` from the shared grid declaration (line 495):

```css
.statement-grid,
.grid-3,
.grid-2,
.contact-grid,
.split-cta {
    display: grid;
    gap: 1rem;
}
```

becomes:

```css
.statement-grid,
.grid-3,
.grid-2,
.contact-grid {
    display: grid;
    gap: 1rem;
}
```

- [ ] **Step 6: Add section--band style**

In `public/css/site.css`, add after the `.section--final-cta` rule:

```css
.section--band {
    text-align: center;
}
```

- [ ] **Step 7: Verify in browser**

```bash
php -S localhost:8080 -t public &
sleep 1
curl -s http://localhost:8080/ | grep -c 'founder-band'
# Expected: 0
curl -s http://localhost:8080/ | grep -c 'cta-band'
# Expected: 1
kill %1
```

- [ ] **Step 8: Commit**

```bash
git add templates/home.html.twig public/css/site.css
git commit -m "feat: tighten home page from 8 to 6 sections

Remove redundant founder-band. Collapse split-CTA into compact
cta-band. Clean up orphaned CSS selectors.

Closes #<home-issue-number>
Closes #<css-cleanup-issue-number>"
```

---

### Task 4: Clean up about page

**Files:**
- Modify: `templates/about.html.twig`

**Closes:** GitHub issue "Remove redundant content from about page"

- [ ] **Step 1: Remove principles and mandate cards, add charter link**

In `templates/about.html.twig`, delete the second `grid-2` section (lines 47–67):

```html
  <section class="section grid-2">
    <article class="card">
      <h3>Founding principles</h3>
      <ul class="list">
        <li>Indigenous sovereignty first</li>
        <li>Technology must serve the people</li>
        <li>Protocols over products</li>
        <li>Transparency and accountability</li>
        <li>No parallel governance paths</li>
      </ul>
    </article>
    <article class="card">
      <h3>Mandate domains</h3>
      <ul class="list">
        <li>AI governance and sovereignty</li>
        <li>Digital skills and workforce development</li>
        <li>Research and policy</li>
        <li>Platform ecosystem stewardship</li>
      </ul>
    </article>
  </section>
```

And add a charter reference after the purpose/relationship section. After the closing `</section>` of the `grid-2` with Purpose and Relationship cards (line 45), add:

```html
  <section class="section">
    <p class="section-copy">Read the <a href="/founding-charter" class="text-link">founding charter</a> for principles, mandate, and governance commitments.</p>
  </section>
```

- [ ] **Step 2: Verify in browser**

```bash
php -S localhost:8080 -t public &
sleep 1
curl -s http://localhost:8080/about | grep -c 'Founding principles'
# Expected: 0
curl -s http://localhost:8080/about | grep -c 'founding-charter'
# Expected: 1 (the link)
kill %1
```

- [ ] **Step 3: Commit**

```bash
git add templates/about.html.twig
git commit -m "feat: remove redundant principles/mandate from about page

Content lives canonically in the founding charter. About page
now links there instead of duplicating.

Closes #<about-issue-number>"
```

---

### Task 5: Expand Waaseyaa page

**Files:**
- Modify: `templates/waaseyaa.html.twig`

**Closes:** GitHub issue "Expand Waaseyaa page with concrete content"

- [ ] **Step 1: Replace waaseyaa.html.twig content**

Replace the entire `{% block content %}` in `templates/waaseyaa.html.twig` with:

```html
{% block content %}
  <section class="page-header">
    <div class="eyebrow">Governance platform</div>
    <h1>Waaseyaa</h1>
    <p>An entity-first, AI-native framework that encodes governance, protocol, and accountability into working infrastructure.</p>
  </section>

  <section class="section">
    <div class="section-title">
      <div class="eyebrow">What it is</div>
      <h2>A framework built for sovereignty, not bolted onto it.</h2>
    </div>
    <p class="section-copy">
      Waaseyaa is a modular PHP framework built on Symfony components. It replaces legacy CMS architecture with a clean, seven-layer system designed for platforms where data sovereignty, access control, and cultural protocol are structural requirements rather than afterthoughts. It powers this site and <a href="/minoo" class="text-link">Minoo</a>.
    </p>
  </section>

  <section class="section">
    <div class="section-title">
      <div class="eyebrow">Capabilities</div>
      <h2>What Waaseyaa provides.</h2>
    </div>
    <div class="grid-3">
      <article class="card">
        <h3>Entity &amp; relationship system</h3>
        <p>Typed entities with temporal boundaries, directionality, and qualifiers. Model governance hierarchies, knowledge lineages, clan structures, and protocol relationships as first-class data.</p>
      </article>
      <article class="card">
        <h3>Deny-by-default access control</h3>
        <p>Entity-level and field-level policies. Tiered disclosure: public knowledge versus restricted protocols. Every access decision is explicit and auditable.</p>
      </article>
      <article class="card">
        <h3>AI-native architecture</h3>
        <p>Four dedicated AI packages for schema, agents, pipelines, and vector search. AI agents operate as governed users with full audit trails inside the same access boundaries as human users.</p>
      </article>
    </div>
  </section>

  <section class="section">
    <div class="section-title">
      <div class="eyebrow">Why it matters</div>
      <h2>Built for Indigenous governance.</h2>
    </div>
    <div class="grid-2">
      <article class="card">
        <h3>Data sovereignty by design</h3>
        <p>Granular control over who accesses what, at field and relationship level. No data leaves without explicit policy. Communities govern their own information architecture.</p>
      </article>
      <article class="card">
        <h3>Protocol encoded in infrastructure</h3>
        <p>Temporal bounds, consent tracking, and relationship directionality capture real governance rules. Seasonal protocols, initiation sequences, and knowledge restrictions are modeled structurally rather than managed informally.</p>
      </article>
    </div>
  </section>

  <section class="section">
    <p class="section-copy">
      Waaseyaa is one of the Council's principal technical expressions. OIATC governs the platform's direction; Waaseyaa encodes the Council's governance standards into deployable infrastructure.
    </p>
    <div class="cta-row">
      <a class="button" href="https://waaseyaa.org" target="_blank" rel="noopener">Technical documentation at waaseyaa.org</a>
    </div>
  </section>
{% endblock %}
```

- [ ] **Step 2: Verify in browser**

```bash
php -S localhost:8080 -t public &
sleep 1
curl -s http://localhost:8080/waaseyaa | grep -c 'entity-first'
# Expected: 1
curl -s http://localhost:8080/waaseyaa | grep -c 'waaseyaa.org'
# Expected: 1
kill %1
```

- [ ] **Step 3: Commit**

```bash
git add templates/waaseyaa.html.twig
git commit -m "feat: expand Waaseyaa page with concrete content

Replace 2 vague cards with substantive sections covering
capabilities, governance relevance, and link to waaseyaa.org.

Closes #<waaseyaa-issue-number>"
```

---

### Task 6: Expand Minoo page

**Files:**
- Modify: `templates/minoo.html.twig`

**Closes:** GitHub issue "Expand Minoo page with concrete content"

- [ ] **Step 1: Replace minoo.html.twig content**

Replace the entire `{% block content %}` in `templates/minoo.html.twig` with:

```html
{% block content %}
  <section class="page-header">
    <div class="eyebrow">Community platform</div>
    <h1>Minoo</h1>
    <p>An Indigenous knowledge and community platform for language, teachings, and cultural continuity.</p>
  </section>

  <section class="section">
    <div class="section-title">
      <div class="eyebrow">What it is</div>
      <h2>A platform that centers community knowledge.</h2>
    </div>
    <p class="section-copy">
      Minoo makes Indigenous languages, teachings, histories, and cultural knowledge accessible, searchable, and engaging. It centers Elder and Knowledge Keeper voices, maintains data sovereignty, and supports interactive learning through culturally grounded design. Built on <a href="/waaseyaa" class="text-link">Waaseyaa</a>.
    </p>
  </section>

  <section class="section">
    <div class="section-title">
      <div class="eyebrow">What people use it for</div>
      <h2>Language, teachings, and community.</h2>
    </div>
    <div class="grid-3">
      <article class="card">
        <h3>Language &amp; dictionary</h3>
        <p>Searchable dictionary with dialect support, example sentences, word parts, pronunciation, and consent-tracked speakers. Anishinaabemowin localization underway.</p>
      </article>
      <article class="card">
        <h3>Teachings &amp; oral histories</h3>
        <p>Cultural teachings archive with regional and dialect variants. Collections for oral histories and community knowledge preserved on community terms.</p>
      </article>
      <article class="card">
        <h3>Community &amp; connection</h3>
        <p>Social feed, private messaging, community groups, events, and contributor directories. Map-based discovery for nearby content and communities.</p>
      </article>
    </div>
  </section>

  <section class="section">
    <div class="section-title">
      <div class="eyebrow">Who it serves</div>
      <h2>Built for the people who carry knowledge forward.</h2>
    </div>
    <div class="grid-2">
      <article class="card">
        <h3>Elders &amp; Knowledge Keepers</h3>
        <p>Dedicated interface for content oversight and cultural authority. Elder voices are centered in design and governance.</p>
      </article>
      <article class="card">
        <h3>Language learners</h3>
        <p>Dictionary search, gamified learning with word games and crosswords, and daily challenges that make language practice engaging and accessible.</p>
      </article>
      <article class="card">
        <h3>Community members</h3>
        <p>Social features, events, and group participation. Stay connected with community life and contribute to shared knowledge.</p>
      </article>
      <article class="card">
        <h3>Volunteers &amp; coordinators</h3>
        <p>Self-service signup, elder support workflows, and community management dashboards for people doing the organizing work.</p>
      </article>
    </div>
  </section>

  <section class="section">
    <div class="section-title">
      <div class="eyebrow">Data sovereignty</div>
      <h2>Community data stays under community governance.</h2>
    </div>
    <p class="section-copy">
      Consent-tracked speakers. Creative Commons licensing. Deny-by-default access control inherited from Waaseyaa. No data leaves without explicit policy. OIATC treats Minoo as part of the same stewardship architecture as Waaseyaa: different public role, same sovereignty-first foundation.
    </p>
    <div class="cta-row">
      <a class="button" href="https://minoo.live" target="_blank" rel="noopener">Explore Minoo at minoo.live</a>
    </div>
  </section>
{% endblock %}
```

- [ ] **Step 2: Verify in browser**

```bash
php -S localhost:8080 -t public &
sleep 1
curl -s http://localhost:8080/minoo | grep -c 'dictionary'
# Expected: 1
curl -s http://localhost:8080/minoo | grep -c 'minoo.live'
# Expected: 1
kill %1
```

- [ ] **Step 3: Commit**

```bash
git add templates/minoo.html.twig
git commit -m "feat: expand Minoo page with concrete content

Replace 2 vague cards with substantive sections covering features,
audiences, data sovereignty, and link to minoo.live.

Closes #<minoo-issue-number>"
```

---

### Task 7: Add mobile navigation

**Files:**
- Modify: `templates/base.html.twig`
- Modify: `public/css/site.css`

**Closes:** GitHub issue "Add mobile navigation hamburger toggle"

- [ ] **Step 1: Add toggle button and inline script to base template**

In `templates/base.html.twig`, add the toggle button inside `.site-header__inner`, just before the `<nav>`:

```html
        <button class="site-nav__toggle" aria-expanded="false" aria-controls="site-nav" aria-label="Menu">
          <span class="site-nav__toggle-bar"></span>
          <span class="site-nav__toggle-bar"></span>
          <span class="site-nav__toggle-bar"></span>
        </button>
```

Add `id="site-nav"` to the `<nav>` element:

```html
        <nav class="site-nav" id="site-nav" aria-label="Primary">
```

Add inline script before `</body>`:

```html
    <script>
    (function() {
      var toggle = document.querySelector('.site-nav__toggle');
      var nav = document.getElementById('site-nav');
      if (!toggle || !nav) return;
      toggle.addEventListener('click', function() {
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        nav.classList.toggle('is-open');
      });
      nav.addEventListener('click', function(e) {
        if (e.target.tagName === 'A') {
          toggle.setAttribute('aria-expanded', 'false');
          nav.classList.remove('is-open');
        }
      });
    })();
    </script>
```

- [ ] **Step 2: Add mobile nav CSS**

In `public/css/site.css`, add the toggle button styles after the `.site-nav__action` rule (after line 173):

```css
.site-nav__toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 2.6rem;
    height: 2.6rem;
    padding: 0.5rem;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: rgba(255, 251, 245, 0.6);
    cursor: pointer;
    transition: background-color 0.18s ease;
}

.site-nav__toggle:hover {
    background: rgba(255, 251, 245, 0.9);
}

.site-nav__toggle-bar {
    display: block;
    width: 100%;
    height: 2px;
    background: var(--text);
    border-radius: 1px;
    transition: transform 0.2s ease, opacity 0.2s ease;
}

.site-nav__toggle[aria-expanded="true"] .site-nav__toggle-bar:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
}

.site-nav__toggle[aria-expanded="true"] .site-nav__toggle-bar:nth-child(2) {
    opacity: 0;
}

.site-nav__toggle[aria-expanded="true"] .site-nav__toggle-bar:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
}
```

- [ ] **Step 3: Add mobile breakpoint rules**

In `public/css/site.css`, inside the `@media (max-width: 900px)` block, add:

```css
    .site-nav__toggle {
        display: flex;
    }

    .site-nav {
        display: none;
        width: 100%;
        flex-direction: column;
        gap: 0.25rem;
        padding: 0.75rem 0;
        border-top: 1px solid var(--line);
    }

    .site-nav.is-open {
        display: flex;
    }

    .site-nav__group {
        flex-direction: column;
    }

    .site-nav a {
        padding: 0.7rem 1rem;
        border-radius: 12px;
    }
```

Update the existing 900px `.site-header__inner` rule to allow wrapping:

```css
    .site-header__inner {
        flex-wrap: wrap;
        flex-direction: row;
        align-items: center;
    }
```

Remove the existing 900px `.site-nav` rule (which sets `justify-content: flex-start`) since we're replacing it.

- [ ] **Step 4: Verify mobile nav works**

```bash
php -S localhost:8080 -t public &
sleep 1
curl -s http://localhost:8080/ | grep -c 'site-nav__toggle'
# Expected: 1
curl -s http://localhost:8080/ | grep -c 'aria-controls="site-nav"'
# Expected: 1
kill %1
```

- [ ] **Step 5: Commit**

```bash
git add templates/base.html.twig public/css/site.css
git commit -m "feat: add mobile navigation hamburger toggle

Visible below 900px. Animates to X when open. Links stack
vertically. Closes on link click. No build step needed.

Closes #<mobile-nav-issue-number>"
```

---

### Task 8: Add head meta and accessibility

**Files:**
- Modify: `templates/base.html.twig`
- Modify: `public/css/site.css`

**Closes:** GitHub issue "Add head meta: OG tags, skip-to-content, favicon prep"

- [ ] **Step 1: Add OG tags to head**

In `templates/base.html.twig`, after the `<meta name="description">` tag, add:

```html
  <meta property="og:title" content="{% block og_title %}Ontario Indigenous AI &amp; Technology Council{% endblock %}">
  <meta property="og:description" content="{% block og_description %}Indigenous digital sovereignty in Ontario through governance, training, research, and platform stewardship.{% endblock %}">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://oiatc.ca{{ path }}">
  <meta property="og:image" content="{% block og_image %}/images/og-default.png{% endblock %}">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
```

- [ ] **Step 2: Add skip-to-content link**

In `templates/base.html.twig`, add as first child of `<body>`, before `<div class="site-shell">`:

```html
  <a class="skip-link" href="#main-content">Skip to content</a>
```

Add `id="main-content"` to `<main>`:

```html
    <main id="main-content">
```

- [ ] **Step 3: Add skip-link CSS**

In `public/css/site.css`, add after the `body` rule (after line 44):

```css
.skip-link {
    position: absolute;
    top: -100%;
    left: 1rem;
    z-index: 100;
    padding: 0.75rem 1.2rem;
    background: var(--forest);
    color: var(--cream);
    border-radius: 0 0 8px 8px;
    text-decoration: none;
    font-size: 0.9rem;
    transition: top 0.15s ease;
}

.skip-link:focus {
    top: 0;
}
```

- [ ] **Step 4: Verify**

```bash
php -S localhost:8080 -t public &
sleep 1
curl -s http://localhost:8080/ | grep -c 'og:title'
# Expected: 1
curl -s http://localhost:8080/ | grep -c 'skip-link'
# Expected: 1
curl -s http://localhost:8080/ | grep -c 'main-content'
# Expected: 1
kill %1
```

- [ ] **Step 5: Commit**

```bash
git add templates/base.html.twig public/css/site.css
git commit -m "feat: add OG meta tags, favicon links, and skip-to-content

OG tags use Twig blocks for per-page overrides. Skip-link is
visually hidden until focused for keyboard navigation.

Closes #<meta-issue-number>"
```

---

### Task 9: Add 404 page

**Files:**
- Create: `templates/404.html.twig`
- Modify: `src/Controller/PageController.php`
- Modify: `src/Provider/SiteServiceProvider.php`

**Closes:** GitHub issue "Add 404 error page"

- [ ] **Step 1: Create 404 template**

Create `templates/404.html.twig`:

```html
{% extends 'base.html.twig' %}

{% block title %}Page not found | OIATC{% endblock %}

{% block content %}
  <section class="page-header">
    <h1>Page not found</h1>
    <p>The page you're looking for doesn't exist or has been moved.</p>
    <div class="cta-row">
      <a class="button" href="/">Return to home</a>
    </div>
  </section>
{% endblock %}
```

- [ ] **Step 2: Add notFound method to PageController**

In `src/Controller/PageController.php`, add a new public method after `contact()`:

```php
    public function notFound(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return new SsrResponse($this->twig->render('404.html.twig', ['path' => $request->getPathInfo()]), 404);
    }
```

Note: The `SsrResponse` constructor accepts a second `statusCode` parameter. The `render()` private method doesn't support this, so we call `SsrResponse` directly.

- [ ] **Step 3: Register catch-all route**

In `src/Provider/SiteServiceProvider.php`, add a catch-all route at the end of the `routes()` method, after the foreach loop:

```php
        // 404 catch-all (must be last)
        $router->addRoute(
            'page.not_found',
            RouteBuilder::create('/{path}')
                ->controller('App\\Controller\\PageController::notFound')
                ->render()
                ->methods('GET')
                ->requirement('path', '.+')
                ->build(),
        );
```

Check that `RouteBuilder` has a `requirement()` method. If not, use Symfony's route requirements directly. Verify by checking the RouteBuilder API:

```bash
grep -n 'function requirement' vendor/waaseyaa/routing/src/RouteBuilder.php
```

If `requirement()` doesn't exist, use:

```php
        $router->addRoute(
            'page.not_found',
            RouteBuilder::create('/{path}')
                ->controller('App\\Controller\\PageController::notFound')
                ->render()
                ->methods('GET')
                ->build(),
        );
```

And add the requirement via the Symfony Route object directly, or check if the framework's catch-all pattern works differently.

- [ ] **Step 4: Verify**

```bash
php -S localhost:8080 -t public &
sleep 1
curl -s -o /dev/null -w '%{http_code}' http://localhost:8080/nonexistent
# Expected: 404
curl -s http://localhost:8080/nonexistent | grep -c 'Page not found'
# Expected: 1
kill %1
```

- [ ] **Step 5: Commit**

```bash
git add templates/404.html.twig src/Controller/PageController.php src/Provider/SiteServiceProvider.php
git commit -m "feat: add 404 error page

Catch-all route renders branded 404 template for unmatched paths.

Closes #<404-issue-number>"
```

---

### Task 10: Add general inquiry to contact page

**Files:**
- Modify: `templates/contact.html.twig`
- Modify: `public/css/site.css`

**Closes:** GitHub issue "Add general inquiry fallback to contact page"

- [ ] **Step 1: Add fifth contact article**

In `templates/contact.html.twig`, add after the "Founding-circle or advisory interest" article (before `</section>`):

```html
    <article>
      <h3>General inquiries</h3>
      <p>For anything that doesn't fit the categories above.</p>
      <a class="button-secondary" href="mailto:info@oiatc.ca?subject=OIATC%20General%20Inquiry">Email OIATC</a>
    </article>
```

- [ ] **Step 2: Update contact-grid CSS for 5 items**

In `public/css/site.css`, add after the existing `.contact-grid` rules:

```css
.contact-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
```

This overrides the `grid-2` default. The 5 items will flow as 3 + 2 on desktop. The existing 640px breakpoint already collapses to `1fr`.

Note: Since `.contact-grid` already inherits `grid-template-columns: repeat(2, minmax(0, 1fr))` from the shared `.grid-2, .contact-grid` rule, we need to override it. Add this rule after the shared grid declarations (after line 508).

- [ ] **Step 3: Verify**

```bash
php -S localhost:8080 -t public &
sleep 1
curl -s http://localhost:8080/contact | grep -c 'General inquiries'
# Expected: 1
curl -s http://localhost:8080/contact | grep -c 'info@oiatc.ca'
# Expected: 1
kill %1
```

- [ ] **Step 4: Commit**

```bash
git add templates/contact.html.twig public/css/site.css
git commit -m "feat: add general inquiry fallback to contact page

Fifth contact article for info@oiatc.ca. Grid updated to 3-column
layout (3+2 on desktop, stacked on mobile).

Closes #<contact-issue-number>"
```

---

### Task 11: Generate favicon and OG image assets

**Files:**
- Create: `public/favicon.svg`
- Create: `public/favicon.ico`
- Create: `public/apple-touch-icon.png`
- Create: `public/images/og-default.png`

**Closes:** GitHub issue "Generate favicon and OG image assets"

- [ ] **Step 1: Create favicon SVG**

Create `public/favicon.svg` — a simple SVG using the OIATC brand mark (the rounded square with "OIATC" text, using the forest-to-lake gradient):

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#08190f"/>
      <stop offset="100%" stop-color="#187a6c"/>
    </linearGradient>
  </defs>
  <rect width="32" height="32" rx="7" fill="url(#g)"/>
  <text x="16" y="20" text-anchor="middle" fill="#f9f2e6" font-family="Georgia,serif" font-size="7" letter-spacing="0.5">OIATC</text>
</svg>
```

- [ ] **Step 2: Generate favicon.ico and apple-touch-icon.png**

Use ImageMagick (available on the system) or a similar tool:

```bash
# Check if ImageMagick is available
which convert || which magick

# Generate 32x32 ICO from SVG
convert public/favicon.svg -resize 32x32 public/favicon.ico

# Generate 180x180 PNG for Apple touch icon
convert public/favicon.svg -resize 180x180 public/apple-touch-icon.png
```

If ImageMagick is not available, use `rsvg-convert` or note that these need manual generation.

- [ ] **Step 3: Generate OG image**

Create a simple HTML file, render it to PNG at 1200x630. Or create a minimal SVG-based image and convert:

```bash
mkdir -p public/images
convert -size 1200x630 \
  -define gradient:direction=southeast \
  gradient:'#08190f'-'#187a6c' \
  -gravity center \
  -fill '#f9f2e6' \
  -font Georgia \
  -pointsize 64 \
  -annotate 0 'Ontario Indigenous AI\n& Technology Council' \
  public/images/og-default.png
```

If the exact command doesn't work, adjust based on available tools. The key requirement is a 1200x630 image with OIATC branding.

- [ ] **Step 4: Verify files exist**

```bash
ls -la public/favicon.svg public/favicon.ico public/apple-touch-icon.png public/images/og-default.png
```

- [ ] **Step 5: Commit**

```bash
git add public/favicon.svg public/favicon.ico public/apple-touch-icon.png public/images/og-default.png
git commit -m "feat: add favicon and OG image assets

SVG favicon with OIATC brand mark. ICO and Apple touch icon
generated from SVG. Default OG image for social sharing.

Closes #<favicon-issue-number>"
```

---

### Task 12: Final verification

**Files:**
- None (verification only)

- [ ] **Step 1: Run full site check**

```bash
php -S localhost:8080 -t public &
sleep 1

echo "=== Nav links (should be 6, no Charter) ==="
curl -s http://localhost:8080/ | grep -oP '(?<=site-nav__group).*?(?=</div>)' | grep -oP 'href="[^"]*"' | sort -u

echo "=== Footer links (should include Charter) ==="
curl -s http://localhost:8080/ | grep -oP '(?<=site-footer__links).*?(?=</div>)' | grep -oP 'href="[^"]*"' | sort -u

echo "=== Home sections (should be 6) ==="
curl -s http://localhost:8080/ | grep -c '<section'

echo "=== About (no principles/mandate) ==="
curl -s http://localhost:8080/about | grep -c 'Founding principles'

echo "=== Waaseyaa (expanded) ==="
curl -s http://localhost:8080/waaseyaa | grep -c '<section'

echo "=== Minoo (expanded) ==="
curl -s http://localhost:8080/minoo | grep -c '<section'

echo "=== 404 ==="
curl -s -o /dev/null -w '%{http_code}' http://localhost:8080/nonexistent

echo "=== Contact (5 articles) ==="
curl -s http://localhost:8080/contact | grep -c '<article'

echo "=== OG tags ==="
curl -s http://localhost:8080/ | grep -c 'og:title'

echo "=== Skip link ==="
curl -s http://localhost:8080/ | grep -c 'skip-link'

echo "=== Mobile nav toggle ==="
curl -s http://localhost:8080/ | grep -c 'site-nav__toggle'

kill %1
```

Expected results:
- Nav: 6 links (Home, About, Waaseyaa, Minoo, Grants, Contact)
- Footer: 6 links (including Charter)
- Home: 6 `<section>` tags
- About: 0 occurrences of "Founding principles"
- Waaseyaa: 5 `<section>` tags
- Minoo: 5 `<section>` tags
- 404: HTTP 404 status
- Contact: 5 `<article>` tags
- OG tags: 1
- Skip link: 1
- Mobile toggle: 1

- [ ] **Step 2: Verify all GitHub issues are closed**

```bash
gh issue list --repo waaseyaa/oiatc-waaseyaa --milestone "MVP Tightening" --state open
# Expected: no open issues
```

- [ ] **Step 3: Close milestone if all issues done**

```bash
milestone_num=$(gh milestone list --repo waaseyaa/oiatc-waaseyaa --json number,title --jq '.[] | select(.title=="MVP Tightening") | .number')
gh api repos/waaseyaa/oiatc-waaseyaa/milestones/"$milestone_num" -X PATCH -f state=closed
```
