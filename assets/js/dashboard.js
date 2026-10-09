(() => {
  'use strict';

  const toast = document.getElementById('toast');
  if (toast) {
    window.addEventListener('load', () => {
      toast.classList.add('show');
      window.setTimeout(() => toast.classList.remove('show'), 2600);
      window.setTimeout(() => toast.remove(), 3200);
    });
  }



  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebarClose = document.getElementById('sidebarClose');

  const closeSidebar = () => {
    if (!sidebar) return;
    sidebar.classList.remove('open');
    if (sidebarOverlay) sidebarOverlay.setAttribute('hidden', '');
    if (mobileMenuBtn) mobileMenuBtn.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('sidebar-open');
  };

  const openSidebar = () => {
    if (!sidebar) return;
    sidebar.classList.add('open');
    if (sidebarOverlay) sidebarOverlay.removeAttribute('hidden');
    if (mobileMenuBtn) mobileMenuBtn.setAttribute('aria-expanded', 'true');
    document.body.classList.add('sidebar-open');
  };

  if (mobileMenuBtn && sidebar) {
    mobileMenuBtn.addEventListener('click', () => {
      sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
  }
  if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
  if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
  if (sidebar) {
    sidebar.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 900px)').matches) closeSidebar();
      });
    });
  }

  const userBtn = document.getElementById('userBtn');
  const userMenu = document.getElementById('userMenu');
  if (userBtn && userMenu) {
    userBtn.addEventListener('click', () => {
      const isHidden = userMenu.hasAttribute('hidden');
      if (isHidden) {
        userMenu.removeAttribute('hidden');
      } else {
        userMenu.setAttribute('hidden', '');
      }
      userBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
      if (!userMenu.contains(event.target) && !userBtn.contains(event.target)) {
        userMenu.setAttribute('hidden', '');
        userBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  document.querySelectorAll('[data-href]').forEach((element) => {
    element.addEventListener('click', () => {
      const href = element.dataset.href;
      if (href && href !== '#') {
        window.location.href = href;
      }
    });
  });

  const overlay = document.getElementById('confirmOverlay');
  const message = document.getElementById('confirmMessage');
  const accept = document.getElementById('confirmAccept');
  const cancel = document.getElementById('confirmCancel');
  let pendingForm = null;

  const closeConfirm = () => {
    if (!overlay) return;
    overlay.setAttribute('hidden', '');
    pendingForm = null;
  };

  if (overlay && message && accept && cancel) {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        pendingForm = form;
        message.textContent = form.dataset.confirm || '¿Deseas continuar?';
        overlay.removeAttribute('hidden');
        cancel.focus();
      });
    });

    accept.addEventListener('click', () => {
      if (!pendingForm) return;
      const form = pendingForm;
      closeConfirm();
      form.submit();
    });

    cancel.addEventListener('click', closeConfirm);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) closeConfirm();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !overlay.hasAttribute('hidden')) {
        closeConfirm();
      }
    });
  }
})();
