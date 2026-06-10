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

$appFavicon = app_setting('app_favicon', '');
$faviconUrl = base_url('favicon.ico');
if ($appFavicon !== '') {
    $faviconUrl = base_url($appFavicon);
}

$primaryColor = app_setting('primary_color', '');
$secondaryColor = app_setting('secondary_color', '');
$appLogo = app_setting('app_logo', '');
?>
<!doctype html>
<html lang="en" data-theme="<?= esc($theme); ?>">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $authTitle; ?> &middot; <?= $authBrand; ?></title>
  <link rel="icon" id="appFavicon" href="<?= $faviconUrl; ?>?v=<?= esc(app_setting('asset_version', '1')); ?>">
  <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>">
  <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.css'); ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/app.css'); ?>?v=<?= esc(app_setting('asset_version', '1')); ?>">
  <?php if ($primaryColor !== '' || $secondaryColor !== '') { ?>
  <style>
  :root {
    <?php if ($primaryColor !== '') { ?>
    --primary: <?= esc($primaryColor); ?>;
    --primary-300: <?= esc($primaryColor); ?>;
    <?php } ?>
    <?php if ($secondaryColor !== '') { ?>
    --primary-700: <?= esc($secondaryColor); ?>;
    <?php } ?>
    <?php
    $gradStart = '#0792cd';
    if ($primaryColor !== '') {
        $gradStart = $primaryColor;
    }
    $gradEnd = '#0476a7';
    if ($secondaryColor !== '') {
        $gradEnd = $secondaryColor;
    }
    ?>
    --grad-primary: linear-gradient(135deg, <?= esc($gradStart); ?> 0%, <?= esc($gradEnd); ?> 100%);
  }
  </style>
  <?php } ?>
  <script>
  (function(){
    var t = localStorage.getItem('noc-theme') || '<?= esc($theme); ?>';
    document.documentElement.setAttribute('data-theme', t);
  })();
  </script>
  <script src="<?= base_url('assets/vendor/jquery/jquery-3.7.1.min.js'); ?>"></script>
  <script src="<?= base_url('assets/js/datatable.js'); ?>?v=<?= esc(app_setting('asset_version', '1')); ?>"></script>
  <script src="<?= base_url('assets/js/app.js'); ?>?v=<?= esc(app_setting('asset_version', '1')); ?>"></script>
</head>

<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="auth-brand">
        <?php if ($appLogo !== '') { ?>
          <img src="<?= base_url($appLogo); ?>?v=<?= esc(app_setting('asset_version', '1')); ?>" alt="Logo" style="max-height: 42px; width: auto; object-fit: contain;">
        <?php } else { ?>
          <div class="brand-mark"><i class="bi bi-broadcast-pin"></i></div>
        <?php } ?>
        <span class="brand-text"><?= $authBrand; ?></span>
      </div>
      <p class="auth-tagline">Real-time alert &amp; ticket orchestration for NOC teams.</p>
