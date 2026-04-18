/**
 * auth-guard.js — Global JWT expiry handler
 * Intercepts all fetch() and XMLHttpRequest calls.
 * If a 401 is received, clears the token and redirects to /login.
 */
(function () {
  'use strict';

  var LOGIN_PATH = '/login';

  function clearTokenAndRedirect() {
    try { localStorage.removeItem('finovate_token'); } catch (e) {}
    try { sessionStorage.removeItem('finovate_token'); } catch (e) {}
    document.cookie = 'finovate_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC; SameSite=Lax';
    if (window.location.pathname !== LOGIN_PATH) {
      window.location.href = LOGIN_PATH;
    }
  }

  /* ── Intercept fetch ── */
  var _originalFetch = window.fetch;
  window.fetch = function () {
    return _originalFetch.apply(this, arguments).then(function (response) {
      if (response.status === 401) {
        // Clone to allow the original caller to still read the body if needed
        response.clone().json().then(function (data) {
          var msg = (data && data.message) ? data.message : '';
          if (msg.toLowerCase().indexOf('expired') !== -1 || msg.toLowerCase().indexOf('jwt') !== -1 || response.status === 401) {
            clearTokenAndRedirect();
          }
        }).catch(function () {
          clearTokenAndRedirect();
        });
      }
      return response;
    });
  };

  /* ── Intercept XMLHttpRequest ── */
  var _open = XMLHttpRequest.prototype.open;
  var _send = XMLHttpRequest.prototype.send;

  XMLHttpRequest.prototype.open = function (method, url) {
    this._url = url;
    return _open.apply(this, arguments);
  };

  XMLHttpRequest.prototype.send = function () {
    var xhr = this;
    var _onreadystatechange = xhr.onreadystatechange;
    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4 && xhr.status === 401) {
        clearTokenAndRedirect();
      }
      if (_onreadystatechange) _onreadystatechange.apply(this, arguments);
    };
    return _send.apply(this, arguments);
  };

})();
