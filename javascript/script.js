(function () {
  const $ = (id) => document.getElementById(id);

  const search     = $('search');
  const fType      = $('filter-type');
  const fLocation  = $('filter-location');
  const fStatus    = $('filter-status');
  const grid       = $('resource-grid');
  const emptyMsg   = $('empty-msg');
  const viewGrid   = $('view-grid');
  const viewList   = $('view-list');
  const refreshBtn = $('refresh-btn');
  const syncedEl   = $('last-synced');

  /* Filtering */
  function applyFilters() {
    const q    = search.value.trim().toLowerCase();
    const type = fType.value;
    const loc  = fLocation.value;
    const stat = fStatus.value;

    let visible = 0;

    // Query fresh each time, because refresh replaces the cards
    grid.querySelectorAll('.res-card').forEach((card) => {
      const d = card.dataset;
      const matches =
        (!q    || d.name.includes(q) || d.building.toLowerCase().includes(q)) &&
        (!type || d.category === type) &&
        (!loc  || d.building === loc) &&
        (!stat || d.status === stat);

      card.hidden = !matches;
      if (matches) visible++;
    });

    emptyMsg.hidden = visible > 0;
  }

  [search, fType, fLocation, fStatus].forEach((el) => {
    el.addEventListener(el === search ? 'input' : 'change', applyFilters);
  });

  /* ---------- Grid / list toggle ---------- */
  function setView(mode) {
    const isList = mode === 'list';
    grid.classList.toggle('list-view', isList);
    viewList.classList.toggle('active', isList);
    viewGrid.classList.toggle('active', !isList);
  }

  viewGrid.addEventListener('click', () => setView('grid'));
  viewList.addEventListener('click', () => setView('list'));

  /* ---------- Refresh ---------- */
  function updateSyncedTime() {
    syncedEl.textContent = new Date().toLocaleTimeString('en-US', {
      hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
  }

  async function refresh() {
    refreshBtn.classList.add('spinning');
    try {
      const res = await fetch(window.location.href, { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const doc = new DOMParser().parseFromString(await res.text(), 'text/html');

      // Swap the four stat numbers
      ['available', 'booked', 'repair', 'total'].forEach((key) => {
        const fresh = doc.getElementById('count-' + key);
        if (fresh) $('count-' + key).textContent = fresh.textContent;
      });

      // Swap the cards, then re-apply the current filters
      const freshGrid = doc.getElementById('resource-grid');
      if (freshGrid) grid.innerHTML = freshGrid.innerHTML;

      applyFilters();
      updateSyncedTime();
    } catch (err) {
      console.error('Refresh failed:', err);
    } finally {
      refreshBtn.classList.remove('spinning');
    }
  }

  refreshBtn.addEventListener('click', refresh);
  setInterval(refresh, 30000); // auto-refresh every 30 seconds
})();