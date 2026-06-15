<?php

/**
 * Reusable date-range widget — preset buttons + From/To date inputs.
 *
 * Accepted $data keys (all optional):
 *   drFromId   — id="" on the From input   (default: none)
 *   drToId     — id="" on the To input     (default: none)
 *   drFromName — name="" on the From input (default: 'f_from')
 *   drToName   — name="" on the To input   (default: 'f_to')
 *   drFrom     — initial From value        (default: today)
 *   drTo       — initial To value          (default: today)
 *   drDefault  — active preset on render:
 *                today | yesterday | 7d | 30d | month | all
 *                (default: 'today')
 */

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
// Fall back to 'today' for removed presets ('all', 'month').
$drDefault = 'today';
if (isset($drDefault)) {
  $drDefault = (string) $drDefault;
}
if (!isset($presetLabels[$drDefault])) {
  $drDefault = 'today';
}

$inlineClass = '';
if ($drInline) {
  $inlineClass = ' is-inline';
}
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
      <?php if ($drFromId !== '') { ?>id="<?= esc($drFromId) ?>" <?php } ?>
      name="<?= esc($drFromName) ?>"
      data-date-range-from
      value="<?= esc($drFrom) ?>"
      data-default="<?= esc($drFrom) ?>">
    <span class="drw-sep">–</span>
    <input type="date"
      class="form-control form-control-sm"
      <?php if ($drToId !== '') { ?>id="<?= esc($drToId) ?>" <?php } ?>
      name="<?= esc($drToName) ?>"
      data-date-range-to
      value="<?= esc($drTo) ?>"
      data-default="<?= esc($drTo) ?>">
  </div>
  <span class="drw-error text-danger small" style="display:none;"></span>
</div>