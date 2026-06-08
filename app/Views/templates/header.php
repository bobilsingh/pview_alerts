<?php
$pageTitle = 'Alert System';
if (isset($title) && $title !== '') {
    $pageTitle = esc($title);
}

// Persistent theme logic
$theme = 'dark'; // default
$sessionTheme = \Config\Services::session()->get('theme');
if ($sessionTheme) {
    $theme = $sessionTheme;
} else if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
}
?>
<!doctype html>
<html lang="en" data-theme="<?= esc($theme); ?>">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title><?= $pageTitle; ?> - AlertOps</title>
   <link rel="icon" id="appFavicon" type="image/x-icon" href="<?= base_url('favicon.ico'); ?>">
   <meta name="app-setting-datatable_page_length" content="<?= app_setting_int('datatable_page_length', 10); ?>">
   <!-- Live-poll settings — read by initBellLivePoll() in app.js -->
   <meta name="app-setting-live_poll_seconds" content="<?= app_setting_int('live_poll_seconds', 15); ?>">
   <meta name="app-setting-live_audio_enabled" content="<?= app_setting_bool('live_audio_enabled', true) ? '1' : '0'; ?>">
   <meta name="app-setting-live_browser_notify" content="<?= app_setting_bool('live_browser_notify', true) ? '1' : '0'; ?>">
   <!-- Session idle timeout and analytics refresh — read by initAutoLogout() / initAnalyticsTab() -->
   <meta name="app-setting-session_idle_timeout_minutes" content="<?= app_setting_int('session_idle_timeout_minutes', 30); ?>">
   <meta name="app-setting-analytics_refresh_seconds" content="<?= app_setting_int('analytics_refresh_seconds', 30); ?>">

   <!-- ===== Stylesheets ===== -->
   <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>">
   <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.css'); ?>">
   <link rel="stylesheet" href="<?= base_url('assets/vendor/datatables/css/dataTables.bootstrap5.min.css'); ?>">
   <link rel="stylesheet" href="<?= base_url('assets/vendor/select2/css/select2.min.css'); ?>">
   <link rel="stylesheet" href="<?= base_url('assets/vendor/toastr/toastr.min.css'); ?>">
   <link rel="stylesheet" href="<?= base_url('assets/vendor/sweetalert2/sweetalert2.min.css'); ?>">
   <?php
   // Cache-buster sourced from the app_settings 'asset_version' row so an
   // admin can force every browser to reload after a manual edit on the
   // server — no source bump required. Editable from Settings → Assets.
   $assetVersion = (string) app_setting('asset_version', '1');
   if ($assetVersion === '') {
       $assetVersion = '1';
   }
   ?>
   <link rel="stylesheet" href="<?= base_url('assets/css/app.css'); ?>?v=<?= esc($assetVersion); ?>">
   <link rel="stylesheet" href="<?= base_url('assets/css/theme-dark.css'); ?>?v=<?= esc($assetVersion); ?>">
   <script>
   (function(){
     var t = localStorage.getItem('noc-theme') || '<?= esc($theme); ?>';
     document.documentElement.setAttribute('data-theme', t);
   })();
   </script>

   <!-- ===== JS libraries ===== -->
   <script src="<?= base_url('assets/vendor/jquery/jquery-3.7.1.min.js'); ?>"></script>
   <script src="<?= base_url('assets/vendor/jquery-ui/jquery-ui-1.13.2.min.js'); ?>"></script>
   <script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
   <script src="<?= base_url('assets/vendor/datatables/js/jquery.dataTables.min.js'); ?>"></script>
   <script src="<?= base_url('assets/vendor/datatables/js/dataTables.bootstrap5.min.js'); ?>"></script>
   <script src="<?= base_url('assets/vendor/select2/js/select2.min.js'); ?>"></script>
   <script src="<?= base_url('assets/vendor/toastr/toastr.min.js'); ?>"></script>
   <script src="<?= base_url('assets/vendor/sweetalert2/sweetalert2.min.js'); ?>"></script>
   <script src="<?= base_url('assets/vendor/chartjs/chart.umd.min.js'); ?>"></script>
   <script src="<?= base_url('assets/vendor/vis-network/vis-network.min.js'); ?>"></script>
   <script src="<?= base_url('assets/js/app.js'); ?>?v=<?= esc($assetVersion); ?>"></script>
   <script src="<?= base_url('assets/js/datatable.js'); ?>?v=<?= esc($assetVersion); ?>"></script>
</head>

<body>
   <a href="#mainContent" class="skip-to-content">Skip to main content</a>
   <div class="layout">
