// Progressive enhancement only. Every action is also validated and authorized on the server.
(function () {
  var dlg = document.getElementById('confirm-dialog'), text = document.getElementById('confirm-text');
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!(f instanceof HTMLFormElement)) { return; }
    var msg = f.getAttribute('data-confirm');
    if (msg && !f.dataset.confirmed && dlg && typeof dlg.showModal === 'function') {
      ev.preventDefault();
      text.textContent = msg;
      var submitter = ev.submitter;
      dlg.returnValue = '';
      dlg.addEventListener('close', function onClose() {
        dlg.removeEventListener('close', onClose);
        if (dlg.returnValue === 'ok') { f.dataset.confirmed = '1'; if (f.requestSubmit) { f.requestSubmit(submitter || undefined); } else { f.submit(); } }
      });
      dlg.showModal();
      return;
    }
    if (f.method && f.method.toLowerCase() === 'post') {   // prevent accidental double submits
      setTimeout(function () { f.querySelectorAll('button[type=submit],button:not([type])').forEach(function (b) { b.disabled = true; }); }, 20);
    }
  });
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (t.matches && t.matches('[data-add-allergy]')) {
      var tpl = document.getElementById('allergy-row-tpl'), box = document.getElementById('allergy-rows');
      if (tpl && box) { box.appendChild(tpl.content.cloneNode(true)); }
    }
    if (t.matches && t.matches('[data-menu]')) { var s = document.getElementById('side'); if (s) { s.classList.toggle('open'); } }
  });
  window.addEventListener('pageshow', function () {   // re-enable buttons when the user navigates back
    document.querySelectorAll('button[disabled]').forEach(function (b) { b.disabled = false; });
  });
})();
