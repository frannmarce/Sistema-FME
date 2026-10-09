(() => {
  'use strict';

  const STORAGE_KEY = 'fme_session_cache_v1';
  const CACHE_TTL_MS = 30 * 60 * 1000;
  const contextElement = document.getElementById('fmeSessionContext');

  if (!contextElement) return;

  const safeRead = () => {
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;

      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return null;

      const expiresAt = Number(parsed.expiresAt || 0);
      if (!expiresAt || expiresAt <= Date.now()) {
        window.localStorage.removeItem(STORAGE_KEY);
        return null;
      }

      return parsed;
    } catch (error) {
      try {
        window.localStorage.removeItem(STORAGE_KEY);
      } catch (_) {
        // localStorage puede estar deshabilitado por el navegador.
      }
      return null;
    }
  };

  const safeWrite = (value) => {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
      return true;
    } catch (error) {
      return false;
    }
  };

  const clear = () => {
    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch (_) {
      // La sesión PHP sigue siendo la autoridad aunque localStorage no esté disponible.
    }
  };

  const applyDisplayCache = (cache) => {
    if (!cache) return;

    document.querySelectorAll('[data-session-username]').forEach((element) => {
      element.textContent = cache.username || 'Usuario';
    });

    document.querySelectorAll('[data-session-role]').forEach((element) => {
      element.textContent = cache.role || '';
    });
  };

  let serverContext;
  try {
    serverContext = JSON.parse(contextElement.textContent || '{}');
  } catch (error) {
    clear();
    return;
  }

  const serverUserId = Number(serverContext.userId || 0);
  const now = Date.now();
  const cached = safeRead();

  // Si existe una caché válida del mismo usuario, se puede reutilizar para la UI.
  // Nunca se utiliza para autorizar módulos ni reemplazar $_SESSION.
  if (cached && Number(cached.userId || 0) === serverUserId) {
    applyDisplayCache(cached);
  }

  const synchronizedCache = {
    userId: serverUserId,
    username: String(serverContext.username || 'Usuario'),
    role: String(serverContext.role || ''),
    lastModule: String(serverContext.module || 'panel'),
    cachedAt: now,
    lastActivityAt: now,
    expiresAt: now + CACHE_TTL_MS
  };

  const cacheAvailable = safeWrite(synchronizedCache);
  applyDisplayCache(synchronizedCache);

  const status = document.getElementById('sessionCacheStatus');
  if (status) {
    status.textContent = cacheAvailable
      ? 'Caché local de sesión activa'
      : 'Caché local no disponible';
  }

  // Limpiar la copia local antes del cierre explícito de sesión.
  document.querySelectorAll('a[href="logout.php"]').forEach((link) => {
    link.addEventListener('click', clear);
  });

  // API mínima para inspección/depuración. No concede permisos.
  window.FMESessionCache = Object.freeze({
    get: safeRead,
    clear
  });
})();
