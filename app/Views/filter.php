<?php

/**
 * Unified filter bar view containing the card header layout.
 * Used to display common action buttons, filter counts, and filters titles.
 */

// Title and badge settings
if (isset($fbTitle)) {
  $fbTitle = (string) $fbTitle;
} else {
  $fbTitle = 'Filters';
}
if (isset($fbCountId)) {
  $fbCountId = (string) $fbCountId;
} else {
  $fbCountId = '';
}
if (isset($fbCountStart)) {
  $fbCountStart = (int) $fbCountStart;
} else {
  $fbCountStart = 0;
}

// Action button settings
if (isset($fbApplyId)) {
  $fbApplyId = (string) $fbApplyId;
} else {
  $fbApplyId = '';
}
if (isset($fbSubmit)) {
  $fbSubmit = (bool) $fbSubmit;
} else {
  $fbSubmit = false;
}

// Reset button settings
if (isset($fbResetId)) {
  $fbResetId = (string) $fbResetId;
} else {
  $fbResetId = '';
}
if (isset($fbResetHref)) {
  $fbResetHref = (string) $fbResetHref;
} else {
  $fbResetHref = '';
}
if (isset($fbResetClass)) {
  $fbResetClass = (string) $fbResetClass;
} else {
  $fbResetClass = '';
}
?>
<div class="card-header filter-bar-header">
  <span class="filter-bar-title">
    <i class="bi bi-funnel"></i>
    <?= esc($fbTitle) ?>
    <?php if ($fbCountId !== '') { ?>
      <span class="badge rounded-pill bg-primary ms-1 filter-bar-badge" id="<?= esc($fbCountId) ?>" <?php if ($fbCountStart === 0) { ?>hidden<?php } ?>>
        <?= $fbCountStart ?>
      </span>
    <?php } ?>
  </span>

  <div class="filter-bar-actions">
    <?php if ($fbApplyId !== '') { ?>
      <button type="button" id="<?= esc($fbApplyId) ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-check-lg"></i> Apply
      </button>
    <?php } elseif ($fbSubmit) { ?>
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="bi bi-check-lg"></i> Apply
      </button>
    <?php } ?>

    <?php if ($fbResetHref !== '') { ?>
      <a href="<?= esc($fbResetHref) ?>" class="btn btn-sm btn-light <?= esc($fbResetClass) ?>">
        <i class="bi bi-x-lg"></i> Reset
      </a>
    <?php } elseif ($fbResetId !== '') { ?>
      <button type="button" id="<?= esc($fbResetId) ?>" class="btn btn-sm btn-light">
        <i class="bi bi-x-lg"></i> Reset
      </button>
    <?php } ?>
  </div>
</div>