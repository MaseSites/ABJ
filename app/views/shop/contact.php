<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="panel">
  <h1>Kontakt</h1>
  <form method="post">
    <input name="name" placeholder="Name" required>
    <input name="email" type="email" placeholder="E-Mail" required>
    <input name="subject" placeholder="Betreff" required>
    <textarea name="message" placeholder="Nachricht" required></textarea>
    <button class="button" type="submit">Senden</button>
  </form>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
