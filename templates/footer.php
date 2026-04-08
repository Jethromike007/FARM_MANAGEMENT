<?php // templates/footer.php ?>
    </main>
  </div><!-- /ff-main -->
</div><!-- /ff-layout -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Bootstrap Icons CSS (required for all bi-* icon classes in buttons/badges) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Chart.js — must load before any inline chart scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- FarmFlow app.js -->
<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>

<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>