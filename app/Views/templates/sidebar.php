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
$appLogo       = app_setting('app_logo', '');

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
    <?php if ($appLogo !== '') { ?>
      <img src="<?= base_url($appLogo); ?>?v=<?= esc(app_setting('asset_version', '1')); ?>" alt="Logo" class="brand-logo-img" style="max-width: 30px; max-height: 30px; object-fit: contain;">
    <?php } else { ?>
      <div class="brand-mark"><i class="bi bi-broadcast-pin"></i></div>
    <?php } ?>
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
    // Predefined category order to preserve user navigation structure
    $predefinedCategories = ['Overview', 'Configuration', 'Operations', 'System', 'Administration'];

    $db = \Config\Database::connect();
    $modulesFromDb = $db->table('modules')
      ->where('show_in_menu', 1)
      ->orderBy('sort_order', 'asc')
      ->get()->getResultArray();

    $grouped = [];
    foreach ($modulesFromDb as $m) {
      $permKey = '';
      if (!empty($m['permission_module_key'])) {
        $permKey = $m['permission_module_key'];
      } else {
        $permKey = $m['module_key'];
      }

      $permAction = '';
      if (!empty($m['permission_action'])) {
        $permAction = $m['permission_action'];
      } else {
        $permAction = 'view';
      }

      if (has_module_access($permKey, $permAction) === true) {
        $grouped[$m['category']][] = $m;
      }
    }

    $allCategories = array_unique(array_merge($predefinedCategories, array_keys($grouped)));

    foreach ($allCategories as $cat) {
      if (empty($grouped[$cat])) {
        continue;
      }
      echo '<div class="nav-section">' . esc($cat) . '</div>';
      foreach ($grouped[$cat] as $m) {
        $uriPath = (string) $m['uri_path'];
        $parts = explode('/', $uriPath);
        
        $pFirst = '';
        if (isset($parts[0])) {
          $pFirst = $parts[0];
        }
        
        $pSecond = '';
        if (isset($parts[1])) {
          $pSecond = $parts[1];
        }

        $isActive = false;
        if ($pFirst === $first) {
          if ($pSecond === $second) {
            $isActive = true;
          } elseif ($pSecond === '') {
            $hasMoreSpecific = false;
            foreach ($modulesFromDb as $other) {
              $otherUri = (string) $other['uri_path'];
              $otherParts = explode('/', $otherUri);
              
              $otherFirst = '';
              if (isset($otherParts[0])) {
                $otherFirst = $otherParts[0];
              }
              
              $otherSecond = '';
              if (isset($otherParts[1])) {
                $otherSecond = $otherParts[1];
              }
              
              if ($otherFirst === $first && $otherSecond === $second) {
                $otherPermKey = '';
                if (!empty($other['permission_module_key'])) {
                  $otherPermKey = $other['permission_module_key'];
                } else {
                  $otherPermKey = $other['module_key'];
                }
                
                $otherPermAction = '';
                if (!empty($other['permission_action'])) {
                  $otherPermAction = $other['permission_action'];
                } else {
                  $otherPermAction = 'view';
                }
                
                if (has_module_access($otherPermKey, $otherPermAction) === true) {
                  $hasMoreSpecific = true;
                  break;
                }
              }
            }
            if (!$hasMoreSpecific) {
              $isActive = true;
            }
          }
        }

        $activeClass = '';
        if ($isActive) {
          $activeClass = 'active';
        }
        
        $linkUrl = site_url($uriPath);
        
        $iconClass = '';
        if (!empty($m['icon'])) {
          $iconClass = $m['icon'];
        } else {
          $iconClass = 'bi-circle';
        }
    ?>
        <a class="nav-link <?= $activeClass; ?>" href="<?= esc($linkUrl); ?>">
          <i class="<?= esc($iconClass); ?>"></i>
          <span><?= esc($m['name']); ?></span>
        </a>
    <?php
      }
    }
    ?>
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
      <?php
      $globalDateRange = get_global_date_range();
      $globalDatePreset = $globalDateRange['preset'];
      $globalDateStart = $globalDateRange['start'];
      $globalDateEnd = $globalDateRange['end'];
      $globalDateLabel = get_global_date_range_label($globalDatePreset, $globalDateStart, $globalDateEnd);
      ?>
      <div class="dropdown global-date-dropdown me-2">
        <button type="button" class="btn btn-topbar-filter dropdown-toggle" id="globalDateRangeToggle" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
          <i class="bi bi-calendar3 me-2"></i>
          <span id="globalDateRangeLabel"><?= esc($globalDateLabel); ?></span>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0 global-date-menu-premium" aria-labelledby="globalDateRangeToggle">
          <div id="globalDateStateStore" 
               data-preset="<?= esc($globalDatePreset); ?>" 
               data-start="<?= esc($globalDateStart); ?>" 
               data-end="<?= esc($globalDateEnd); ?>"
               data-update-url="<?= site_url('global_date_range/update'); ?>"
               data-reset-url="<?= site_url('global_date_range/reset'); ?>"
               data-csrf-name="<?= csrf_token(); ?>"
               data-csrf-hash="<?= csrf_hash(); ?>">
          </div>
          <div class="premium-picker-container">
            <!-- Sidebar Presets -->
            <div class="picker-presets">
              <?php
              $presets = [
                  'today'      => 'Today',
                  'yesterday'  => 'Yesterday',
                  '7d'         => 'Last 7 Days',
                  '30d'        => 'Last 30 Days',
                  '90d'        => 'Last 90 Days',
                  'this_month' => 'This Month',
                  'last_month' => 'Last Month',
                  'custom'     => 'Custom Range'
              ];
              foreach ($presets as $pKey => $pLabel) {
                  $activeClass = '';
                  if ($globalDatePreset === $pKey) {
                      $activeClass = 'active';
                  }
              ?>
                <button type="button" class="preset-btn <?= $activeClass; ?>" data-preset="<?= esc($pKey); ?>"><?= esc($pLabel); ?></button>
              <?php } ?>
            </div>

            <!-- Main Calendar Area -->
            <div class="picker-main">
              <div class="calendars-wrapper">
                <!-- Left Calendar -->
                <div class="calendar-panel left-calendar" data-calendar-side="left">
                  <div class="calendar-header">
                    <button type="button" class="btn-cal-nav prev-month"><i class="bi bi-chevron-left"></i></button>
                    <span class="month-year-label"></span>
                    <button type="button" class="btn-cal-nav next-month" style="visibility: hidden;"><i class="bi bi-chevron-right"></i></button>
                  </div>
                  <div class="calendar-weekdays">
                    <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                  </div>
                  <div class="calendar-days">
                    <!-- Days will be generated by JS -->
                  </div>
                </div>

                <!-- Right Calendar -->
                <div class="calendar-panel right-calendar" data-calendar-side="right">
                  <div class="calendar-header">
                    <button type="button" class="btn-cal-nav prev-month" style="visibility: hidden;"><i class="bi bi-chevron-left"></i></button>
                    <span class="month-year-label"></span>
                    <button type="button" class="btn-cal-nav next-month"><i class="bi bi-chevron-right"></i></button>
                  </div>
                  <div class="calendar-weekdays">
                    <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                  </div>
                  <div class="calendar-days">
                    <!-- Days will be generated by JS -->
                  </div>
                </div>
              </div>

              <!-- Footer Actions -->
              <div class="picker-footer">
                <div class="footer-reset">
                  <button type="button" class="btn btn-sm btn-link text-decoration-none text-danger ps-0" id="globalDateResetBtn">Reset</button>
                </div>
                <div class="footer-buttons">
                  <button type="button" class="btn btn-sm btn-outline-cancel" id="globalDateCancelBtn">Cancel</button>
                  <button type="button" class="btn btn-sm btn-apply-premium" id="globalDateApplyBtn">Apply</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

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