<?php
// Build the role list from the roles table (passed in by the controller
// as $rolesAll). No hardcoded fallback — the page is super-admin-only
// and the controller is the single source of truth for which roles
// exist; an empty $roles renders an empty grid rather than re-inventing
// the legacy three built-ins.
$roles = [];
if (isset($rolesAll) && is_array($rolesAll)) {
	foreach ($rolesAll as $r) {
		$roles[(string) $r['role_key']] = (string) $r['label'];
	}
}

$modules = module_registry();

// Let's index the current permissions by [role][module_key]
$indexed = [];
foreach ($permissions as $p) {
	$r = $p['role'];
	$m = $p['module_key'];
	$indexed[$r][$m] = $p;
}
?>

<div class="page-head">
	<div>
		<h2>Module Control Panel</h2>
		<div class="subtitle">Manage page visibility, sidebar menus, and operational action permissions dynamically per role.</div>
	</div>
	<a href="<?= site_url('settings'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Settings</a>
</div>

<form method="post" action="<?= site_url('module_control_panel/save'); ?>" data-loading-form="1">
	<div class="card mb-4">
		<div class="card-header p-0">
			<ul class="nav nav-tabs border-bottom-0" id="roleTabs" role="tablist">
				<?php
				$firstTab = true;
				foreach ($roles as $roleKey => $roleLabel) {
				?>
					<li class="nav-item" role="presentation">
						<button class="nav-link py-3 px-4 <?php if ($firstTab === true) {
																			echo 'active';
																			$firstTab = false;
																		} ?>"
							id="tab-<?= esc($roleKey); ?>"
							data-bs-toggle="tab"
							data-bs-target="#pane-<?= esc($roleKey); ?>"
							type="button"
							role="tab"
							aria-controls="pane-<?= esc($roleKey); ?>"
							aria-selected="true">
							<strong><?= esc($roleLabel); ?></strong>
						</button>
					</li>
				<?php } ?>
			</ul>
		</div>

		<div class="tab-content" id="roleTabContent">
			<?php
			$firstPane = true;
			foreach ($roles as $roleKey => $roleLabel) {
			?>
				<div class="tab-pane fade show <?php if ($firstPane === true) {
																echo 'active';
																$firstPane = false;
															} ?>"
					id="pane-<?= esc($roleKey); ?>"
					role="tabpanel"
					aria-labelledby="tab-<?= esc($roleKey); ?>">

					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0">
							<thead class="table-light">
								<tr>
									<th style="width: 35%; padding-left: 20px;">Module / Feature</th>
									<th class="text-center" style="width: 15%;">View Access</th>
									<th class="text-center" style="width: 15%;">Add Action</th>
									<th class="text-center" style="width: 15%;">Edit Action</th>
									<th class="text-center" style="width: 15%;">Delete Action</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($modules as $modKey => $modInfo) { ?>
									<?php
									// Settings is hardcoded to super_admin only — skip it in the
									// permission grid since changing its checkboxes has no effect.
									if ($modKey === 'settings') {
										continue;
									}
									$viewVal = 0;
									$addVal  = 0;
									$editVal = 0;
									$delVal  = 0;

									if (isset($indexed[$roleKey][$modKey])) {
										$row     = $indexed[$roleKey][$modKey];
										$viewVal = (int) $row['can_view'];
										$addVal  = (int) $row['can_add'];
										$editVal = (int) $row['can_edit'];
										$delVal  = (int) $row['can_delete'];
									}
									?>
									<tr>
										<td style="padding-left: 20px;">
											<div class="fw-bold"><?= esc($modInfo['name']); ?></div>
											<small class="text-muted"><?= esc($modInfo['desc']); ?></small>
										</td>
										<td class="text-center">
											<div class="form-check form-switch d-inline-block">
												<input class="form-check-input" type="checkbox"
													name="perms[<?= esc($roleKey); ?>][<?= esc($modKey); ?>][view]"
													value="1"
													<?php if ($viewVal === 1) {
														echo 'checked';
													} ?>>
											</div>
										</td>
										<td class="text-center">
											<div class="form-check form-switch d-inline-block">
												<input class="form-check-input" type="checkbox"
													name="perms[<?= esc($roleKey); ?>][<?= esc($modKey); ?>][add]"
													value="1"
													<?php if ($addVal === 1) {
														echo 'checked';
													} ?>>
											</div>
										</td>
										<td class="text-center">
											<div class="form-check form-switch d-inline-block">
												<input class="form-check-input" type="checkbox"
													name="perms[<?= esc($roleKey); ?>][<?= esc($modKey); ?>][edit]"
													value="1"
													<?php if ($editVal === 1) {
														echo 'checked';
													} ?>>
											</div>
										</td>
										<td class="text-center">
											<div class="form-check form-switch d-inline-block">
												<input class="form-check-input" type="checkbox"
													name="perms[<?= esc($roleKey); ?>][<?= esc($modKey); ?>][delete]"
													value="1"
													<?php if ($delVal === 1) {
														echo 'checked';
													} ?>>
											</div>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php } ?>
		</div>
	</div>
	<div class="d-flex justify-content-end gap-2 mb-5">
		<a href="<?= site_url('settings'); ?>" class="btn btn-light btn-lg px-4">Cancel</a>
		<button type="submit" class="btn btn-primary btn-lg px-4">
			<i class="bi bi-check-lg"></i> Save Permissions
		</button>
	</div>
</form>

<!-- Manage Modules -->
<div class="card mb-5">
	<div class="card-header d-flex align-items-center justify-content-between">
		<div>
			<strong><i class="bi bi-puzzle"></i> Manage Modules</strong>
			<div class="text-muted small mt-1">
				<span class="badge bg-info text-dark me-1">BUILT-IN</span> modules are part of the system and cannot be removed.
				<span class="badge bg-secondary ms-1 me-1">CUSTOM</span> modules you added appear with a <strong>Remove</strong> button.
			</div>
		</div>
		<?php if (has_module_access('module_control_panel', 'add') === true) { ?>
			<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#addModulePanel" aria-expanded="false">
				<i class="bi bi-plus-lg"></i> Add Module
			</button>
		<?php } ?>
	</div>

	<?php if (has_module_access('module_control_panel', 'add') === true) { ?>
		<div class="collapse" id="addModulePanel">
			<div class="card-body border-bottom">
				<form method="post" action="<?= site_url('module_control_panel/module/add'); ?>" data-loading-form="1">
					<?= csrf_field(); ?>
					<div class="row g-2">
						<div class="col-md-2">
							<label class="form-label fw-semibold">Module Key <span class="text-danger">*</span></label>
							<input type="text" name="module_key" class="form-control" placeholder="e.g. my_reports" pattern="[a-z][a-z0-9_]{1,49}" required>
						</div>
						<div class="col-md-3">
							<label class="form-label fw-semibold">Display Name <span class="text-danger">*</span></label>
							<input type="text" name="name" class="form-control" placeholder="e.g. My Reports" required>
						</div>
						<div class="col-md-5">
							<label class="form-label fw-semibold">Description</label>
							<input type="text" name="description" class="form-control" placeholder="Short description shown in the permission grid">
						</div>
						<div class="col-md-2 d-flex align-items-end">
							<button type="submit" class="btn btn-primary w-100">Add</button>
						</div>
					</div>
					<div class="mt-2 small text-muted">
						<i class="bi bi-info-circle"></i>
						<strong>Module Key</strong> must match the first argument of <code>check_module_access()</code> in your controller.
						Example: if your controller calls <code>check_module_access('my_reports', 'view')</code> then the key is <code>my_reports</code>.
						Use lowercase letters, digits, and underscores only.
					</div>
				</form>
			</div>
		</div>
	<?php } ?>

	<div class="card-body p-0">
		<table class="table table-sm table-hover align-middle mb-0">
			<thead class="table-light">
				<tr>
					<th style="padding-left: 20px;">Module Key</th>
					<th>Display Name</th>
					<th>Description</th>
					<th class="text-center">Type</th>
					<th class="text-end" style="padding-right: 16px;">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($modules as $modKey => $modInfo) { ?>
					<tr>
						<td style="padding-left: 20px;"><code><?= esc($modKey); ?></code></td>
						<td><strong><?= esc($modInfo['name']); ?></strong></td>
						<td class="text-muted small"><?= esc($modInfo['desc']); ?></td>
						<td class="text-center">
							<?php if (!empty($modInfo['is_builtin'])) { ?>
								<span class="badge bg-info text-dark">BUILT-IN</span>
							<?php } else { ?>
								<span class="badge bg-secondary">CUSTOM</span>
							<?php } ?>
						</td>
						<td class="text-end" style="padding-right: 16px;">
							<?php if (empty($modInfo['is_builtin']) && has_module_access('module_control_panel', 'delete') === true) { ?>
								<a href="<?= site_url('module_control_panel/module/delete/' . esc($modKey)); ?>"
								   class="btn btn-sm btn-outline-danger"
								   data-method="post"
								   data-confirm-message="Remove module '<?= esc($modInfo['name']); ?>'? This will also delete all its role permissions.">
									<i class="bi bi-trash"></i> Remove
								</a>
							<?php } else { ?>
								<span class="text-muted small" title="Built-in modules cannot be removed">—</span>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>