(function () {
  'use strict';

  function activateTab(name) {
    document.querySelectorAll('[data-om9-tab]').forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-om9-tab') === name);
    });
    document.querySelectorAll('[data-om9-panel]').forEach(function (panel) {
      panel.classList.toggle('active', panel.getAttribute('data-om9-panel') === name);
    });
    if (history.replaceState) {
      history.replaceState(null, '', '#' + name);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-om9-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        activateTab(btn.getAttribute('data-om9-tab'));
      });
    });

    document.querySelectorAll('[data-om9-open-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var name = btn.getAttribute('data-om9-open-tab');
        activateTab(name);
        var tabs = document.querySelector('.om9-detail-tabs');
        if (tabs) tabs.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    var hash = (window.location.hash || '').replace('#', '');
    if (hash && document.querySelector('[data-om9-tab="' + hash + '"]')) {
      activateTab(hash);
    }

    var editModal = document.getElementById('om9EditIssue');
    if (editModal) {
      editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        if (!button) return;
        var form = document.getElementById('om9IssueUpdateForm');
        var title = document.getElementById('om9IssueTitle');
        var serial = document.getElementById('om9IssueSerial');
        var status = document.getElementById('om9IssueStatus');
        var resolution = document.getElementById('om9IssueResolution');
        var cost = document.getElementById('om9IssueCost');
        if (form) form.action = button.getAttribute('data-update-url') || '#';
        if (title) title.textContent = 'Phiếu #' + (button.getAttribute('data-id') || '');
        if (serial) serial.textContent = button.getAttribute('data-serial') || 'Chưa có serial';
        if (status) status.value = button.getAttribute('data-status') || 'received';
        if (resolution) resolution.value = button.getAttribute('data-resolution') || '';
        if (cost) cost.value = button.getAttribute('data-cost') || 0;
      });
    }
  });
})();
