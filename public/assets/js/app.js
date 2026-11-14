/* ==========================================================================
   PolishedPro — progressive enhancement
   Every feature here is optional: the app works with JavaScript disabled.
   All behaviour is bound via data attributes so the CSP can forbid inline
   scripts entirely.
   ========================================================================== */

(function () {
  'use strict';

  var doc = document;

  function on(root, event, selector, handler) {
    root.addEventListener(event, function (e) {
      var target = e.target.closest(selector);
      if (target && root.contains(target)) handler(e, target);
    });
  }

  function csrfToken() {
    var meta = doc.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  /* ----------------------------------------------------------------------
     Theme toggle — persisted, and applied before paint by the inline-free
     bootstrap in the layout head.
     ---------------------------------------------------------------------- */

  function currentTheme() {
    var stored = null;
    try { stored = localStorage.getItem('pp-theme'); } catch (e) { /* private mode */ }
    if (stored === 'light' || stored === 'dark') return stored;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function applyTheme(theme) {
    doc.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('pp-theme', theme); } catch (e) { /* ignore */ }

    doc.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
    });
  }

  on(doc, 'click', '[data-theme-toggle]', function () {
    applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
  });

  /* ----------------------------------------------------------------------
     Mobile navigation
     ---------------------------------------------------------------------- */

  on(doc, 'click', '[data-nav-toggle]', function (e, btn) {
    var nav = doc.getElementById(btn.getAttribute('aria-controls') || 'primary-nav');
    if (!nav) return;

    var open = nav.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  /* ----------------------------------------------------------------------
     Flash messages: dismiss, and auto-hide the non-critical ones
     ---------------------------------------------------------------------- */

  on(doc, 'click', '[data-dismiss]', function (e, btn) {
    var box = btn.closest('.alert');
    if (box) box.remove();
  });

  doc.querySelectorAll('.flash-stack .alert').forEach(function (alert, i) {
    if (alert.classList.contains('alert--error')) return; // errors stay until dismissed

    setTimeout(function () {
      alert.style.transition = 'opacity 240ms ease, transform 240ms ease';
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-6px)';
      setTimeout(function () { alert.remove(); }, 260);
    }, 5200 + i * 400);
  });

  /* ----------------------------------------------------------------------
     Close open <details> dropdowns on outside click / Escape
     ---------------------------------------------------------------------- */

  doc.addEventListener('click', function (e) {
    doc.querySelectorAll('details.dropdown[open], details.confirm[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });

  doc.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;

    doc.querySelectorAll('details.dropdown[open], details.confirm[open]').forEach(function (d) {
      d.removeAttribute('open');
      var summary = d.querySelector('summary');
      if (summary) summary.focus();
    });
  });

  /* ----------------------------------------------------------------------
     Favourite toggle — optimistic, reverts if the request fails
     ---------------------------------------------------------------------- */

  on(doc, 'click', '[data-favorite]', function (e, btn) {
    e.preventDefault();

    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    var wasOn = btn.classList.contains('is-on');
    btn.classList.toggle('is-on', !wasOn);
    btn.setAttribute('aria-pressed', String(!wasOn));

    fetch(btn.dataset.favorite, {
      method: 'POST',
      headers: {
        'X-CSRF-Token': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    })
      .then(function (res) {
        if (res.status === 401) {
          window.location.href = btn.dataset.loginUrl || '/login';
          throw new Error('unauthenticated');
        }
        if (!res.ok) throw new Error('request failed');
        return res.json();
      })
      .then(function (data) {
        var on = !!data.favorited;
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-pressed', String(on));
        btn.setAttribute('aria-label', on ? 'Remove from saved artists' : 'Save this artist');
      })
      .catch(function () {
        // Put it back the way it was.
        btn.classList.toggle('is-on', wasOn);
        btn.setAttribute('aria-pressed', String(wasOn));
      })
      .finally(function () {
        btn.dataset.busy = '0';
      });
  });

  /* ----------------------------------------------------------------------
     Booking form: reflect the chosen slot in the summary panel
     ---------------------------------------------------------------------- */

  on(doc, 'change', '[data-slot-input]', function (e, input) {
    var target = doc.querySelector('[data-slot-display]');
    if (target) target.textContent = input.dataset.label || input.value;

    var submit = doc.querySelector('[data-booking-submit]');
    if (submit) submit.removeAttribute('disabled');
  });

  /* ----------------------------------------------------------------------
     Filter forms: submit on change so filtering feels immediate
     ---------------------------------------------------------------------- */

  on(doc, 'change', '[data-autosubmit] select, [data-autosubmit] input[type="checkbox"], [data-autosubmit] input[type="radio"]', function (e, field) {
    var form = field.closest('form');
    if (form) form.submit();
  });

  /* ----------------------------------------------------------------------
     Search box: debounce, then submit
     ---------------------------------------------------------------------- */

  doc.querySelectorAll('[data-search-input]').forEach(function (input) {
    var timer = null;

    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        var form = input.closest('form');
        if (form) form.submit();
      }, 550);
    });
  });

  /* ----------------------------------------------------------------------
     Password strength hint (guidance only — the server enforces the policy)
     ---------------------------------------------------------------------- */

  doc.querySelectorAll('[data-strength-for]').forEach(function (meter) {
    var input = doc.getElementById(meter.dataset.strengthFor);
    if (!input) return;

    var fill = meter.querySelector('.strength__fill');
    var label = meter.querySelector('.strength__label');
    var words = ['Too short', 'Weak', 'Getting there', 'Good', 'Strong'];

    input.addEventListener('input', function () {
      var value = input.value;
      var score = 0;

      if (value.length >= 10) score++;
      if (value.length >= 14) score++;
      if (/[a-z]/.test(value) && /[A-Z0-9]/.test(value)) score++;
      if (/[^A-Za-z0-9]/.test(value)) score++;
      if (value.length < 10) score = 0;

      if (fill) {
        fill.style.width = (score / 4) * 100 + '%';
        fill.setAttribute('data-level', String(score));
      }
      if (label) label.textContent = value ? words[score] : '';
    });
  });

  /* ----------------------------------------------------------------------
     Prevent double submits
     ---------------------------------------------------------------------- */

  doc.querySelectorAll('form[data-once]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('[type="submit"]');
      if (!button) return;

      // Re-enable if the browser restores the page from bfcache.
      setTimeout(function () {
        button.setAttribute('disabled', 'disabled');
        button.dataset.originalText = button.textContent;
        if (button.dataset.busyText) button.textContent = button.dataset.busyText;
      }, 0);
    });
  });

  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;

    doc.querySelectorAll('form[data-once] [type="submit"][disabled]').forEach(function (button) {
      button.removeAttribute('disabled');
      if (button.dataset.originalText) button.textContent = button.dataset.originalText;
    });
  });

  /* ----------------------------------------------------------------------
     Character counters
     ---------------------------------------------------------------------- */

  doc.querySelectorAll('[data-counter-for]').forEach(function (counter) {
    var input = doc.getElementById(counter.dataset.counterFor);
    if (!input) return;

    var max = parseInt(input.getAttribute('maxlength') || '0', 10);
    if (!max) return;

    var update = function () {
      counter.textContent = input.value.length + ' / ' + max;
    };

    input.addEventListener('input', update);
    update();
  });

  /* ----------------------------------------------------------------------
     Copy-to-clipboard (booking references, 2FA setup keys)
     ---------------------------------------------------------------------- */

  on(doc, 'click', '[data-copy]', function (e, btn) {
    var text = btn.dataset.copy;
    if (!text || !navigator.clipboard) return;

    navigator.clipboard.writeText(text).then(function () {
      var original = btn.textContent;
      btn.textContent = 'Copied';
      setTimeout(function () { btn.textContent = original; }, 1600);
    });
  });

  /* ----------------------------------------------------------------------
     Image preview before upload
     ---------------------------------------------------------------------- */

  doc.querySelectorAll('[data-preview-for]').forEach(function (preview) {
    var input = doc.getElementById(preview.dataset.previewFor);
    if (!input) return;

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file || !/^image\//.test(file.type)) return;

      var url = URL.createObjectURL(file);

      if (preview.tagName === 'IMG') {
        preview.src = url;
      } else {
        preview.style.backgroundImage = 'url(' + url + ')';
        preview.textContent = '';
      }

      preview.classList.remove('hidden');
    });
  });

  /* ----------------------------------------------------------------------
     Schedule editor: add and remove time windows
     ---------------------------------------------------------------------- */

  on(doc, 'click', '[data-add-window]', function (e, btn) {
    var container = doc.querySelector('[data-windows="' + btn.dataset.addWindow + '"]');
    var template = doc.getElementById('window-template');
    if (!container || !template) return;

    var clone = template.content.cloneNode(true);

    clone.querySelectorAll('[name]').forEach(function (field) {
      field.name = field.name.replace('__DAY__', btn.dataset.addWindow);
    });

    container.appendChild(clone);
  });

  on(doc, 'click', '[data-remove-window]', function (e, btn) {
    var row = btn.closest('.time-range');
    if (row) row.remove();
  });

  /* ----------------------------------------------------------------------
     Portfolio lightbox

     Every photo is already a link to the full image, so with JavaScript off
     it opens in a new tab. This just keeps people on the page and lets them
     arrow through the gallery.
     ---------------------------------------------------------------------- */

  var lightbox = null;

  function galleryLinks(from) {
    var scope = from.closest('.gallery') || doc;
    return Array.prototype.slice.call(scope.querySelectorAll('a[data-lightbox]'));
  }

  function closeLightbox() {
    if (!lightbox) return;
    lightbox.el.remove();
    doc.body.style.overflow = lightbox.overflow;
    if (lightbox.opener) lightbox.opener.focus();
    lightbox = null;
  }

  function showAt(index) {
    if (!lightbox) return;

    var total = lightbox.links.length;
    lightbox.index = (index + total) % total;

    var link = lightbox.links[lightbox.index];
    var caption = link.getAttribute('data-caption') || '';

    lightbox.img.src = link.getAttribute('href');
    lightbox.img.alt = caption || 'Portfolio photo';
    lightbox.caption.textContent = caption;
    lightbox.caption.hidden = caption === '';
  }

  function openLightbox(link) {
    var links = galleryLinks(link);
    if (links.length === 0) return;

    var el = doc.createElement('div');
    el.className = 'lightbox';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-label', 'Photo viewer');

    var close = doc.createElement('button');
    close.type = 'button';
    close.className = 'lightbox__close';
    close.setAttribute('aria-label', 'Close');
    close.textContent = '\u2715';

    var img = doc.createElement('img');

    var caption = doc.createElement('p');
    caption.className = 'lightbox__caption';

    el.appendChild(close);
    el.appendChild(img);
    el.appendChild(caption);

    if (links.length > 1) {
      ['prev', 'next'].forEach(function (dir) {
        var btn = doc.createElement('button');
        btn.type = 'button';
        btn.className = 'lightbox__nav lightbox__nav--' + dir;
        btn.setAttribute('aria-label', dir === 'prev' ? 'Previous photo' : 'Next photo');
        btn.textContent = dir === 'prev' ? '\u2039' : '\u203A';
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          showAt(lightbox.index + (dir === 'prev' ? -1 : 1));
        });
        el.appendChild(btn);
      });
    }

    close.addEventListener('click', closeLightbox);

    // A click on the backdrop closes; a click on the photo itself does not.
    el.addEventListener('click', function (e) {
      if (e.target === el) closeLightbox();
    });

    doc.body.appendChild(el);

    lightbox = {
      el: el,
      img: img,
      caption: caption,
      links: links,
      index: 0,
      opener: link,
      overflow: doc.body.style.overflow
    };

    doc.body.style.overflow = 'hidden';
    showAt(links.indexOf(link));
    close.focus();
  }

  on(doc, 'click', 'a[data-lightbox]', function (e, link) {
    // Leave modified clicks alone — someone asking for a new tab should get one.
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

    e.preventDefault();
    openLightbox(link);
  });

  doc.addEventListener('keydown', function (e) {
    if (!lightbox) return;

    if (e.key === 'Escape') { closeLightbox(); }
    else if (e.key === 'ArrowLeft')  { showAt(lightbox.index - 1); }
    else if (e.key === 'ArrowRight') { showAt(lightbox.index + 1); }
  });

  /* ----------------------------------------------------------------------
     PayPal subscription buttons. Server confirmation owns all access changes.
     ---------------------------------------------------------------------- */

  function setupPaypalSubscriptionButtons() {
    var containers = doc.querySelectorAll('[data-paypal-subscription]');
    if (!containers.length) return;

    containers.forEach(function (container) {
      var button = container.querySelector('.paypal-subscription__button');
      var status = container.querySelector('[data-paypal-status]');
      if (!button || !window.paypal || !window.paypal.Buttons) {
        if (status) status.textContent = 'PayPal could not load. Refresh the page or try again later.';
        return;
      }

      window.paypal.Buttons({
        style: { layout: 'vertical', shape: 'rect', label: 'paypal', height: 44 },
        createSubscription: function (_data, actions) {
          var subscription = {
            plan_id: container.getAttribute('data-plan-id'),
            custom_id: container.getAttribute('data-customer-reference')
          };
          var startTime = container.getAttribute('data-start-time');
          if (startTime) subscription.start_time = startTime;
          return actions.subscription.create(subscription);
        },
        onApprove: function (data) {
          if (status) status.textContent = 'PayPal approval received. Confirming the first payment…';

          var body = new URLSearchParams();
          body.set('_token', csrfToken());
          body.set('subscription_id', data.subscriptionID || '');

          return fetch(container.getAttribute('data-confirm-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
          }).then(function (response) {
            return response.json().then(function (result) {
              if (!response.ok && response.status !== 202) {
                throw new Error(result.message || 'The subscription could not be verified.');
              }
              return result;
            });
          }).then(function (result) {
            if (status) status.textContent = result.message || 'PayPal is confirming your payment.';
            if (result.status === 'active') window.location.reload();
          }).catch(function () {
            if (status) status.textContent = 'PayPal approval was received, but we could not confirm it yet. Refresh this page shortly; access starts only after payment confirmation.';
          });
        },
        onCancel: function () {
          if (status) status.textContent = 'Checkout cancelled. Nothing has been activated.';
        },
        onError: function () {
          if (status) status.textContent = 'PayPal checkout could not start. Please try again later.';
        }
      }).render(button).catch(function () {
        if (status) status.textContent = 'PayPal checkout could not load. Refresh the page or try again later.';
      });
    });
  }

  /* ----------------------------------------------------------------------
     Boot
     ---------------------------------------------------------------------- */

  applyTheme(currentTheme());
  setupPaypalSubscriptionButtons();
})();
