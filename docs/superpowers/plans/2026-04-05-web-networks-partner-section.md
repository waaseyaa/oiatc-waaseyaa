# Web Networks Infrastructure Partner Section — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Web Networks as a visible infrastructure partner on the OIATC About page, with logo, description, and data sovereignty context.

**Architecture:** A new `<section>` in `about.html.twig` between the Purpose/Relationship cards and the charter link. One new CSS class (`.partner-card`) using the existing `.card--surface` as base with a grid layout for logo + text. SVG logo downloaded to `public/images/partners/`.

**Tech Stack:** Twig templates, plain CSS (no build step), SVG asset

**Spec:** `docs/superpowers/specs/2026-04-05-web-networks-partner-section-design.md`

---

### Task 1: Download Web Networks logo

**Files:**
- Create: `public/images/partners/web-networks-logo.svg`

- [ ] **Step 1: Create the partners directory**

```bash
mkdir -p public/images/partners
```

- [ ] **Step 2: Download the SVG logo**

```bash
curl -o public/images/partners/web-networks-logo.svg https://web.net/themes/custom/webnetv2/logo.svg
```

- [ ] **Step 3: Verify the file downloaded correctly**

```bash
file public/images/partners/web-networks-logo.svg
head -5 public/images/partners/web-networks-logo.svg
```

Expected: File identified as SVG/XML. First lines should contain `<svg` or `<?xml`.

- [ ] **Step 4: Commit the asset**

```bash
git add public/images/partners/web-networks-logo.svg
git commit -m "asset: add Web Networks logo SVG"
```

---

### Task 2: Add partner card CSS

**Files:**
- Modify: `public/css/site.css` (append before the Storm Theme block)

- [ ] **Step 1: Add partner card styles to site.css**

Append the following CSS **before** the `/* ── Storm Theme ──` comment block (which starts around line 1260+). This goes at the end of the MVP Polish section:

```css
/* ── Partner card ── */
.partner-card {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 2rem;
    align-items: center;
}

.partner-card__logo {
    width: 100%;
    max-width: 180px;
    height: auto;
}

.partner-card__body h3 {
    margin: 0 0 0.4rem;
    font-family: var(--font-display);
    font-size: 1.55rem;
}

.partner-card__body h3 a {
    color: var(--copper);
    text-decoration: none;
}

.partner-card__body h3 a:hover {
    color: var(--copper-deep);
}

.partner-card__meta {
    color: var(--muted);
    font-size: 0.95rem;
    margin: 0 0 1rem;
}

.partner-card__body p {
    color: var(--muted);
}
```

- [ ] **Step 2: Add responsive rule inside the existing `@media (max-width: 640px)` block**

Find the `@media (max-width: 640px)` block (around line 928) and add inside it:

```css
    .partner-card {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .partner-card__logo {
        margin: 0 auto;
        max-width: 140px;
    }
```

- [ ] **Step 3: Verify CSS is syntactically valid**

```bash
cd /home/jones/dev/oiatc-waaseyaa && php -S localhost:8080 -t public &
sleep 1
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/css/site.css
kill %1
```

Expected: HTTP 200. The CSS file loads without errors.

- [ ] **Step 4: Commit the CSS changes**

```bash
git add public/css/site.css
git commit -m "style: add partner card layout with responsive stacking"
```

---

### Task 3: Add partner section to About page template

**Files:**
- Modify: `templates/about.html.twig`

- [ ] **Step 1: Add the infrastructure partner section**

In `templates/about.html.twig`, insert the following new section **after** the closing `</section>` of the `grid-2` section (the Purpose/Relationship cards) and **before** the final `<section class="section">` that contains the charter link:

```twig
  <section class="section reveal">
    <div class="eyebrow">Infrastructure Partner</div>
    <article class="card card--surface partner-card">
      <div class="partner-card__logo-wrap">
        <img src="/images/partners/web-networks-logo.svg" alt="Web Networks logo" class="partner-card__logo">
      </div>
      <div class="partner-card__body">
        <h3><a href="https://web.net" target="_blank" rel="noopener">Web Networks</a></h3>
        <p class="partner-card__meta">Non-profit worker co-op · Founded 1987 · One of Canada's first ISPs · All infrastructure on Canadian soil</p>
        <p>Indigenous data sovereignty starts with where data physically lives. OIATC's infrastructure is hosted through Web Networks in Toronto, on Canadian-owned, open-source systems with no foreign cloud dependencies. The long-term vision is to build infrastructure on First Nations land. Until that's possible, Web Networks provides the foundation: 38 years of serving non-profits, governments, and Indigenous institutions, including the Legislative Assembly of Nunavut and Nunavut Public Library Services.</p>
      </div>
    </article>
  </section>
```

- [ ] **Step 2: Verify the template renders**

```bash
cd /home/jones/dev/oiatc-waaseyaa && php -S localhost:8080 -t public &
sleep 1
curl -s http://localhost:8080/about | grep -c "Web Networks"
kill %1
```

Expected: At least 2 matches (the heading link and the body text).

- [ ] **Step 3: Commit the template change**

```bash
git add templates/about.html.twig
git commit -m "feat: add Web Networks infrastructure partner section to About page"
```

---

### Task 4: Visual check and final commit

- [ ] **Step 1: Start the dev server**

```bash
cd /home/jones/dev/oiatc-waaseyaa && php -S localhost:8080 -t public
```

- [ ] **Step 2: Open http://localhost:8080/about in a browser and verify:**

1. The partner section appears after the Purpose/Relationship cards
2. Web Networks logo displays on the left, text on the right
3. The heading "Web Networks" links to https://web.net
4. The eyebrow reads "Infrastructure Partner"
5. The section fades in on scroll (`.reveal` class)
6. Resize browser to mobile width: logo stacks above text, centered

- [ ] **Step 3: If the SVG logo has color issues on the dark card background**

The Web Networks logo may be designed for a light background. If it appears invisible or hard to read on the `.card--surface` background, add a filter or background treatment:

```css
.partner-card__logo {
    /* Only add if logo is not visible on dark background */
    filter: brightness(0) invert(1);
}
```

Or wrap the logo in a white-background container:

```css
.partner-card__logo-wrap {
    background: rgba(255, 255, 255, 0.9);
    border-radius: var(--radius-small);
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
```

Choose whichever looks better. Commit if changes were needed:

```bash
git add public/css/site.css
git commit -m "fix: adjust partner logo visibility on dark background"
```

- [ ] **Step 4: Stop the dev server**

`Ctrl+C` or `kill %1`
