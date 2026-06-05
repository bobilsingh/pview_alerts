<?php
$forcedFlag = false;
if (isset($forced)) {
  $forcedFlag = (bool) $forced;
}
$appName = app_setting('app_name', 'pView Alert System');
$rotateDays = (int) app_setting('password_rotate_days', 90);
?>

<h4 class="mb-1">
  <?php if ($forcedFlag) { ?>
    Update your password
  <?php } else { ?>
    Change your password
  <?php } ?>
</h4>
<p class="text-muted mb-4">
  <?php if ($forcedFlag) { ?>
    Your password is older than <?= (int) $rotateDays; ?> days. For security, please set a new one to continue using <?= esc($appName); ?>.
  <?php } else { ?>
    Set a new password for your <?= esc($appName); ?> account.
  <?php } ?>
</p>

<?php if (!empty($error)) { ?>
  <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= esc($error); ?></div>
<?php } ?>

<form action="<?= site_url('password/change'); ?>" method="post" autocomplete="off" data-loading-form="1">

  <label class="form-label">Current password</label>
  <div class="input-group mb-3">
    <span class="input-group-text"><i class="bi bi-lock"></i></span>
    <input type="password" name="current_password" id="currentPassword" class="form-control" required autofocus
      data-caps-warn="1">
    <button type="button" class="btn btn-outline-secondary" data-toggle-password="currentPassword" tabindex="-1" title="Show / hide password" aria-label="Show or hide password">
      <i class="bi bi-eye" aria-hidden="true"></i>
    </button>
  </div>

  <label class="form-label">New password</label>
  <div class="input-group mb-3">
    <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
    <input type="password" name="new_password" id="newPassword" class="form-control" required
      data-caps-warn="1" minlength="8">
    <button type="button" class="btn btn-outline-secondary" data-toggle-password="newPassword" tabindex="-1" title="Show / hide password">
      <i class="bi bi-eye"></i>
    </button>
  </div>

  <label class="form-label">Confirm new password</label>
  <div class="input-group mb-4">
    <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
    <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required
      data-caps-warn="1" minlength="8">
    <button type="button" class="btn btn-outline-secondary" data-toggle-password="confirmPassword" tabindex="-1" title="Show / hide password">
      <i class="bi bi-eye"></i>
    </button>
  </div>

  <button type="submit" class="btn btn-primary w-100">
    <i class="bi bi-check-lg"></i> <span class="btn-label">Update password</span>
  </button>

  <form method="post" action="<?= site_url('logout'); ?>" class="text-center mt-3">
    <?= csrf_field(); ?>
    <button type="submit" class="btn btn-link text-muted small p-0">
      <i class="bi bi-box-arrow-right"></i> Sign out
    </button>
  </form>
</form>