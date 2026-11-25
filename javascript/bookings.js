(function () {
  // Guest page: switch between Upcoming / Pending / Past
  const tabs   = document.querySelectorAll('[data-tab]');
  const panels = document.querySelectorAll('[data-panel]');

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((t) => t.classList.toggle('active', t === tab));
      panels.forEach((p) => { p.hidden = p.dataset.panel !== tab.dataset.tab; });
    });
  });

  // Admin page: show or hide the Details row under a booking
  document.querySelectorAll('[data-toggle-detail]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const row = document.getElementById(btn.dataset.toggleDetail);
      if (row) row.hidden = !row.hidden;
    });
  });
})();
