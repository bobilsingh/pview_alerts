<?php

/**
 * Unified filter bar view containing both header and date widget layouts.
 * Can render only the date range widget, or the full card header.
 */

// Title and badge settings
$fbTitle = 'Filters';
if (isset($fbTitle)) {
    $fbTitle = (string) $fbTitle;
}
$fbCountId = '';
if (isset($fbCountId)) {
    $fbCountId = (string) $fbCountId;
}
$fbCountStart = 0;
if (isset($fbCountStart)) {
    $fbCountStart = (int) $fbCountStart;
}

// Action button settings
$fbApplyId = '';
if (isset($fbApplyId)) {
    $fbApplyId = (string) $fbApplyId;
}
$fbSubmit = false;
if (isset($fbSubmit)) {
    $fbSubmit = (bool) $fbSubmit;
}

// Reset button settings
$fbResetId = '';
if (isset($fbResetId)) {
    $fbResetId = (string) $fbResetId;
}
$fbResetHref = '';
if (isset($fbResetHref)) {
    $fbResetHref = (string) $fbResetHref;
}
$fbResetClass = '';
if (isset($fbResetClass)) {
    $fbResetClass = (string) $fbResetClass;
}

// Date Range Widget settings
$drFromId = '';
if (isset($drFromId)) {
    $drFromId = (string) $drFromId;
}
$drInline = false;
if (isset($drInline)) {
    $drInline = (bool) $drInline;
}
$drToId = '';
if (isset($drToId)) {
    $drToId = (string) $drToId;
}
$drFromName = 'f_from';
if (isset($drFromName)) {
    $drFromName = (string) $drFromName;
}
$drToName = 'f_to';
if (isset($drToName)) {
    $drToName = (string) $drToName;
}
$drFrom = date('Y-m-d');
if (isset($drFrom)) {
    $drFrom = (string) $drFrom;
}
$drTo = date('Y-m-d');
if (isset($drTo)) {
    $drTo = (string) $drTo;
}
$presetLabels = [
  'today'     => 'Today',
  'yesterday' => 'Yesterday',
  '7d'        => '7d',
  '30d'       => '30d',
];
$drDefault = 'today';
if (isset($drDefault)) {
    $drDefault = (string) $drDefault;
}
if (!isset($presetLabels[$drDefault])) {
  $drDefault = 'today';
}

// Rendering mode control
$only_widget = false;
if (isset($only_widget)) {
    $only_widget = (bool) $only_widget;
}
$show_date_widget = false;
if (isset($show_date_widget)) {
    $show_date_widget = (bool) $show_date_widget;
}
$fbDateWidget = '';
if (isset($fbDateWidget)) {
    $fbDateWidget = (string) $fbDateWidget;
}

// ---------------------------------------------------------
// RENDER DATE RANGE WIDGET HTML
// ---------------------------------------------------------
$inlineClass = '';
if ($drInline) {
    $inlineClass = ' is-inline';
}

ob_start();
?>
<div class="date-range-widget<?= $inlineClass ?>" data-date-range>
  <div class="drw-presets">
    <?php foreach ($presetLabels as $key => $label) {
        $activeClass = '';
        if ($key === $drDefault) {
            $activeClass = ' active';
        }
        ?>
      <button type="button"
        class="drw-preset<?= $activeClass ?>"
        data-preset="<?= esc($key) ?>">
        <?= esc($label) ?>
      </button>
    <?php } ?>
  </div>
  <div class="drw-inputs">
    <input type="date"
      class="form-control form-control-sm"
      <?php if ($drFromId !== '') { ?>id="<?= esc($drFromId) ?>"<?php } ?>
      name="<?= esc($drFromName) ?>"
      data-date-range-from
      value="<?= esc($drFrom) ?>"
      data-default="<?= esc($drFrom) ?>">
    <span class="drw-sep">–</span>
    <input type="date"
      class="form-control form-control-sm"
      <?php if ($drToId !== '') { ?>id="<?= esc($drToId) ?>"<?php } ?>
      name="<?= esc($drToName) ?>"
      data-date-range-to
      value="<?= esc($drTo) ?>"
      data-default="<?= esc($drTo) ?>">
  </div>
  <span class="drw-error text-danger small" style="display:none;"></span>
</div>
<?php
$renderedDateWidget = ob_get_clean();

// ---------------------------------------------------------
// OUTPUT ACCORDING TO MODE
// ---------------------------------------------------------
if ($only_widget) {
    echo $renderedDateWidget;
} else {
    // If we want the date widget and no pre-rendered HTML was supplied, use the internal layout
    if ($fbDateWidget === '' && $show_date_widget) {
        $fbDateWidget = $renderedDateWidget;
    }
    ?>
<div class="card-header filter-bar-header">
  <span class="filter-bar-title">
    <i class="bi bi-funnel"></i>
    <?= esc($fbTitle) ?>
    <?php if ($fbCountId !== '') { ?>
      <span class="badge rounded-pill bg-primary ms-1 filter-bar-badge"
        id="<?= esc($fbCountId) ?>"
        <?php if ($fbCountStart === 0) { ?>hidden<?php } ?>>
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
<?php
}
