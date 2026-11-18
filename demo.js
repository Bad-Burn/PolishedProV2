'use strict';

const artists = [
  {
    id: 'mariel', name: 'Mariel Santos Nails', specialty: 'Nails', area: 'Makati', rating: '4.9', image: 'nails',
    photo: 'A manicure with dark polish and warm-toned nail art',
    bio: 'Structured gel, clean lines, and hand-painted nail art.',
    services: [
      { id: 'gel', name: 'Structured gel manicure', price: 950, minutes: 90 },
      { id: 'pedicure', name: 'Classic pedicure', price: 700, minutes: 60 },
    ],
  },
  {
    id: 'jonas', name: 'Jonas Rivera Hair', specialty: 'Hair', area: 'Quezon City', rating: '4.8', image: 'hair',
    photo: 'Long, styled hair with warm highlights',
    bio: 'Personalized haircuts, styling, and lived-in color.',
    services: [
      { id: 'styling', name: 'Wash and styling', price: 850, minutes: 60 },
      { id: 'haircut', name: 'Haircut and styling', price: 1200, minutes: 75 },
    ],
  },
  {
    id: 'andrea', name: 'Andrea Lim Makeup', specialty: 'Make-up', area: 'Pasig', rating: '5.0', image: 'makeup',
    photo: 'An artist applying makeup to a client',
    bio: 'Soft-glam makeup for events, shoots, and celebrations.',
    services: [
      { id: 'event', name: 'Event make-up', price: 2500, minutes: 90 },
      { id: 'trial', name: 'Bridal trial', price: 3500, minutes: 120 },
    ],
  },
];
const timeSlots = ['10:00 AM', '12:00 PM', '2:00 PM', '4:00 PM'];
const cycles = {
  monthly: { amount: 149, label: 'Monthly', period: 'month' },
  yearly: { amount: 1490, label: 'Yearly', period: 'year' },
};
const timeZone = 'Asia/Manila';
const currency = new Intl.NumberFormat('en-PH', {
  style: 'currency', currency: 'PHP', maximumFractionDigits: 0, minimumFractionDigits: 0,
});
const storageKey = 'polishedpro-presentation-v1';
const main = document.querySelector('main');
const htmlEscapes = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
const escapeHtml = value => String(value).replace(/[&<>"']/g, char => htmlEscapes[char]);
const shortDate = new Intl.DateTimeFormat('en-PH', { timeZone, month: 'short', day: 'numeric' });
const fullDate = new Intl.DateTimeFormat('en-PH', { timeZone, month: 'short', day: 'numeric', year: 'numeric' });
const weekday = new Intl.DateTimeFormat('en-PH', { timeZone, weekday: 'short' });

function dayKey(offset = 0) {
  const date = new Date(Date.now() + (offset * 24 + 8) * 60 * 60 * 1000);
  return date.toISOString().slice(0, 10);
}

function dateOf(key) {
  return new Date(key + 'T12:00:00+08:00');
}

function paidThrough(key, cycle) {
  const start = dateOf(key);
  const months = cycle === 'yearly' ? 12 : 1;
  const first = new Date(Date.UTC(start.getUTCFullYear(), start.getUTCMonth() + months, 1));
  const last = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0));
  first.setUTCDate(Math.min(start.getUTCDate(), last.getUTCDate()));
  return first.toISOString().slice(0, 10);
}

function newDemo() {
  return {
    trialEnd: dayKey(14),
    bookings: [{
      reference: 'PP-DEMO-001', artistId: 'mariel', serviceId: 'pedicure',
      date: dayKey(2), time: '10:00 AM', status: 'confirmed',
    }],
    payment: null,
  };
}

function loadDemo() {
  try {
    const data = JSON.parse(localStorage.getItem(storageKey));
    if (data && Array.isArray(data.bookings) && data.trialEnd) return data;
  } catch {
    // The presentation can continue with in-memory state if storage is unavailable.
  }
  return newDemo();
}

function saveDemo() {
  try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch { /* In-memory fallback. */ }
}

let state = loadDemo();
let search = { q: '', category: '' };
let adminQuery = '';
let selected = null;
let cycle = state.payment?.cycle ?? 'monthly';
let notice = '';
let verificationTimer;

function route() {
  return (location.hash.slice(1) || 'home').split('/');
}

function go(destination) {
  if (location.hash === '#' + destination) render(true);
  else location.hash = destination;
}

function currentArtist(id) {
  return artists.find(artist => artist.id === id);
}

function heading(title, subtitle) {
  return '<header class="section-head stack"><h1 class="section-title">' + title + '</h1><p class="lede">' + subtitle + '</p></header>';
}

function page(title, body, extra = '') {
  document.title = title + ' | PolishedPro demo';
  main.innerHTML = '<div class="wrap section ' + extra + '">' + body + '</div>';
}

function artistCard(artist) {
  const startingPrice = Math.min(...artist.services.map(service => service.price));
  return `<article class="listing">
    <img src="images/${artist.image}.webp" alt="${artist.photo}">
    <div class="listing-copy stack">
      <div class="row between"><span class="chip">${artist.specialty}</span><span class="rating">★ ${artist.rating}</span></div>
      <h3><a href="#profile/${artist.id}">${artist.name}</a></h3>
      <p class="small muted">${artist.area} · From ${currency.format(startingPrice)}</p>
    </div>
  </article>`;
}

function home() {
  document.title = 'PolishedPro | Presentation demo';
  main.innerHTML = `<div class="wrap">
    <section class="hero">
      <div class="hero-copy stack">
        <p class="eyebrow">Metro Manila &amp; nearby</p>
        <h1>Find your next <em class="accent">beauty appointment.</em></h1>
        <p class="lede">Browse independent beauty professionals, compare services and prices, and request a time that works for you.</p>
        <div class="actions"><a class="button primary" href="#artists">Find an artist</a><a class="button ghost" href="#pricing">See artist pricing</a></div>
      </div>
      <div class="hero-photos">
        <img src="images/nails.webp" alt="A finished manicure with dark polish and nail art">
        <img src="images/hair.webp" alt="Long, styled hair with warm highlights">
        <img src="images/skin.webp" alt="A client receiving a facial treatment">
      </div>
    </section>
    <section class="section-short">
      <header class="section-head row between"><h2>Featured artists</h2><a href="#artists">Browse all artists</a></header>
      <div class="grid grid-3">${artists.map(artistCard).join('')}</div>
    </section>
  </div>`;
}

function browse() {
  const categories = artists.map(artist => artist.specialty);
  const query = search.q.toLowerCase();
  const results = artists.filter(artist =>
    (!search.category || artist.specialty === search.category)
    && [artist.name, artist.specialty, artist.area, ...artist.services.map(service => service.name)]
      .some(label => label.toLowerCase().includes(query))
  );
  page('Browse artists', `${heading('Browse artists', 'Search by artist, service, or area.')}
    <form class="filter" id="artist-search">
      <label>Search<input type="search" name="query" placeholder="Gel manicure, hairstylist, Makati…" value="${escapeHtml(search.q)}"></label>
      <label>Service category<select name="category"><option value="">All categories</option>
        ${categories.map(cat => `<option ${cat === search.category ? 'selected' : ''}>${cat}</option>`).join('')}
      </select></label>
      <button class="button primary">Search</button>
    </form>
    <p class="muted small section-head" role="status">${results.length} ${results.length === 1 ? 'artist' : 'artists'}</p>
    ${results.length ? `<div class="grid grid-3">${results.map(artistCard).join('')}</div>`
      : '<div class="card stack"><p>No artists match your search.</p><button type="button" class="button" data-action="clear-search">Clear filters</button></div>'}`);
}

function profile(artist) {
  page(artist.name, `<div class="split">
    <section class="stack">
      <a href="#artists">← Browse artists</a>
      <div class="chips"><span class="chip">${artist.specialty}</span><span class="rating">★ ${artist.rating}</span><span class="muted small">${artist.area}</span></div>
      <h1 class="section-title">${artist.name}</h1>
      <p class="lede">${artist.bio}</p>
      <h2>Service menu</h2>
      ${artist.services.map(service => `<article class="card service">
        <div class="stack"><h3>${service.name}</h3><p class="muted">${service.minutes} min · ${currency.format(service.price)}</p></div>
        <a class="button primary" href="#book/${artist.id}/${service.id}">Choose a time</a>
      </article>`).join('')}
    </section>
    <img class="profile-photo" src="images/${artist.image}.webp" alt="${artist.photo}">
  </div>`);
}

function slotTaken(artist, date, time) {
  return state.bookings.some(booking => booking.artistId === artist && booking.date === date && booking.time === time);
}

function book(artist, service) {
  const key = artist.id + '/' + service.id;
  if (selected?.key !== key || selected.date < dayKey(1)) {
    selected = { key, date: dayKey(1), time: '' };
  }
  const days = Array.from({ length: 5 }, (_, index) => dayKey(index + 1));
  page('Book an appointment', `<div class="split">
    <section class="stack">
      <a href="#profile/${artist.id}">← ${artist.name}</a>
      <p class="eyebrow">Book an appointment</p>
      <h1 class="section-title">${service.name}</h1>
      <p class="muted">${artist.name} · ${service.minutes} min</p>
      <h2>Pick a date</h2>
      <div class="picker" role="group" aria-label="Appointment date">
        ${days.map(date => `<button type="button" class="button" data-action="date" data-value="${date}"
          aria-pressed="${date === selected.date}">${weekday.format(dateOf(date))} ${shortDate.format(dateOf(date))}</button>`).join('')}
      </div>
      <h2>Pick a time</h2>
      <div class="picker" role="group" aria-label="Appointment time">
        ${timeSlots.map(time => `<button type="button" class="button" data-action="time" data-value="${time}"
          aria-pressed="${time === selected.time}" ${slotTaken(artist.id, selected.date, time) ? 'disabled' : ''}>${time}</button>`).join('')}
      </div>
    </section>
    <aside class="card stack">
      <h2>Your appointment</h2>
      <dl>
        <div><dt>Date</dt><dd>${fullDate.format(dateOf(selected.date))}</dd></div>
        <div><dt>Time</dt><dd>${selected.time || 'Choose a time'}</dd></div>
        <div><dt>Service price</dt><dd>${currency.format(service.price)}</dd></div>
      </dl>
      <p class="muted small">Clients pay their artist directly.</p>
      <button type="button" class="button primary" data-action="request" ${selected.time ? '' : 'disabled'}>Request this slot</button>
    </aside>
  </div>`);
}

function bookingStatus(booking) {
  return booking.status === 'confirmed'
    ? '<span class="chip chip-ok">Confirmed</span>'
    : '<span class="chip chip-wait">Awaiting confirmation</span>';
}

function bookingList(artistView) {
  const list = state.bookings.slice().reverse().map(booking => {
    const artist = currentArtist(booking.artistId);
    const service = artist.services.find(item => item.id === booking.serviceId);
    return `<li class="row between">
      <div><strong>${service.name}</strong>
        <p class="muted small">${artist.name} · ${fullDate.format(dateOf(booking.date))} · ${booking.time}</p>
        <p class="muted small reference">${escapeHtml(booking.reference)}</p>
      </div>
      <div class="actions">${bookingStatus(booking)}
        ${artistView && booking.status === 'pending'
          ? `<button type="button" class="button primary" data-action="confirm" data-value="${escapeHtml(booking.reference)}">Confirm booking</button>` : ''}
      </div>
    </li>`;
  }).join('');
  return `<ul class="booking-list">${list}</ul>`;
}

function client() {
  page('My bookings', `${heading('My bookings', 'See your appointment requests and confirmed bookings.')}
    ${notice ? `<p class="banner ok section-head" role="status">${escapeHtml(notice)}</p>` : ''}
    <section class="card stack">
      <div class="row between"><h2>Upcoming appointments</h2><a class="button" href="#artists">Find an artist</a></div>
      ${bookingList(false)}
    </section>`);
}

function artistDashboard() {
  page('Artist bookings', `${heading('Artist bookings', 'Open the request queue to confirm a client’s appointment.')}
    <div class="stack">
      <div class="actions"><a class="button primary" href="#billing">Plan &amp; billing</a></div>
      <section class="card stack"><h2>Booking queue</h2>${bookingList(true)}</section>
    </div>`);
}

function admin() {
  const bookings = state.bookings.filter(item => item.reference.toUpperCase().includes(adminQuery.toUpperCase())).reverse();
  page('Administrator bookings', `${heading('Administrator bookings', 'Look up an appointment using the same reference shown to the client and artist.')}
    <section class="card stack">
      <form class="filter" id="admin-search">
        <label>Booking reference<input type="search" name="query" value="${escapeHtml(adminQuery)}" placeholder="PP-DEMO-002"></label>
        <button class="button primary">Find booking</button>
      </form>
      <div class="table-scroll"><table>
        <thead><tr><th scope="col">Reference</th><th scope="col">Artist</th><th scope="col">Appointment</th><th scope="col">Status</th></tr></thead>
        <tbody>${bookings.map(booking => `<tr>
          <td>${escapeHtml(booking.reference)}</td><td>${currentArtist(booking.artistId).name}</td>
          <td>${fullDate.format(dateOf(booking.date))} · ${booking.time}</td><td>${bookingStatus(booking)}</td>
        </tr>`).join('')}</tbody>
      </table></div>
      <p class="muted small" role="status">${bookings.length ? bookings.length + ' matching' : 'No matching'} ${bookings.length === 1 ? 'booking' : 'bookings'}</p>
    </section>`);
}

function billingPicker() {
  return `<div class="picker" role="group" aria-label="Billing cycle">
    ${Object.entries(cycles).map(([id, term]) => `<button type="button" class="button" data-action="cycle" data-value="${id}" aria-pressed="${id === cycle}">${term.label}</button>`).join('')}
  </div>`;
}

function planCard(publicPage) {
  const term = cycles[cycle];
  const paid = ['active', 'ending'].includes(state.payment?.status);
  return `<section class="card stack">
    <div class="row between"><h2>Premium</h2>${cycle === 'yearly' ? '<span class="chip chip-ok">Save 17%</span>' : ''}</div>
    <p class="price">${currency.format(term.amount)} <small>/ ${term.period}</small></p>
    <ul class="items">
      <li>Up to 3 active services</li><li>6 portfolio photos</li><li>Standard search placement</li>
      <li>Booking calendar and client messages</li><li>Reviews and ratings</li>
    </ul>
    ${publicPage ? '<a class="button primary" href="#billing">Open demo billing</a>'
      : paid ? '<button type="button" class="button" disabled>Current plan</button>'
      : state.payment?.status === 'pending' ? '<button type="button" class="button" disabled>Confirming payment…</button>'
      : '<a class="button primary" href="#checkout">Continue with PayPal</a>'}
  </section>`;
}

function pricing() {
  page('Artist pricing', `${heading('One Premium artist plan', 'A 14-day onboarding trial with no card required. PolishedPro takes 0% commission on appointments.')}
    <div class="split">
      <div class="stack">${billingPicker()}${planCard(true)}</div>
      <aside class="card stack"><h2>How billing works</h2><p class="muted">Artist subscriptions renew monthly or yearly through PayPal.</p>
      <p class="muted">Client accounts are free. Clients pay artists directly for their appointments.</p></aside>
    </div>`);
}

function billing() {
  const payment = state.payment;
  const paid = ['active', 'ending'].includes(payment?.status);
  const trial = dayKey() <= state.trialEnd;
  const label = paid ? 'Premium' : trial ? 'Premium trial' : 'No active plan';
  const periodLabel = payment?.status === 'ending' ? 'Access ends' : 'Renews';
  page('Plan & billing', `${heading('Plan &amp; billing', 'Manage your Premium plan and billing cycle.')}
    <div class="stack">
      <div class="actions"><a class="button" href="#artist">← Artist bookings</a></div>
      <p class="banner">Checkout simulation. Demo payments are recorded in this browser.</p>
      ${notice ? `<p class="banner ok" role="status">${escapeHtml(notice)}</p>` : ''}
      ${payment?.status === 'pending' ? '<p class="banner wait" role="status">Confirming your first payment…</p>' : ''}
      <div class="split">
        <section class="card stack">
          <p class="eyebrow">Current plan</p>
          <h2>${label}</h2>
          ${paid ? `<p>${currency.format(cycles[payment.cycle].amount)} per ${cycles[payment.cycle].period} · ${periodLabel} ${fullDate.format(dateOf(payment.ends))}</p>`
            : trial ? `<p>Trial ends ${fullDate.format(dateOf(state.trialEnd))}</p>` : '<p>Subscribe to resume accepting new bookings.</p>'}
          <div class="meter"><label for="services-used">Active services · 2 of 3</label><progress id="services-used" value="2" max="3">2 of 3</progress></div>
          <div class="meter"><label for="portfolio-used">Portfolio photos · 3 of 6</label><progress id="portfolio-used" value="3" max="6">3 of 6</progress></div>
          ${payment?.status === 'active' ? '<a class="button ghost" href="#cancel-plan">Cancel plan</a>' : ''}
        </section>
        <div class="stack">${billingPicker()}${planCard(false)}</div>
      </div>
      ${trial && !paid ? '<p class="muted small">The 14-day onboarding trial is separate. Your first PayPal charge starts when you approve the subscription.</p>' : ''}
      ${paid ? `<section class="card stack"><h2>Payment history</h2>
        <div class="table-scroll"><table><thead><tr><th scope="col">Date</th><th scope="col">Reference</th><th scope="col">Amount</th><th scope="col">Status</th></tr></thead>
        <tbody><tr><td>${fullDate.format(dateOf(payment.paidOn))}</td><td>DEMO-PAYPAL-001</td><td>${currency.format(cycles[payment.cycle].amount)}</td><td><span class="chip chip-ok">Completed</span></td></tr></tbody></table></div>
      </section>` : ''}
    </div>`);
}

function checkout() {
  if (state.payment) return billing();
  const term = cycles[cycle];
  page('PayPal checkout simulation', `<section class="card checkout stack">
    <p class="eyebrow">PayPal checkout simulation</p>
    <h1 class="section-title">Approve Premium</h1>
    <p class="lede">Try the subscription approval step with a simulated payment.</p>
    <dl><div><dt>Billing cycle</dt><dd>${term.label}</dd></div><div><dt>Due at approval</dt><dd>${currency.format(term.amount)}</dd></div>
    <div><dt>Renews</dt><dd>${currency.format(term.amount)} per ${term.period}</dd></div></dl>
    <div class="actions"><button type="button" class="button primary" data-action="pay">Approve simulated payment</button><button type="button" class="button" data-action="cancel-checkout">Cancel checkout</button></div>
  </section>`);
}

function cancelPlan() {
  if (state.payment?.status !== 'active') return billing();
  page('Cancel renewal', `<section class="card checkout stack">
    <h1 class="section-title">Cancel renewal?</h1>
    <p>You keep Premium access through ${fullDate.format(dateOf(state.payment.ends))}.</p>
    <div class="actions"><button type="button" class="button primary" data-action="cancel-plan">Cancel renewal</button><a class="button" href="#billing">Back to billing</a></div>
  </section>`);
}

function verifyPayment() {
  if (state.payment?.status !== 'pending') return;
  state.payment.status = 'active';
  saveDemo();
  if (route()[0] === 'billing') {
    notice = 'Simulated payment confirmed. Premium is active.';
    render();
  }
}

function render(navigate = false) {
  const [screen, artistId, serviceId] = route();
  const artist = currentArtist(artistId);
  const service = artist?.services.find(item => item.id === serviceId);
  const screens = {
    home, artists: browse, pricing, client, artist: artistDashboard, admin, billing, checkout, 'cancel-plan': cancelPlan,
    profile: () => artist ? profile(artist) : browse(),
    book: () => service ? book(artist, service) : browse(),
  };
  (Object.hasOwn(screens, screen) ? screens[screen] : home)();
  const role = ['billing', 'checkout', 'cancel-plan'].includes(screen) ? 'artist' : screen;
  for (const link of document.querySelectorAll('.roles a')) {
    if (link.hash === '#' + role) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  }
  if (navigate) {
    window.scrollTo(0, 0);
    main.focus({ preventScroll: true });
  }
  notice = '';
}

document.addEventListener('click', event => {
  if (event.target.closest('.skip')) {
    event.preventDefault();
    main.focus();
    return;
  }
  const button = event.target.closest('[data-action]');
  if (!button) return;
  const { action, value } = button.dataset;
  switch (action) {
    case 'reset':
      if (!window.confirm('Reset all bookings and payments in this demo?')) return;
      clearTimeout(verificationTimer);
      state = newDemo();
      saveDemo();
      search = { q: '', category: '' };
      adminQuery = '';
      selected = null;
      cycle = 'monthly';
      go('home');
      return;
    case 'clear-search':
      search = { q: '', category: '' };
      break;
    case 'date':
      selected.date = value;
      selected.time = '';
      break;
    case 'time':
      selected.time = value;
      break;
    case 'request': {
      const [, artistId, serviceId] = route();
      if (!selected?.time || slotTaken(artistId, selected.date, selected.time)) return;
      const reference = 'PP-DEMO-' + String(state.bookings.length + 1).padStart(3, '0');
      state.bookings.push({ reference, artistId, serviceId, date: selected.date, time: selected.time, status: 'pending' });
      selected = null;
      saveDemo();
      notice = 'Booking request sent. Reference ' + reference + '.';
      go('client');
      return;
    }
    case 'confirm': {
      const booking = state.bookings.find(item => item.reference === value && item.status === 'pending');
      if (!booking) return;
      booking.status = 'confirmed';
      saveDemo();
      break;
    }
    case 'cycle':
      if (Object.hasOwn(cycles, value)) cycle = value;
      break;
    case 'pay':
      if (state.payment) return;
      state.payment = { cycle, status: 'pending', paidOn: dayKey(), ends: paidThrough(dayKey(), cycle) };
      saveDemo();
      verificationTimer = setTimeout(verifyPayment, 1700);
      go('billing');
      return;
    case 'cancel-checkout':
      notice = 'Checkout cancelled.';
      go('billing');
      return;
    case 'cancel-plan':
      if (state.payment?.status !== 'active') return;
      state.payment.status = 'ending';
      saveDemo();
      notice = 'Renewal cancelled. Your paid access continues through the period end.';
      go('billing');
      return;
    default:
      return;
  }
  render();
  const replacement = [...main.querySelectorAll('[data-action]')].find(candidate => candidate.dataset.action === action && candidate.dataset.value === value);
  (replacement ?? main).focus({ preventScroll: true });
});

main.addEventListener('submit', event => {
  event.preventDefault();
  const data = new FormData(event.target);
  if (event.target.id === 'artist-search') {
    search = { q: String(data.get('query')).trim(), category: String(data.get('category')) };
    render();
    document.querySelector('#artist-search input').focus();
  } else if (event.target.id === 'admin-search') {
    adminQuery = String(data.get('query')).trim();
    render();
    document.querySelector('#admin-search input').focus();
  }
});

window.addEventListener('hashchange', () => render(true));
if (state.payment?.status === 'pending') verificationTimer = setTimeout(verifyPayment, 1700);
render();
