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

$drFromId   = isset($drFromId)   ? (string) $drFromId   : '';
$drInline   = isset($drInline)   ? (bool)   $drInline   : false;
$drToId     = isset($drToId)     ? (string) $drToId     : '';
$drFromName = isset($drFromName) ? (string) $drFromName : 'f_from';
$drToName   = isset($drToName)   ? (string) $drToName   : 'f_to';
$drFrom     = isset($drFrom)     ? (string) $drFrom     : date('Y-m-d');
$drTo       = isset($drTo)       ? (string) $drTo       : date('Y-m-d');
$presetLabels = [
  'today'     => 'Today',
  'yesterday' => 'Yesterday',
  '7d'        => '7d',
  '30d'       => '30d',
];
// Fall back to 'today' for removed presets ('all', 'month').
$drDefault = isset($drDefault) ? (string) $drDefault : 'today';
if (!isset($presetLabels[$drDefault])) {
  $drDefault = 'today';
}
?>
<div class="date-range-widget<?= $drInline ? ' is-inline' : '' ?>" data-date-range>
  <div class="drw-presets">
    <?php foreach ($presetLabels as $key => $label) { ?>
      <button type="button"
        class="drw-preset<?= $key === $drDefault ? ' active' : '' ?>"
        data-preset="<?= esc($key) ?>">
        <?= esc($label) ?>
      </button>
    <?php } ?>
  </div>
  <div class="drw-inputs">
    <input type="date"
      class="form-control form-control-sm"
      <?= $drFromId !== '' ? 'id="' . esc($drFromId) . '"' : '' ?>
      name="<?= esc($drFromName) ?>"
      data-date-range-from
      value="<?= esc($drFrom) ?>"
      data-default="<?= esc($drFrom) ?>">
    <span class="drw-sep">–</span>
    <input type="date"
      class="form-control form-control-sm"
      <?= $drToId !== '' ? 'id="' . esc($drToId) . '"' : '' ?>
      name="<?= esc($drToName) ?>"
      data-date-range-to
      value="<?= esc($drTo) ?>"
      data-default="<?= esc($drTo) ?>">
  </div>
</div>