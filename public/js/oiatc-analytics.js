/*
 * oiatc-analytics.js — first-party analytics + page furniture for oiatc.ca.
 * Privacy-respecting: no cookies, no fingerprinting, no third parties.
 *
 * Tracking beacons (pageview + engagement) respect Do Not Track / Global
 * Privacy Control and are skipped entirely when set. The share control and
 * the aggregate "read count" are NOT tracking (they don't identify the
 * reader), so they still work under DNT.
 */
(function () {
  'use strict';

  var COUNT_FLOOR = 10; // don't show a read count below this (avoids "read 2 times")
  var ARTICLE_RE = /^\/(explainers|positions|practice|news)\//;

  var dnt =
    navigator.doNotTrack === '1' ||
    window.doNotTrack === '1' ||
    navigator.globalPrivacyControl === true;

  // ---------------------------------------------------------------------------
  // Tracking beacons (skipped under Do Not Track)
  // ---------------------------------------------------------------------------
  if (!dnt) {
    var viewId =
      (crypto.randomUUID && crypto.randomUUID()) ||
      (Date.now().toString(36) + Math.random().toString(36).slice(2));

    var startTime = Date.now();
    var maxScroll = 0;
    var sent = false;
    var ticking = false;

    var send = function (payload) {
      try {
        fetch('/api/collect', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
          keepalive: true,
          credentials: 'omit',
        });
      } catch (e) {
        // ignore
      }
    };

    send({ t: 'pageview', p: location.pathname, r: document.referrer || '', v: viewId });

    var computeScroll = function () {
      ticking = false;
      var doc = document.documentElement;
      var body = document.body;
      var scrollTop = window.pageYOffset || doc.scrollTop || (body && body.scrollTop) || 0;
      var viewportHeight = window.innerHeight || doc.clientHeight || 0;
      var documentHeight = Math.max(
        doc.scrollHeight,
        body ? body.scrollHeight : 0,
        doc.offsetHeight,
        body ? body.offsetHeight : 0,
        doc.clientHeight
      );
      if (documentHeight <= 0) {
        return;
      }
      var pct = Math.round(((scrollTop + viewportHeight) / documentHeight) * 100);
      if (pct < 0) pct = 0;
      if (pct > 100) pct = 100;
      if (pct > maxScroll) maxScroll = pct;
    };

    var onScroll = function () {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(computeScroll);
      }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    computeScroll();

    var sendEngagement = function () {
      if (sent) return;
      sent = true;
      var json = JSON.stringify({
        t: 'engagement',
        v: viewId,
        s: maxScroll,
        d: Date.now() - startTime,
      });
      if (navigator.sendBeacon) {
        try {
          navigator.sendBeacon('/api/collect', new Blob([json], { type: 'application/json' }));
          return;
        } catch (e) {
          // fall through to fetch
        }
      }
      try {
        fetch('/api/collect', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: json,
          keepalive: true,
          credentials: 'omit',
        });
      } catch (e) {
        // ignore
      }
    };

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        sendEngagement();
      }
    });
    window.addEventListener('pagehide', sendEngagement);
  }

  // ---------------------------------------------------------------------------
  // Page furniture: share control + aggregate read count (always, incl. DNT)
  // ---------------------------------------------------------------------------
  function shareUrl() {
    var canonical = document.querySelector('link[rel="canonical"]');
    return (canonical && canonical.href) || location.href;
  }

  function copyLink(button) {
    var url = shareUrl();
    var done = function () {
      var original = button.textContent;
      button.textContent = 'Link copied';
      setTimeout(function () {
        button.textContent = original;
      }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done, function () {});
      return;
    }
    try {
      var input = document.createElement('input');
      input.value = url;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      document.body.removeChild(input);
      done();
    } catch (e) {
      // ignore
    }
  }

  function onShare(button) {
    if (navigator.share) {
      navigator
        .share({ title: document.title, url: shareUrl() })
        .catch(function () {});
      return;
    }
    copyLink(button);
  }

  function injectStyles() {
    if (document.getElementById('oiatc-furniture-style')) return;
    var css =
      '.oiatc-share{margin-top:40px;padding-top:24px;border-top:1px solid var(--rule,#d9d2c2);' +
      'display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;' +
      'font-family:var(--sans,system-ui,sans-serif)}' +
      '.oiatc-share__count{font-size:13px;color:var(--ink-mute,#5b6473);font-variant-numeric:tabular-nums}' +
      '.oiatc-share__btn{font-family:inherit;font-size:12px;font-weight:600;letter-spacing:.06em;' +
      'text-transform:uppercase;color:var(--accent,#6a3a1f);background:transparent;' +
      'border:1px solid var(--accent-soft,#b88a5a);border-radius:4px;padding:8px 16px;cursor:pointer;' +
      'transition:background .15s,color .15s}' +
      '.oiatc-share__btn:hover{background:var(--accent,#6a3a1f);color:#fff}' +
      '.oiatc-updates{margin-top:40px;padding-top:24px;border-top:1px solid var(--rule,#d9d2c2);font-family:var(--sans,system-ui,sans-serif)}' +
      '.oiatc-updates__h{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--accent,#6a3a1f);margin:0 0 12px}' +
      '.oiatc-updates__row{display:flex;gap:14px;align-items:baseline;padding:10px 0;border-bottom:1px solid var(--rule-soft,#ebe6d8);color:var(--ink,#0d1420)}' +
      '.oiatc-updates__row:hover{color:var(--accent,#6a3a1f)}' +
      '.oiatc-updates__date{font-family:var(--mono,ui-monospace,monospace);font-size:12px;color:var(--accent,#6a3a1f);white-space:nowrap;font-variant-numeric:tabular-nums}' +
      '.oiatc-updates__t{font-size:16px;line-height:1.4}' +
      '.oiatc-updates__all{display:inline-block;margin-top:14px;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--accent,#6a3a1f);border-bottom:none}';
    var style = document.createElement('style');
    style.id = 'oiatc-furniture-style';
    style.textContent = css;
    document.head.appendChild(style);
  }

  function initFurniture() {
    if (!ARTICLE_RE.test(location.pathname)) return;
    if (document.getElementById('oiatc-furniture')) return;
    var main = document.querySelector('main') || document.body;
    if (!main) return;

    injectStyles();

    var bar = document.createElement('div');
    bar.className = 'oiatc-share';
    bar.id = 'oiatc-furniture';

    var count = document.createElement('span');
    count.className = 'oiatc-share__count';
    count.hidden = true;

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'oiatc-share__btn';
    button.textContent = navigator.share ? 'Share this page' : 'Copy link';
    button.addEventListener('click', function () {
      onShare(button);
    });

    bar.appendChild(count);
    bar.appendChild(button);
    main.appendChild(bar);

    fetch('/api/page-stats?path=' + encodeURIComponent(location.pathname), {
      credentials: 'omit',
    })
      .then(function (r) {
        return r.ok ? r.json() : null;
      })
      .then(function (data) {
        if (!data || typeof data.views !== 'number' || data.views < COUNT_FLOOR) return;
        count.textContent = 'Read ' + data.views.toLocaleString() + ' times';
        count.hidden = false;
      })
      .catch(function () {});
  }

  // "Latest updates" block on explainer pages: newest news posts tagged to
  // this explainer (slug = first path segment after /explainers/).
  function initLatestUpdates() {
    var match = location.pathname.match(/^\/explainers\/([^\/]+)/);
    if (!match) return;
    var slug = match[1];
    var main = document.querySelector('main');
    if (!main || document.getElementById('oiatc-updates')) return;

    fetch('/api/explainer-updates?explainer=' + encodeURIComponent(slug), { credentials: 'omit' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.posts || data.posts.length === 0) return;
        injectStyles();

        var box = document.createElement('aside');
        box.id = 'oiatc-updates';
        box.className = 'oiatc-updates';

        var heading = document.createElement('p');
        heading.className = 'oiatc-updates__h';
        heading.textContent = 'Latest updates';
        box.appendChild(heading);

        data.posts.forEach(function (post) {
          var row = document.createElement('a');
          row.className = 'oiatc-updates__row';
          row.href = '/news/' + encodeURIComponent(post.slug);

          var date = document.createElement('span');
          date.className = 'oiatc-updates__date';
          date.textContent = new Date(post.published_at * 1000).toLocaleDateString('en-CA', {
            year: 'numeric', month: 'short', day: 'numeric',
          });

          var title = document.createElement('span');
          title.className = 'oiatc-updates__t';
          title.textContent = post.title;

          row.appendChild(date);
          row.appendChild(title);
          box.appendChild(row);
        });

        var all = document.createElement('a');
        all.className = 'oiatc-updates__all';
        all.href = '/news?explainer=' + encodeURIComponent(slug);
        all.textContent = 'All news →';
        box.appendChild(all);

        main.appendChild(box);
      })
      .catch(function () {});
  }

  function initPageExtras() {
    initLatestUpdates();
    initFurniture();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPageExtras);
  } else {
    initPageExtras();
  }
})();
