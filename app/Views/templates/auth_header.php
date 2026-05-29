<?php
$authTitle = 'Sign in';
if (isset($title) && $title !== '') {
    $authTitle = esc($title);
}
$authBrand = esc(app_setting('app_name', 'pView Alert System'));

// Persistent theme logic for the auth screens. No session yet (user is
// not logged in), so we only consult the long-lived `theme` cookie
// written by initThemeToggle() and fall back to 'dark'. The inline
// boot script below then prefers localStorage when present so a
// fresh tab on the same browser still picks up the user's last choice.
$theme = 'dark';
if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
}
?>
<!doctype html>
<html lang="en" data-theme="<?= esc($theme); ?>">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $authTitle; ?> &middot; <?= $authBrand; ?></title>
  <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>">
  <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.css'); ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/app.css'); ?>?v=3">
  <script>
  (function(){
    var t = localStorage.getItem('noc-theme') || '<?= esc($theme); ?>';
    document.documentElement.setAttribute('data-theme', t);
  })();
  </script>
  <script src="<?= base_url('assets/vendor/jquery/jquery-3.7.1.min.js'); ?>"></script>
  <script src="<?= base_url('assets/js/app.js'); ?>?v=20"></script>
</head>

<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="auth-brand">
        <div class="brand-mark"><i class="bi bi-broadcast-pin"></i></div>
        <span class="brand-text"><?= $authBrand; ?></span>
      </div>
      <p class="auth-tagline">Real-time alert &amp; ticket orchestration for NOC teams.</p>
