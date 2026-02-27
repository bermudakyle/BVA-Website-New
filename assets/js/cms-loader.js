/* ═══════════════════════════════════════════════════════
   BVA CMS Loader — Targeted Field Injection
   Fetches /content/pages/{pageId}.json and injects values
   into elements with data-field, data-list attributes.
   Falls back silently to hardcoded HTML if fetch fails.
   ═══════════════════════════════════════════════════════ */

async function loadPageContent(pageId) {
  let data;
  try {
    const res = await fetch('/content/pages/' + pageId + '.json');
    if (!res.ok) return; // fallback to hardcoded HTML
    data = await res.json();
  } catch {
    return; // network error — fallback
  }

  /* ── Scalar fields: data-field="key.nested.path" ─────── */
  document.querySelectorAll('[data-field]').forEach(el => {
    const val = deepGet(data, el.dataset.field);
    if (val == null || val === '') return;
    if (el.tagName === 'IMG') {
      el.src = val;
      if (el.dataset.fieldAlt) el.alt = deepGet(data, el.dataset.fieldAlt) || el.alt;
    } else if (el.dataset.html !== undefined) {
      el.innerHTML = val;
    } else {
      el.textContent = val;
    }
  });

  /* ── List fields: data-list="key" data-template="tplId" ─ */
  document.querySelectorAll('[data-list]').forEach(container => {
    const items = deepGet(data, container.dataset.list);
    if (!Array.isArray(items) || !items.length) return;

    const tplId = container.dataset.template;
    const tpl   = tplId
      ? document.getElementById(tplId)
      : container.querySelector('template');
    if (!tpl) return;

    /* Remove existing non-template children (skeleton placeholders) */
    Array.from(container.children).forEach(ch => {
      if (ch.tagName !== 'TEMPLATE') ch.remove();
    });

    items.forEach(item => {
      const clone = tpl.content.cloneNode(true);

      /* Inject scalar fields within the clone */
      clone.querySelectorAll('[data-field]').forEach(el => {
        const val = deepGet(item, el.dataset.field);
        if (val == null || val === '') return;
        if (el.tagName === 'IMG') {
          el.src = val;
          if (el.dataset.fieldAlt) el.alt = deepGet(item, el.dataset.fieldAlt) || el.alt;
        } else if (el.dataset.html !== undefined) {
          el.innerHTML = val;
        } else {
          el.textContent = val;
        }
      });

      /* Inject href/src attributes: data-attr-href="key" */
      clone.querySelectorAll('[data-attr-href]').forEach(el => {
        const val = deepGet(item, el.dataset.attrHref);
        if (val) el.href = val;
      });

      /* Add CSS class from data: data-attr-class="key" */
      clone.querySelectorAll('[data-attr-class]').forEach(el => {
        const val = deepGet(item, el.dataset.attrClass);
        if (val) el.classList.add(val);
      });

      /* Set element ID from data: data-attr-id="key" */
      clone.querySelectorAll('[data-attr-id]').forEach(el => {
        const val = deepGet(item, el.dataset.attrId);
        if (val) el.id = val;
      });

      /* Render tag spans: data-tags="key" — renders <span class="ltag"> per item */
      clone.querySelectorAll('[data-tags]').forEach(el => {
        const tags = deepGet(item, el.dataset.tags);
        if (!Array.isArray(tags)) return;
        el.innerHTML = tags.map(t => `<span class="ltag">${escHtml(t)}</span>`).join('');
      });

      /* Conditional visibility: data-show-if="key" — hide if falsy */
      clone.querySelectorAll('[data-show-if]').forEach(el => {
        const val = deepGet(item, el.dataset.showIf);
        if (!val) el.style.display = 'none';
      });

      /* Feature lists: data-features="key" — renders <li> per array item */
      clone.querySelectorAll('[data-features]').forEach(ul => {
        const feats = deepGet(item, ul.dataset.features);
        if (!Array.isArray(feats)) return;
        ul.innerHTML = feats.map(f =>
          `<li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>${escHtml(f)}</li>`
        ).join('');
      });

      container.appendChild(clone);
    });
  });
}

/* ── Deep property getter: "a.b.c" from nested object ─── */
function deepGet(obj, path) {
  if (!path) return undefined;
  return path.split('.').reduce((o, k) => (o != null ? o[k] : undefined), obj);
}

/* ── HTML escape for feature list items ──────────────── */
function escHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
