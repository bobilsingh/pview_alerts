<?php
// Per-user notification matrix. Rows = projects (with "All projects" first
// as the catch-all), columns = severity (info, major, critical). Each cell
// is a checkbox: checked = email me for that combination.
//
// Lenient default: when the user has never visited this page, no rows
// exist in user_notification_settings, so user_notify_allowed() returns
// true and they get every alert. Once they save the form, every cell is
// stored explicitly. If they uncheck "critical for project X" they
// genuinely stop receiving those emails.

if (!isset($existing) || !is_array($existing)) {
  $existing = [];
}

$severities = ['info' => 'Info', 'major' => 'Major', 'critical' => 'Critical'];

// Decide checked-state from $existing, lenient default true.
function notif_pref_checked($existing, $project_id, $severity)
{
  $key = (int) $project_id . '|' . (string) $severity;
  if (isset($existing[$key])) {
    return ((int) $existing[$key]) === 1;
  }
  return true; // lenient default
}
?>

<div class="page-head">
  <div>
    <h2>Notification Preferences</h2>
    <div class="subtitle">Pick which severity emails you want, per project. "All projects" is a catch-all — used when a specific project row is unset.</div>
  </div>
  <a href="<?= site_url('dashboard'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<form method="post" action="<?= site_url('me/notifications'); ?>" data-loading-form="1">
  <div class="card mb-3">
    <div class="card-header">
      <strong>Email me for these tickets</strong>
    </div>
    <div class="card-body p-0">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 50%; padding-left: 20px;">Project</th>
            <?php foreach ($severities as $sevKey => $sevLabel) { ?>
              <th class="text-center" style="width: 16%;"><?= esc($sevLabel); ?></th>
            <?php } ?>
          </tr>
        </thead>
        <tbody>
          <!-- All-projects catch-all row -->
          <tr>
            <td style="padding-left: 20px;">
              <strong>All projects</strong>
              <div><small class="text-muted">Used when no specific project row is checked below.</small></div>
            </td>
            <?php foreach ($severities as $sevKey => $sevLabel) { ?>
              <?php $checked = notif_pref_checked($existing, 0, $sevKey); ?>
              <td class="text-center">
                <div class="form-check form-switch d-inline-block">
                  <input type="checkbox" class="form-check-input"
                    name="pref_0_<?= esc($sevKey); ?>"
                    value="1" <?php if ($checked) {
                                echo 'checked';
                              } ?>>
                </div>
              </td>
            <?php } ?>
          </tr>
          <!-- Per-project rows -->
          <?php foreach ($projects as $p) { ?>
            <tr>
              <td style="padding-left: 20px;">
                <strong><?= esc($p['name']); ?></strong>
              </td>
              <?php foreach ($severities as $sevKey => $sevLabel) { ?>
                <?php $checked = notif_pref_checked($existing, (int) $p['id'], $sevKey); ?>
                <td class="text-center">
                  <div class="form-check form-switch d-inline-block">
                    <input type="checkbox" class="form-check-input"
                      name="pref_<?= (int) $p['id']; ?>_<?= esc($sevKey); ?>"
                      value="1" <?php if ($checked) {
                                  echo 'checked';
                                } ?>>
                  </div>
                </td>
              <?php } ?>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
  <button type="submit" class="btn btn-primary">
    <i class="bi bi-check-lg"></i> Save Preferences
  </button>
  <a href="<?= site_url('dashboard'); ?>" class="btn btn-light">Cancel</a>
</form>