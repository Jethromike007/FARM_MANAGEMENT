<?php // templates/footer.php ?>
    </main><!-- /ff-content -->
  </div><!-- /ff-main -->
</div><!-- /ff-layout -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- FarmFlow App JS -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>

<?php if (isset($extraScripts)): ?>
  <?= $extraScripts ?>
<?php endif; ?>

</body>
</html>
