# OIATC Design Pass Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tighten the OIATC public site design, fix the underpowered hero treatment, and add Russell Jones as visible founding leadership without shifting the site into a personal brand.

**Architecture:** Keep the existing route structure and controller intact while reshaping the presentation layer. Make the design pass by updating shared layout markup, homepage and About templates, one founder image asset, and the public stylesheet, with integration coverage focused on founder presence and key hero content.

**Tech Stack:** Waaseyaa, Twig, PHP, PHPUnit, static CSS

---

### Task 1: Lock The New Public Content In Tests

**Files:**
- Modify: `tests/Integration/PublicPagesTest.php`

- [ ] Add assertions for founder presence on the homepage and About page.
- [ ] Run `./vendor/bin/phpunit tests/Integration/PublicPagesTest.php`
- [ ] Confirm the new expectations fail before implementation.

### Task 2: Add Founder Asset And Update Presentation Templates

**Files:**
- Create: `public/images/founding/russell-jones.jpg`
- Modify: `templates/base.html.twig`
- Modify: `templates/home.html.twig`
- Modify: `templates/about.html.twig`

- [ ] Copy the approved founder portrait into a public OIATC asset path.
- [ ] Rework the header brand and footer support structure.
- [ ] Replace the current homepage hero side panels with a founder-integrated composition and reduce repeated card patterns through the middle of the page.
- [ ] Add a founder context section to the About page with image, biography, and leadership statement.

### Task 3: Refactor The Visual System

**Files:**
- Modify: `public/css/site.css`

- [ ] Introduce a stronger hero background, revised type hierarchy, and calmer navigation styling.
- [ ] Add founder/profile styles, section variants, and a more editorial homepage rhythm.
- [ ] Add responsive ordering so founder content appears directly after the hero thesis on smaller screens.

### Task 4: Verify The Design Pass

**Files:**
- Verify: `tests/Integration/PublicPagesTest.php`
- Verify: `templates/*.html.twig`
- Verify: `public/css/site.css`

- [ ] Run `./vendor/bin/phpunit`
- [ ] Smoke-check the homepage and About page visually in a browser.
- [ ] Summarize the design changes and any follow-up deployment action.
