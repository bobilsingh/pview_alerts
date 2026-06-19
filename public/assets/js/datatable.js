// ============================================================
// datatable.js
// DataTable initialization, filters, bulk actions, saved filters,
// activity log table, and analytics for the pView Alert System.
// Loaded after app.js — depends on globals defined there
// (APP_COLORS, escapeHtml, showSuccess, showError, etc.)
// ============================================================

// ============================================================
// 01. TABLE UTILITIES — shared helpers and base config
// ============================================================

// Returns the admin-configured default rows-per-page, falling back to 10.
function getTablePageLength() {
  var pageLength = getSettingInt("datatable_page_length");
  if (pageLength > 0) {
    return pageLength;
  }
  return 10;
}

// Applies global DataTables language overrides (empty state templates).
function setupDataTablesDefaults() {
  if (typeof $.fn === "undefined" || !$.fn.dataTable) {
    return;
  }

  $.extend(true, $.fn.dataTable.defaults, {
    language: {
      processing: '<span class="dt-dots" role="status" aria-label="Loading"><span class="dt-dot"></span><span class="dt-dot"></span><span class="dt-dot"></span></span>',
      emptyTable: '<div class="dt-empty"><i class="bi bi-inbox"></i><div class="dt-empty-title">Nothing here yet</div><div class="dt-empty-hint">Use the "Add" button above to create your first record.</div></div>',
      zeroRecords: '<div class="dt-empty"><i class="bi bi-search"></i><div class="dt-empty-title">No matching records</div><div class="dt-empty-hint">Try a different search term or clear the filters.</div></div>',
    },
  });
}

// Registry of DataTable jQuery objects — used by resizeAllTables().
var dtRegistry = [];

// Adds a table to the resize registry (deduplicates by DOM node).
function trackDataTable($table) {
  var tableNode;
  var i;

  if (!$table || !$table.length) {
    return;
  }

  tableNode = $table[0];
  for (i = 0; i < dtRegistry.length; i++) {
    if (dtRegistry[i][0] === tableNode) {
      return;
    }
  }
  dtRegistry.push($table);
}

// Re-adjusts column widths on all tracked DataTables.
function resizeAllTables() {
  var i;
  var $t;

  if (typeof $.fn.DataTable === "undefined") {
    return;
  }

  for (i = 0; i < dtRegistry.length; i++) {
    $t = dtRegistry[i];
    if (!$t || !$t.length) {
      continue;
    }
    if (!$.fn.DataTable.isDataTable($t[0])) {
      continue;
    }
    $t.DataTable().columns.adjust();
  }
}

// Re-aligns column widths on window resize and sidebar toggle.
var dtAdjustPending = null;

function initTableAutoResize() {
  $appWindow.off("resize.dataTableAdjust").on("resize.dataTableAdjust", function () {
    if (dtAdjustPending !== null) {
      return;
    }
    if (window.requestAnimationFrame) {
      dtAdjustPending = window.requestAnimationFrame(function () {
        dtAdjustPending = null;
        resizeAllTables();
      });
    } else {
      dtAdjustPending = window.setTimeout(function () {
        dtAdjustPending = null;
        resizeAllTables();
      }, 16);
    }
  });

  $appDocument.off("click.dataTableAdjust").on("click.dataTableAdjust", "#sidebarToggle, [data-sidebar-toggle]", function () {
    window.setTimeout(adjustAllDataTables, 300);
  });
}

// Common wrapper for all server-side DataTables.
function initServerTable(tableSelector, ajaxUrl, columns, extra) {
  var $table;
  var config;

  if (typeof $.fn.DataTable === "undefined") {
    return;
  }

  $table = $(tableSelector);
  if (!$table.length || $.fn.DataTable.isDataTable($table[0])) {
    return;
  }

  console.log("[DataTable] Initializing server-side table: " + tableSelector + " (URL: " + ajaxUrl + ")");

  config = {
    processing: true,
    serverSide: true,
    autoWidth: false,
    scrollX: true,
    pageLength: getTablePageLength(),
    lengthMenu: [10, 25, 50, 100],
    columns: columns,
    ajax: {
      url: ajaxUrl,
      type: "GET",
      dataSrc: "data",
      error: function (xhr) {
        var msg = "Failed to load data.";
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        console.error("[DataTable] Ajax error loading data for " + tableSelector + ": " + msg, xhr);
        showError(msg);
      },
    },
    language: {
      emptyTable: "No records found.",
      zeroRecords: "No matching records found.",
      info: "Showing _START_ to _END_ of _TOTAL_ entries",
      infoEmpty: "No entries",
      infoFiltered: "(filtered from _MAX_ total)",
      search: "",
      searchPlaceholder: "Search…",
      paginate: {
        first: "First",
        last: "Last",
        previous: "Previous",
        next: "Next",
      },
    },
    drawCallback: function () {
      console.log("[DataTable] Table redrawn: " + tableSelector);
      var api = this.api && this.api();
      if (api && typeof api.columns === "function") {
        api.columns.adjust();
      }
    },
  };

  if (extra && typeof extra === "object") {
    $.each(extra, function (k, v) {
      config[k] = v;
    });
  }

  $table.DataTable(config);
  trackDataTable($table);
}

// Reads the AJAX URL from the table's data-table-url attribute and inits a server-side table.
function initTableFromDataUrl(tableSelector, columns, extra) {
  var $table = $(tableSelector);
  var ajaxUrl;

  if (!$table.length) {
    return;
  }

  ajaxUrl = $table.attr("data-table-url");
  if (!ajaxUrl) {
    console.warn("[DataTable] Table exists but is missing data-table-url attribute: " + tableSelector);
    return;
  }

  console.log("[DataTable] Found data URL for " + tableSelector + ": " + ajaxUrl);
  initServerTable(tableSelector, ajaxUrl, columns, extra);
}

// DataTables render helper — truncates long strings with a tooltip for full value.
function truncateCell(maxLen) {
  return function (data, type, row) {
    if (!data) {
      return "";
    }
    var str = String(data);
    if (str.length <= maxLen) {
      return escapeHtml(str);
    }
    var truncated = str.substring(0, maxLen) + "...";
    return '<span title="' + escapeHtml(str) + '" style="cursor: help; border-bottom: 1px dotted var(--text-muted);">' + escapeHtml(truncated) + "</span>";
  };
}

// ============================================================
// 02. SIMPLE TABLES — client-side pagination for static HTML tables
// ============================================================

function initSimpleTables() {
  if (typeof $.fn.DataTable === "undefined") {
    return;
  }

  $("[data-simple-table='1']").each(function () {
    var table = this;
    var $table = $(table);
    var defaultLen = getTablePageLength();
    var pageLength;
    var orderColumn;
    var orderDirection;
    var lengthChange;
    var options;

    if ($.fn.DataTable.isDataTable(table)) {
      return;
    }

    options = {
      pageLength: defaultLen,
      lengthMenu: [10, 25, 50, 100],
      lengthChange: true,
      autoWidth: false,
      scrollX: false,
      language: {
        emptyTable: "No records to display yet.",
        zeroRecords: "No matching records found.",
        paginate: {
          first: "First",
          last: "Last",
          previous: "Previous",
          next: "Next",
        },
      },
    };

    pageLength = $table.attr("data-page-length");
    orderColumn = $table.attr("data-order-col");
    orderDirection = $table.attr("data-order-dir");
    lengthChange = $table.attr("data-length-change");

    if (pageLength !== undefined && pageLength !== "") {
      options.pageLength = parseInt(pageLength, 10);
    }

    if (!orderDirection) {
      orderDirection = "asc";
    }

    if (orderColumn !== undefined && orderColumn !== "") {
      options.order = [[parseInt(orderColumn, 10), orderDirection]];
    }

    if (lengthChange !== undefined) {
      options.lengthChange = toBoolean(lengthChange);
    }

    $table.DataTable(options);
    trackDataTable($table);
  });
}

// ============================================================
// 03. PROJECTS TABLE
// ============================================================

function initProjectsTable() {
  initTableFromDataUrl(
    "#projectsTable",
    [
      { data: "name", orderable: true },
      {
        data: "description",
        orderable: false,
        render: truncateCell(70),
      },
      { data: "status", orderable: true },
      { data: "created_by", orderable: false },
      { data: "created_at", orderable: true },
      { data: "actions", orderable: false },
    ],
    { order: [[4, "desc"]] },
  );
}

// ============================================================
// 04. USERS TABLE
// ============================================================

function initUsersTable() {
  initTableFromDataUrl(
    "#usersTable",
    [
      { data: "user_id", orderable: true },
      { data: "name", orderable: true },
      { data: "email", orderable: false },
      { data: "role", orderable: false },
      { data: "phone", orderable: false },
      { data: "is_active", orderable: true },
      { data: "created_at", orderable: true },
      { data: "actions", orderable: false },
    ],
    { order: [[6, "desc"]] },
  );
}

// ============================================================
// 05. ALERTS TABLE
// ============================================================

function initAlertsTable() {
  initTableFromDataUrl(
    "#alertsTable",
    [
      { data: "name", orderable: true },
      { data: "project", orderable: true },
      { data: "flow", orderable: true },
      { data: "severity", orderable: true },
      { data: "threshold", orderable: false },
      { data: "active", orderable: true },
      { data: "actions", orderable: false },
    ],
    { order: [[0, "asc"]] },
  );
}

// ============================================================
// 06. FLOWS TABLE
// ============================================================

function initFlowsTable() {
  initTableFromDataUrl(
    "#flowsTable",
    [
      { data: "name", orderable: true },
      { data: "project", orderable: true },
      { data: "state_count", orderable: false },
      { data: "status", orderable: true },
      { data: "created_by", orderable: false },
      { data: "created_at", orderable: true },
      { data: "actions", orderable: false },
    ],
    { order: [[5, "desc"]] },
  );
}

// Initialises all simple server-side list tables in one call.
function initListTables() {
  initUsersTable();
  initProjectsTable();
  initAlertsTable();
  initFlowsTable();
}

// ============================================================
// 07. TICKETS TABLE — server-side with AJAX filter support
// ============================================================

// ticketFilters is module-scoped so initTicketsAjaxFilters can mutate it
// and the DataTable's ajax.data() reads the new values on every reload.
var ticketFilters = {};

// Reads initial filter state from the table's data-* attributes (server-rendered).
function getTicketFilters($table) {
  return {
    mode: $table.attr("data-ticket-mode") || "",
    status: $table.attr("data-filter-status") || "",
    q: $table.attr("data-filter-q") || "",
    project_id: $table.attr("data-filter-project-id") || "",
    flow_id: $table.attr("data-filter-flow-id") || "",
    alert_type: $table.attr("data-filter-alert-type") || "",
    priority: $table.attr("data-filter-priority") || "",
  };
}

function initTicketsTable() {
  var $table;
  var ajaxUrl;
  var ticketPageLen;

  if (typeof $.fn.DataTable === "undefined") {
    return;
  }

  $table = $("#ticketsTable");
  if (!$table.length) {
    return;
  }

  if ($.fn.DataTable.isDataTable($table[0])) {
    return;
  }

  ajaxUrl = $table.attr("data-table-url");
  if (!ajaxUrl) {
    return;
  }

  ticketFilters = getTicketFilters($table);
  ticketPageLen = getTablePageLength();

  $table.DataTable({
    processing: true,
    serverSide: true,
    autoWidth: false,
    scrollX: true,
    pageLength: ticketPageLen,
    lengthMenu: [10, 25, 50, 100],
    order: [[9, "desc"]],
    ajax: {
      url: ajaxUrl,
      type: "GET",
      data: function (data) {
        $.each(ticketFilters, function (key, value) {
          data[key] = value;
        });
        return data;
      },
    },
    columns: [
      // DEMO: bulk column hidden — { data: "select", orderable: false, className: "ticket-bulk-cell text-center" },
      { data: "alarm_id_html", orderable: true },
      { data: "title_html", orderable: true },
      { data: "severity", orderable: true },
      { data: "priority", orderable: true },
      { data: "state", orderable: true },
      { data: "level", orderable: false },
      { data: "assignee", orderable: false },
      { data: "tat", orderable: false },
      { data: "created_at", orderable: true },
      { data: "actions", orderable: false, className: "text-center" },
    ],
    drawCallback: function () {
      initTatCountdowns();
      resizeAllTables();
    },
    initComplete: function () {
      var base = $table.attr("data-export-base") || "";
      var mode = $table.attr("data-export-mode") || "";
      if (!base) {
        return;
      }
      var $length = $table.closest(".dataTables_wrapper").find(".dataTables_length");
      if (!$length.length) {
        return;
      }
      $length.append('<a class="btn btn-sm btn-light ms-2" id="ticketsExportBtn"' + ' href="' + base + (mode ? "?mode=" + mode : "") + '"' + ' data-export-base="' + base + '"' + ' data-mode="' + mode + '"' + ' title="Export current view as CSV">' + '<i class="bi bi-download"></i> Export CSV</a>');
    },
  });

  trackDataTable($table);
}

// ============================================================
// 08. BULK ACTIONS — multi-select resolve / close tickets from list
// DEMO: entire section hidden — uncomment to restore
// ============================================================

/* DEMO HIDDEN
var bulkSelected = {};

function getSelectedIds() {
  var ids = [];
  var key;

  for (key in bulkSelected) {
    if (bulkSelected.hasOwnProperty(key)) {
      ids.push(key);
    }
  }

  return ids;
}

function countSelected() {
  var count = 0;
  var key;

  for (key in bulkSelected) {
    if (bulkSelected.hasOwnProperty(key)) {
      count++;
    }
  }

  return count;
}

function refreshBulkToolbar() {
  var $toolbar = $("#bulkToolbar");
  var count;

  if (!$toolbar.length) {
    return;
  }

  count = countSelected();
  $("#bulkSelectedCount").text(count);

  if (count === 0) {
    $toolbar.attr("hidden", "hidden");
  } else {
    $toolbar.removeAttr("hidden");
  }
}

function clearBulkSelection() {
  bulkSelected = {};
  $(".bulk-select").prop("checked", false);
  $("#bulkSelectAll").prop("checked", false);
  refreshBulkToolbar();
}

function postBulkAction($btn) {
  var url = $btn.attr("data-bulk-url");
  var action = $btn.attr("data-bulk-action");
  var ids = getSelectedIds();
  var verb;
  var $icon;
  var origIconClass;

  if (!url || !action || ids.length === 0) {
    return;
  }

  verb = "resolve";
  if (action === "close") {
    verb = "close";
  }

  confirmDialog(verb + " " + ids.length + " ticket(s)?", function () {
    $btn.prop("disabled", true);
    $icon = $btn.find("i").first();
    origIconClass = "";

    if ($icon.length) {
      origIconClass = $icon.attr("class") || "";
      $icon.attr("class", "spinner-border spinner-border-sm me-1");
    }

    $.ajax({
      url: url,
      type: "POST",
      data: { ids: ids, action: action },
      dataType: "json",
      success: function (response) {
        var $table;

        if (response && response.success) {
          showSuccess(response.message || "Done");
          clearBulkSelection();

          $table = $("#ticketsTable");
          if ($.fn.DataTable && $.fn.DataTable.isDataTable($table[0])) {
            $table.DataTable().ajax.reload(null, false);
          }

          if (typeof refreshBellBadge === "function") {
            refreshBellBadge();
          }
        } else {
          showError(extractErrorMessage(response, "Bulk action failed"));
        }
      },
      error: function () {
        showError("Network error during bulk action");
      },
      complete: function () {
        $btn.prop("disabled", false);
        if ($icon.length && origIconClass) {
          $icon.attr("class", origIconClass);
        }
      },
    });
  });
}
*/ // END DEMO HIDDEN

// Handles inline Reopen button clicks in the tickets list table.
function initListReopenButtons() {
  $appDocument.off("click.listReopen").on("click.listReopen", ".list-reopen-btn", function (e) {
    e.stopPropagation();
    var $btn = $(this);
    var url = $btn.data("url");
    if (!url) {
      return;
    }
    console.log("[DataTable Action] Reopen button clicked. Target URL: " + url);
    confirmDialog("Reopen this ticket?", function () {
      console.log("[DataTable Action] Reopen confirmed. Sending request to: " + url);
      $btn.prop("disabled", true);
      $.ajax({
        url: url,
        type: "POST",
        dataType: "json",
        success: function (res) {
          if (res && res.success) {
            console.log("[DataTable Action] Reopen success. Server response: " + (res.message || ""));
            showSuccess(res.message || "Ticket reopened");
            var dt = $("#ticketsTable").DataTable();
            if (dt) {
              console.log("[DataTable Action] Reloading tickets list DataTable...");
              dt.ajax.reload(null, false);
            }
          } else {
            var errorMsg = res && res.message ? res.message : "Failed to reopen";
            console.error("[DataTable Action] Reopen failed: " + errorMsg);
            showError(errorMsg);
            $btn.prop("disabled", false);
          }
        },
        error: function (xhr) {
          console.error("[DataTable Action] Network error during reopen request.", xhr);
          showError("Network error");
          $btn.prop("disabled", false);
        },
      });
    });
  });
}

/* DEMO HIDDEN
function initBulkActions() {
  $appDocument.off("change.bulkSelect").on("change.bulkSelect", ".bulk-select", function () {
    var id = $(this).attr("data-bulk-id");
    if (!id) {
      return;
    }

    if (this.checked) {
      bulkSelected[id] = true;
    } else {
      delete bulkSelected[id];
    }
    refreshBulkToolbar();
  });

  $appDocument.off("change.bulkSelectAll").on("change.bulkSelectAll", "#bulkSelectAll", function () {
    var checked = this.checked;

    $(".bulk-select").each(function () {
      var id = $(this).attr("data-bulk-id");
      this.checked = checked;
      if (!id) {
        return;
      }
      if (checked) {
        bulkSelected[id] = true;
      } else {
        delete bulkSelected[id];
      }
    });

    refreshBulkToolbar();
  });

  $appDocument.off("click.bulkAction").on("click.bulkAction", "[data-bulk-action]", function () {
    postBulkAction($(this));
  });

  $appDocument.off("click.bulkClear").on("click.bulkClear", "#bulkClearBtn", clearBulkSelection);

  // Re-apply checked state after every DataTables redraw so selections persist across pages.
  $appDocument.off("draw.dt.bulkRestore").on("draw.dt.bulkRestore", function () {
    $(".bulk-select").each(function () {
      var id = $(this).attr("data-bulk-id");
      if (id && bulkSelected[id]) {
        this.checked = true;
      }
    });
  });
}
*/ // END DEMO HIDDEN

// ============================================================
// 09. SAVED FILTERS — named filter bookmarks for the tickets table
// ============================================================

function initSavedFilters() {
  $appDocument.off("click.savedFilterAdd").on("click.savedFilterAdd", "#savedFilterAddBtn", function () {
    var $btn = $(this);
    var url = $btn.attr("data-save-url");
    var qs = $btn.attr("data-current-qs") || "";
    var name;

    if (!url) {
      return;
    }

    name = window.prompt("Name this filter:", "");
    if (name === null) {
      return;
    }

    name = $.trim(name || "");
    if (name === "") {
      return;
    }

    $.ajax({
      url: url,
      type: "POST",
      data: { name: name, query_params: qs },
      dataType: "json",
      success: function (response) {
        if (response && response.success) {
          showSuccess(response.message || "Saved");
          window.location.reload();
        } else {
          showError(extractErrorMessage(response, "Failed to save filter"));
        }
      },
      error: function () {
        showError("Network error");
      },
    });
  });

  $appDocument.off("click.savedFilterDelete").on("click.savedFilterDelete", ".saved-filter-delete", function (event) {
    var $btn = $(this);
    var url = $btn.attr("data-saved-url");

    event.preventDefault();
    event.stopPropagation();

    if (!url) {
      return;
    }

    confirmDialog("Remove this saved filter?", function () {
      $.ajax({
        url: url,
        type: "POST",
        dataType: "json",
        success: function (response) {
          if (response && response.success) {
            showSuccess(response.message || "Removed");
            var $dropdown = $btn.closest(".saved-filters-dropdown");
            $btn.closest(".saved-filter-row").remove();

            var $menu = $dropdown.find(".dropdown-menu");
            var remaining = $menu.find(".saved-filter-row").length;
            var $badge = $dropdown.find("> button .badge");
            if (remaining > 0) {
              $badge.text(remaining);
            } else {
              $badge.remove();
              if ($menu.find(".no-saved-filters-msg").length === 0) {
                $menu.prepend('<span class="dropdown-item-text text-muted small no-saved-filters-msg">No saved filters yet.</span>');
              }
            }
          } else {
            showError(extractErrorMessage(response, "Failed to remove filter"));
          }
        },
        error: function () {
          showError("Network error");
        },
      });
    });
  });
}

// ============================================================
// 10. TICKET TABLE FILTERS — status pills, form, URL pushState
// ============================================================

// Parses a query string from a URL into a plain key→value object.
function parseQueryString(url) {
  var qs;
  var hashCut;
  var qIdx;
  var out = {};
  var pairs;
  var i;
  var p;
  var eq;
  var k;
  var v;

  if (typeof url !== "string") {
    return out;
  }

  hashCut = url.split("#")[0];
  qIdx = hashCut.indexOf("?");
  qs = "";

  if (qIdx >= 0) {
    qs = hashCut.substring(qIdx + 1);
  }

  if (qs === "") {
    return out;
  }

  pairs = qs.split("&");
  for (i = 0; i < pairs.length; i++) {
    p = pairs[i];
    if (!p) {
      continue;
    }

    eq = p.indexOf("=");
    k = "";
    v = "";

    if (eq < 0) {
      k = decodeURIComponent(p);
    } else {
      k = decodeURIComponent(p.substring(0, eq));
      v = decodeURIComponent(p.substring(eq + 1).replace(/\+/g, " "));
    }

    if (k !== "") {
      out[k] = v;
    }
  }

  return out;
}

// Synchronises all filter UI elements (pills, form inputs, badges) with the given params.
function syncTicketsFilterUI(params) {
  var status;
  var $form;
  var $hiddenStatus;
  var count;
  var $saveBtn;
  var rebuilt;
  var keysInOrder;
  var k;
  var key;
  var v;

  status = params.status || "";
  $(".filter-pills .filter-pill").each(function () {
    var $a = $(this);
    var hrefParams = parseQueryString($a.attr("href") || "");
    var pStatus = hrefParams.status || "";

    if (pStatus === status) {
      $a.addClass("active");
    } else {
      $a.removeClass("active");
    }
  });

  $form = $("#ticketsFilterForm");
  if ($form.length) {
    $form.find("[name='q']").val(params.q || "");
    $form.find("[name='project_id']").val(params.project_id || "");
    $form.find("[name='flow_id']").val(params.flow_id || "");
    $form.find("[name='alert_type']").val(params.alert_type || "");
    $form.find("[name='priority']").val(params.priority || "");

    var $drw = $form.find("[data-date-range]").first();
    if ($drw.length) {
      if (params.f_from || params.f_to) {
        $drw.find("[data-date-range-from]").val(params.f_from || "");
        $drw.find("[data-date-range-to]").val(params.f_to || "");
        $drw.find(".drw-preset").removeClass("active");
      } else {
        setDateRangePreset($drw, "today");
      }
    }

    $hiddenStatus = $form.find("input[type=hidden][name='status']");
    if (status === "") {
      $hiddenStatus.remove();
    } else {
      if ($hiddenStatus.length) {
        $hiddenStatus.val(status);
      } else {
        $form.find(".card-body > .row").prepend('<input type="hidden" name="status" value="' + escapeHtml(status) + '">');
      }
    }

    $form.find("select").each(function () {
      if ($(this).hasClass("select2-hidden-accessible")) {
        $(this).trigger("change.select2");
      }
    });
  }

  count = 0;
  if ((params.q || "") !== "") {
    count++;
  }
  if (parseInt(params.project_id || "0", 10) > 0) {
    count++;
  }
  if (parseInt(params.flow_id || "0", 10) > 0) {
    count++;
  }
  if ((params.alert_type || "") !== "") {
    count++;
  }
  if ((params.priority || "") !== "") {
    count++;
  }
  if ((params.f_from || "") !== "" || (params.f_to || "") !== "") {
    count++;
  }

  var $badge = $("#ticketsFilterBadge");
  if ($badge.length) {
    $badge.text(count);
    if (count === 0) {
      $badge.attr("hidden", "hidden");
    } else {
      $badge.removeAttr("hidden");
    }
  }

  $saveBtn = $("#savedFilterAddBtn");
  if ($saveBtn.length) {
    rebuilt = [];
    keysInOrder = ["status", "q", "project_id", "flow_id", "alert_type", "priority", "f_from", "f_to"];

    for (k = 0; k < keysInOrder.length; k++) {
      key = keysInOrder[k];
      v = params[key] || "";
      if (v !== "" && v !== "0") {
        rebuilt.push(encodeURIComponent(key) + "=" + encodeURIComponent(v));
      }
    }

    $saveBtn.attr("data-current-qs", rebuilt.join("&"));

    var hasFilter = count > 0 || (params.status || "") !== "";
    var $saveHint = $("#savedFilterAddHint");
    if (hasFilter) {
      $saveBtn.removeAttr("hidden");
      if ($saveHint.length) {
        $saveHint.attr("hidden", "hidden");
      }
    } else {
      $saveBtn.attr("hidden", "hidden");
      if ($saveHint.length) {
        $saveHint.removeAttr("hidden");
      }
    }
  }
}

// Applies a URL's query params to the tickets DataTable and pushes browser history.
function applyTicketUrl(url, doPushState) {
  var $table = $("#ticketsTable");
  var params;

  if (!$table.length) {
    return;
  }
  if (typeof $.fn.DataTable === "undefined") {
    return;
  }
  if (!$.fn.DataTable.isDataTable($table[0])) {
    return;
  }

  params = parseQueryString(url);

  ticketFilters.status = params.status || "";
  ticketFilters.q = params.q || "";
  ticketFilters.project_id = params.project_id || "";
  ticketFilters.flow_id = params.flow_id || "";
  ticketFilters.alert_type = params.alert_type || "";
  ticketFilters.priority = params.priority || "";
  ticketFilters.f_from = params.f_from || "";
  ticketFilters.f_to = params.f_to || "";

  syncTicketsFilterUI(params);
  updateTicketsExportHref(params);
  $table.DataTable().ajax.reload(null, false);

  if (doPushState && window.history && window.history.pushState) {
    window.history.pushState({ ticketsUrl: url }, "", url);
  }
}

// Rebuilds the Export CSV href to reflect the current AJAX filter state.
function updateTicketsExportHref(params) {
  var $btn = $("#ticketsExportBtn");
  if (!$btn.length) {
    return;
  }

  var base = $btn.attr("data-export-base") || "";
  var mode = $btn.attr("data-mode") || "";
  if (!base) {
    return;
  }

  var parts = [];
  if (mode) {
    parts.push("mode=" + encodeURIComponent(mode));
  }
  if (params.status) {
    parts.push("status=" + encodeURIComponent(params.status));
  }
  if (params.q) {
    parts.push("q=" + encodeURIComponent(params.q));
  }
  if (params.project_id) {
    parts.push("project_id=" + encodeURIComponent(params.project_id));
  }
  if (params.flow_id) {
    parts.push("flow_id=" + encodeURIComponent(params.flow_id));
  }
  if (params.alert_type) {
    parts.push("alert_type=" + encodeURIComponent(params.alert_type));
  }
  if (params.priority) {
    parts.push("priority=" + encodeURIComponent(params.priority));
  }
  if (params.f_from) {
    parts.push("f_from=" + encodeURIComponent(params.f_from));
  }
  if (params.f_to) {
    parts.push("f_to=" + encodeURIComponent(params.f_to));
  }

  $btn.attr("href", base + (parts.length ? "?" + parts.join("&") : ""));
}

// Wires status pills, filter form submit, saved filter links, and browser back button.
function initTicketsAjaxFilters() {
  var clickSelector;

  if (!$("#ticketsTable").length) {
    return;
  }

  clickSelector = ".filter-pills .filter-pill, .saved-filter-link, .tickets-filter-reset";

  $appDocument.off("click.ticketsAjax", clickSelector).on("click.ticketsAjax", clickSelector, function (event) {
    var href = $(this).attr("href");
    if (!href) {
      return;
    }
    event.preventDefault();
    applyTicketUrl(href, true);
  });

  $appDocument.off("submit.ticketsAjax").on("submit.ticketsAjax", "#ticketsFilterForm", function (event) {
    var $form = $(this);
    var qs = $form.serialize();
    var target = $form.attr("action") || window.location.pathname;

    event.preventDefault();

    if (qs) {
      target = target + "?" + qs;
    }

    applyTicketUrl(target, true);
  });

  $appWindow.off("popstate.ticketsAjax").on("popstate.ticketsAjax", function () {
    applyTicketUrl(window.location.href, false);
  });
}

// ============================================================
// 11. ACTIVITY LOGS TABLE — server-side with filters and CSV export
// ============================================================

function initAuditLogTable() {
  var $table = $("#activityLogsTable");
  if (!$table.length || typeof $.fn.DataTable === "undefined") {
    return;
  }
  if ($.fn.DataTable.isDataTable($table[0])) {
    return;
  }

  var ajaxUrl = $table.attr("data-table-url");
  if (!ajaxUrl) {
    return;
  }

  var dt = $table.DataTable({
    processing: true,
    serverSide: true,
    autoWidth: false,
    scrollX: true,
    pageLength: getTablePageLength(),
    lengthMenu: [10, 25, 50, 100],
    order: [[0, "desc"]],
    columns: [
      { data: "created_at", orderable: true },
      { data: "user", orderable: true },
      { data: "module", orderable: true },
      { data: "action", orderable: true },
      { data: "entity", orderable: false },
      { data: "summary", orderable: false },
      { data: "login", orderable: false },
      { data: "logout", orderable: false },
      { data: "source", orderable: false },
    ],
    ajax: {
      url: ajaxUrl,
      type: "GET",
      dataSrc: "data",
      data: function (d) {
        d.f_user = $("#filterUser").val() || "";
        d.f_module = $("#filterModule").val() || "";
        d.f_action = $("#filterAction").val() || "";
        d.f_role = $("#filterRole").val() || "";
        d.f_status = $("#filterStatus").val() || "";
        d.f_project = $("#filterProject").val() || "";
        d.f_from = $("#filterFrom").val() || "";
        d.f_to = $("#filterTo").val() || "";
      },
      error: function (xhr) {
        var msg = "Failed to load activity log.";
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        showError(msg);
      },
    },
    language: {
      emptyTable: "No activity recorded yet.",
      zeroRecords: "No matching events.",
      info: "Showing _START_ to _END_ of _TOTAL_ events",
      infoEmpty: "No events",
      infoFiltered: "(filtered from _MAX_ total)",
      search: "",
      searchPlaceholder: "Search…",
    },
    drawCallback: function () {
      var api = this.api && this.api();
      if (api && typeof api.columns === "function") {
        api.columns.adjust();
      }
    },
    initComplete: function () {
      var exportUrl = $table.attr("data-export-url") || "";
      if (!exportUrl) {
        return;
      }
      var $length = $table.closest(".dataTables_wrapper").find(".dataTables_length");
      if (!$length.length) {
        return;
      }
      $length.append('<button type="button" class="btn btn-sm btn-light ms-2" id="activityExportBtn"' + ' data-export-url="' + exportUrl + '">' + '<i class="bi bi-download"></i> Export CSV</button>');
    },
  });
  trackDataTable($table);

  // Counts and updates the filter badge in the activity log header.
  function updateActivityFilterBadge() {
    var count = 0;
    if ($("#filterUser").val()) {
      count++;
    }
    if ($("#filterModule").val()) {
      count++;
    }
    if ($("#filterAction").val()) {
      count++;
    }
    if ($("#filterRole").val()) {
      count++;
    }
    if ($("#filterStatus").val()) {
      count++;
    }
    if ($("#filterProject").val()) {
      count++;
    }
    var from = $("#filterFrom").val() || "";
    var to = $("#filterTo").val() || "";
    var today = (function () {
      var d = new Date();
      return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2);
    })();
    if (from !== "" && from !== today) {
      count++;
    } else if (to !== "" && to !== today) {
      count++;
    }

    var $badge = $("#activityFilterBadge");
    if ($badge.length) {
      $badge.text(count);
      if (count === 0) {
        $badge.attr("hidden", "hidden");
      } else {
        $badge.removeAttr("hidden");
      }
    }
  }

  $("#activityFilterForm").on("change input", "input, select, [data-date-range-from], [data-date-range-to]", updateActivityFilterBadge);
  updateActivityFilterBadge();

  $("#activityApplyBtn").on("click", function () {
    dt.ajax.reload();
  });

  $("#activityResetBtn").on("click", function () {
    $("#filterUser").val("");
    $("#filterModule").val("");
    $("#filterAction").val("");
    $("#filterRole").val("");
    $("#filterStatus").val("");
    $("#filterProject").val("");
    var $drw = $("#activityFilterForm [data-date-range]").first();
    if ($drw.length) {
      setDateRangePreset($drw, "today");
    } else {
      $("#filterFrom").val($("#filterFrom").attr("data-default") || "");
      $("#filterTo").val($("#filterTo").attr("data-default") || "");
    }
    updateActivityFilterBadge();
    dt.ajax.reload();
  });

  // CSV export carries the same filters the table is currently showing.
  $("#activityExportBtn").on("click", function () {
    var url = $(this).attr("data-export-url");
    if (!url) {
      return;
    }
    var params = $.param({
      f_user: $("#filterUser").val() || "",
      f_module: $("#filterModule").val() || "",
      f_action: $("#filterAction").val() || "",
      f_role: $("#filterRole").val() || "",
      f_status: $("#filterStatus").val() || "",
      f_project: $("#filterProject").val() || "",
      f_from: $("#filterFrom").val() || "",
      f_to: $("#filterTo").val() || "",
      q: $(".dataTables_filter input").val() || "",
    });
    window.location.href = url + (url.indexOf("?") === -1 ? "?" : "&") + params;
  });
}

// ============================================================
// 12. CRON RUNS TABLE — server-side with date + status filters
// ============================================================

function initCronRunsTable() {
  var $table = $("#cronRunsTable");
  if (!$table.length || typeof $.fn.DataTable === "undefined") {
    return;
  }
  if ($.fn.DataTable.isDataTable($table[0])) {
    return;
  }

  var ajaxUrl = $table.attr("data-table-url");
  if (!ajaxUrl) {
    return;
  }

  var dt = $table.DataTable({
    processing: true,
    serverSide: true,
    autoWidth: false,
    pageLength: getTablePageLength(),
    lengthMenu: [10, 25, 50, 100],
    order: [[1, "desc"]],
    columns: [
      { data: "script", orderable: true },
      { data: "started", orderable: true },
      { data: "duration", orderable: true },
      { data: "tickets", orderable: true, className: "text-center" },
      { data: "sent", orderable: true, className: "text-center" },
      { data: "failed", orderable: true, className: "text-center" },
      { data: "status", orderable: true, className: "text-center" },
      { data: "summary", orderable: false },
    ],
    ajax: {
      url: ajaxUrl,
      type: "GET",
      dataSrc: "data",
      error: function (xhr) {
        var msg = "Failed to load cron run history.";
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        showError(msg);
      },
    },
    language: {
      emptyTable: "No cron runs recorded yet.",
      zeroRecords: "No runs match the selected filters.",
      info: "Showing _START_ to _END_ of _TOTAL_ runs",
      infoEmpty: "No runs",
      infoFiltered: "(filtered from _MAX_ total)",
      search: "",
      searchPlaceholder: "Search…",
    },
    drawCallback: function () {
      var api = this.api && this.api();
      if (api && typeof api.columns === "function") {
        api.columns.adjust();
      }
    },
  });
}

// ============================================================
// 13. ANALYTICS — fetch, render charts/tables, drilldown modal
// ============================================================

var analyticsModuleChart = null;
var analyticsRefreshTimer = null;
var analyticsInFlight = false;

// Returns true when the Analytics tab panel is the visible one.
function analyticsTabActive() {
  var $pane = $("#tab-analytics");
  return $pane.length && $pane.hasClass("active");
}

// Fetches analytics data for the selected date range and renders all widgets.
function loadAnalytics() {
  var $table = $("#activityLogsTable");
  var analyticsUrl = $table.attr("data-analytics-url");
  if (!analyticsUrl || analyticsInFlight) {
    return;
  }

  var fFrom = $("#analyticsFrom").val() || "";
  var fTo = $("#analyticsTo").val() || "";

  analyticsInFlight = true;
  $.ajax({
    url: analyticsUrl,
    type: "GET",
    data: { f_from: fFrom, f_to: fTo },
    dataType: "json",
    success: function (res) {
      if (!res || !res.success || !res.data) {
        return;
      }
      renderAnalytics(res.data);
      var now = new Date();
      var hh = ("0" + now.getHours()).slice(-2);
      var mm = ("0" + now.getMinutes()).slice(-2);
      var ss = ("0" + now.getSeconds()).slice(-2);
      $("#analyticsLastRefresh").text("Last refresh: " + hh + ":" + mm + ":" + ss);
      $("#analyticsLiveBadge").removeAttr("hidden");
    },
    error: function () {
      showError("Failed to load analytics.");
    },
    complete: function () {
      analyticsInFlight = false;
    },
  });
}

// Renders KPI cards, top-users table, session table, module bar chart, and failed-events table.
function renderAnalytics(data) {
  var auth = data.auth || {};

  $("#kpiLoginsToday").text(auth.logins_today || 0);
  $("#kpiLoginsPeriod").text(auth.logins_period || 0);
  $("#kpiFailedToday").text(auth.failed_today || 0);
  $("#kpiFailedPeriod").text(auth.failed_period || 0);

  var topUsers = data.top_users || [];
  var topHtml = "";
  var i, u;
  if (topUsers.length === 0) {
    topHtml = '<tr><td colspan="4" class="text-center text-muted py-3">No activity in this period.</td></tr>';
  } else {
    for (i = 0; i < topUsers.length; i++) {
      u = topUsers[i];
      topHtml += "<tr>" + "<td><strong>" + escapeHtml(u.user_name || u.user_id) + "</strong>" + '<br><small class="text-muted">@' + escapeHtml(u.user_id) + "</small></td>" + '<td><span class="badge bg-secondary">' + escapeHtml(u.user_role || "-") + "</span></td>" + '<td class="text-end fw-bold">' + parseInt(u.event_count, 10) + "</td>" + '<td class="small text-muted">' + escapeHtml((u.last_seen || "").substring(0, 16)) + "</td>" + "</tr>";
    }
  }
  $("#analyticsTopUsersBody").html(topHtml);

  var sessions = data.session_avg || [];
  var sessHtml = "";
  if (sessions.length === 0) {
    sessHtml = '<tr><td colspan="3" class="text-center text-muted py-3">No sessions in period.</td></tr>';
  } else {
    for (i = 0; i < sessions.length; i++) {
      var s = sessions[i];
      sessHtml += "<tr>" + '<td class="small">' + escapeHtml(s.user_name || s.user_id) + "</td>" + '<td class="text-end">' + s.avg_minutes + "</td>" + '<td class="text-end text-muted">' + s.session_count + "</td>" + "</tr>";
    }
  }
  $("#analyticsSessionBody").html(sessHtml);

  var modules = data.modules || [];
  var chartLabels = [];
  var chartValues = [];
  var chartColors = ["#0ea5e9", "#10b981", "#8b5cf6", "#f59e0b", "#ef4444", "#64748b", "#06b6d4", "#84cc16", "#f97316", "#ec4899"];
  for (i = 0; i < modules.length; i++) {
    chartLabels.push(modules[i].module);
    chartValues.push(parseInt(modules[i].cnt, 10));
  }
  var ctx = document.getElementById("analyticsModuleChart");
  if (ctx && typeof Chart !== "undefined") {
    if (analyticsModuleChart) {
      analyticsModuleChart.destroy();
    }
    var isDark = $appHtml.attr("data-theme") === "dark";
    var tickColor = isDark ? APP_COLORS.textLight : APP_COLORS.textDark;
    var gridColor = getTrendGridColor(isDark);
    analyticsModuleChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: chartLabels,
        datasets: [
          {
            label: "Events",
            data: chartValues,
            backgroundColor: chartColors.slice(0, chartLabels.length),
            borderRadius: 4,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { precision: 0, color: tickColor },
            grid: { color: gridColor },
          },
          y: {
            grid: { display: false },
            ticks: { color: tickColor },
          },
        },
      },
    });
  }

  var failed = data.failed || [];
  var failedHtml = "";
  $("#analyticsFailedBadge").text(failed.length);
  if (failed.length === 0) {
    failedHtml = '<tr><td colspan="6" class="text-center text-success py-3">' + '<i class="bi bi-check-circle"></i> No failed events in this period.</td></tr>';
  } else {
    for (i = 0; i < failed.length; i++) {
      var f = failed[i];
      var src = f.source || "Unknown";
      var srcIcon = src === "Mobile" ? "bi-phone" : src === "API" ? "bi-braces" : "bi-globe";
      var srcColor = src === "Mobile" ? "bg-info text-dark" : src === "API" ? "bg-warning text-dark" : "bg-primary";
      failedHtml += "<tr>" + '<td class="small text-muted">' + escapeHtml((f.created_at || "").substring(0, 16)) + "</td>" + '<td class="small">' + escapeHtml(f.user_name || f.user_id || "-") + "</td>" + '<td><span class="badge bg-secondary">' + escapeHtml(f.module) + "</span></td>" + '<td><span class="badge bg-danger">' + escapeHtml(f.action) + "</span></td>" + '<td class="small">' + escapeHtml(f.summary || "-") + "</td>" + '<td><span class="badge ' + srcColor + '"><i class="bi ' + srcIcon + ' me-1"></i>' + escapeHtml(src) + "</span></td>" + "</tr>";
    }
  }
  $("#analyticsFailedBody").html(failedHtml);
}

// Wires the Analytics tab: initial load, apply button, 30s auto-refresh, visibility.
function initAnalyticsTab() {
  if (!$("#tab-analytics").length) {
    return;
  }

  if (analyticsRefreshTimer) {
    clearInterval(analyticsRefreshTimer);
    analyticsRefreshTimer = null;
  }

  loadAnalytics();

  $("#analyticsApplyBtn").off("click.analytics").on("click.analytics", function () {
    loadAnalytics();
  });

  var analyticsRefreshMs = parseInt($('meta[name="app-setting-analytics_refresh_seconds"]').attr("content") || "30", 10) * 1000;

  if (analyticsRefreshMs > 0) {
    analyticsRefreshTimer = setInterval(
      function () {
        if (!document.hidden && analyticsTabActive()) {
          loadAnalytics();
        }
      },
      Math.max(10000, analyticsRefreshMs),
    );
  }

  $appDocument.off("visibilitychange.analytics").on("visibilitychange.analytics", function () {
    if (!document.hidden && analyticsTabActive()) {
      loadAnalytics();
    }
  });

  $appDocument.off("shown.bs.tab.analytics").on("shown.bs.tab.analytics", "#tab-analytics-link", function () {
    loadAnalytics();
  });
}
