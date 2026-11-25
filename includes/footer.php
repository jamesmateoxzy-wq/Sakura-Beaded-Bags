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

<script src="<?= e(sbb_asset_url('js/main.js')) ?>"></script>
<?php if ($pageJs): ?><script src="<?= e(sbb_asset_url('js/' . $pageJs)) ?>"></script><?php endif; ?>
</body>
</html>