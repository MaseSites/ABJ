    </main><!-- .admin-main -->
  </div><!-- .admin-content -->
</div><!-- .admin-shell -->
<script src="<?= url('/js/admin.js') ?>?v=41"></script>
<script src="<?= url('/js/inventory.js') ?>?v=41"></script>
<?php if (!admin_is_root()): ?>
<script>
/* Eingeschränktes Lookup-Konto: alle Aktionsknöpfe sperren, ausser denen in
   einem erlaubten data-cap-Container. (Server-seitig zusätzlich abgesichert.) */
(function () {
  var body = document.body;
  if (!body.classList.contains('admin-lookup')) return;
  var allowed = (body.getAttribute('data-admin-caps') || '').split(',').filter(Boolean);
  var main = document.querySelector('.admin-main');
  if (!main) return;
  function ok(el) {
    var c = el.closest('[data-cap]');
    return !!c && allowed.indexOf(c.getAttribute('data-cap')) !== -1;
  }
  main.querySelectorAll('button, input[type=submit]').forEach(function (btn) {
    if (ok(btn)) return;
    btn.disabled = true;
    btn.classList.add('is-locked');
    if (!btn.title) btn.title = 'Keine Berechtigung – nur Lesezugriff';
  });
})();
</script>
<?php endif; ?>
</body>
</html>
