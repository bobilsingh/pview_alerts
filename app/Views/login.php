<?php
$oldLoginValue = '';
if (isset($oldLogin)) {
  $oldLoginValue = $oldLogin;
}

$appName = app_setting('app_name', 'pView Alert System');
$showDemo = (int) app_setting('login_show_demo_creds', 0);
?>

<h4 class="mb-1">Sign in to <?= esc($appName); ?></h4>
<p class="text-muted mb-4">Authenticate with your operator credentials.</p>

<?php if (!empty($error)) { ?>
  <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= esc($error); ?></div>
<?php } ?>

<form action="<?= site_url('login'); ?>" method="post" autocomplete="off" data-loading-form="1">

  <label class="form-label">User ID or Email</label>
  <div class="input-group mb-3">
    <span class="input-group-text"><i class="bi bi-person"></i></span>
    <input type="text" name="login" class="form-control" required autofocus placeholder="jdoe or operator@fapps.com"
      value="<?= esc($oldLoginValue); ?>">
  </div>

  <label class="form-label">Password</label>
  <div class="input-group mb-4">
    <span class="input-group-text"><i class="bi bi-lock"></i></span>
    <input type="password" name="password" id="loginPassword" class="form-control" required placeholder="********"
      data-caps-warn="1">
    <button type="button" class="btn btn-outline-secondary" data-toggle-password="loginPassword" tabindex="-1"
      title="Show / hide password" aria-label="Show or hide password">
      <i class="bi bi-eye" aria-hidden="true"></i>
    </button>
  </div>

  <button type="submit" class="btn btn-primary w-100">
    <i class="bi bi-box-arrow-in-right"></i> <span class="btn-label">Sign In</span>
  </button>
</form>

<?php
if (ENVIRONMENT !== 'production' && $showDemo === 1) {
  ?>
  <div class="auth-foot">
    <i class="bi bi-info-circle"></i>
    Default operator: <code>admin</code> / <code>Admin@1234</code>
  </div>
  <?php
}
?>