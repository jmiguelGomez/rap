/* Preferencias de interfaz exclusivamente. Nunca persistir texto ni datos de clientes. */
(() => {
 'use strict';
 const root = document.documentElement;
 const sidebar = document.getElementById('sidebar');
 const burger = document.getElementById('sb-burger');
 const overlay = document.getElementById('sb-overlay');
 const collapse = document.getElementById('sb-collapse');
 const main = document.getElementById('main-content');
 const topbar = document.querySelector('.topbar');
 const mobile = window.matchMedia('(max-width: 900px)');
 const storage = { get(key) { try { return localStorage.getItem(key); } catch (_) { return null; } }, set(key,value) { try { localStorage.setItem(key,value); } catch (_) {} } };
 function toggleSidebar(open) {
  sidebar?.classList.toggle('open',open);
  overlay?.classList.toggle('open',open);
  document.body.classList.toggle('sidebar-open',open);
  burger?.setAttribute('aria-expanded',String(open));
  if (main) main.inert = open;
  if (topbar) topbar.inert = open;
  if (sidebar) sidebar.inert = mobile.matches && !open;
  if (open) document.getElementById('sb-close')?.focus();
  else if (mobile.matches) burger?.focus();
 }
 burger?.addEventListener('click',() => toggleSidebar(true));
 overlay?.addEventListener('click',() => toggleSidebar(false));
 document.getElementById('sb-close')?.addEventListener('click',() => toggleSidebar(false));
 sidebar?.querySelectorAll('a').forEach(a => a.addEventListener('click',() => { if (mobile.matches) toggleSidebar(false); }));
 function collapseLabel() {
  const expanded = !root.classList.contains('sb-collapsed');
  collapse?.setAttribute('aria-expanded',String(expanded));
  collapse?.setAttribute('aria-label',expanded ? 'Colapsar menú' : 'Ampliar menú');
 }
 collapse?.addEventListener('click',() => {
  root.classList.toggle('sb-collapsed');
  storage.set('check-sidebar',root.classList.contains('sb-collapsed')?'collapsed':'expanded');
  collapseLabel();
 });
 collapseLabel();
 if (sidebar) sidebar.inert = mobile.matches;
 mobile.addEventListener('change',() => toggleSidebar(false));
 const dialog = document.getElementById('quick-nav');
 const search = document.getElementById('quick-search');
 const results = document.getElementById('quick-results');
 if (dialog && search && results) {
  const destinations = [...document.querySelectorAll('.sb-nav a,.app-cfg-menu a')];
  destinations.forEach(source => {
   const link = document.createElement('a'); link.href = source.href;
   link.textContent = source.textContent.trim(); results.append(link);
  });
  const normal = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  function filter() {
   let visible=0;
   results.querySelectorAll('a').forEach(a => { a.hidden=!normal(a.textContent).includes(normal(search.value)); if (!a.hidden) visible++; });
   document.getElementById('quick-empty').hidden = visible > 0;
  }
  function open() { if (dialog.open) return; search.value=''; filter(); dialog.showModal(); search.focus(); }
  document.getElementById('quick-open')?.addEventListener('click',open);
  document.getElementById('quick-close')?.addEventListener('click',() => dialog.close());
  dialog.addEventListener('click',e => { if (e.target===dialog) { const b=dialog.getBoundingClientRect(); if(e.clientX<b.left||e.clientX>b.right||e.clientY<b.top||e.clientY>b.bottom) dialog.close(); } });
  dialog.addEventListener('keydown',e => { if (e.key==='Escape') { e.preventDefault(); e.stopPropagation(); dialog.close(); } });
  search.addEventListener('input',filter);
  search.addEventListener('keydown',e => { if (e.key==='ArrowDown') { e.preventDefault(); results.querySelector('a:not([hidden])')?.focus(); } });
  document.addEventListener('keydown',e => { if ((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k' && !document.querySelector('.modal.show,.sidebar.open')) { e.preventDefault(); open(); } });
 }
 document.addEventListener('keydown',e => {
  if (!sidebar?.classList.contains('open')) return;
  if (e.key==='Escape') { e.preventDefault(); e.stopImmediatePropagation(); toggleSidebar(false); }
  if (e.key==='Tab') {
   const items=[...sidebar.querySelectorAll('a,button')].filter(el=>el.offsetParent!==null);
   const first=items[0],last=items[items.length-1];
   if (e.shiftKey && document.activeElement===first) { e.preventDefault(); last?.focus(); }
   else if (!e.shiftKey && document.activeElement===last) { e.preventDefault(); first?.focus(); }
  }
 },true);
 // Mantener nombres de columnas al convertir listados sencillos en fichas móviles.
 function labelTables() {
  document.querySelectorAll('main table').forEach(table => {
   if (!table.closest('.table-responsive,.note-editor')) {
    const scroller=document.createElement('div'); scroller.className='table-responsive';
    table.before(scroller); scroller.append(table);
   }
   const heads=[...table.querySelectorAll('thead tr:first-child th')];
   if (!heads.length || heads.some(h=>h.colSpan>1) || table.querySelector('tbody [rowspan]')) return;
   const rows=[...table.querySelectorAll('tbody tr')];
   if (rows.some(row=>row.cells.length!==heads.length && !row.querySelector('.dataTables_empty'))) return;
   table.classList.add('mobile-records');
   rows.forEach(row=>[...row.cells].forEach((cell,i)=>{ cell.dataset.column=heads[i]?.textContent.trim() || 'Acciones'; }));
  });
  document.querySelectorAll('button[data-bs-toggle="dropdown"]').forEach(button=>{
   if (!button.textContent.trim() && !button.hasAttribute('aria-label')) button.setAttribute('aria-label','Abrir acciones');
  });
 }
 labelTables();
 if (window.jQuery) window.jQuery(document).on('draw.dt',labelTables);
})();
