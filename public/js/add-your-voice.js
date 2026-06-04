/*
 * Add-your-voice petition card.
 *
 * Progressive enhancement for templates/partials/add-your-voice.html.twig.
 * Hydrates each card from /api/petition/{slug} (ask, recipient, live count),
 * submits the signature as JSON to /api/petition/sign (same-origin, no third
 * party), and shows a confirmation with a private remove link. No trackers,
 * no external requests. Self-guards so multiple includes init only once.
 */
(function () {
  if (window.__ayvLoaded) return;
  window.__ayvLoaded = true;

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  function fmt(n) {
    try { return Number(n).toLocaleString(); } catch (e) { return String(n); }
  }

  function setCount(card, n) {
    if (n === null || n === undefined) return;
    var box = card.querySelector('[data-ayv-countbox]');
    var el = card.querySelector('[data-ayv-count]');
    if (el) el.textContent = fmt(n);
    if (box) box.hidden = false;
  }

  function hydrate(card, slug) {
    fetch('/api/petition/' + encodeURIComponent(slug), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.ok) return;
        var ask = card.querySelector('[data-ayv-ask]');
        if (ask && !ask.textContent.trim() && data.the_ask) ask.textContent = data.the_ask;
        var rec = card.querySelector('[data-ayv-recipient]');
        if (rec && !rec.textContent.trim() && data.recipient) rec.textContent = 'To: ' + data.recipient;
        setCount(card, data.count);
      })
      .catch(function () { /* count just stays hidden; form still works */ });
  }

  function bind(card) {
    if (card.__ayvReady) return;
    card.__ayvReady = true;

    var slug = card.getAttribute('data-slug') || '';
    hydrate(card, slug);

    var form = card.querySelector('[data-ayv-form]');
    if (!form) return;
    var msg = card.querySelector('[data-ayv-msg]');
    var submit = card.querySelector('[data-ayv-submit]');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (msg) { msg.textContent = ''; msg.classList.remove('is-error'); }

      var payload = {
        campaign: slug,
        name: (form.elements['name'] || {}).value || '',
        email: (form.elements['email'] || {}).value || '',
        member_flag: (form.querySelector('input[name="member_flag"]:checked') || {}).value || 'supporter',
        comment: (form.elements['comment'] || {}).value || '',
        show_name_publicly: !!(form.querySelector('input[name="show_name_publicly"]') || {}).checked,
        consent: !!(form.querySelector('input[name="consent"]') || {}).checked,
        website: (form.elements['website'] || {}).value || '' // honeypot
      };

      if (!payload.consent) {
        if (msg) { msg.textContent = 'Please check the consent box to add your voice.'; msg.classList.add('is-error'); }
        return;
      }

      if (submit) { submit.disabled = true; submit.textContent = 'Adding…'; }

      fetch('/api/petition/sign', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
        .then(function (res) {
          if (!res.body || !res.body.ok) {
            var err = (res.body && res.body.error) || 'Something went wrong. Please try again.';
            if (msg) { msg.textContent = err; msg.classList.add('is-error'); }
            if (submit) { submit.disabled = false; submit.textContent = 'Add your voice'; }
            return;
          }
          finish(card, res.body);
        })
        .catch(function () {
          if (msg) { msg.textContent = 'Could not reach the server. Please try again.'; msg.classList.add('is-error'); }
          if (submit) { submit.disabled = false; submit.textContent = 'Add your voice'; }
        });
    });
  }

  function finish(card, body) {
    card.classList.add('is-done');
    setCount(card, body.count);

    var done = card.querySelector('[data-ayv-donecount]');
    if (done && body.count !== null && body.count !== undefined) {
      done.textContent = 'You are one of ' + fmt(body.count) + ' so far.';
    }

    var removeWrap = card.querySelector('[data-ayv-removewrap]');
    var removeLink = card.querySelector('[data-ayv-remove]');
    if (removeWrap && removeLink && body.manage_url) {
      removeLink.setAttribute('href', body.manage_url);
      removeWrap.hidden = false;
    }
  }

  ready(function () {
    var cards = document.querySelectorAll('[data-ayv]');
    for (var i = 0; i < cards.length; i++) bind(cards[i]);
  });
})();
