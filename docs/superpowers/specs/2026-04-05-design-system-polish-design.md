# Design System Polish — Translucent Dark Surfaces

**Date:** 2026-04-05
**Status:** Approved
**Scope:** Replace all cream/light backgrounds with translucent dark surfaces, fix text contrast, set size floors

## Problem

The CSS has a split personality: the Storm dark palette (`:root` variables) defines a dark theme, but many components still use hardcoded cream backgrounds (`rgba(255,251,245,...)`) from an earlier light theme. Text colors calibrated for the wrong surface create contrast failures site-wide.

Key failures:
- `--muted` (#8298b8) gets ~2.8:1 contrast on cream backgrounds (needs 4.5:1)
- `.button-secondary` has white text on cream bg (~1.3:1, invisible)
- `.founder-profile__meta` at 0.82rem is barely legible
- `.card--surface h3` uses dark #1a3a4a that's invisible if bg renders dark
- `.brand__eyebrow` and `.label` at 0.68rem are too small

## Design

### New Surface Tokens

Three translucent dark surface levels, replacing all cream backgrounds:

| Token | Value | Use |
|-------|-------|-----|
| `--paper` | `rgba(22,30,46,0.85)` | Cards, profiles, charter sections (primary surface) |
| `--paper-strong` | `rgba(28,48,72,0.6)` | Statement sections, bands, emphasis surfaces |
| `--paper-deep` | `rgba(14,20,32,0.9)` | Tables, footer, recessed/inset areas |

All surfaces get `border: 1px solid var(--line)` for subtle edge definition and `backdrop-filter: blur(8px)` for the frosted glass effect.

### Component Background Migration

| Component | Current | New |
|-----------|---------|-----|
| `.card` (base) | `var(--paper)` (keep) | No change needed |
| `.card--surface` | `rgba(255,251,245,0.8)` | `var(--paper)` |
| `.card--surface h3` | `color: #1a3a4a` | `color: var(--text)` |
| `.card--surface p, .card--surface .eyebrow` | `color: #3d4f5f` | `color: var(--muted)` |
| `.founder-card` | `rgba(252,248,242,0.99)` | `var(--paper)` |
| `.founder-profile__content` | `rgba(255,251,245,0.82)` | `var(--paper)` |
| `.statement` | `rgba(255,251,245,0.74)` | `var(--paper-strong)` |
| `.section--surface .pillar` | cream gradient | `var(--paper)` |
| `.funding-table` | `rgba(255,251,245,0.86)` | `var(--paper-deep)` |
| `.site-footer` | `rgba(255,251,245,0.66)` | `var(--paper-deep)` |
| `.button-secondary` | `rgba(255,251,245,0.66)` + `color: var(--text)` | `background: transparent; border: 1px solid var(--line-strong); color: var(--text)` |
| `.button-secondary:hover` | `rgba(255,251,245,0.9)` | `background: rgba(180,204,240,0.08); border-color: var(--copper)` |
| `.section--band` | `rgba(255,251,245,0.5)` | `var(--paper-strong)` |

### Text Color Consolidation

Remove all hardcoded dark text colors. One text color system for all surfaces:

| Role | Token | Value |
|------|-------|-------|
| Headings | `var(--text)` | `#dce8f4` |
| Body / secondary | `var(--muted)` | `#94aac8` (bumped from #8298b8 for better contrast) |
| Accent labels (eyebrow) | `var(--copper)` | `#e8a020` |
| Links | `var(--copper)` or `var(--lake)` | `#e8a020` or `#3890b8` |

Selectors to update:
- `.card--surface h3`: `#1a3a4a` to `var(--text)`
- `.card--surface p, .card--surface .eyebrow`: `#3d4f5f` to `var(--muted)`
- `.button-secondary`: `color: var(--text)` (already correct once bg is fixed)
- `.founder-card h2, .founder-card .eyebrow, .founder-card__copy`: Remove Storm Theme overrides, let them inherit `var(--text)` and `var(--muted)`
- `.founder-card__meta`: Remove Storm Theme override
- `.statement p`: Already `var(--muted)`, will work on dark surface
- `.site-footer__brand p`: Already `var(--muted)`, will work on dark surface

### Font Size Floors

| Selector | Current | New |
|----------|---------|-----|
| `.eyebrow` | `0.72rem` | `0.78rem` |
| `.label` | `0.68rem` | `0.75rem` |
| `.brand__eyebrow` | `0.68rem` | `0.75rem` |
| `.founder-profile__meta` | `0.82rem` | `0.85rem` |

### `--muted` Color Bump

Update `--muted` in `:root` from `#8298b8` to `#94aac8`. This improves contrast ratio on dark surfaces from ~4.9:1 to ~5.8:1 while maintaining the blue-grey feel.

### Border Treatment

All translucent surface components get a subtle border for edge definition:

```css
border: 1px solid var(--line);  /* rgba(180,204,240,0.1) */
border-radius: var(--radius);   /* 26px, existing token */
```

Components that already have borders keep their existing treatment. The `var(--line)` border prevents translucent cards from "bleeding" into each other.

### Backdrop Filter

Add `backdrop-filter: blur(8px)` to `.card`, `.statement`, `.charter-section`, `.founder-profile__content`, `.founder-card` for the frosted glass effect.

Include `-webkit-backdrop-filter: blur(8px)` for Safari support.

Fallback: browsers that don't support backdrop-filter will see slightly more transparent cards, which is acceptable.

## What We Are Not Doing

- No layout changes (grid, spacing, structure stay the same)
- No typography changes beyond size floor bumps
- No template/HTML changes (all CSS-only)
- No new pages or components
- No JavaScript changes

## Files Modified

| File | Change |
|------|--------|
| `public/css/site.css` | All changes (surface tokens, backgrounds, text colors, size floors) |

## Verification

After changes, every page should pass this check:
1. No text is invisible or barely legible on its background
2. Cards feel distinct from the page background through translucency and borders
3. The page gradient subtly shows through card surfaces
4. Footer reads clearly
5. All buttons have visible text
6. Eyebrows and labels are legible
