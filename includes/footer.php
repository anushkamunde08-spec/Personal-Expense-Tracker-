<?php
/**
 * Footer Layout Component
 * Personal Expense Tracker
 */
?>
    </main> <!-- End Main Content -->

    <footer class="mt-auto py-3 bg-body border-top text-center text-muted small">
      <div class="container-fluid">
        <span>&copy; <?php echo date('Y'); ?> <?php echo e(APP_NAME); ?>. Built with PHP &amp; MySQL.</span>
      </div>
    </footer>

  </div> <!-- End Content Wrapper -->

</div> <!-- End Layout Wrapper -->

<!-- Bootstrap 5 JavaScript Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom App JS -->
<script src="<?php echo BASE_URL; ?>assets/js/app.js"></script>

</body>
</html>
