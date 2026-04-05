# Design System Polish — Translucent Dark Surfaces Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace all cream/light backgrounds in site.css with translucent dark surfaces, fix text contrast, and set minimum font sizes.

**Architecture:** All changes are CSS-only in one file (`public/css/site.css`). We update `:root` tokens, then migrate each component's background from cream to dark translucent, remove hardcoded dark text colors, add backdrop-filter for frosted glass, and bump small font sizes. The Storm Theme block already handles some components (`.founder-card`, `.button-secondary`); we leave those alone and fix the rest.

**Tech Stack:** Plain CSS, no build step

**Spec:** `docs/superpowers/specs/2026-04-05-design-system-polish-design.md`

---

### Task 1: Update `:root` design tokens

**Files:**
- Modify: `public/css/site.css:1-22`

- [ ] **Step 1: Update the `:root` block**

In `public/css/site.css`, change the `:root` block. Three changes:
1. Update `--paper` from `rgba(22, 30, 46, 0.95)` to `rgba(22, 30, 46, 0.85)` (slightly more translucent)
2. Update `--paper-strong` from `#1a2840` to `rgba(28, 48, 72, 0.6)` (translucent for statement surfaces)
3. Update `--muted` from `#8298b8` to `#94aac8` (better contrast on dark surfaces)

Find:
```css
    --paper: rgba(22, 30, 46, 0.95);
    --paper-strong: #1a2840;
```
Replace with:
```css
    --paper: rgba(22, 30, 46, 0.85);
    --paper-strong: rgba(28, 48, 72, 0.6);
```

Find:
```css
    --muted: #8298b8;
```
Replace with:
```css
    --muted: #94aac8;
```

Add a new token after `--paper-strong`:
```css
    --paper-deep: rgba(14, 20, 32, 0.9);
```

- [ ] **Step 2: Verify the file is valid CSS**

```bash
php -S localhost:8082 -t public > /dev/null 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}" http://localhost:8082/css/site.css
kill %1
```

Expected: HTTP 200.

- [ ] **Step 3: Commit**

```bash
git add public/css/site.css
git commit -m "style: update root tokens for translucent dark surface system"
```

---

### Task 2: Add backdrop-filter to shared component rule

**Files:**
- Modify: `public/css/site.css:388-401`

- [ ] **Step 1: Add backdrop-filter to the shared border-radius/shadow rule**

Find the existing shared rule at line 388:
```css
.founder-card,
.stance-card,
.card,
.pillar,
.lane,
.contact-grid article,
.charter-section,
.funding-table,
.statement,
.founder-profile__content {
    border-radius: var(--radius);
    border: 1px solid var(--line);
    box-shadow: var(--shadow-soft);
}
```

Replace with:
```css
.founder-card,
.stance-card,
.card,
.pillar,
.lane,
.contact-grid article,
.charter-section,
.funding-table,
.statement,
.founder-profile__content {
    border-radius: var(--radius);
    border: 1px solid var(--line);
    box-shadow: var(--shadow-soft);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
}
```

- [ ] **Step 2: Commit**

```bash
git add public/css/site.css
git commit -m "style: add backdrop-filter blur to all surface components"
```

---

### Task 3: Migrate component backgrounds from cream to dark translucent

**Files:**
- Modify: `public/css/site.css` (multiple locations)

This task changes 6 components that still have cream backgrounds and are NOT already overridden by the Storm Theme block. The Storm block already handles `.founder-card` (line 1384) and `.button-secondary` (line 1372), so we skip those.

- [ ] **Step 1: Change `.statement` background**

Find:
```css
.statement {
    padding: 1.6rem 1.8rem;
    background: rgba(255, 251, 245, 0.74);
}
```

Replace with:
```css
.statement {
    padding: 1.6rem 1.8rem;
    background: var(--paper-strong);
}
```

- [ ] **Step 2: Change `.section--surface .pillar` background**

Find:
```css
.section--surface .pillar {
    background: linear-gradient(180deg, rgba(252, 248, 242, 0.9), rgba(240, 233, 220, 0.85));
}
```

Replace with:
```css
.section--surface .pillar {
    background: var(--paper);
}
```

- [ ] **Step 3: Change `.card--surface` background and text colors**

Find:
```css
.card--surface {
    background: rgba(255, 251, 245, 0.8);
}

.card--surface h3 {
    color: #1a3a4a;
}

.card--surface p,
.card--surface .eyebrow {
    color: #3d4f5f;
}
```

Replace with:
```css
.card--surface {
    background: var(--paper);
}

.card--surface h3 {
    color: var(--text);
}

.card--surface p,
.card--surface .eyebrow {
    color: var(--muted);
}
```

- [ ] **Step 4: Change `.founder-profile__content` background**

Find:
```css
.founder-profile__content {
    padding: 2rem;
    background: rgba(255, 251, 245, 0.82);
}
```

Replace with:
```css
.founder-profile__content {
    padding: 2rem;
    background: var(--paper);
}
```

- [ ] **Step 5: Change `.funding-table` background**

Find:
```css
.funding-table {
    overflow: hidden;
    background: rgba(255, 251, 245, 0.86);
}
```

Replace with:
```css
.funding-table {
    overflow: hidden;
    background: var(--paper-deep);
}
```

- [ ] **Step 6: Change `.site-footer` background**

Find:
```css
.site-footer {
    margin-top: auto;
    border-top: 1px solid var(--line);
    background: rgba(255, 251, 245, 0.66);
}
```

Replace with:
```css
.site-footer {
    margin-top: auto;
    border-top: 1px solid var(--line);
    background: var(--paper-deep);
}
```

- [ ] **Step 7: Change `.section--band` background (in MVP Polish block)**

Find:
```css
.section--band {
    border-top: 1px solid var(--line);
    border-bottom: 1px solid var(--line);
    background: rgba(255, 251, 245, 0.5);
    padding: 1.6rem 0;
}
```

Replace with:
```css
.section--band {
    border-top: 1px solid var(--line);
    border-bottom: 1px solid var(--line);
    background: var(--paper-strong);
    padding: 1.6rem 0;
}
```

- [ ] **Step 8: Commit**

```bash
git add public/css/site.css
git commit -m "style: migrate all cream backgrounds to dark translucent surfaces"
```

---

### Task 4: Fix base-layer text colors and button

**Files:**
- Modify: `public/css/site.css` (multiple locations)

These are text color fixes in the base CSS (not Storm block) for components that now sit on dark surfaces.

- [ ] **Step 1: Fix `.founder-card__meta` and `.founder-profile__meta` color**

Find:
```css
.founder-card__meta,
.founder-profile__meta {
    margin: 0.55rem 0 0.9rem;
    color: var(--forest);
    letter-spacing: 0.06em;
    text-transform: uppercase;
    font-size: 0.82rem;
}
```

Replace with:
```css
.founder-card__meta,
.founder-profile__meta {
    margin: 0.55rem 0 0.9rem;
    color: var(--lake);
    letter-spacing: 0.06em;
    text-transform: uppercase;
    font-size: 0.85rem;
}
```

(Changes: `var(--forest)` to `var(--lake)` for visibility on dark bg, font-size bumped to `0.85rem`)

- [ ] **Step 2: Fix base `.button-secondary` colors**

Find:
```css
.button-secondary {
    border: 1px solid rgba(72, 53, 33, 0.16);
    background: rgba(255, 251, 245, 0.66);
    color: var(--text);
}

.button-secondary:hover {
    background: rgba(255, 251, 245, 0.9);
    border-color: rgba(72, 53, 33, 0.26);
}
```

Replace with:
```css
.button-secondary {
    border: 1px solid var(--line-strong);
    background: transparent;
    color: var(--text);
}

.button-secondary:hover {
    background: rgba(180, 204, 240, 0.08);
    border-color: var(--copper);
}
```

(The Storm block at line 1372 will further override these, but the base values should also be sane.)

- [ ] **Step 3: Commit**

```bash
git add public/css/site.css
git commit -m "style: fix text colors and button for dark surfaces"
```

---

### Task 5: Bump minimum font sizes

**Files:**
- Modify: `public/css/site.css` (multiple locations)

- [ ] **Step 1: Bump base `.eyebrow` font size**

Find:
```css
.eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.72rem;
    color: var(--copper);
}
```

Replace with:
```css
.eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.78rem;
    color: var(--copper);
}
```

- [ ] **Step 2: Bump MVP Polish eyebrow/label overrides**

Find (in the MVP Polish section):
```css
/* ── Eyebrow: tighter and bolder ── */
.eyebrow,
.brand__eyebrow {
    font-size: 0.68rem;
    letter-spacing: 0.2em;
    font-weight: 600;
}

/* ── Label: pill → chip ── */
.label {
    border-radius: 5px;
    font-weight: 600;
    font-size: 0.68rem;
    letter-spacing: 0.12em;
}
```

Replace with:
```css
/* ── Eyebrow: tighter and bolder ── */
.eyebrow,
.brand__eyebrow {
    font-size: 0.75rem;
    letter-spacing: 0.2em;
    font-weight: 600;
}

/* ── Label: pill → chip ── */
.label {
    border-radius: 5px;
    font-weight: 600;
    font-size: 0.75rem;
    letter-spacing: 0.12em;
}
```

- [ ] **Step 3: Commit**

```bash
git add public/css/site.css
git commit -m "style: bump minimum font sizes for legibility"
```

---

### Task 6: Remove redundant Storm Theme overrides

**Files:**
- Modify: `public/css/site.css` (Storm Theme block, ~line 1384-1395)

Now that the base styles use dark surfaces, some Storm Theme overrides are redundant. The `.founder-card` override at line 1384 used to fix the cream background; now the base is dark via `var(--paper)`. But the Storm override uses `rgba(14, 20, 34, 0.98)` which is darker/more opaque. We should keep it since it matches the translucent aesthetic but is intentionally more opaque for the card. Same for `.button-secondary` Storm overrides (line 1372-1381) which add the lake-blue tint.

**Decision: Leave the Storm block as-is.** The base values are now sane defaults, and the Storm block adds polish on top. No changes needed.

- [ ] **Step 1: Verify no action needed**

Read through the Storm Theme block (lines ~1312-1400) and confirm that all overrides still make sense now that base styles are dark. The existing Storm overrides for `.founder-card`, `.button-secondary`, `.hero__backdrop`, `.brand__mark`, `.site-nav` are all intentional design refinements, not cream-to-dark fixes.

- [ ] **Step 2: No commit needed for this task**

---

### Task 7: Visual verification across all pages

- [ ] **Step 1: Start the dev server**

```bash
php -S localhost:8082 -t public > /dev/null 2>&1 &
```

- [ ] **Step 2: Check each page in the browser**

Open each URL and verify:

1. **http://localhost:8082/** (Home)
   - Hero section readable
   - "Protect sovereignty" / "Build capacity" / "Steward platforms" cards have light text on dark translucent surfaces
   - Statement section uses `--paper-strong` (slightly lighter blue-dark)
   - Pillars, lanes readable
   - Band section uses `--paper-strong`
   - Footer has dark `--paper-deep` background with readable text

2. **http://localhost:8082/about** (About)
   - Founder profile meta text ("Ojibwe from Sagamok...") readable at 0.85rem in `--lake` color
   - Purpose / Relationship cards have light text on dark surface
   - Partner card (Web Networks) still looks good
   - Charter link readable

3. **http://localhost:8082/founding-charter** (Charter)
   - Charter section cards are dark translucent
   - Section headings and body text readable

4. **http://localhost:8082/grants** (Grants)
   - Funding table uses `--paper-deep` (darker recessed surface)
   - Table text readable
   - Bottom cards readable

5. **http://localhost:8082/contact** (Contact)
   - Contact grid cards have light text on dark surfaces
   - Buttons visible and readable

6. **http://localhost:8082/waaseyaa** and **http://localhost:8082/minoo**
   - Check for any remaining cream backgrounds

- [ ] **Step 3: Check mobile layout**

Resize browser to ~375px width and verify cards still look good stacked.

- [ ] **Step 4: Stop the dev server**

```bash
kill %1
```
