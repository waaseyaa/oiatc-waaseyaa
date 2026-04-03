# OIATC Site: Prototype → MVP Tightening

**Date:** 2026-04-03
**Status:** Approved

## Problem

The prototype site has 7 pages with significant content redundancy (founding principles appear 3 times, mandate domains 3 times, founder bio 3 times across 2 pages), two thin stub pages (Waaseyaa, Minoo), and missing MVP infrastructure (mobile nav, favicon, OG meta, 404 page, skip-to-content).

## Goals

1. Eliminate content redundancy — each concept has one canonical home
2. Expand Waaseyaa and Minoo pages with concrete, substantive content
3. Tighten navigation from 7 to 6 top-level links
4. Add MVP infrastructure (mobile nav, meta, accessibility, error page)

## Live references

- OIATC site: oiatc.ca (this project)
- Waaseyaa framework site: https://waaseyaa.org (source at ~/dev/waaseyaa.org)
- Minoo platform: https://minoo.live (source at ~/dev/minoo)

---

## 1. Navigation Restructure

**Current:** Home, About, Waaseyaa, Minoo | Grants, Charter, Contact (7 links)

**New:** Home, About, Waaseyaa, Minoo | Grants, Contact (6 links)

- Remove Charter from nav
- Charter remains linked from: footer, home page CTA card, about page
- Footer links updated to match: About, Waaseyaa, Minoo, Grants, Charter, Contact

## 2. Home Page — Reduce from 8 to 6 Sections

### Keep (with minor edits)
1. **Hero** — founding declaration, founder card, stance card, CTAs. No changes.
2. **"Why OIATC exists"** — three statements (protect sovereignty, build capacity, steward platforms). No changes.
3. **"What we steward"** — three pillars (Council, Waaseyaa, Minoo). No changes.
4. **Lanes** — governance, training, research. No changes.
5. **Final CTA** — partner call. No changes.

### Cut
- **Founder-band** (lines 104–118) — redundant with hero founder card. Remove entirely.

### Merge
- **Split-CTA** (grants card + charter card) — collapse into a single lighter band. Keep grants CTA, keep charter CTA, but as a compact row rather than two feature cards. Style as a simple two-link band above the final CTA.

**Result:** 6 sections: hero, why, pillars, lanes, grants+charter band, final CTA.

## 3. About Page — Remove Redundant Content

### Keep
- Page header (as-is)
- Founder profile section (as-is)
- Purpose and Relationship to Nations cards (as-is)

### Remove
- Founding principles card — duplicates charter
- Mandate domains card — duplicates charter

### Add
- Single line below the purpose/relationship cards: "Read the founding charter for principles, mandate, and governance commitments." with link to /founding-charter.

## 4. Waaseyaa Page — Expand with Concrete Content

Current page has 2 vague cards. Replace with substantive content drawn from the framework codebase and waaseyaa.org.

### Page structure

**Header:** Keep eyebrow "Governance platform", title "Waaseyaa", update subtitle to: "An entity-first, AI-native framework that encodes governance, protocol, and accountability into working infrastructure."

**Section 1: What Waaseyaa is** (prose block)
Waaseyaa is a modular PHP framework built on Symfony components. It replaces legacy CMS architecture with a clean, 7-layer system designed for platforms where data sovereignty, access control, and cultural protocol are structural requirements — not afterthoughts. It powers this site and Minoo.

**Section 2: Capabilities** (3-column grid)
- **Entity & relationship system** — Typed entities with temporal boundaries, directionality, and qualifiers. Model governance hierarchies, knowledge lineages, clan structures, and protocol relationships as first-class data.
- **Deny-by-default access control** — Entity-level and field-level policies. Tiered disclosure: public knowledge vs. restricted protocols. Every access decision is explicit.
- **AI-native architecture** — Four dedicated AI packages (schema, agent, pipeline, vector). AI agents operate as governed users with full audit trails. AI is not bolted on — it runs inside the same access and accountability boundaries as human users.

**Section 3: Why it matters for Indigenous governance** (2-column grid)
- **Data sovereignty by design** — Granular control over who accesses what, at field and relationship level. No data leaves without explicit policy.
- **Protocol encoded in infrastructure** — Temporal bounds, consent tracking, and relationship directionality capture real governance rules. Seasonal protocols, initiation sequences, and knowledge restrictions are modeled structurally.

**Section 4: Relationship to OIATC** (compact statement)
Waaseyaa is one of the Council's principal technical expressions. OIATC governs the platform's direction; Waaseyaa encodes the Council's governance standards into deployable infrastructure.

**Section 5: Link out**
CTA linking to https://waaseyaa.org for technical documentation, architecture details, and developer onboarding.

## 5. Minoo Page — Expand with Concrete Content

Current page has 2 vague cards. Replace with substantive content drawn from the Minoo codebase (17 entity types, 746 tests, live at minoo.live).

### Page structure

**Header:** Keep eyebrow "Community platform", title "Minoo", update subtitle to: "An Indigenous knowledge and community platform for language, teachings, and cultural continuity."

**Section 1: What Minoo is** (prose block)
Minoo is a community-driven platform that makes Indigenous languages, teachings, histories, and cultural knowledge accessible, searchable, and engaging. It centers Elder and Knowledge Keeper voices, maintains data sovereignty, and provides interactive learning through culturally grounded design. Built on Waaseyaa.

**Section 2: What people use it for** (3-column grid)
- **Language & dictionary** — Searchable dictionary with dialect support, example sentences, word parts, pronunciation, and consent-tracked speakers. Anishinaabemowin localization underway.
- **Teachings & oral histories** — Cultural teachings archive with regional and dialect variants. Collections for oral histories and community knowledge.
- **Community & connection** — Social feed, private messaging, community groups, events, and contributor directories. Map-based discovery for nearby content and communities.

**Section 3: Who it serves** (4-item grid or list)
- **Elders & Knowledge Keepers** — Dedicated interface for content oversight and cultural authority
- **Language learners** — Dictionary search, gamified learning (word games, crosswords, daily challenges)
- **Community members** — Social features, events, group participation
- **Volunteers & coordinators** — Self-service signup, elder support workflows, community management dashboards

**Section 4: Data sovereignty** (compact statement)
Consent-tracked speakers. CC BY-NC-SA content licensing. Deny-by-default access control inherited from Waaseyaa. Community data stays under community governance.

**Section 5: Relationship to OIATC** (compact statement)
OIATC treats Minoo as part of the same stewardship architecture as Waaseyaa: different public role, same sovereignty-first foundation.

**Section 6: Link out**
CTA linking to https://minoo.live to explore the platform.

## 6. Mobile Navigation

Add a hamburger toggle button visible below 768px breakpoint.

### Behavior
- Button: `<button class="site-nav__toggle" aria-expanded="false" aria-controls="site-nav">` with a hamburger icon (CSS-only, three lines)
- On click: toggles `is-open` class on nav element, updates `aria-expanded`
- Nav slides down or fades in below the header
- Links stack vertically in mobile view
- Clicking a link closes the menu

### Implementation
- Small inline `<script>` in base.html.twig (no build step needed)
- CSS additions to site.css for toggle button, mobile nav layout, and transitions
- Toggle button hidden above 768px

## 7. Head Meta & Accessibility

### Favicon
- Generate a simple favicon from the OIATC brand mark
- Add `<link rel="icon">` to base.html.twig `<head>`
- Formats: favicon.ico (32x32), favicon.svg (scalable), apple-touch-icon.png (180x180)

### Open Graph tags
Add to base.html.twig with per-page overrides via Twig blocks:

```html
<meta property="og:title" content="{% block og_title %}Ontario Indigenous AI & Technology Council{% endblock %}">
<meta property="og:description" content="{% block og_description %}Indigenous digital sovereignty in Ontario through governance, training, research, and platform stewardship.{% endblock %}">
<meta property="og:type" content="website">
<meta property="og:url" content="https://oiatc.ca{{ path }}">
<meta property="og:image" content="{% block og_image %}https://oiatc.ca/images/og-default.png{% endblock %}">
```

Generate a default OG image (1200x630) with OIATC branding.

### Skip-to-content
Add as first child of `<body>`:
```html
<a class="skip-link" href="#main-content">Skip to content</a>
```
Add `id="main-content"` to `<main>`. Style skip-link as visually hidden until focused.

## 8. 404 Page

New template: `templates/404.html.twig`

```
{% extends 'base.html.twig' %}
{% block title %}Page not found | OIATC{% endblock %}
{% block content %}
  <section class="page-header">
    <h1>Page not found</h1>
    <p>The page you're looking for doesn't exist or has been moved.</p>
    <a class="button" href="/">Return to home</a>
  </section>
{% endblock %}
```

Wire up in the controller or framework error handling.

## 9. Contact Page — Add General Fallback

Add a fifth article to the contact grid:

```html
<article>
  <h3>General inquiries</h3>
  <p>For anything that doesn't fit the categories above.</p>
  <a class="button-secondary" href="mailto:info@oiatc.ca?subject=OIATC%20General%20Inquiry">Email OIATC</a>
</article>
```

Adjust grid CSS to accommodate 5 items (2+3 or 3+2 layout on desktop).

## 10. CSS Changes Summary

- Remove `.founder-band` styles (section removed)
- Simplify `.split-cta` into a compact two-link band
- Add `.site-nav__toggle` hamburger styles
- Add mobile nav breakpoint styles (below 768px)
- Add `.skip-link` styles
- Adjust `.contact-grid` for 5 items
- No new CSS files — all changes in existing site.css

## 11. What Is NOT Changing

- Grants page — MVP-ready as-is
- Charter page — clean governing document, no changes
- Footer structure — only link list updates (add Waaseyaa, Minoo; keep Charter)
- Color palette, typography, design system — all stay
- Site architecture (Waaseyaa framework, Twig, SSR) — no changes

---

## Implementation Order

1. Navigation restructure + footer links (base.html.twig)
2. Home page tightening (home.html.twig, site.css)
3. About page cleanup (about.html.twig)
4. Waaseyaa page expansion (waaseyaa.html.twig, site.css)
5. Minoo page expansion (minoo.html.twig, site.css)
6. Mobile nav (base.html.twig, site.css)
7. Head meta + accessibility (base.html.twig, site.css)
8. 404 page (404.html.twig, controller/error handling)
9. Contact page tweak (contact.html.twig, site.css)
10. Favicon + OG image generation
