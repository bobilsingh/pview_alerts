<?php

/**
 * Sidebar + topbar.
 * Loaded after templates/header.php on every secured page.
 */
$role         = logged_user_role();
$isAdmin      = role_has_admin_scope($role);
$isSuperAdmin = ($role === ROLE_SUPER_ADMIN);
$userName = logged_user_name();
$userUid  = logged_user_id();
$userMail = session('user_email');

$initialSource = $userMail;
if ($userName !== '') {
  $initialSource = $userName;
}
$initials = strtoupper(substr($initialSource, 0, 1));
$releaseVersion = config('App')->releaseVersion;
$appName       = app_setting('app_name', 'pView');

$uri = service('uri');
$first = $uri->getSegment(1);
$second = $uri->getSegment(2);

$_app_model = new \App\Models\app_model();

// Bell-icon notification badge — see App_model::ticketCountActionable
// for the SQL. Scoped by role: admins see all, users see only tickets
// they raised / are assigned to / are listed in any level user IDs of.
$actionableCounts = $_app_model->ticketCountActionable(logged_user_id(), $isAdmin);
$actionableTotal  = (int) $actionableCounts['total'];

if ($actionableTotal > 99) {
  $actionableBadge = '99+';
} else {
  $actionableBadge = (string) $actionableTotal;
}

if ($actionableTotal === 0) {
  $actionableTip = 'No actionable tickets';
} else {
  $tipParts = [];
  if ($actionableCounts['critical_open'] > 0) {
    $tipParts[] = $actionableCounts['critical_open'] . ' critical open';
  }
  if ($actionableCounts['escalated'] > 0) {
    $tipParts[] = $actionableCounts['escalated'] . ' escalated';
  }
  $actionableTip = $actionableTotal . ' actionable: ' . implode(' · ', $tipParts);
}

$breadcrumbTitle = 'Page';
if (isset($title) && $title !== '') {
  $breadcrumbTitle = esc($title);
}

$bellUrl = site_url('tickets');
if ($isAdmin) {
  $bellUrl = site_url('tickets/all');
}

$okMsg = \Config\Services::session()->getFlashdata('success');
$errMsg = \Config\Services::session()->getFlashdata('error');
?>

<aside class="sidebar" id="appSidebar">
  <div class="brand">
    <div class="brand-mark"><i class="bi bi-broadcast-pin"></i></div>
    <div class="brand-text">
      <div class="brand-title-row">
        <span><?= esc($appName); ?></span>
        <small class="brand-version"><?= esc($releaseVersion); ?></small>
      </div>
      <small class="brand-subtitle">Alert System</small>
    </div>
  </div>

  <nav class="nav">
    <?php
    // Overview group — Dashboard lives in its own section at the top of
    // the menu so it sits above Configuration / Operations / System and
    // matches operator muscle-memory ("status page first").
    if (has_module_access('dashboard', 'view') === true) {
    ?>
      <div class="nav-section">Overview</div>
      <a class="nav-link <?php if ($first === 'dashboard') {
                            echo 'active';
                          } ?>" href="<?= site_url('dashboard'); ?>"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
    <?php } ?>

    <?php
    $showConfig = false;
    if (has_module_access('projects', 'view') === true) {
      $showConfig = true;
    }
    if (has_module_access('flows', 'view') === true) {
      $showConfig = true;
    }
    if (has_module_access('alerts', 'view') === true) {
      $showConfig = true;
    }
    if (has_module_access('escalation', 'view') === true) {
      $showConfig = true;
    }

    if ($showConfig === true) {
    ?>
      <div class="nav-section">Configuration</div>
      <?php if (has_module_access('projects', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'projects') {
                              echo 'active';
                            } ?>" href="<?= site_url('projects'); ?>"><i class="bi bi-folder2-open"></i><span>Projects</span></a>
      <?php } ?>
      <?php if (has_module_access('flows', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'flows') {
                              echo 'active';
                            } ?>" href="<?= site_url('flows'); ?>"><i class="bi bi-diagram-3"></i><span>Flows</span></a>
      <?php } ?>
      <?php if (has_module_access('alerts', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'alerts') {
                              echo 'active';
                            } ?>" href="<?= site_url('alerts'); ?>"><i class="bi bi-bell-fill"></i><span>Alert Defs</span></a>
      <?php } ?>
      <?php if (has_module_access('escalation', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'escalation') {
                              echo 'active';
                            } ?>" href="<?= site_url('escalation'); ?>"><i class="bi bi-graph-up-arrow"></i><span>Escalation</span></a>
      <?php } ?>
    <?php } ?>

    <?php
    // Dashboard moved to its own Overview group above — Operations now
    // covers tickets only.
    $showOps = false;
    if (has_module_access('tickets', 'view') === true) {
      $showOps = true;
    }
    if (has_module_access('tickets', 'add') === true) {
      $showOps = true;
    }
    if (has_module_access('tickets_all', 'view') === true) {
      $showOps = true;
    }

    if ($showOps === true) {
    ?>
      <div class="nav-section">Operations</div>
      <?php if (has_module_access('tickets', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'tickets' && $second === '') {
                              echo 'active';
                            } ?>" href="<?= site_url('tickets'); ?>"><i class="bi bi-inbox-fill"></i><span>My Tickets</span></a>
      <?php } ?>
      <?php if (has_module_access('tickets', 'add') === true) { ?>
        <a class="nav-link <?php if ($first === 'tickets' && $second === 'create') {
                              echo 'active';
                            } ?>" href="<?= site_url('tickets/create'); ?>"><i class="bi bi-plus-square"></i><span>Raise Ticket</span></a>
      <?php } ?>
      <?php if (has_module_access('tickets_all', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'tickets' && $second === 'all') {
                              echo 'active';
                            } ?>" href="<?= site_url('tickets/all'); ?>"><i class="bi bi-list-task"></i><span>All Tickets</span></a>
      <?php } ?>
    <?php } ?>

    <?php
    // --- System section: manage users and monitor the system ---
    $showSystem = false;
    if (has_module_access('users', 'view') === true) {
      $showSystem = true;
    }
    if (has_module_access('api_keys', 'view') === true) {
      $showSystem = true;
    }
    if (has_module_access('activity_logs', 'view') === true) {
      $showSystem = true;
    }
    if (has_module_access('cron_panel', 'view') === true) {
      $showSystem = true;
    }

    if ($showSystem === true) {
    ?>
      <div class="nav-section">System</div>
      <?php if (has_module_access('users', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'users') {
                              echo 'active';
                            } ?>" href="<?= site_url('users'); ?>"><i class="bi bi-people-fill"></i><span>Users</span></a>
      <?php } ?>
      <?php if (has_module_access('api_keys', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'api_keys') {
                              echo 'active';
                            } ?>" href="<?= site_url('api_keys'); ?>"><i class="bi bi-key-fill"></i><span>API Keys</span></a>
      <?php } ?>
      <?php if (has_module_access('activity_logs', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'activity_logs') {
                              echo 'active';
                            } ?>" href="<?= site_url('activity_logs'); ?>"><i class="bi bi-clipboard-data"></i><span>Activity Log</span></a>
      <?php } ?>
      <?php if (has_module_access('cron_panel', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'cron_panel') {
                              echo 'active';
                            } ?>" href="<?= site_url('cron_panel'); ?>"><i class="bi bi-clock-history"></i><span>Cron Panel</span></a>
      <?php } ?>
    <?php } ?>

    <?php
    $showAdmin = false;
    if (has_module_access('roles', 'view') === true) {
      $showAdmin = true;
    }
    if (has_module_access('settings', 'view') === true) {
      $showAdmin = true;
    }
    if (has_module_access('module_control_panel', 'view') === true) {
      $showAdmin = true;
    }

    if ($showAdmin === true) {
    ?>
      <div class="nav-section">Administration</div>
      <?php if (has_module_access('roles', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'roles') {
                              echo 'active';
                            } ?>" href="<?= site_url('roles'); ?>"><i class="bi bi-person-badge"></i><span>Roles</span></a>
      <?php } ?>
      <?php if (has_module_access('settings', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'settings') {
                              echo 'active';
                            } ?>" href="<?= site_url('settings'); ?>"><i class="bi bi-gear-fill"></i><span>Settings</span></a>
      <?php } ?>
      <?php if (has_module_access('module_control_panel', 'view') === true) { ?>
        <a class="nav-link <?php if ($first === 'module_control_panel') {
                              echo 'active';
                            } ?>" href="<?= site_url('module_control_panel'); ?>"><i class="bi bi-shield-lock-fill"></i><span>Manage Module</span></a>
      <?php } ?>
    <?php } ?>
  </nav>

  <div class="sidebar-footer">
    <?php
    $userTip = $userName;
    if ($userUid !== '') {
      $userTip = $userName . ' (@' . $userUid . ')';
    }
    ?>
    <div class="user-chip" data-user-tip="<?= esc($userTip); ?>">
      <div class="avatar"><?= esc($initials); ?></div>
      <div class="user-meta">
        <div class="name"><?= esc($userName); ?></div>
        <div class="role">
          <?php if ($userUid !== '') { ?>
            <span class="user-handle">@<?= esc($userUid); ?></span> &middot;
          <?php } ?>
          <?= esc(str_replace('_', ' ', $role)); ?>
        </div>
      </div>
    </div>
  </div>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

<div class="main">
  <header class="topbar">
    <div class="topbar-left">
      <button type="button"
        class="sidebar-toggle"
        id="sidebarToggle"
        aria-label="Open navigation menu"
        aria-controls="appSidebar"
        aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
      <?php
      $bcHomeUrl   = first_accessible_module_url();
      $bcHomeLabel = first_accessible_module_label();
      ?>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="<?= esc($bcHomeUrl); ?>"><?= esc($bcHomeLabel); ?></a></li>
          <li class="breadcrumb-item active"><?= $breadcrumbTitle; ?></li>
        </ol>
      </nav>
    </div>
    <div class="topbar-right">
      <button type="button"
        id="themeToggle"
        class="theme-toggle"
        data-update-url="<?= site_url('users/update_theme'); ?>"
        title="Toggle light / dark theme"
        aria-label="Toggle light / dark theme">
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
      </button>

      <div class="dropdown topbar-bell-dropdown">
        <button type="button" class="topbar-bell" id="topbarBell" title="<?= esc($actionableTip); ?>" aria-label="<?= esc($actionableTip); ?>" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-actionable-url="<?= esc(site_url('notifications/actionable_count')); ?>" data-recent-url="<?= esc(site_url('notifications/recent')); ?>" data-bell-target="<?= esc($bellUrl); ?>">
          <i class="bi bi-bell-fill"></i>
          <span class="bell-badge<?php if ($actionableTotal > 0) {
                                    echo ' is-critical';
                                  } ?>" data-count="<?= $actionableTotal; ?>" <?php if ($actionableTotal === 0) {
                                                                                echo 'hidden';
                                                                              } ?>>
            <?= esc($actionableBadge); ?>
          </span>
        </button>
        <div class="dropdown-menu dropdown-menu-end bell-dropdown" id="bellDropdown" aria-labelledby="topbarBell">
          <div class="bell-dropdown-header">
            <strong>Notifications</strong>
            <span class="text-muted small"><?= esc($actionableTip); ?></span>
          </div>
          <div class="bell-dropdown-body" id="bellDropdownBody">
            <div class="text-center text-muted py-3 small">Loading…</div>
          </div>
          <div class="bell-dropdown-footer">
            <a href="<?= esc($bellUrl); ?>" class="small">View all tickets <i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </div>
      <div class="topbar-user" title="<?= esc($userMail); ?>">
        <div class="avatar-sm"><?= esc($initials); ?></div>
        <span class="name"><?= esc($userName); ?></span>
      </div>

      <form method="post" action="<?= site_url('logout'); ?>" class="d-inline">
        <?= csrf_field(); ?>
        <button type="submit" class="topbar-logout" title="Sign out" aria-label="Sign out">
          <i class="bi bi-box-arrow-right"></i>
        </button>
      </form>
    </div>
  </header>

  <main class="content" id="mainContent">

    <?php if ($okMsg) { ?>
      <div class="alert alert-success alert-dismissible fade show"><?= esc($okMsg); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php } ?>
    <?php if ($errMsg) { ?>
      <div class="alert alert-danger alert-dismissible fade show"><?= esc($errMsg); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php } ?>