document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (event) {
      const message = el.getAttribute('data-confirm') || 'Xác nhận thực hiện?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });
  const orderSelect = document.querySelector('[data-consignment-order-select]');
  if (orderSelect) {
    orderSelect.addEventListener('change', function () {
      const url = new URL(window.location.href);
      if (this.value) url.searchParams.set('order_id', this.value); else url.searchParams.delete('order_id');
      window.location.href = url.toString();
    });
  }
});
