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
- **21,721 dictionary entries: REMOVED 2026-06-14 as an overclaim.** That count
  is the Ojibwe People's Dictionary's content, not something OIATC created or
  preserved, so presenting it as OIATC's proof was wrong. The Ojibwe People's
  Dictionary stays only as an attributed external source on the Anishinaabemowin
  page. The home proof tile now reads "27 / curated corpus items" (Russell's
  call, 2026-06-14), the real curated count from one Elder's teachings already
  stated on the Anishinaabemowin page, chosen over a "4 programs" stat that would
  have duplicated the masthead facts panel.
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

## 2026-06-14: Anishinaabemowin Lesson 1 showcase (built locally, awaiting Russell's review before deploy)

Added a showcase block to `/anishinaabemowin` (the program's public story home, not
the thin funder page): a lead crediting Steven Bennett (Facebook profile linked,
he has consented to reuse), a static 4-card taste of Lesson 1 kitchen words with
orthography preserved exactly, a short "how it is made" tying to the sovereign-AI
position, a dated proof line, and a "learn it on Minoo" funnel CTA. Showcase only:
no app logic or interactivity here; the real app lives on Minoo.

**Held: the AI step is omitted on purpose.** Per Russell, the "AI drafts, the
Elder decides" framing is not published until he stress-tests it with an Elder.
The showcase says "the corpus is curated by hand" and stops there.

**PLACEHOLDER, do not forget: the Minoo CTA is a "relaunching soon" state.**
minoo.live is not live yet (DNS propagating). The flip is one line in
`templates/anishinaabemowin/home.html.twig`: change `{% set minoo_relaunching = true %}`
to `false` (the live URL `minoo_url` is already wired). Do that when minoo.live resolves.

Status: built and verified locally. NOT deployed; waiting on Russell's review.

## 2026-06-14: coherence pass (built locally, awaiting Russell's review before deploy)

Brought the rest of the site up to the funder-spine standard (the model pages are
/programs/community-knowledge and /anokii).

- **Rebuilt /anishinaabemowin** to the program-page standard (funder partial,
  fund-wrap--narrow). Lead story credits Steven Bennett (Facebook linked,
  consented). Hero is the words: a static 4-card taste of Lesson 1 kitchen terms,
  text only, orthography exactly as written, no placeholder image boxes. Then a
  "What is shipped" list (doll linked; corpus, Lesson 1, games described, Minoo
  items not linked while Minoo relaunches), a short "how it is made" tying to
  /positions/sovereign-ai (AI step still omitted), a "learn it on Minoo"
  relaunching-soon CTA (same one-line flip flag), and a "fundable next step"
  echoing the Support ask. REMOVED from the public page: the 01-08 numbered scheme
  and table of contents, the multi-paragraph census essay (kept one stat line),
  the "Settled and deciding" section, and the "Progress log" changelog. Body went
  from ~36 KB to ~21 KB. Doll cluster still linked.
- **Nav consistency:** removed the back-link topnav overrides so every
  base-extending page inherits the full nav (Programs / News / About / Support +
  Dark): anokii home + lens shell (ak-brand now links /anokii), the three doll
  pages, where-your-data-lives, how-sagamok, ai-in-coursework, news index + post,
  records-request + letter, petition result + privacy. Self-contained docs (RHT,
  Massey, positions, disclosure) keep their own mastheads (out of scope; not
  base-extending).
- **Design system** already absent from public nav/footer; confirmed, no change.
- **/resources/sagamok -> /anokii/sagamok 301** already in place and verified
  locally; already absent from sitemap and the PAGES corpus; no orphaned template
  exists. Nothing to remove.
- **Footer / Minoo:** footer already carries no minoo.live link; the only
  remaining minoo.live in templates is the flag URL behind minoo_relaunching and
  the internal design-system demo page (not public chrome).
- **/programs/transparency (Member resources):** aligned its second section from
  "What you can ask for" to "The fundable next step", matching Community Knowledge.

Verified locally (body size + title, not just status). NOT deployed; awaiting review.

## 2026-06-14: second coherence pass (built locally, awaiting Russell's review before deploy)

JOBS 1, 2, 4 DONE and verified locally. JOB 3 NOT done (see below).

- **Job 1 (status ladder):** added one reusable badge to the funder partial,
  `.fund-stage` with `--idea` / `--proto` / `--live`. The honest principle line
  ("Live does not mean finished...") plus a three-tier legend sit near the top of
  /programs. Each program page's work list is now "The work, by stage" with a
  badge per item: community-knowledge (all Live), anokii (all Live),
  member-resources (all Live), anishinaabemowin funder page (corpus Live, Lesson 1
  Prototype, games Live, doll Idea). Anokii home module chips standardized:
  Co-Intelligence and Resources -> Live; the six disabled preview modules (Drive,
  Data Rooms, Workspaces, Vault, Governance, Portal) -> Idea (designed, not built).
- **Job 2 (/anishinaabemowin):** rebuilt to the program-page standard. Hero +
  words taste + how-it-is-made + Minoo CTA (relaunching placeholder) + fundable
  next step kept. Work list is "The work, by stage": curated corpus Live, Lesson 1
  Prototype, language games Live, the talking doll its own Idea item.
- **Job 4 (rename):** /programs/transparency -> /programs/member-resources.
  Template renamed (git mv), controller method programMemberResources, new route,
  301 from the old path (verified locally), all internal links updated (home,
  /programs, about, support, base footer, how-sagamok, disclosure), sitemap and
  the IngestDocsCommand PAGES corpus updated. body_class -> route-program-member-resources.
- **Job 3 (unify long-form docs onto the base shell): NOT DONE.** Safe recipe is
  validated: per doc, change `:root {` -> `.docwrap {` and `body {` -> `.docwrap {`
  (scopes the doc's tokens + body styling to a wrapper so the shell's site.css
  tokens are untouched), wrap the content in `<div class="docwrap">`, drop the
  doc's own `<header class="top">` and `<footer class="end">`, and switch the head
  to `{% extends 'base.html.twig' %}` + title/description/body_class +
  `{% block head_styles %}`. The shell's nav/footer survive because site.css
  class selectors (.top__meta a, .site-foot a) outrank the docs' bare-tag rules.
  Docs to convert: positions/prescribeit, positions/counter-disinformation,
  disclosure/sagamok-portal, explainers/robinson-huron-treaty (+ distribution-models),
  explainers/massey-solar-project (+ what-youve-heard, voices,
  climate-and-environment). Deferred because each needs multi-line exact-match
  edits and this working tree is being re-normalized by a linter mid-edit, so
  doing all of them in one pass risks breaking documents without per-doc verify.
  Note: the docs are light-themed; per the instruction their body styling is
  preserved exactly, so in dark mode the shell will be dark while the doc body
  stays light (an accepted trade for nav consistency).

NOT deployed. The unrelated anokii-shared-graph migration changes in the working
tree (composer, AppServiceProvider additions, config/anokii.yaml, new tests) were
left untouched.

## 2026-06-14 Job 3 (long-form unify onto base shell) — IN PROGRESS, committed incrementally

Tree confirmed clean and Jobs 1/2/4 committed (commit c4eee8d) before starting; the
anokii-shared-graph migration is merged (no longer mid-migration).

Transformation recipe (validated, theme-aware): per doc, `{% extends 'base.html.twig' %}`
+ title/description/body_class blocks + `{% block head_styles %}`; the doc's `:root`
and `body` rules become `.docwrap` with COLOUR tokens remapped to site tokens
(--bg->--paper, --surface->--paper-2/3, --ink-soft->--ink-2, --ink-mute->--ink-3,
--rule-soft->--rule, --highlight->--accent-wash; --ink/--rule/--accent/--accent-soft
omitted so they inherit site values; fonts->--font-*; gaps/content kept). Content
wrapped in `<div class="docwrap">`; the doc's own masthead and footer dropped (base
provides nav + footer). Layout/typography/content unchanged; only colour changes.

DONE and verified in BOTH light and dark, committed:
- positions/prescribeit (commit ec4ad3d)
- positions/counter-disinformation (commit 49eb387)

REMAINING (not yet done):
- disclosure/sagamok-portal — heavier: originally DARK-themed, ~27 hardcoded hex
  values scattered through the rules (not just :root) plus an inline SVG diagram
  with its own palette. Needs per-rule colour remap + the SVG either remapped or
  put in a fixed-dark panel.
- explainers/robinson-huron-treaty + robinson-huron-treaty-distribution-models
- explainers/massey-solar-project + -what-youve-heard + -voices + -climate-and-environment
  (the explainer clusters share a structure, so once one is converted the rest follow
  the same edits; several have inline SVG/specimen visuals to handle like the disclosure.)

Stopped before deploy for review, with the 2 positions durable on a clean tree.

### 2026-06-14 Job 3 COMPLETE — all long-form docs unified onto the base shell

All self-contained long-form docs now `{% extends 'base.html.twig' %}` with standard
nav + footer + light/dark toggle. Their colours are remapped to site tokens via a
`.docwrap` wrapper so the body follows the theme; layout/typography/content unchanged.
The doc mastheads were dropped; the editorial footers (update dates, related links)
were kept as content.

Fixed a latent bug from the first two: the docs' own `.top` masthead CSS was leaking
onto the base header (constraining it to 880px). Renamed every dead `.top*` selector
to `.doctop*` in all docs so it can no longer collide with the base `.top` masthead.

Unified + verified (200, correct <title>, content preserved, base nav + footer present,
masthead un-polluted, reads in light AND dark):
- positions/prescribeit, positions/counter-disinformation
- disclosure/sagamok-portal
- explainers/robinson-huron-treaty (+ /distribution-models)
- explainers/massey-solar-project (+ /what-youve-heard, /voices, /climate-and-environment)

Skipped (already base-extending): positions/sovereign-ai, explainers/where-your-data-lives,
explainers/how-sagamok-is-organized.

## 2026-06-15 Third council member: Oliver Zielke (retire "council of two")

OIATC adds Oliver Zielke as a third council member and director. No other people added
(no Elder, no Steven Bennett yet). Numberless, future-proof framing throughout.

Public pages updated (built + verified locally, NOT deployed):
- home: hero -> "A small council"; council heading -> "A small council, held with care.";
  new Oliver card; Web Networks card drops "second member"; director ask -> "one more".
- about: meta + og description -> "a small council"; "Where we are" names three members;
  director ask -> "One more arm's-length director"; Web Networks drops "second member";
  new Oliver profile.
- support: director ask -> "One more arm's-length director".
- base: default meta description -> "A small council".
- one-pager (oiatc-onepager-source.html): h1 + "Also seeking" line; OIATC_One_Pager.pdf regenerated.

Oliver card copy (suggested wording, pending Russell's confirmation):
  eyebrow "Council member" / name "Oliver Zielke" / meta "Director · Non-profit technology" /
  body "Former Director at Web Networks, with decades in non-profit and open-source technology.
  Joins the board as OIATC incorporates and grounds its governance."

Flagged, NOT changed (await decision):
- templates/design-system.html.twig (6 occurrences of council-of-two / two-members / second-member
  in the internal component showcase).
- one-pager line "an incorporated not-for-profit with an Elder seat and two arm's-length directors
  (in progress)" - target governance structure, may be unchanged if Oliver is one of those seats.

3 cards now sit in a 2-column grid on both home and /about (2 on row 1, Oliver in the left
column of row 2 at the same width). Stop before deploy for review.

## 2026-06-15 Honesty pass (true status and tense)

Rule applied: nothing aspirational, prototype, demo, or planned is stated as a delivered
present fact. Built + verified locally; NOT deployed. (Same working tree as the council update.)

HOME (home.html.twig):
- Hero: "improving the systems that serve First Nations" -> "so First Nations choose the systems that serve them" (purpose, not claimed impact).
- Anishinaabemowin card: badge "Flagship · live" -> "Flagship · in build"; body now reads corpus now / Lesson 1 in prototype / games returning at relaunch / doll in design / story nights to come.
- Community knowledge card: "explainers members actually use" -> "explainers for members"; "Cited by local press" -> "Used by a local reporter".
- Anokii card: "Nation-governed... Two vantages live" -> "designed to be Nation-governed... Two OIATC-run reference vantages running".
- Support line + Funds-held fact: "named trusteeship" -> "trusteeship being arranged / before we accept funds".
- Oliver bio: "decades in" -> "a background in".

/about: Anokii one-liner -> "designed to be Nation-governed"; "explainers members actually use" -> "for members"; trusteeship -> future tense; Oliver inline + profile "decades" -> "a background".

/support: "Two community vantages are live" -> "Two OIATC-run reference vantages are running"; trusteeship -> future tense.

base.html.twig: (default meta already "working to improve" = purpose; left as-is).

/programs (index): flagship badge "live" -> "in build"; Anishinaabemowin body reframed (stages); unlock "What is shipped" -> "What is built"; Anokii body -> "designed to be Nation-governed... OIATC-run reference vantages run today, seeded from public information"; unlock "The live vantages" -> "The reference vantages"; community-knowledge "actually use" -> "for members" and "cited by local press" -> "A local reporter used the Massey explainer".

/programs/anishinaabemowin: meta "already shipped" -> stage-accurate; "It records fluent speakers now" -> "It records one fluent speaker"; "Consent is recorded as data and checked at every step" -> "The speaker agreed to how his recordings are used, and that consent is tracked"; "ships learning tools" -> "builds"; games badge Live -> Prototype.

/anishinaabemowin (program home): meta reframed (stages); "records fluent speakers ... ships learning tools" -> "records one fluent speaker's teachings ... builds learning tools"; games badge Live -> Prototype.

/programs/anokii: meta + lede + core paragraph reframed so the design is Nation-governed but the two running vantages are honestly OIATC-run demonstrations seeded from public information ("no Nation governs them yet"); consent/audit "records ... keeps" -> "is built to record ... keep"; the three instance/vantage badges Live -> Prototype (the Where-your-data-lives explainer stays Live); next-step "Two vantages are live" -> "Two OIATC-run reference vantages are running"; Sagamok/Massey vantages labelled as demonstrations from public information.

/programs/member-resources: "resources a member can actually read/use" -> "can read/use".

Anokii vantage pages (/anokii, /anokii/sagamok): already carried honest disclaimers
(independent of the communities named, public information only, links to official sources);
left as-is. Not changed (flagged earlier): design-system.html.twig sample copy.

Verified: all changed pages 200; new wording present; 0 residual overclaims
(actually use / vantages live / cited by local press / already shipped / checked at every
step / records fluent speakers) on public pages; no em dashes added.
