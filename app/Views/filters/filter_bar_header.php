<?php

/**
 * Common filter bar card-header partial.
 * Drop inside a .card as the first child (before .card-body).
 *
 * Accepted $data keys:
 *   fbTitle      — header label (default: 'Filters')
 *   fbCountId    — id="" on the active-count badge (optional)
 *   fbCountStart — initial badge number shown; badge hidden when 0 (default: 0)
 *
 *   Apply button — one of:
 *     fbApplyId    — id="" for an AJAX/button-type apply button
 *     fbSubmit     — (bool) true → render a type="submit" button inside the form
 *
 *   Reset control — one of:
 *     fbResetId    — id="" for an AJAX/button-type reset button
 *     fbResetHref  — href for a reset anchor link
 *     fbResetClass — extra CSS classes on the reset anchor (default: '')
 */

$fbTitle      = isset($fbTitle)      ? (string) $fbTitle      : 'Filters';
$fbCountId    = isset($fbCountId)    ? (string) $fbCountId    : '';
$fbCountStart = isset($fbCountStart) ? (int)    $fbCountStart : 0;
$fbApplyId    = isset($fbApplyId)    ? (string) $fbApplyId    : '';
$fbSubmit     = isset($fbSubmit)     ? (bool)   $fbSubmit     : false;
$fbResetId    = isset($fbResetId)    ? (string) $fbResetId    : '';
$fbResetHref  = isset($fbResetHref)  ? (string) $fbResetHref  : '';
$fbResetClass = isset($fbResetClass) ? (string) $fbResetClass : '';
// Pre-rendered date-range widget HTML to place left of Apply (optional).
$fbDateWidget = isset($fbDateWidget) ? (string) $fbDateWidget : '';
?>
<div class="card-header filter-bar-header">
  <span class="filter-bar-title">
    <i class="bi bi-funnel"></i>
    <?= esc($fbTitle) ?>
    <?php if ($fbCountId !== '') { ?>
      <span class="badge rounded-pill bg-primary ms-1 filter-bar-badge"
        id="<?= esc($fbCountId) ?>"
        <?= $fbCountStart === 0 ? 'hidden' : '' ?>>
        <?= $fbCountStart ?>
      </span>
    <?php } ?>
  </span>

  <div class="filter-bar-actions">
    <?php if ($fbDateWidget !== '') { ?>
      <span class="filter-bar-sep"></span>
      <div class="filter-bar-date"><?= $fbDateWidget ?></div>
    <?php } ?>

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
      <a href="<?= esc($fbResetHref) ?>"
        class="btn btn-sm btn-light <?= esc($fbResetClass) ?>">
        <i class="bi bi-x-lg"></i> Reset
      </a>
    <?php } elseif ($fbResetId !== '') { ?>
      <button type="button" id="<?= esc($fbResetId) ?>" class="btn btn-sm btn-light">
        <i class="bi bi-x-lg"></i> Reset
      </button>
    <?php } ?>
  </div>
</div>