/* ============================================================================
   PARKit — common.js
   Shared across every page: ticker, nav scroll state, mobile menu, silk reveals.
   Adapted from the NovaPay template's motion language (--silk easing, IO reveals).
============================================================================ */

// ---- Live availability ticker (falls back to demo numbers if API absent) ----
const tickerDemoData = [
  { sym: 'Zone A', price: '40 places', chg: 'Entrée principale', up: true },
  { sym: 'Zone B', price: '25 places', chg: 'Niveau couvert', up: true },
  { sym: 'Zone C', price: '30 places', chg: 'Longue durée', up: true },
  { sym: 'Tarif', price: '2.5 DT / h', chg: 'dès la 1ère heure', up: true },
  { sym: 'Retard toléré', price: '15 min', chg: 'inclus par défaut', up: true },
  { sym: 'Accès', price: 'Auto', chg: 'reconnaissance de plaque', up: true },
];

function renderTicker(items) {
  const track = document.getElementById('tickerTrack');
  if (!track) return;
  track.innerHTML = '';
  [...items, ...items].forEach(t => {
    const el = document.createElement('div');
    el.className = 'ticker-item';
    el.innerHTML = `<span class="ticker-sym">${t.sym}</span><span class="ticker-price">${t.price}</span><span class="${t.up ? 'ticker-up' : 'ticker-dn'}">${t.chg}</span>`;
    track.appendChild(el);
  });
}
renderTicker(tickerDemoData);

// If a backend is available, replace with live zone availability
fetch('api/zones.php').then(r => r.ok ? r.json() : null).then(data => {
  if (!data || !data.zones) return;
  const items = data.zones.map(z => ({
    sym: z.label.split('—')[0].trim(),
    price: `${z.available}/${z.capacity} libres`,
    chg: z.available > 0 ? 'Disponible' : 'Complet',
    up: z.available > 0,
  }));
  renderTicker(items);
}).catch(() => { /* pas de backend branché — on garde la démo */ });

// ---- Nav scroll state ----
const nav = document.getElementById('mainNav');
if (nav) {
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 10);
  });
}
window.addEventListener('load', () => {
  document.querySelectorAll('.hero-content,.hero-visual').forEach((el, i) => {
    setTimeout(() => el.classList.add('visible'), i * 150 + 100);
  });
});

// ---- Mobile menu ----
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');
let lockedScrollY = 0;
if (hamburger && mobileMenu) {
  hamburger.addEventListener('click', () => {
    const open = mobileMenu.classList.contains('open');
    if (open) {
      mobileMenu.classList.remove('open');
      hamburger.classList.remove('open');
      hamburger.setAttribute('aria-expanded', 'false');
      document.body.style.position = '';
      document.body.style.top = '';
      window.scrollTo({ top: lockedScrollY, behavior: 'instant' });
    } else {
      lockedScrollY = window.scrollY;
      document.body.style.position = 'fixed';
      document.body.style.top = `-${lockedScrollY}px`;
      mobileMenu.classList.add('open');
      hamburger.classList.add('open');
      hamburger.setAttribute('aria-expanded', 'true');
    }
  });
  mobileMenu.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      mobileMenu.classList.remove('open');
      hamburger.classList.remove('open');
      hamburger.setAttribute('aria-expanded', 'false');
      document.body.style.position = '';
      document.body.style.top = '';
      window.scrollTo({ top: lockedScrollY, behavior: 'instant' });
    });
  });
}

// ---- Smooth in-page anchors ----
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const href = a.getAttribute('href');
    if (href === '#') return;
    const target = document.querySelector(href);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});

// ---- Silk reveal on scroll ----
const revealObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      revealObs.unobserve(e.target);
    }
  });
}, { threshold: 0.12 });
document.querySelectorAll('.silk-reveal,.silk-reveal-left,.silk-reveal-right').forEach((el, i) => {
  el.style.animationDelay = (i % 4) * 0.08 + 's';
  revealObs.observe(el);
});

const sectionHeaders = document.querySelectorAll('.section-title,.section-tag,.section-sub');
sectionHeaders.forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(16px)';
  el.style.transition = 'opacity .8s var(--silk), transform .8s var(--silk)';
});
const headerObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.opacity = '1';
      e.target.style.transform = 'translateY(0)';
      headerObs.unobserve(e.target);
    }
  });
}, { threshold: 0.3 });
sectionHeaders.forEach(el => headerObs.observe(el));

// ---- FAQ (used on index.html) ----
function buildFaq(faqs) {
  const faqList = document.getElementById('faqList');
  if (!faqList) return;
  faqs.forEach(f => {
    const item = document.createElement('div');
    item.className = 'faq-item';
    item.innerHTML = `<div class="faq-q"><span class="faq-q-text">${f.q}</span><svg class="faq-chevron" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg></div><div class="faq-a">${f.a}</div>`;
    item.querySelector('.faq-q').addEventListener('click', () => item.classList.toggle('open'));
    faqList.appendChild(item);
  });
}
let allExpanded = false;
function toggleAllFaq() {
  allExpanded = !allExpanded;
  document.querySelectorAll('.faq-item').forEach(el => allExpanded ? el.classList.add('open') : el.classList.remove('open'));
  const labelEl = document.getElementById('faqToggleLabel');
  if (labelEl) labelEl.textContent = allExpanded ? 'Tout réduire' : 'Tout développer';
  const icon = document.getElementById('faqToggleIcon');
  if (icon) icon.innerHTML = allExpanded
    ? '<line x1="5" y1="12" x2="19" y2="12"/>'
    : '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>';
}

// ---- Stats counter (used on index.html) ----
const statNums = document.querySelectorAll('.stat-num[data-target]');
const statsObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      const el = e.target;
      const target = parseFloat(el.dataset.target);
      const suffix = el.dataset.suffix || '';
      const decimal = parseInt(el.dataset.decimal) || 0;
      const prefix = el.dataset.prefix || '';
      let startTime = null;
      const duration = 1600;
      function animate(ts) {
        if (!startTime) startTime = ts;
        const progress = Math.min((ts - startTime) / duration, 1);
        const ease = 1 - Math.pow(1 - progress, 3);
        const val = target * ease;
        el.textContent = prefix + (decimal ? val.toFixed(decimal) : Math.round(val)) + suffix;
        if (progress < 1) requestAnimationFrame(animate);
      }
      requestAnimationFrame(animate);
      const bar = el.closest('.stat-block')?.querySelector('.stat-bar');
      if (bar) setTimeout(() => { bar.style.width = bar.dataset.width; }, 200);
      statsObs.unobserve(el);
    }
  });
}, { threshold: 0.5 });
statNums.forEach(el => statsObs.observe(el));

const statBlocks = document.querySelectorAll('.stat-block');
statBlocks.forEach((el, i) => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = `opacity .8s var(--silk) ${i * 0.1}s, transform .8s var(--silk) ${i * 0.1}s, border-color .6s var(--silk)`;
});
const statObs2 = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.opacity = '1';
      e.target.style.transform = 'translateY(0)';
      statObs2.unobserve(e.target);
    }
  });
}, { threshold: 0.2 });
statBlocks.forEach(el => statObs2.observe(el));
