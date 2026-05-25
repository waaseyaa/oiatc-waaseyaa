/*
 * oiatc-analytics.js — first-party analytics for oiatc.ca.
 * Privacy-respecting: no cookies, no fingerprinting, no third parties.
 * Respects Do Not Track / Global Privacy Control and bails out entirely
 * when set. Sends a pageview on load and a single engagement beacon
 * (max scroll depth + dwell time) when the page is hidden or unloaded.
 */
(function () {
  'use strict';

  if (
    navigator.doNotTrack === '1' ||
    window.doNotTrack === '1' ||
    navigator.globalPrivacyControl === true
  ) {
    return;
  }

  var viewId =
    (crypto.randomUUID && crypto.randomUUID()) ||
    (Date.now().toString(36) + Math.random().toString(36).slice(2));

  var startTime = Date.now();
  var maxScroll = 0;
  var sent = false;
  var ticking = false;

  function send(payload) {
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
  }

  send({ t: 'pageview', p: location.pathname, r: document.referrer || '', v: viewId });

  function computeScroll() {
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
    if (pct < 0) {
      pct = 0;
    }
    if (pct > 100) {
      pct = 100;
    }
    if (pct > maxScroll) {
      maxScroll = pct;
    }
  }

  function onScroll() {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(computeScroll);
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  computeScroll();

  function sendEngagement() {
    if (sent) {
      return;
    }
    sent = true;
    var payload = {
      t: 'engagement',
      v: viewId,
      s: maxScroll,
      d: Date.now() - startTime,
    };
    var json = JSON.stringify(payload);
    if (navigator.sendBeacon) {
      try {
        navigator.sendBeacon(
          '/api/collect',
          new Blob([json], { type: 'application/json' })
        );
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
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      sendEngagement();
    }
  });
  window.addEventListener('pagehide', sendEngagement);
})();
