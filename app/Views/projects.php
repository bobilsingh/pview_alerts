<?php
if (!function_exists('view_value')) {
  function view_value($source, $key, $default = '')
  {
    if (is_array($source) && array_key_exists($key, $source)) {
      return $source[$key];
    }

    return $default;
  }
}

if (!isset($view) || $view === '') {
  $view = 'list';
}
?>

<?php if ($view === 'list') { ?>

  <div class="page-head">
    <div>
      <h2>Projects</h2>
      <div class="subtitle">Each project groups related flows and alerts.</div>
    </div>
    <a href="<?= site_url('projects/add'); ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg"></i> Add Project
    </a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table id="projectsTable" class="table align-middle mb-0"
        data-table-url="<?= site_url('projects/data_table'); ?>">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Status</th>
            <th>Created By</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

<?php } else if ($view === 'form') { ?>
  <?php
  $isEdit = !empty($project);
  if ($isEdit) {
    $action = site_url('projects/update/' . $project['id']);
    $pageTitle = 'Edit Project';
  } else {
    $action = site_url('projects/save');
    $pageTitle = 'Add Project';
  }

  $projectName = '';
  $projectDescription = '';
  $currentStatus = 'active';

  if ($isEdit) {
    if (isset($project['name'])) {
      $projectName = $project['name'];
    }
    if (isset($project['description'])) {
      $projectDescription = $project['description'];
    }
    if (isset($project['status'])) {
      $currentStatus = $project['status'];
    }
  }
  ?>

  <div class="page-head">
    <div>
      <h2><?= esc($pageTitle); ?></h2>
    </div>
    <a href="<?= site_url('projects'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="<?= esc($action); ?>" data-loading-form="1" data-dirty-guard="1">

        <div class="mb-3">
          <label class="form-label">Project Name *</label>
          <input type="text" name="name" class="form-control" required maxlength="200"
            autofocus data-char-counter="1"
            value="<?= esc($projectName); ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4" maxlength="1000"
            data-char-counter="1"><?= esc($projectDescription); ?></textarea>
        </div>

        <?php if ($isEdit) { ?>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?php if ($currentStatus === 'active') {
                                        echo 'selected';
                                      } ?>>Active</option>
              <option value="inactive" <?php if ($currentStatus === 'inactive') {
                                          echo 'selected';
                                        } ?>>Inactive</option>
            </select>
          </div>
        <?php } ?>

        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg"></i>
          <?php if ($isEdit) { ?>
            Update
          <?php } else { ?>
            Create
          <?php } ?>
        </button>
        <a href="<?= site_url('projects'); ?>" class="btn btn-light">Cancel</a>
      </form>
    </div>
  </div>
<?php } ?>