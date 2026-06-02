# Publishing to /news

The news section is entity-first. A post is a `news_post` entity, not a hand
written page. Because the entity is defined (`src/Entity/NewsPost.php`,
registered in `config/entity-types.php`), the framework already gives us
storage, validation, a JSON:API CRUD surface, and admin editing for free. The
public pages (`/news`, `/news/{slug}`, `/news/rss.xml`) and the "Latest
updates" block on explainers are themed in `templates/news/` and the analytics
script.

## A post has five fields

- `title` — the headline.
- `slug` — the url-friendly id. The post lives at `/news/{slug}`. Keep it short and lowercase with hyphens.
- `body` — the post text. HTML is allowed and rendered as-is.
- `published_at` — a unix timestamp. Posts sort newest first by this.
- `related_explainer` — the slug of the explainer this post updates, for example `massey-solar-project` or `robinson-huron-treaty`. This is what ties the post to an explainer and makes it show in that explainer's "Latest updates" block.
- `status` — published or not. Unpublished posts are hidden from the public pages and the feed.

## How to publish

Two ways, no code or deploy needed for a new post.

1. Admin (preferred). Open the admin app, find the News post type, and create
   or edit a post. Fill the fields above and save. It appears on `/news`
   immediately.

2. JSON:API (for scripts). Authenticated `POST /api/news_post` with a
   JSON:API body:

   ```
   POST /api/news_post
   Content-Type: application/vnd.api+json

   {"data":{"type":"news_post","attributes":{
     "title":"Council reaffirms support",
     "slug":"council-reaffirms-support",
     "body":"<p>...</p>",
     "published_at":1779000000,
     "related_explainer":"massey-solar-project",
     "status":true
   }}}
   ```

   Anonymous writes are refused on purpose; authenticate first. Read endpoints
   (`GET /api/news_post`, `GET /api/news_post/{id}`) are also available.

## What happens automatically

- The post shows on `/news`, newest first, and is filterable at `/news?explainer={slug}`.
- It gets its own page at `/news/{slug}` with a link back to its explainer.
- It enters the RSS feed at `/news/rss.xml`.
- The three newest posts for an explainer appear in that explainer's "Latest updates" block (injected by `/js/oiatc-analytics.js`, fed by `/api/explainer-updates?explainer={slug}`).

## Seeded editorial posts

There is no empty-section bootstrap example. A fixed set of editorial posts is
ensured by slug on `/news` read (see `NewsController::announcementPosts()`):
missing ones are created, existing rows are left as the admin last saved them.
The retired placeholder example (slug `massey-solar-ieso-contract-awarded`) is
healed in place to its real copy by `NewsController::healLegacyExample()` where
that old row still exists.
