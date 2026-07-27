(function(){
  var FOCUSABLE = 'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';
  var openStack = [];
  var pendingDeleteUrl = '';

  // Reduced-motion is handled globally in tokens.css
  // (`@media (prefers-reduced-motion:reduce){*{transition:none!important}}`),
  // so no JS branching is needed here for open/close animation.

  function ensureDeleteModal(){
    if (document.getElementById('confirmDeleteModal')) return;
    var wrap = document.createElement('div');
    wrap.innerHTML =
      '<div id="confirmDeleteModal" class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmDeleteModal-title">' +
        '<div class="confirm-modal__panel">' +
          '<div class="confirm-modal__header">' +
            '<h3 class="confirm-modal__title" id="confirmDeleteModal-title">Xác nhận xóa</h3>' +
            '<button type="button" class="confirm-modal__close" data-modal-close aria-label="Đóng"><i class="bi bi-x-lg"></i></button>' +
          '</div>' +
          '<div class="confirm-modal__body"><p id="confirmDeleteModal-text">Bạn có chắc chắn muốn xóa mục này? Hành động này không thể hoàn tác.</p></div>' +
          '<div class="confirm-modal__footer">' +
            '<button type="button" class="btn btn-ghost" data-modal-close>Hủy</button>' +
            '<button type="button" class="btn btn-danger" id="confirmDeleteModal-confirm">Xác nhận xóa</button>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(wrap.firstElementChild);
  }

  function getFocusable(modal){
    return Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE)).filter(function(el){
      return el.offsetParent !== null;
    });
  }

  function openModal(modal, opener){
    if (!modal) return;
    openStack.push({ modal: modal, opener: opener || document.activeElement });
    modal.classList.add('is-open');
    var focusables = getFocusable(modal);
    (focusables[0] || modal).focus();
  }

  function closeModal(modal){
    if (!modal || !modal.classList.contains('is-open')) return;
    modal.classList.remove('is-open');
    var idx = -1;
    for (var i = openStack.length - 1; i >= 0; i--) {
      if (openStack[i].modal === modal) { idx = i; break; }
    }
    var entry = idx > -1 ? openStack.splice(idx, 1)[0] : null;
    if (entry && entry.opener && typeof entry.opener.focus === 'function') {
      entry.opener.focus();
    }
  }

  function topModal(){
    return openStack.length ? openStack[openStack.length - 1].modal : null;
  }

  function trapFocus(e){
    var modal = topModal();
    if (!modal || e.key !== 'Tab') return;
    var focusables = getFocusable(modal);
    if (!focusables.length) { e.preventDefault(); return; }
    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault(); first.focus();
    }
  }

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      var modal = topModal();
      if (modal) closeModal(modal);
      return;
    }
    trapFocus(e);
  });

  document.addEventListener('click', function(e){
    var opener = e.target.closest('[data-modal-open]');
    if (opener) {
      var id = opener.getAttribute('data-modal-open');
      var modal = document.getElementById(id);
      if (modal) { e.preventDefault(); openModal(modal, opener); }
      return;
    }

    var closer = e.target.closest('[data-modal-close]');
    if (closer) {
      var modalToClose = closer.closest('.confirm-modal');
      if (modalToClose) { e.preventDefault(); closeModal(modalToClose); }
      return;
    }

    if (e.target.classList && e.target.classList.contains('confirm-modal')) {
      closeModal(e.target);
      return;
    }

    var deleteBtn = e.target.closest('[data-delete-url]');
    if (deleteBtn) {
      e.preventDefault();
      ensureDeleteModal();
      var deleteModal = document.getElementById('confirmDeleteModal');
      var textEl = document.getElementById('confirmDeleteModal-text');
      pendingDeleteUrl = deleteBtn.getAttribute('data-delete-url') || '';
      if (textEl) {
        textEl.textContent = deleteBtn.getAttribute('data-confirm-text') ||
          'Bạn có chắc chắn muốn xóa mục này? Hành động này không thể hoàn tác.';
      }
      openModal(deleteModal, deleteBtn);
      return;
    }

    var confirmBtn = e.target.closest('#confirmDeleteModal-confirm');
    if (confirmBtn && pendingDeleteUrl) {
      window.location.href = pendingDeleteUrl;
    }
  });

  ensureDeleteModal();
})();
