/* ═══════════════════════════════════════════
   BVA — Shared JavaScript
   ═══════════════════════════════════════════ */

/* ── Navbar scroll solid ──────────────────── */
const nav = document.getElementById('nav');
if (nav) {
  window.addEventListener('scroll', () => {
    nav.classList.toggle('solid', window.scrollY > 60);
  }, { passive: true });
  // Start solid on non-hero pages
  if (!document.querySelector('.hero')) nav.classList.add('solid');
}

/* ── Active nav link ──────────────────────── */
(function () {
  const path = window.location.pathname.replace(/\/$/, '') || '/index.html';
  document.querySelectorAll('.nav-links a').forEach(a => {
    const href = a.getAttribute('href')?.replace(/\/$/, '') || '';
    if (href && path.endsWith(href)) a.classList.add('active');
  });
})();

/* ── Reveal on scroll ─────────────────────── */
const revealEls = document.querySelectorAll('.reveal');
if (revealEls.length) {
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
  }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
  revealEls.forEach(el => io.observe(el));
  // Fallback for screenshots / print
  setTimeout(() => revealEls.forEach(el => el.classList.add('in')), 500);
}

/* ── Mobile menu ──────────────────────────── */
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu    = document.getElementById('mobile-menu');
const mobileClose   = document.getElementById('mobile-menu-close');

if (mobileMenuBtn && mobileMenu) {
  mobileMenuBtn.addEventListener('click', () => mobileMenu.classList.add('open'));
  if (mobileClose) mobileClose.addEventListener('click', () => mobileMenu.classList.remove('open'));
  mobileMenu.addEventListener('click', e => {
    if (e.target === mobileMenu) mobileMenu.classList.remove('open');
  });
  mobileMenu.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => mobileMenu.classList.remove('open'));
  });
}

/* ── Hero Slider ──────────────────────────── */
(function () {
  const slides = document.querySelectorAll('.hero-slide');
  const dots   = document.querySelectorAll('.hero-dot');
  if (!slides.length) return;

  let current = 0;
  let timer;

  const show = n => {
    slides[current].classList.remove('active');
    if (dots[current]) dots[current].classList.remove('active');
    current = (n + slides.length) % slides.length;
    slides[current].classList.add('active');
    if (dots[current]) dots[current].classList.add('active');
  };

  show(0);
  const start = () => { window._heroTimer = timer = setInterval(() => show(current + 1), 5500); };
  const stop  = () => clearInterval(timer);
  start();

  dots.forEach((dot, i) => {
    dot.addEventListener('click', () => { stop(); show(i); start(); });
  });
})();

/* ── Gallery Lightbox ─────────────────────── */
(function () {
  const items    = document.querySelectorAll('.gallery-item[data-src]');
  const lightbox = document.getElementById('lightbox');
  const lbImg    = document.getElementById('lightbox-img');
  const lbClose  = document.getElementById('lightbox-close');
  const lbPrev   = document.getElementById('lightbox-prev');
  const lbNext   = document.getElementById('lightbox-next');
  const lbCap    = document.getElementById('lightbox-caption');

  if (!lightbox || !items.length) return;

  let idx = 0;
  const srcs  = Array.from(items).map(el => el.dataset.src);
  const alts  = Array.from(items).map(el => el.dataset.alt || '');
  const cats  = Array.from(items).map(el => el.dataset.cat || '');

  const open = i => {
    idx = i;
    lbImg.src = srcs[idx];
    if (lbCap) lbCap.innerHTML = `<span style="color:var(--teal);font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;">${cats[idx]}</span><br>${alts[idx]}`;
    lightbox.classList.add('open');
    document.body.style.overflow = 'hidden';
  };
  const close = () => { lightbox.classList.remove('open'); document.body.style.overflow = ''; };
  const prev  = () => open((idx - 1 + srcs.length) % srcs.length);
  const next  = () => open((idx + 1) % srcs.length);

  items.forEach((el, i) => el.addEventListener('click', () => open(i)));
  if (lbClose) lbClose.addEventListener('click', close);
  if (lbPrev)  lbPrev.addEventListener('click', prev);
  if (lbNext)  lbNext.addEventListener('click', next);
  lightbox.addEventListener('click', e => { if (e.target === lightbox) close(); });
  document.addEventListener('keydown', e => {
    if (!lightbox.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
  });
})();

/* ── Gallery category filter ──────────────── */
(function () {
  const filterBtns = document.querySelectorAll('.filter-btn');
  const galleryItems = document.querySelectorAll('.gallery-item');
  if (!filterBtns.length) return;

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const cat = btn.dataset.filter;
      galleryItems.forEach(item => {
        const match = cat === 'All' || item.dataset.cat === cat;
        item.style.display = match ? '' : 'none';
      });
    });
  });
})();
