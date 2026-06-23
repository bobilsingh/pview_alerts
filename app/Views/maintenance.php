<?php
$appName = app_setting('app_name', 'pView Alert System');
if (isset($isSuperAdmin)) {
  $isSuperAdmin = (bool) $isSuperAdmin;
} else {
  $isSuperAdmin = false;
}
?>
<div class="text-center py-4">
  <div class="mb-3" style="font-size:3rem;line-height:1;">
    <i class="bi bi-tools text-warning"></i>
  </div>
  <h3 class="fw-bold mb-2">Under Maintenance</h3>
  <p class="text-muted mb-4">
    <?= esc($appName); ?> is currently undergoing scheduled maintenance.<br>
    We'll be back shortly. Thank you for your patience.
  </p>
  <hr class="my-3">

  <?php if ($isSuperAdmin) { ?>
    <p class="small text-muted mb-3">
      You are signed in as <strong>super_admin</strong>.
    </p>
    <form method="post" action="<?= site_url('maintenance/disable'); ?>">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-toggle-off"></i> Disable Maintenance Mode
      </button>
    </form>
  <?php } else { ?>
    <p class="small text-muted mb-0">
      If you are an administrator,
      <a href="<?= site_url('login'); ?>">sign in here</a>.
    </p>
  <?php } ?>
</div>