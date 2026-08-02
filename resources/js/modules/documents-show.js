/**
 * modules/documents-show.js — all behavior for the document detail page.
 *
 * Loaded on demand from app.js when <body data-page="documents.show"> is present.
 * Reads server data from #doc-show-data (data-* attributes) and the optional
 * #doc-ocr-snippet JSON island — NO Blade runs in this file.
 */

import { apiPost, apiPut, apiDelete } from '../helpers/http.js';
import { toast } from '../helpers/toast.js';
import { serializeForm, withLoading, escapeHtml } from '../helpers/dom.js';
import { dialog } from '../helpers/dialog.js';

// pdf.js is loaded from CDN as an ESM module (matches the previous inline setup).
const PDFJS_SRC = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs';
const PDFJS_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.worker.min.mjs';

export async function init() {
  const data = document.getElementById('doc-show-data');
  if (!data) return;
  const cfg = data.dataset; // streamUrl, updateUrl, destroyUrl, approveUrl, rejectUrl, indexUrl, canEdit, canApprove

  initClickToHighlight();
  await initPdfViewer(cfg);      // also wires the find/highlight box
  initOcrSnippet();
  if (cfg.canApprove === '1') initApproveReject(cfg);
  if (cfg.canEdit === '1') {
    initSelectToFill();
    initEditHandlers(cfg);
  }
}

/* ---- Click a metadata value → fill the highlight box --------------------- */
function initClickToHighlight() {
  const findInput = document.getElementById('pdf-find');
  if (!findInput) return;

  const fill = (value) => {
    findInput.value = value;
    findInput.dispatchEvent(new Event('input'));
    findInput.focus({ preventScroll: true });
  };

  document.querySelectorAll('.dm-meta-clickable[data-highlight-value]').forEach((el) => {
    const value = el.dataset.highlightValue;
    el.addEventListener('click', () => fill(value));
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fill(value); }
    });
  });
}

/* ---- PDF.js viewer + text-layer highlighter ----------------------------- */
async function initPdfViewer(cfg) {
  const canvas = document.getElementById('pdf-canvas');
  if (!canvas) return;

  const pdfjsLib = await import(/* @vite-ignore */ PDFJS_SRC);
  pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER;

  const ctx = canvas.getContext('2d');
  const textLayerDiv = document.getElementById('pdf-text-layer');
  const status = document.getElementById('pdf-status');
  const findInput = document.getElementById('pdf-find');
  const findCount = document.getElementById('pdf-find-count');
  let pdfDoc = null, pageNum = 1, rendering = false;

  async function render(num) {
    rendering = true;
    const page = await pdfDoc.getPage(num);
    const container = document.getElementById('pdf-container');
    const base = page.getViewport({ scale: 1 });
    const scale = Math.min((container.clientWidth - 8) / base.width, 2);
    const viewport = page.getViewport({ scale });

    canvas.width = viewport.width;
    canvas.height = viewport.height;
    await page.render({ canvasContext: ctx, viewport }).promise;

    textLayerDiv.innerHTML = '';
    textLayerDiv.style.width = viewport.width + 'px';
    textLayerDiv.style.height = viewport.height + 'px';
    const textContent = await page.getTextContent();
    renderTextLayer(pdfjsLib, textLayerDiv, textContent, viewport);

    document.getElementById('pdf-page').textContent = num;
    rendering = false;
    applyHighlight();
  }

  function applyHighlight() {
    const term = findInput.value.trim();
    let count = 0;
    textLayerDiv.querySelectorAll('span').forEach((span) => {
      const text = span.textContent;
      if (!term) { span.innerHTML = escapeHtml(text); return; }
      const re = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
      if (re.test(text)) {
        span.innerHTML = escapeHtml(text).replace(re, '<mark>$1</mark>');
        count += (text.match(re) || []).length;
      } else {
        span.innerHTML = escapeHtml(text);
      }
    });
    findCount.textContent = term ? (count + ' coincidencia(s) en esta página') : '';
  }

  pdfjsLib.getDocument(cfg.streamUrl).promise.then((doc) => {
    pdfDoc = doc;
    document.getElementById('pdf-count').textContent = doc.numPages;
    status.textContent = '';
    render(pageNum);
  }).catch(() => { status.textContent = 'No se pudo cargar el PDF.'; });

  document.getElementById('pdf-prev').addEventListener('click', () => {
    if (rendering || pageNum <= 1) return; pageNum--; render(pageNum);
  });
  document.getElementById('pdf-next').addEventListener('click', () => {
    if (rendering || !pdfDoc || pageNum >= pdfDoc.numPages) return; pageNum++; render(pageNum);
  });

  let findTimer;
  findInput.addEventListener('input', () => {
    clearTimeout(findTimer);
    findTimer = setTimeout(applyHighlight, 200);
  });
}

function renderTextLayer(pdfjsLib, textLayerDiv, textContent, viewport) {
  textContent.items.forEach((item) => {
    if (!item.str) return;
    const span = document.createElement('span');
    span.textContent = item.str;
    const tx = pdfjsLib.Util.transform(viewport.transform, item.transform);
    const fontSize = Math.hypot(tx[2], tx[3]);
    span.style.left = tx[4] + 'px';
    span.style.top = (tx[5] - fontSize) + 'px';
    span.style.fontSize = fontSize + 'px';
    span.style.fontFamily = 'sans-serif';
    textLayerDiv.appendChild(span);
  });
}

/* ---- OCR snippet highlight (scanned-doc fallback) ------------------------ */
function initOcrSnippet() {
  const el = document.getElementById('ocr-snippet');
  const island = document.getElementById('doc-ocr-snippet');
  if (!el || !island) return;

  let payload;
  try { payload = JSON.parse(island.textContent); } catch { return; }
  const { term, body } = payload;
  if (!term || !body) return;

  const lower = body.toLowerCase();
  const idx = lower.indexOf(term.toLowerCase());
  if (idx === -1) { el.textContent = 'Sin coincidencia visible en el texto extraído.'; return; }

  const start = Math.max(0, idx - 80);
  const slice = body.substring(start, start + 200);
  const re = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
  el.innerHTML = (start > 0 ? '… ' : '') + escapeHtml(slice).replace(re, '<mark>$1</mark>') + ' …';
}

/* ---- Approve / reject ---------------------------------------------------- */
function initApproveReject(cfg) {
  const approveBtn = document.getElementById('btn-approve-doc');
  const rejectBtn = document.getElementById('btn-reject-doc');

  approveBtn?.addEventListener('click', async () => {
    const ok = await dialog.confirm({
      title: 'Aprobar documento',
      message: '¿Aprobar este documento? Quedará visible para todos los usuarios del área.',
      confirmText: 'Aprobar',
    });
    if (!ok) return;
    try {
      const r = await apiPost(cfg.approveUrl);
      toast.success(r.message);
      setTimeout(() => location.reload(), 700);
    } catch (err) { toast.error(err.data?.message || err.message || 'No se pudo aprobar.'); }
  });

  rejectBtn?.addEventListener('click', async () => {
    const reason = await dialog.prompt({
      title: 'Rechazar documento',
      label: 'Motivo del rechazo (se mostrará al editor)',
      placeholder: 'Ej. El folio no coincide con el XML…',
      confirmText: 'Rechazar',
      required: true,
      danger: true,
    });
    if (reason === null) return; // cancelled
    try {
      const r = await apiPost(cfg.rejectUrl, { reason });
      toast.success(r.message);
      setTimeout(() => location.reload(), 700);
    } catch (err) { toast.error(err.data?.message || err.message || 'No se pudo rechazar.'); }
  });
}

/* ---- Select PDF text → fill a metadata field ---------------------------- */
function initSelectToFill() {
  const textLayer = document.getElementById('pdf-text-layer');
  const captureButtons = Array.from(document.querySelectorAll('.dm-capture-btn'));
  if (!textLayer || captureButtons.length === 0) return;

  let armedTarget = null;

  const disarm = () => {
    armedTarget = null;
    captureButtons.forEach((b) => { b.classList.remove('active', 'btn-primary'); b.classList.add('btn-outline-secondary'); });
    textLayer.classList.remove('dm-capturing');
  };
  const arm = (btn) => {
    const target = btn.dataset.target;
    if (armedTarget === target) { disarm(); return; }
    disarm();
    armedTarget = target;
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('active', 'btn-primary');
    textLayer.classList.add('dm-capturing');
    toast.info('Selecciona texto en el PDF para llenar el campo.', 2500);
  };
  const hasTextLayer = () => textLayer.querySelectorAll('span').length > 0;

  const refreshAvailability = () => {
    const ok = hasTextLayer();
    captureButtons.forEach((b) => {
      b.disabled = !ok;
      b.title = ok
        ? 'Capturar del PDF: clic aquí y selecciona texto en el documento'
        : 'No disponible: este PDF no tiene capa de texto (documento escaneado)';
    });
  };
  let checks = 0;
  const availTimer = setInterval(() => {
    refreshAvailability();
    if (hasTextLayer() || ++checks > 20) clearInterval(availTimer);
  }, 300);

  captureButtons.forEach((btn) => {
    btn.addEventListener('click', (e) => { e.preventDefault(); if (!btn.disabled) arm(btn); });
  });

  textLayer.addEventListener('mouseup', () => {
    if (!armedTarget) return;
    const sel = window.getSelection();
    const text = sel ? sel.toString().trim() : '';
    if (text === '') return;
    const input = document.getElementById(armedTarget);
    if (input) {
      input.value = text;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.focus({ preventScroll: true });
      toast.success('Campo actualizado.', 1800);
    }
    sel.removeAllRanges();
    disarm();
  });

  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && armedTarget) disarm(); });
}

/* ---- Edit / save / delete metadata --------------------------------------- */
function initEditHandlers(cfg) {
  const form = document.getElementById('meta-form');
  if (!form) return;
  const viewMode = form.querySelector('[data-view-mode]');
  const editMode = form.querySelector('[data-edit-mode]');

  document.getElementById('btn-edit-meta')?.addEventListener('click', () => {
    viewMode.classList.add('d-none'); editMode.classList.remove('d-none');
  });
  document.getElementById('meta-cancel')?.addEventListener('click', () => {
    editMode.classList.add('d-none'); viewMode.classList.remove('d-none');
  });
  document.getElementById('meta-save')?.addEventListener('click', async (e) => {
    const payload = serializeForm(form);
    try {
      await withLoading(e.currentTarget, async () => {
        await apiPut(cfg.updateUrl, payload);
        toast.success('Guardado. Recargando…');
        setTimeout(() => location.reload(), 600);
      });
    } catch (err) {
      const msg = err.data?.errors ? Object.values(err.data.errors)[0] : err.message;
      toast.error(msg);
    }
  });
  document.getElementById('btn-delete-doc')?.addEventListener('click', async () => {
    const ok = await dialog.confirm({
      title: 'Eliminar documento',
      message: '¿Eliminar este documento y sus archivos? Esta acción no se puede deshacer.',
      confirmText: 'Eliminar',
      danger: true,
    });
    if (!ok) return;
    try {
      await apiDelete(cfg.destroyUrl);
      toast.success('Eliminado. Redirigiendo…');
      setTimeout(() => { location.href = cfg.indexUrl; }, 600);
    } catch (err) { toast.error(err.message); }
  });
}
