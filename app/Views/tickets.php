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

if (!function_exists('ticket_activity_icon')) {
  function ticket_activity_icon($type)
  {
    $map = [
      'created' => 'bi-plus-circle text-primary',
      'commented' => 'bi-chat-left text-secondary',
      'state_changed' => 'bi-arrow-right-circle text-info',
      'level_escalated' => 'bi-graph-up-arrow text-danger',
      'assigned' => 'bi-person-check text-success',
      'attachment' => 'bi-paperclip text-warning',
      'resolved' => 'bi-check2-circle text-success',
      'closed' => 'bi-x-circle text-dark',
      'api_update' => 'bi-cloud-arrow-down text-info',
      'title_changed' => 'bi-pencil text-secondary',
      'description_changed' => 'bi-pencil text-secondary',
      'priority_changed' => 'bi-flag text-secondary',
    ];

    $cls = 'bi-circle';
    if (isset($map[$type])) {
      $cls = $map[$type];
    }

    return '<i class="bi ' . $cls . '"></i>';
  }
}
?>

<?php if ($view === 'list') { ?>
  <?php
  $cur = '';
  $search = '';
  $projectSel = 0;
  $flowSel = 0;
  $typeSel = '';
  $prioSel = '';

  if (isset($filters) && is_array($filters)) {
    if (isset($filters['status'])) {
      $cur = (string) $filters['status'];
    }
    if (isset($filters['search'])) {
      $search = (string) $filters['search'];
    }
    if (isset($filters['project_id'])) {
      $projectSel = (int) $filters['project_id'];
    }
    if (isset($filters['flow_id'])) {
      $flowSel = (int) $filters['flow_id'];
    }
    if (isset($filters['alert_type'])) {
      $typeSel = (string) $filters['alert_type'];
    }
    if (isset($filters['priority'])) {
      $prioSel = (string) $filters['priority'];
    }
  }

  $isAll = false;
  if (isset($mode) && $mode === 'all') {
    $isAll = true;
  }

  $pageH = 'My Tickets';
  $subtitle = "Tickets assigned to you, or where you're a level user.";
  $baseUrl = site_url('tickets');
  $ticketMode = 'my';

  if ($isAll) {
    $pageH = 'All Tickets';
    $subtitle = 'Admin view of every ticket.';
    $baseUrl = site_url('tickets/all');
    $ticketMode = 'all';
  }

  $statuses = [
    '' => 'All',
    'active' => 'Active',
    'open' => 'Open',
    'in_progress' => 'In Progress',
    'escalated' => 'Escalated',
    'resolved' => 'Resolved',
    'closed' => 'Closed',
  ];

  $severityOptions = [
    'info' => 'Info',
    'major' => 'Major',
    'critical' => 'Critical',
  ];

  $priorityOptions = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'urgent' => 'Urgent',
  ];
  ?>

  <div class="page-head">
    <div>
      <h2><?= esc($pageH); ?></h2>
      <div class="subtitle"><?= esc($subtitle); ?></div>
    </div>
    <a href="<?= site_url('tickets/create'); ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg"></i> Raise Ticket
    </a>
  </div>

  <?php
  // Count filters actively narrowing the list (status pills are
  // their own UI; here we count the extra refinements applied via
  // the dropdown form below).
  $activeFilterCount = 0;
  if ($search !== '') {
    $activeFilterCount++;
  }
  if ($projectSel > 0) {
    $activeFilterCount++;
  }
  if ($flowSel > 0) {
    $activeFilterCount++;
  }
  if ($typeSel !== '') {
    $activeFilterCount++;
  }
  if ($prioSel !== '') {
    $activeFilterCount++;
  }
  ?>

  <?php
  $savedFiltersList = [];
  if (isset($savedFilters) && is_array($savedFilters)) {
    $savedFiltersList = $savedFilters;
  }
  $hasFilter = ($activeFilterCount > 0 || $cur !== '');

  // Export URL is built server-side; JS re-builds it dynamically when
  // filters change via AJAX (see #ticketsExportBtn handler in app.js).
  $exportParams = [
    'mode'       => $ticketMode,
    'status'     => $cur,
    'q'          => $search,
    'project_id' => $projectSel,
    'flow_id'    => $flowSel,
    'alert_type' => $typeSel,
    'priority'   => $prioSel,
    'f_from'     => !empty($filters['f_from']) ? $filters['f_from'] : '',
    'f_to'       => !empty($filters['f_to'])   ? $filters['f_to']   : '',
  ];
  $exportParams = array_filter($exportParams);
  $exportUrl    = site_url('tickets/export') . '?' . http_build_query($exportParams);

  $drInitFrom = !empty($filters['f_from']) ? $filters['f_from'] : date('Y-m-d');
  $drInitTo   = !empty($filters['f_to'])   ? $filters['f_to']   : date('Y-m-d');
  ?>

  <!-- Status pills row -->
  <div class="d-flex gap-3 mb-2 flex-wrap align-items-center">
    <div class="filter-pills">
      <?php foreach ($statuses as $key => $label) { ?>
        <?php
        $params = [
          'status' => $key,
          'q' => $search,
          'project_id' => or_default($projectSel, null),
          'flow_id' => or_default($flowSel, null),
          'alert_type' => or_default($typeSel, null),
          'priority' => or_default($prioSel, null),
        ];
        $params = array_filter($params);
        $url = $baseUrl;
        if (!empty($params)) {
          $url .= '?' . http_build_query($params);
        }
        ?>
        <a class="filter-pill <?= $cur === $key ? 'active' : '' ?>" href="<?= esc($url); ?>"><?= esc($label); ?></a>
      <?php } ?>
    </div>

  </div>

  <!-- Filter card -->
  <form class="card mb-3 tickets-filter-form" id="ticketsFilterForm" method="get" action="<?= esc($baseUrl); ?>">

    <!-- Common header: title + badge + saved filters + export + apply + reset -->
    <div class="card-header filter-bar-header">
      <span class="filter-bar-title">
        <i class="bi bi-funnel"></i> Filters
        <span class="badge rounded-pill bg-primary ms-1 filter-bar-badge"
              id="ticketsFilterBadge"
              <?= $activeFilterCount === 0 ? 'hidden' : '' ?>>
          <?= $activeFilterCount ?>
        </span>
      </span>

      <div class="filter-bar-actions">

        <!-- Saved filters dropdown -->
        <div class="dropdown saved-filters-dropdown">
          <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bookmarks"></i> Saved
            <?php if (!empty($savedFiltersList)) { ?>
              <span class="badge bg-secondary ms-1"><?= count($savedFiltersList); ?></span>
            <?php } ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end">
            <?php if (empty($savedFiltersList)) { ?>
              <span class="dropdown-item-text text-muted small no-saved-filters-msg">No saved filters yet.</span>
            <?php } else { ?>
              <?php foreach ($savedFiltersList as $sf) { ?>
                <?php
                $sfUrl = $baseUrl;
                if (!empty($sf['query_params'])) {
                  $sfUrl .= '?' . $sf['query_params'];
                }
                ?>
                <div class="saved-filter-row">
                  <a class="dropdown-item saved-filter-link" href="<?= esc($sfUrl); ?>">
                    <i class="bi bi-funnel"></i> <?= esc($sf['name']); ?>
                  </a>
                  <button type="button" class="btn btn-sm btn-link text-danger saved-filter-delete"
                    title="Remove saved filter"
                    aria-label="Remove saved filter"
                    data-saved-id="<?= (int) $sf['id']; ?>"
                    data-saved-url="<?= site_url('tickets/saved/delete/' . (int) $sf['id']); ?>">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                  </button>
                </div>
              <?php } ?>
            <?php } ?>
            <div class="dropdown-divider"></div>
            <button type="button" class="dropdown-item" id="savedFilterAddBtn"
              data-save-url="<?= site_url('tickets/saved/save'); ?>"
              data-current-qs="<?= esc(http_build_query(array_filter([
                                  'status'     => $cur,
                                  'q'          => $search,
                                  'project_id' => $projectSel,
                                  'flow_id'    => $flowSel,
                                  'alert_type' => $typeSel,
                                  'priority'   => $prioSel,
                                ])), 'attr'); ?>"
              <?= !$hasFilter ? 'hidden' : '' ?>>
              <i class="bi bi-plus-circle text-primary"></i> Save current filter…
            </button>
            <span class="dropdown-item-text text-muted small" id="savedFilterAddHint"
              <?= $hasFilter ? 'hidden' : '' ?>>Apply a filter to save it.</span>
          </div>
        </div>

        <!-- Date range — left of Apply -->
        <span class="filter-bar-sep"></span>
        <div class="filter-bar-date">
          <?= view('filters/filter_bar', [
            'only_widget' => true,
            'drFromName'  => 'f_from',
            'drToName'    => 'f_to',
            'drFrom'      => $drInitFrom,
            'drTo'        => $drInitTo,
            'drDefault'   => 'today',
            'drInline'    => true,
          ]); ?>
        </div>

        <!-- Apply -->
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="bi bi-check-lg"></i> Apply
        </button>

        <!-- Reset -->
        <a href="<?= esc($baseUrl); ?>" class="btn btn-sm btn-light tickets-filter-reset">
          <i class="bi bi-x-lg"></i> Reset
        </a>

      </div>
    </div><!-- /card-header -->

    <!-- Filter controls -->
    <div class="filter-bar-body">
      <?php if ($cur !== '') { ?>
        <input type="hidden" name="status" value="<?= esc($cur); ?>">
      <?php } ?>

      <div class="filter-bar-controls">

        <div class="filter-item filter-item--search">
          <label class="filter-label">Search</label>
          <input type="text" name="q" class="form-control form-control-sm"
            placeholder="Alarm ID or title…" value="<?= esc($search); ?>">
        </div>

        <?php if ($isAll && !empty($projects)) { ?>
          <div class="filter-item filter-item--wide">
            <label class="filter-label">Project</label>
            <select name="project_id" class="form-select form-select-sm">
              <option value="">All projects</option>
              <?php foreach ($projects as $p) { ?>
                <option value="<?= (int) $p['id']; ?>" <?= $projectSel === (int) $p['id'] ? 'selected' : '' ?>>
                  <?= esc($p['name']); ?>
                </option>
              <?php } ?>
            </select>
          </div>
          <div class="filter-item">
            <label class="filter-label">Flow</label>
            <select name="flow_id" class="form-select form-select-sm">
              <option value="">All flows</option>
              <?php foreach ($flows as $f) { ?>
                <option value="<?= (int) $f['id']; ?>" <?= $flowSel === (int) $f['id'] ? 'selected' : '' ?>>
                  <?= esc($f['name']); ?>
                </option>
              <?php } ?>
            </select>
          </div>
        <?php } ?>

        <div class="filter-item">
          <label class="filter-label">Severity</label>
          <select name="alert_type" class="form-select form-select-sm">
            <option value="">All</option>
            <?php foreach ($severityOptions as $key => $label) { ?>
              <option value="<?= $key; ?>" <?= $typeSel === $key ? 'selected' : '' ?>><?= $label; ?></option>
            <?php } ?>
          </select>
        </div>

        <div class="filter-item">
          <label class="filter-label">Priority</label>
          <select name="priority" class="form-select form-select-sm">
            <option value="">All</option>
            <?php foreach ($priorityOptions as $key => $label) { ?>
              <option value="<?= $key; ?>" <?= $prioSel === $key ? 'selected' : '' ?>><?= $label; ?></option>
            <?php } ?>
          </select>
        </div>

      </div><!-- /filter-bar-controls -->
    </div><!-- /filter-bar-body -->

  </form>

  <?php /* DEMO: bulk toolbar hidden
  <div id="bulkToolbar" class="bulk-toolbar" hidden>
    <span class="bulk-summary">
      <span id="bulkSelectedCount">0</span> selected
    </span>
    <div class="bulk-actions">
      <button type="button" class="btn btn-sm btn-success" data-bulk-action="resolve" data-bulk-url="<?= site_url('tickets/bulk'); ?>">
        <i class="bi bi-check-circle"></i> Resolve selected
      </button>
      <button type="button" class="btn btn-sm btn-dark" data-bulk-action="close" data-bulk-url="<?= site_url('tickets/bulk'); ?>">
        <i class="bi bi-x-circle"></i> Close selected
      </button>
      <button type="button" class="btn btn-sm btn-light" id="bulkClearBtn">Clear</button>
    </div>
  </div>
  */ ?>

  <div class="card">
    <div class="card-body p-0">
      <table id="ticketsTable" class="table align-middle mb-0" style="width:100%;"
        data-table-url="<?= site_url('tickets/data_table'); ?>"
        data-ticket-mode="<?= esc($ticketMode, 'attr'); ?>"
        data-filter-status="<?= esc($cur, 'attr'); ?>"
        data-filter-q="<?= esc($search, 'attr'); ?>"
        data-filter-project-id="<?= (int) $projectSel; ?>"
        data-filter-flow-id="<?= (int) $flowSel; ?>"
        data-filter-alert-type="<?= esc($typeSel, 'attr'); ?>"
        data-filter-priority="<?= esc($prioSel, 'attr'); ?>"
        data-export-base="<?= esc(site_url('tickets/export'), 'attr'); ?>"
        data-export-mode="<?= esc($ticketMode, 'attr'); ?>">
        <thead>
          <tr>
            <?php /* DEMO: bulk select-all column hidden
            <th class="ticket-bulk-cell text-center">
              <input type="checkbox" class="form-check-input" id="bulkSelectAll" aria-label="Select all on this page">
            </th>
            */ ?>
            <th>Alarm ID</th>
            <th>Title</th>
            <th>Severity</th>
            <th>Priority</th>
            <th>State</th>
            <th>Level</th>
            <th>Assignee</th>
            <th>TAT</th>
            <th>Created</th>
            <th class="no-sort">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

<?php } else if ($view === 'create') { ?>

  <div class="page-head">
    <div>
      <h2>Raise Ticket</h2>
      <div class="subtitle">Manually create a new alert in a project's flow.</div>
    </div>
    <a href="<?= site_url('tickets'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="<?= site_url('tickets/save'); ?>" data-loading-form="1" data-dirty-guard="1" enctype="multipart/form-data">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Project *</label>
            <select name="project_id" id="projectSelect" class="form-select"
              data-load-target="#flowSelect"
              data-load-url="<?= site_url('tickets/flows_by_project'); ?>"
              data-item-type="flow"
              data-empty-text="Select project first"
              data-default-text="Select flow"
              data-loading-text="Loading..."
              data-no-data-text="No flows found"
              data-error-text="Failed to load flows"
              autofocus
              required>
              <option value="">Select project</option>
              <?php foreach ($projects as $p) { ?>
                <option value="<?= (int) $p['id']; ?>"><?= esc($p['name']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Flow *</label>
            <select name="flow_id" id="flowSelect" class="form-select" required disabled
              data-load-target="#assigneeSelect"
              data-load-url="<?= site_url('tickets/assignable_users'); ?>"
              data-item-type="user"
              data-empty-text="Select flow first"
              data-default-text="Unassigned"
              data-loading-text="Loading..."
              data-no-data-text="No L1 users configured"
              data-error-text="Failed to load users">
              <option value="">Select project first</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Title *</label>
            <input type="text" name="title" class="form-control" required maxlength="300"
              data-char-counter="1"
              placeholder="Short summary of the alert">
          </div>

          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" maxlength="10000"
              data-char-counter="1"
              placeholder="What happened?"></textarea>
          </div>

          <div class="col-md-6">
            <label class="form-label">Severity</label>
            <select name="alert_type" class="form-select">
              <option value="info">Info</option>
              <option value="major">Major</option>
              <option value="critical">Critical</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Assign To</label>
            <select name="assignee_user_id" id="assigneeSelect" class="form-select" disabled>
              <option value="">Select flow first</option>
            </select>
            <small class="text-muted">Optional — leave blank to let an L1 operator pick it up.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Attachment <small class="text-muted">(optional)</small></label>
            <input type="file" name="attachment" class="form-control"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xlsx,.xls,.csv,.txt">
            <small class="text-muted">Max <?= app_setting_int('upload_max_mb', 10); ?> MB &nbsp;·&nbsp; PDF, Word, Excel, image, CSV, TXT.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Start Date</label>
            <input type="date" name="actual_start_date" class="form-control" value="<?= date('Y-m-d'); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">End Date</label>
            <input type="date" name="actual_end_date" class="form-control" value="<?= date('Y-m-d'); ?>">
          </div>
        </div>
        <div class="mt-3 d-flex justify-content-end gap-2">
          <a href="<?= site_url('tickets'); ?>" class="btn btn-light">Cancel</a>
          <button class="btn btn-primary"><i class="bi bi-bell-fill"></i> Raise Ticket</button>
        </div>
      </form>
    </div>
  </div>

<?php } else if ($view === 'detail') { ?>
  <?php
  $alarmId = view_value($ticket, 'alarm_id');
  $ticketTitle = view_value($ticket, 'title');
  $ticketDesc = (string) view_value($ticket, 'description', '');
  $ticketStatus = view_value($ticket, 'status');
  $ticketType = view_value($ticket, 'alert_type');
  $ticketPrio = view_value($ticket, 'priority');
  $currentLevel = (int) view_value($ticket, 'current_level', 0);
  $currentStateId = (int) view_value($ticket, 'current_state_id', 0);
  $isFinal = !empty($ticket['state_is_final']);
  $isClosed = in_array($ticketStatus, ['closed', 'resolved'], true);

  $descToShow = $ticketDesc;
  if ($descToShow === '') {
    $descToShow = 'Click to add description...';
  }

  $projectName = null;
  $flowName = null;
  if (isset($ticket['project_name'])) {
    $projectName = $ticket['project_name'];
  }
  if (isset($ticket['flow_name'])) {
    $flowName = $ticket['flow_name'];
  }

  if (!isset($tatExpiresAt)) {
    $tatExpiresAt = '';
  }
  if (!isset($tatMinutes)) {
    $tatMinutes = 0;
  }

  $priorityOptions = ['low', 'medium', 'high', 'urgent'];

  // Lock the inline editors when the lifecycle has ended — the server
  // also rejects edits but disabling the UI prevents the misleading
  // "edit succeeded then was rolled back" feeling.
  $isTerminal = false;
  if ($ticketStatus === 'resolved' || $ticketStatus === 'closed') {
    $isTerminal = true;
  }
  $attachCount = isset($attachCount) ? (int) $attachCount : 0;
  $attachMax   = isset($attachMax)   ? (int) $attachMax   : 5;
  $attachFull  = $attachCount >= $attachMax;
  ?>

  <div class="page-head">
    <div>
      <h2 class="d-flex align-items-center gap-2">
        <span>Ticket</span>
        <code class="alarm-id-big" data-copy="<?= esc($alarmId); ?>"
          style="cursor:pointer;" title="Click to copy"><?= esc($alarmId); ?></code>
      </h2>
      <div class="subtitle">
        <?= esc(or_default($projectName, '-')); ?> &nbsp;-&nbsp;
        <?= esc(or_default($flowName, '-')); ?> &nbsp;-&nbsp;
        raised <?= esc($ticket['created_at']); ?>
      </div>
    </div>
    <div class="d-flex gap-2" id="ticketHeaderBadges">
      <?= alert_badge($ticketType); ?>
      <?= status_badge($ticketStatus); ?>
    </div>
  </div>

  <div class="row g-3">

    <div class="col-lg-8">

      <div class="card mb-3">
        <div class="card-body">
          <?php
          // Terminal tickets render the same fields but without the
          // editable-inline class, so the JS edit-on-click handler skips them.
          $titleCls = 'ticket-title editable-inline mb-2';
          $descCls  = 'ticket-desc editable-inline mb-4';
          if ($isTerminal) {
            $titleCls = 'ticket-title mb-2';
            $descCls  = 'ticket-desc mb-4';
          }
          ?>
          <h3 class="<?= $titleCls; ?>"
            data-field="title"
            data-url="<?= site_url('tickets/action/' . $alarmId); ?>"><?= esc($ticketTitle); ?></h3>
          <div class="<?= $descCls; ?>"
            data-field="description"
            data-url="<?= site_url('tickets/action/' . $alarmId); ?>"><?= esc($descToShow); ?></div>

          <?php helper('app'); ?>
          <?= flow_widget_html(
            flow_vis_ticket_data($allStates, (int) $currentStateId, isset($allTransitions) ? $allTransitions : []),
            ['subtitle' => 'Ticket progress through this flow', 'variant' => 'ticket', 'legend' => false]
          ); ?>

          <div class="flow-graph-legend">
            <span class="lg passed">Completed</span>
            <span class="lg current">Live</span>
            <span class="lg pending">Pending</span>
          </div>

          <div class="row g-3 mt-3">
            <div class="col-md-6">
              <div class="tat-block">
                <div>
                  <div class="tat-label">Escalation Level</div>
                  <div class="level-indicator mt-2">
                    <?php for ($lvl = 1; $lvl <= 4; $lvl++) { ?>
                      <?php
                      $levelClass = '';
                      if ($lvl === $currentLevel) {
                        $levelClass = 'is-active';
                      } else if ($lvl < $currentLevel) {
                        $levelClass = 'is-passed';
                      }
                      ?>
                      <span class="lvl <?= $levelClass; ?>">L<?= $lvl; ?></span>
                    <?php } ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="tat-block">
                <div>
                  <?php
                  // Single rule for the label:
                  //   - resolved / closed: lifecycle over, no countdown
                  //   - final state:        same, no countdown
                  //   - otherwise:          show "TAT remaining @ Lx - Nm"
                  $tatLabel = 'TAT remaining @ L' . $currentLevel . ' - ' . $tatMinutes . 'm';
                  if ($ticketStatus === 'resolved') {
                    $tatLabel = 'TAT stopped - ticket resolved';
                  } else if ($ticketStatus === 'closed') {
                    $tatLabel = 'TAT stopped - ticket closed';
                  } else if (!empty($state['is_final'])) {
                    $tatLabel = 'TAT stopped - final state';
                  }
                  ?>
                  <div class="tat-label"><?= esc($tatLabel); ?></div>
                  <div class="tat-big mt-2"><span class="tat-countdown" data-tat-expires="<?= esc($tatExpiresAt); ?>" data-tat-total-ms="<?= (int) (tat_total_minutes($ticket, $state) * 60000); ?>"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3" id="ticketActionCard">
        <div class="card-header">
          <strong><i class="bi bi-lightning-charge text-primary"></i> Take Action</strong>
        </div>
        <div class="card-body">
          <?php if ($isTerminal) { ?>
            <div class="alert alert-secondary py-2 mb-3">
              <i class="bi bi-lock"></i>
              This ticket is <strong><?= esc(strtoupper($ticketStatus)); ?></strong> — comments, assignment, state moves and attachments are read-only.
            </div>
          <?php } ?>
          <ul class="nav nav-tabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-comment"><i class="bi bi-chat-left-text"></i> Comment</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-assign"><i class="bi bi-person-check"></i> Assign</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-state"><i class="bi bi-arrow-right-circle"></i> Move State</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-attach"><i class="bi bi-paperclip"></i> Attach</a></li>
          </ul>
          <div class="tab-content pt-3">

            <div class="tab-pane fade show active" id="tab-comment">
              <form id="commentForm" data-url="<?= site_url('tickets/action/' . $alarmId); ?>">
                <input type="hidden" name="type" value="comment">
                <textarea name="comment" rows="3" class="form-control"
                  maxlength="5000"
                  data-mentions="1"
                  data-mention-source="<?= site_url('users/active_json'); ?>"
                  placeholder="Add a comment for the activity log... (type @ to mention a teammate)" required
                  <?php if ($isTerminal) {
                    echo 'disabled';
                  } ?>></textarea>
                <div class="d-flex justify-content-end mt-2">
                  <button class="btn btn-primary" <?php if ($isTerminal) {
                                                    echo 'disabled';
                                                  } ?>><i class="bi bi-send"></i> Add comment</button>
                </div>
              </form>
            </div>

            <div class="tab-pane fade" id="tab-assign">
              <form id="assignForm" data-url="<?= site_url('tickets/assign/' . $alarmId); ?>">
                <div class="d-flex align-items-end gap-2 flex-wrap">
                  <div class="flex-grow-1" style="min-width: 250px;">
                    <label class="form-label">Assignee <small class="text-muted">(all operators in current state)</small></label>
                    <select name="user_id" class="form-select" required <?php if ($isTerminal) {
                                                                          echo 'disabled';
                                                                        } ?>>
                      <option value="">Select user</option>
                      <?php foreach ($assignableUsers as $u) { ?>
                        <option value="<?= esc((string) $u['user_id']); ?>"><?= esc($u['name']); ?> - <?= esc($u['email']); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div>
                    <button class="btn btn-primary" <?php if ($isTerminal) {
                                                      echo 'disabled';
                                                    } ?>><i class="bi bi-person-check"></i> Assign</button>
                  </div>
                </div>
              </form>
            </div>

            <div class="tab-pane fade" id="tab-state">
              <?php
              $hasFwd = !empty($nextStates) && !$isTerminal && !$isFinal;
              $hasBwd = !empty($previousStates) && !$isTerminal;
              ?>

              <!-- Move Forward: next state in sort_order sequence -->
              <div class="mb-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-arrow-right-circle text-success"></i>
                  <span class="fw-semibold small">Move Forward</span>
                </div>
                <?php if ($hasFwd) { ?>
                  <?php if (count($nextStates) === 1) { ?>
                    <button id="moveStateBtn"
                      data-url="<?= site_url('tickets/move_state/' . $alarmId); ?>"
                      data-transition-type="forward"
                      data-target-id="<?= (int) $nextStates[0]['id']; ?>"
                      class="btn btn-success">
                      <i class="bi bi-arrow-right-circle"></i>
                      Move to <?= esc($nextStates[0]['name']); ?>
                    </button>
                  <?php } else { ?>
                    <form class="move-state-typed-form d-flex align-items-end gap-2 flex-wrap"
                      data-url="<?= site_url('tickets/move_state/' . $alarmId); ?>">
                      <input type="hidden" name="transition_type" value="forward">
                      <div class="flex-grow-1" style="min-width:220px;">
                        <select name="target_state_id" class="form-select" required>
                          <option value="">Select next state…</option>
                          <?php foreach ($nextStates as $ns) { ?>
                            <option value="<?= (int) $ns['id']; ?>"><?= esc($ns['name']); ?></option>
                          <?php } ?>
                        </select>
                      </div>
                      <div>
                        <button type="submit" class="btn btn-success">
                          <i class="bi bi-arrow-right-circle"></i> Move Forward
                        </button>
                      </div>
                    </form>
                  <?php } ?>
                <?php } else { ?>
                  <span class="text-muted small">
                    <?php if ($isTerminal) { echo 'Ticket is ' . esc($ticketStatus) . '.'; }
                    elseif ($isFinal)      { echo 'This is the closing state — resolve or close below.'; }
                    else                   { echo 'No further states in this workflow.'; }
                    ?>
                  </span>
                <?php } ?>
              </div>

              <?php if ($hasBwd) { ?>
              <!-- Send Back: any earlier state, decided at runtime by the assignee -->
                <div class="mb-3">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-arrow-left-circle text-danger"></i>
                    <span class="fw-semibold small">Send Back</span>
                    <small class="text-muted">Select an earlier state and provide a reason.</small>
                  </div>
                  <form class="move-state-typed-form d-flex align-items-end gap-2 flex-wrap"
                    data-url="<?= site_url('tickets/move_state/' . $alarmId); ?>">
                    <input type="hidden" name="transition_type" value="backward">
                    <div style="min-width:200px;">
                      <select name="target_state_id" class="form-select" required>
                        <option value="">Select state to send back to…</option>
                        <?php foreach ($previousStates as $ps) { ?>
                          <option value="<?= (int) $ps['id']; ?>"><?= esc($ps['name']); ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="flex-grow-1" style="min-width:200px;">
                      <input type="text" name="reason" class="form-control"
                        placeholder="Reason for sending back (required)" required>
                    </div>
                    <div>
                      <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-arrow-left-circle"></i> Send Back
                      </button>
                    </div>
                  </form>
                </div>
              <?php } ?>

              <hr class="my-3">

              <!-- Resolve / Close / Reopen -->
              <div class="d-flex gap-2 flex-wrap">
                <button id="resolveBtn"
                  data-url="<?= site_url('tickets/resolve/' . $alarmId); ?>"
                  class="btn btn-outline-success"
                  <?php if ($isTerminal) { echo 'disabled'; } ?>>
                  <i class="bi bi-check2-circle"></i> Resolve
                </button>
                <button id="closeBtn"
                  data-url="<?= site_url('tickets/close/' . $alarmId); ?>"
                  class="btn btn-outline-secondary"
                  <?php if ($ticketStatus === 'closed') { echo 'disabled'; } ?>>
                  <i class="bi bi-x-circle"></i> Close
                </button>
                <!--
                <?php if ($ticketStatus === 'resolved') { ?>
                  <button id="reopenBtn"
                    data-url="<?= site_url('tickets/reopen/' . $alarmId); ?>"
                    class="btn btn-outline-warning">
                    <i class="bi bi-arrow-counterclockwise"></i> Reopen
                  </button>
                <?php } ?>
                -->
              </div>
            </div>

            <div class="tab-pane fade" id="tab-attach">
              <?php if ($attachFull): ?>
                <div class="alert alert-warning mb-0">
                  <i class="bi bi-paperclip"></i>
                  Attachment limit reached (<?= $attachMax ?> of <?= $attachMax ?> files used).
                </div>
              <?php else: ?>
                <form id="attachForm" data-url="<?= site_url('tickets/attach/' . $alarmId); ?>"
                  enctype="multipart/form-data">
                  <label class="form-label">
                    Attach evidence
                    <small class="text-muted">(<?= $attachCount ?>/<?= $attachMax ?>)</small>
                  </label>
                  <input type="file" name="file" class="form-control"
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xlsx,.xls,.csv,.txt" required
                    <?php if ($isTerminal) { echo 'disabled'; } ?>>
                  <small class="text-muted">Max 10 MB.</small>
                  <div class="d-flex justify-content-end mt-2">
                    <button class="btn btn-primary" <?php if ($isTerminal) { echo 'disabled'; } ?>>
                      <i class="bi bi-cloud-upload"></i> Upload
                    </button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <strong><i class="bi bi-clock-history text-primary"></i> Activity Timeline</strong>
        </div>
        <div class="card-body">
          <ul class="activity-feed" id="timelineList">
            <?php foreach ($timeline as $a) { ?>
              <?php
              $performer = 'System';
              if (!empty($a['performer_name'])) {
                $performer = $a['performer_name'];
              } else if (!empty($a['performed_by_system'])) {
                $performer = $a['performed_by_system'];
              }
              $cmt = (string) view_value($a, 'comment', '');
              $actionType = view_value($a, 'action_type', '');
              $fromStateName = null;
              $toStateName = null;
              $performedBySystem = null;
              if (isset($a['from_state_name'])) {
                $fromStateName = $a['from_state_name'];
              }
              if (isset($a['to_state_name'])) {
                $toStateName = $a['to_state_name'];
              }
              if (isset($a['performed_by_system'])) {
                $performedBySystem = $a['performed_by_system'];
              }
              ?>
              <li class="activity-item">
                <div class="activity-icon"><?= ticket_activity_icon($actionType); ?></div>
                <div class="activity-body">
                  <div class="activity-meta">
                    <strong><?= esc($performer); ?></strong>
                    <span class="text-muted"><?= esc($a['created_at']); ?></span>
                  </div>
                  <div class="activity-text">
                    <?php
                    switch ($actionType) {
                      case 'created':
                        echo 'Created the ticket';
                        if ($cmt !== '') {
                          echo '. <em>' . esc($cmt) . '</em>';
                        }
                        break;
                      case 'commented':
                        // highlight_mentions() escapes the body first, then
                        // wraps any @user_id tokens in .mention-chip so
                        // mentions stand out from regular comment text.
                        echo highlight_mentions($cmt);
                        break;
                      case 'state_changed':
                        $transDir = isset($a['transition_type']) && $a['transition_type'] === 'backward' ? 'backward' : 'forward';
                        if ($transDir === 'backward') {
                          echo '<span class="badge text-bg-danger me-1"><i class="bi bi-arrow-left-circle"></i> Send back</span>';
                        }
                        echo 'Moved state from <strong>' . esc(or_default($fromStateName, '?')) . '</strong>';
                        echo ' to <strong>' . esc(or_default($toStateName, '?')) . '</strong>';
                        // Show user-supplied reason (stored after ": " in the comment).
                        $stCommentStr = trim((string) ($a['comment'] ?? ''));
                        $stReasonPos  = strpos($stCommentStr, ': ');
                        $stReason     = $stReasonPos !== false ? trim(substr($stCommentStr, $stReasonPos + 2)) : '';
                        if ($stReason !== '') {
                          echo '<div class="small text-muted mt-1"><i class="bi bi-chat-quote me-1"></i>' . esc($stReason) . '</div>';
                        }
                        break;
                      case 'level_escalated':
                        echo 'Escalated to <strong>L' . (int) $a['to_level'] . '</strong>';
                        if ($cmt !== '') {
                          echo '. <em>' . esc($cmt) . '</em>';
                        }
                        break;
                      case 'assigned':
                        if ($cmt !== '') {
                          echo esc($cmt);
                        } else {
                          echo 'Assigned the ticket';
                        }
                        break;
                      case 'attachment':
                        $label = $cmt;
                        if ($label === '') {
                          $label = 'a file';
                        }
                        echo 'Attached <a href="' . esc(site_url('tickets/download/' . $alarmId . '/' . (int) $a['id'])) . '">' . esc($label) . '</a>';
                        break;
                      case 'resolved':
                        echo 'Resolved the ticket';
                        break;
                      case 'closed':
                        echo 'Closed the ticket';
                        break;
                      case 'reopened':
                        echo 'Reopened the ticket';
                        break;
                      case 'api_update':
                        echo 'API update from <code>' . esc(or_default($performedBySystem, '?')) . '</code>';
                        if ($cmt !== '') {
                          echo ' - ' . esc($cmt);
                        }
                        break;
                      case 'title_changed':
                      case 'description_changed':
                      case 'priority_changed':
                        echo esc($cmt);
                        break;
                      default:
                        echo esc($cmt);
                        break;
                    }
                    ?>
                  </div>
                </div>
              </li>
            <?php } ?>
            <?php if (empty($timeline)) { ?>
              <li class="text-muted small">No activity yet.</li>
            <?php } ?>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3" id="ticketDetailsCard">
        <div class="card-header">
          <strong><i class="bi bi-info-circle text-primary"></i> Details</strong>
        </div>
        <div class="card-body p-0">
          <table class="meta-table">
            <tr>
              <td>Project</td>
              <td><?= esc(or_default($projectName, '-')); ?></td>
            </tr>
            <tr>
              <td>Flow</td>
              <td><?= esc(or_default($flowName, '-')); ?></td>
            </tr>
            <tr>
              <td>State</td>
              <td><?= esc(or_default(view_value($ticket, 'state_name', null), '-')); ?></td>
            </tr>
            <tr>
              <td>Status</td>
              <td><?= status_badge($ticketStatus); ?></td>
            </tr>
            <tr>
              <td>Severity</td>
              <td><?= alert_badge($ticketType); ?></td>
            </tr>
            <tr>
              <td>Priority</td>
              <td>
                <select id="priorityInline" class="form-select form-select-sm"
                  data-url="<?= site_url('tickets/action/' . $alarmId); ?>"
                  <?php if ($isTerminal) {
                    echo 'disabled';
                  } ?>>
                  <?php foreach ($priorityOptions as $priority) { ?>
                    <option value="<?= $priority; ?>" <?php if ($ticketPrio === $priority) {
                                                        echo 'selected';
                                                      } ?>>
                      <?= ucfirst($priority); ?>
                    </option>
                  <?php } ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>Assignee</td>
              <td id="assigneeValue"><?= esc(or_default(view_value($ticket, 'assignee_name', null), '-')); ?></td>
            </tr>
            <tr>
              <td>Source</td>
              <td><?= esc(strtoupper(or_default(view_value($ticket, 'source', null), 'UI'))); ?></td>
            </tr>
            <tr>
              <td>Raised By</td>
              <td><?= esc(or_default(view_value($ticket, 'raised_by_name', null), '-')); ?></td>
            </tr>
            <tr>
              <td>Actual Start</td>
              <td class="small">
                <?php
                $asd = view_value($ticket, 'actual_start_date', '');
                if ($asd !== '' && $asd !== null) {
                    echo esc(date('d M Y', strtotime($asd)));
                } else {
                    echo '<span class="text-muted">—</span>';
                }
                ?>
              </td>
            </tr>
            <tr>
              <td>Actual End</td>
              <td class="small">
                <?php
                $aed = view_value($ticket, 'actual_end_date', '');
                if ($aed !== '' && $aed !== null) {
                    echo esc(date('d M Y', strtotime($aed)));
                } else {
                    echo '<span class="text-muted">—</span>';
                }
                ?>
              </td>
            </tr>
            <tr>
              <td>Created</td>
              <td class="text-muted small"><?= esc($ticket['created_at']); ?></td>
            </tr>
            <tr>
              <td>Updated</td>
              <td class="text-muted small"><?= esc($ticket['updated_at']); ?></td>
            </tr>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <strong><i class="bi bi-bell text-primary"></i> Recent Notifications</strong>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush small">
            <?php foreach ($notifications as $n) { ?>
              <?php
              $cls = 'bg-secondary';
              if ($n['status'] === 'sent') {
                $cls = 'bg-success';
              } else if ($n['status'] === 'failed') {
                $cls = 'bg-danger';
              }

              $recipient = '';
              if (!empty($n['recipient_email'])) {
                $recipient = $n['recipient_email'];
              } else if (!empty($n['recipient_phone'])) {
                $recipient = $n['recipient_phone'];
              }
              ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                  <span><i class="bi bi-envelope text-primary"></i> <?= esc($recipient); ?></span>
                  <span class="badge <?= $cls; ?>"><?= esc(strtoupper($n['status'])); ?></span>
                </div>
                <div class="text-muted small mt-1"><?= esc($n['created_at']); ?></div>
              </li>
            <?php } ?>
            <?php if (empty($notifications)) { ?>
              <li class="list-group-item text-muted small">No notifications yet.</li>
            <?php } ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
<?php } ?>