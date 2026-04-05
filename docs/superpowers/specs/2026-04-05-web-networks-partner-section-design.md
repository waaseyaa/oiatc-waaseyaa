# Web Networks Infrastructure Partner Section

**Date:** 2026-04-05
**Status:** Approved
**Scope:** Add Web Networks as infrastructure partner on the About page

## Context

OIATC is in its founding stage. Greg MacKenzie of Web Networks (web.net) has agreed to support the organization. Web Networks is a Toronto-based non-profit worker co-op founded in 1987, one of Canada's first ISPs, with all infrastructure on Canadian soil. Their client list includes the Federation of Canadian Municipalities, the Legislative Assembly of Nunavut, and Nunavut Public Library Services. They are a founding member of the Association for Progressive Communications.

Web Networks provides the credibility anchor that OIATC needs as a new organization. The data sovereignty angle is central: OIATC's infrastructure being hosted on Canadian-owned, open-source systems with no foreign cloud dependencies is a concrete expression of the Council's mission.

The long-term vision is infrastructure on First Nations land. Web Networks is the bridge until that is possible.

## Design

### Placement

New section on `templates/about.html.twig`, inserted **after** the Purpose/Relationship to Nations card grid and **before** the founding charter link.

### Content

**Section heading:** "Infrastructure Partner" (with eyebrow style consistent with page header)

**Card contents:**
- Web Networks SVG logo (left side, ~180px max width)
- "Web Networks" as a linked heading to https://web.net
- One-liner: "Non-profit worker co-op, founded 1987, one of Canada's first ISPs. All infrastructure hosted on Canadian soil."
- Sovereignty paragraph: "Indigenous data sovereignty starts with where data physically lives. OIATC's infrastructure is hosted through Web Networks in Toronto, on Canadian-owned, open-source systems with no foreign cloud dependencies. The long-term vision is to build infrastructure on First Nations land. Until that's possible, Web Networks provides the foundation: 38 years of serving non-profits, governments, and Indigenous institutions, including the Legislative Assembly of Nunavut and Nunavut Public Library Services."

### Layout

- Full-width card using existing `.card--surface` class
- Horizontal layout: logo on left, text on right
- Stacks vertically on mobile (logo above text)
- `.reveal` class for scroll animation (consistent with site)

### Assets

- Download Web Networks SVG logo from `https://web.net/themes/custom/webnetv2/logo.svg`
- Save to `public/images/partners/web-networks-logo.svg`

### CSS

- Minimal additions to `public/css/site.css` for the partner card layout
- Use existing design tokens (--paper, --text, --muted, --copper for the link)
- Logo constrained to ~180px width, vertically centered with text
- Responsive breakpoint to stack vertically on small screens

## What We Are Not Doing

- No new page or route
- No database or entity changes
- No mention of Greg MacKenzie on the site
- No standalone Partners page (can be added later if more partners join)
- No changes to other pages

## Files Modified

| File | Change |
|------|--------|
| `templates/about.html.twig` | Add infrastructure partner section |
| `public/css/site.css` | Add partner card layout styles |
| `public/images/partners/web-networks-logo.svg` | New asset (downloaded from web.net) |
