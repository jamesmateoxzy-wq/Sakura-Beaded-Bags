<?php
/**
 * Shared layout (bottom part). Closes what header.php opened.
 * Optional: set $pageJs = 'dashboard.js' before including to load a page script.
 */
$pageJs = $pageJs ?? '';
?>
    </main>
  </div><!-- /.main -->
</div><!-- /.app -->

<div class="toast-wrap" id="toastWrap" aria-live="polite"></div>

<script src="../assets/js/main.js"></script>
<?php if ($pageJs): ?><script src="../assets/js/<?= e($pageJs) ?>"></script><?php endif; ?>
</body>
</html>