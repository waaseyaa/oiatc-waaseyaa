# Funder-frame restructure: decision log

Started 2026-06-12. Goal: reorganize oiatc.ca around four named programs so a
funder can understand and fund the organization. Run end to end. Approved visual
target: `mockup-home-funder-frame.html`. Strategy: `funder-lens-site-plan.md`.

## The four programs (taxonomy)

1. **Anishinaabemowin** (flagship) — `/anishinaabemowin` + doll cluster.
2. **Anokii** (sovereign community infrastructure) — `/anokii*`, `/explainers/where-your-data-lives`, Waaseyaa.
3. **Community knowledge** — RHT cluster, Massey cluster, `/practice/ai-in-coursework`, news.
4. **Transparency and member resources** — `/explainers/how-sagamok-is-organized`, `/disclosure/sagamok-portal`, records-request flow.

Positions (counter-disinformation, PrescribeIT) stay council-level statements, tagged "Council position", linked from About.

## Verified numbers (house rule: numbers must be real)

- **Pages count:** 24 indexable content routes exist today (home, 2 positions, 6
  explainer pages, ai-in-coursework, anishinaabemowin + 3 doll, disclosure, 3
  anokii, where-your-data-lives, how-sagamok, 2 records-request, news index),
  plus 12+ news posts. "20+ pages shipped and dated" is true and conservative.
  Sitemap was a curated subset (8 URLs); rebuilt to reflect reality.
- **21,721 dictionary entries:** taken from the approved mockup (user-supplied).
  Not independently re-verified here (Minoo dictionary data is not in this repo).
- **2 platforms in active build:** Waaseyaa + Minoo. Real.
- **First-party analytics, no ad-tech:** real (analytics subsystem in repo).
- **Cited by local press:** real (Massey coverage by myespanolanow.com / R. Russell).
- **Reach numbers on program pages:** omitted unless cleanly retrievable from
  `/api/page-stats`; never invented.

## Decisions

- Build with the site's existing design system (site.css tokens + .sec/.shell/
  .eyebrow rhythm), not the mockup's inline CSS. New components (facts panel,
  program cards, proof band, support grid) added as scoped `.fund-*` styles.
- `/resources/sagamok -> /anokii/sagamok` 301 already exists in routes and
  already returns 301 on production; re-verify after this deploy.
- Single deploy at the end (run end-to-end), then full production verification.

## Log

- Read mockup + strategy + current home/base/sitemap. Confirmed taxonomy and
  verified the page count. Created task list. Wrote this log.
- Part A done: home rebuilt to the funder frame (facts panel, 4 program cards,
  proof band, statement, support CTA, council + founder kept below). New pages
  /about, /programs, 4 program pages, /support built on a shared `.fund-*`
  partial. Controller actions + routes wired. Topnav = Programs · News · About ·
  Support (design-system + records-request removed from nav, pages still live).
  Footer: minoo.live replaced by /anishinaabemowin (and the four program links).
  Sitemap rebuilt (29 URLs). New pages added to the ingest corpus.
- Part B done: program breadcrumb + "More from this program" footer added to the
  content pages. Transparency intros (how-sagamok, disclosure) reframed to lead
  with member service, body untouched, no claims added or removed. Both positions
  tagged "Council position" linking /about. News post site-programs-restructure
  added. CTA case for related_explainer 'programs' added.

## Deviations / decisions logged

- Home program-card "reach published" line changed to "Cited by local press"
  (reach numbers are not published; avoided claiming they are).
- Community-knowledge program page: omitted specific analytics reach numbers
  (not cleanly retrievable without risk of invention); kept the verified
  "cited by local press" claim.
- Anokii lens pages (/anokii/sagamok, /anokii/massey) are interactive chat
  surfaces, not editorial content; the program eyebrow lives on the Anokii home
  (/anokii) and the lenses reach the program via the sitewide nav. No breadcrumb
  injected into the chat UI.
- Massey/RHT sub-pages carry the program breadcrumb in the masthead; the full
  "More from this program" block sits on the cluster's main page (sub-pages also
  cross-link within their cluster already).
- Flagged, not changed beyond the edit at hand: the counter-disinformation
  footer previously expanded OIATC as "Ojibway Information & Technology Council"
  (inconsistent with the canonical "Ontario Indigenous AI & Technology Council").
  Replaced that footer line as part of the B3 edit.
- The 4-item top nav shows on the home and the funder pages (home, about,
  programs, the four program pages, support). Deep editorial pages keep their
  existing back-link topnav override; every page still reaches the full nav via
  the global footer (About, Support, Programs, News) plus its program breadcrumb.
- Pre-existing em dashes remain in the long-form body prose of the older docs
  (RHT, Massey, how-Sagamok, where-your-data-lives, ai-in-coursework). They are
  not new or revised copy; a site-wide body copy-edit is out of scope for this
  task. All new and revised copy is em-dash-free.

## Deployed and verified (2026-06-12)

main 810ccaf, OIATC_REF bumped in waaseyaa-infra, container rebuilt, ingest run
(39 sources, 268 chunks). Production checks all green: 8 new pages 200; topnav
on home + funder pages; /resources/sagamok 301 (bare and cache-busted); every
B1 page shows its program eyebrow; sitemap 30 URLs; RSS 14 items incl
site-programs-restructure; zero minoo.live anchors across sampled pages; home +
new pages verified light and dark locally on identical templates.
