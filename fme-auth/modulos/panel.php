<h1 class="title">Panel de control</h1>
<p class="subtitle">Selecciona un módulo para comenzar.</p>

<div class="cards">
  <?php foreach ($modulos as $m): ?>
  <article class="card" id="<?= htmlspecialchars($m['id']) ?>">
    <div class="card-icon"><?= $m['icon'] ?></div>
    <div class="card-title"><?= htmlspecialchars($m['titulo']) ?></div>
    <button class="card-btn" data-href="<?= htmlspecialchars($m['url']) ?>">Abrir</button>
  </article>
  <?php endforeach; ?>
</div>
