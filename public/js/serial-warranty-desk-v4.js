(() => {
  'use strict';
  const ready = (fn) => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn, {once:true}) : fn();
  ready(() => {
    const cards = [...document.querySelectorAll('[data-wd-card]')];
    const detailEmpty = document.querySelector('#wdDetailEmpty');
    const detailContent = document.querySelector('#wdDetailContent');
    const backdrop = document.querySelector('#wdModalBackdrop');
    const addModal = document.querySelector('#wdAddModal');
    const editModal = document.querySelector('#wdEditModal');
    let selectedCard = null;

    const setText = (id, value) => { const el = document.querySelector(id); if (el) el.textContent = value || '—'; };
    const setValue = (id, value) => { const el = document.querySelector(id); if (el) el.value = value || ''; };
    const copyText = async (value, source) => {
      try {
        await navigator.clipboard.writeText(value || '');
        const old = source?.innerHTML;
        if (source) { source.innerHTML = '<i class="bi bi-check2"></i>'; setTimeout(() => source.innerHTML = old, 900); }
      } catch (_) {}
    };

    const warrantyLabel = (card) => {
      const state = card.dataset.filterWarranty;
      const days = Number(card.dataset.days || 0);
      if (state === 'active') return `Còn ${Math.abs(days).toLocaleString('vi-VN')} ngày`;
      if (state === 'expired') return `Hết ${Math.abs(days).toLocaleString('vi-VN')} ngày`;
      return 'Chưa kích hoạt';
    };

    const selectCard = (card) => {
      if (!card) return;
      selectedCard = card;
      cards.forEach(x => x.classList.toggle('is-selected', x === card));
      detailEmpty?.setAttribute('hidden','hidden');
      if (detailContent) detailContent.hidden = false;
      setText('#wdDetailSerial', card.dataset.serial);
      setText('#wdDetailProduct', card.dataset.product);
      setText('#wdDetailSku', card.dataset.sku);
      setText('#wdDetailCustomer', card.dataset.customer);
      setText('#wdDetailPhone', card.dataset.phone);
      setText('#wdDetailSold', card.dataset.soldDisplay);
      setText('#wdDetailStart', card.dataset.startDisplay);
      setText('#wdDetailEnd', card.dataset.endDisplay);
      setText('#wdDetailMonths', `${card.dataset.months || 0} tháng`);
      setText('#wdDetailNote', card.dataset.note);
      const tx = document.querySelector('#wdDetailTransaction');
      if (tx) tx.textContent = card.dataset.state || '—';
      const wb = document.querySelector('#wdDetailWarranty');
      if (wb) { wb.className = `wd-warranty-badge ${card.dataset.filterWarranty || 'inactive'}`; wb.innerHTML = `<i class="bi bi-shield-check"></i>${warrantyLabel(card)}`; }
      const order = document.querySelector('#wdDetailOrder');
      const openOrder = document.querySelector('#wdOpenOrder');
      [order, openOrder].forEach(el => {
        if (!el) return;
        el.textContent = el === order ? (card.dataset.order || '—') : 'Mở đơn';
        el.href = card.dataset.orderUrl || '#';
        el.style.display = card.dataset.orderUrl ? '' : 'none';
      });
      const progress = document.querySelector('#wdProgressBar');
      if (progress) {
        let width = 0;
        if (card.dataset.filterWarranty === 'active') {
          const months = Math.max(1, Number(card.dataset.months || 1));
          const totalDays = months * 30.44;
          width = Math.max(5, Math.min(100, (Number(card.dataset.days || 0) / totalDays) * 100));
        }
        progress.style.width = `${width}%`;
        progress.style.background = card.dataset.filterWarranty === 'expired' ? '#ef4444' : card.dataset.filterWarranty === 'inactive' ? '#f59e0b' : '';
      }
    };

    cards.forEach(card => {
      card.addEventListener('click', (e) => { if (!e.target.closest('[data-wd-copy]')) selectCard(card); });
      card.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectCard(card); } });
    });
    document.querySelectorAll('[data-wd-copy]').forEach(btn => btn.addEventListener('click', (e) => { e.stopPropagation(); copyText(btn.dataset.wdCopy, btn); }));
    document.querySelector('#wdDetailCopy')?.addEventListener('click', function(){ if (selectedCard) copyText(selectedCard.dataset.serial, this); });

    const openModal = (modal) => {
      if (!modal || !backdrop) return;
      modal.hidden = false;
      requestAnimationFrame(() => { backdrop.classList.add('is-open'); modal.classList.add('is-open'); document.body.classList.add('wd-modal-open'); });
    };
    const closeModals = () => {
      backdrop?.classList.remove('is-open');
      [addModal, editModal].forEach(m => m?.classList.remove('is-open'));
      document.body.classList.remove('wd-modal-open');
      setTimeout(() => [addModal, editModal].forEach(m => { if (m) m.hidden = true; }), 180);
    };
    document.querySelectorAll('[data-wd-open="add"]').forEach(btn => btn.addEventListener('click', () => openModal(addModal)));
    document.querySelectorAll('[data-wd-close]').forEach(btn => btn.addEventListener('click', closeModals));
    backdrop?.addEventListener('click', closeModals);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModals(); });

    const populateEdit = (card) => {
      if (!card || !editModal) return;
      const form = document.querySelector('#wdEditForm');
      if (form) form.action = (form.dataset.actionTemplate || '').replace('__ID__', card.dataset.id || '');
      setText('#wdEditSubtitle', `Serial: ${card.dataset.serial || '—'}`);
      setValue('#wdEditProduct', card.dataset.productId);
      setValue('#wdEditCustomer', card.dataset.customerId);
      setValue('#wdEditOrder', card.dataset.orderId);
      setValue('#wdEditSold', card.dataset.soldAt);
      setValue('#wdEditStart', card.dataset.start);
      setValue('#wdEditMonths', card.dataset.months || '60');
      setValue('#wdEditEnd', card.dataset.end);
      setValue('#wdEditNote', card.dataset.note === 'Chưa có ghi chú' ? '' : card.dataset.note);
      openModal(editModal);
    };
    document.querySelector('#wdEditSelected')?.addEventListener('click', () => populateEdit(selectedCard));
    document.querySelector('#wdDeleteSelected')?.addEventListener('click', () => {
      if (!selectedCard) return;
      if (confirm(`Xóa serial ${selectedCard.dataset.serial} khỏi trang tra cứu?`)) document.querySelector(`#wd-delete-${selectedCard.dataset.id}`)?.submit();
    });

    document.querySelectorAll('[data-wd-filter]').forEach(chip => chip.addEventListener('click', () => {
      document.querySelectorAll('[data-wd-filter]').forEach(x => x.classList.remove('is-active'));
      chip.classList.add('is-active');
      const filter = chip.dataset.wdFilter;
      let firstVisible = null;
      cards.forEach(card => {
        const visible = filter === 'all' || (filter === 'manual' ? card.dataset.filterManual === '1' : card.dataset.filterWarranty === filter);
        card.style.display = visible ? '' : 'none';
        if (visible && !firstVisible) firstVisible = card;
      });
      if (selectedCard?.style.display === 'none') selectCard(firstVisible);
    }));

    const addMonths = (dateString, months) => {
      if (!dateString) return '';
      const d = new Date(`${dateString}T00:00:00`);
      if (Number.isNaN(d.getTime())) return '';
      d.setMonth(d.getMonth() + Number(months || 0));
      return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    };
    const formatDate = (value) => value ? value.split('-').reverse().join('/') : '—';
    const updateAddPreview = () => {
      const start = document.querySelector('[data-wd-date-start="add"]')?.value;
      const months = document.querySelector('[data-wd-months="add"]')?.value;
      setText('#wdAddEndPreview', formatDate(addMonths(start, months)));
    };
    const updateEditEnd = () => {
      const start = document.querySelector('[data-wd-date-start="edit"]')?.value;
      const months = document.querySelector('[data-wd-months="edit"]')?.value;
      const end = document.querySelector('#wdEditEnd');
      if (end && start && months) end.value = addMonths(start, months);
    };
    ['[data-wd-date-start="add"]','[data-wd-months="add"]'].forEach(s => document.querySelector(s)?.addEventListener('change', updateAddPreview));
    ['[data-wd-date-start="edit"]','[data-wd-months="edit"]'].forEach(s => document.querySelector(s)?.addEventListener('change', updateEditEnd));
    updateAddPreview();

    if (cards[0]) selectCard(cards[0]);
    if (document.querySelector('.wd-page')?.dataset.wdAutoOpenAdd === '1') openModal(addModal);
  });
})();
