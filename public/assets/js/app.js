// app.js — pView Alert System

// -------------------------------------------------------
// Global colour palette used by charts
// -------------------------------------------------------
var APP_COLORS = {
  primary: "#0EA5E9",
  primaryDark: "#0284C7",
  success: "#10B981",
  major: "#F59E0B",
  critical: "#EF4444",
  textLight: "#94A3B8",
  textDark: "#475569",
  border: "#E2E8F0",
};

// -------------------------------------------------------
// Global state
// -------------------------------------------------------
var tatTimer = null;
var $appDocument = $(document);
var $appWindow = $(window);
var $appHtml = $("html");
var $appBody = $("body");
var APP_MOBILE_BREAKPOINT = "(max-width: 992px)";

// -------------------------------------------------------
// localStorage helpers
// localStorage may throw in private-mode / cross-origin iframes.
// -------------------------------------------------------
function readPref(key) {
  try {
    return localStorage.getItem(key);
  } catch (error) {
    return null;
  }
}

function writePref(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch (error) {
    // ignore quota / access errors - the preference just won't persist
  }
}

// -------------------------------------------------------
// Utility Helpers
// -------------------------------------------------------
function escapeHtml(text) {
  if (text == null) {
    return "";
  }
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function getArrayFromText(value, separator) {
  if (!value) {
    return [];
  }
  return value.split(separator);
}

function getNumberArrayFromText(value) {
  var separator = (value && value.indexOf("||") !== -1) ? "||" : ",";
  var parts = getArrayFromText(value, separator);
  var out = [];
  var i;
  for (i = 0; i < parts.length; i++) {
    out.push(Number(parts[i]) || 0);
  }
  return out;
}

function showSuccess(message) {
  if (typeof toastr !== "undefined") {
    toastr.success(message);
  } else {
    alert(message);
  }
}

function showError(message) {
  if (typeof toastr !== "undefined") {
    toastr.error(message);
  } else {
    alert(message);
  }
}

function getResponseMessage(response, fallback) {
  if (response && response.message) {
    return response.message;
  }
  if (response && response.error) {
    return response.error;
  }
  return fallback || "An error occurred";
}

// Shared AJAX response handler. Returns true when the server reported
// success (and shows the success toast as a side effect), false on
// failure (and shows the error toast). Used by submitNormalForm,
// submitFileForm, bindPostButton and ad-hoc inline AJAX call sites that
// want consistent toast feedback without duplicating the envelope check.
//
// Previously called from three places but never defined — every comment
// submit / form post was throwing "handleAjaxResponse is not defined"
// even though the server-side write had already succeeded.
function handleAjaxResponse(response, successMessage) {
  if (response && response.success) {
    var msg = successMessage;
    if (response.message) {
      msg = response.message;
    }
    if (!msg) {
      msg = "Saved";
    }
    showSuccess(msg);
    return true;
  }
  showError(getResponseMessage(response, "Request failed"));
  return false;
}

function app_setting_int(key) {
  var $el = $("meta[name='app-setting-" + key + "']");
  if ($el.length) {
    return parseInt($el.attr("content"), 10) || 0;
  }
  return 0;
}

function getDefaultPageLength() {
  var pageLength = app_setting_int("datatable_page_length");
  if (pageLength > 0) {
    return pageLength;
  }
  return 10;
}

// -------------------------------------------------------
// Run setup that does not need the page body to be ready
// -------------------------------------------------------
function applyInitialPreferences() {
  if (readPref("pview-sidebar") === "collapsed") {
    $appHtml.attr("data-sidebar", "collapsed");
  } else {
    $appHtml.attr("data-sidebar", "expanded");
  }

  var t = readPref("noc-theme") || "dark";
  $appHtml.attr("data-theme", t);
}

function setupToastr() {
  if (typeof toastr === "undefined") {
    return;
  }
  toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: "toast-top-right",
    timeOut: 4000,
  };
}

applyInitialPreferences();
setupToastr();
setupChartDefaults();
setupDataTablesDefaults();

// -------------------------------------------------------
// Run page setup after the HTML is ready
// -------------------------------------------------------
$appDocument.ready(function () {
  initThemeToggle();
  initConfirmForms();
  initConfirmLinks();
  initCustomTooltips();
  initSimpleTables();
  initServerSideListTables();
  initTicketsTable();
  initSelectFields();
  initAjaxSelectLoaders();
  initTatCountdowns();
  initTrendCharts();
  initSeverityCharts();
  initCopyButtons();
  initTicketActions();
  initFlowMermaid();
  initStateSorter();
  initPasswordToggle();
  initLoadingForms();
  initUserIdLiveCheck();
  initSidebarToggle();
  initSearchShortcut();
  initCharCounters();
  initDirtyFormGuard();
  initCapsLockWarning();
  initBellDropdown();
  initBellLivePoll();
  initBulkActions();
  initSavedFilters();
  initTrendRangePicker();
  initTicketsAjaxFilters();
  initMentionAutocomplete();
  initSendTestEmail();
  initBumpAssetVersion();
  initActivityLogsTable();
  initSidebarScrollPersist();
});

function confirmAction(message, callback) {
  if (typeof Swal === "undefined") {
    if (window.confirm(message)) {
      callback();
    }
    return;
  }

  Swal.fire({
    title: message,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: APP_COLORS.critical,
    confirmButtonText: "Yes",
    cancelButtonText: "No",
  }).then(function (result) {
    if (result.isConfirmed) {
      callback();
    }
  });
}

function initConfirmLinks() {
  $appDocument.off("click.confirmLinks").on("click.confirmLinks", "[data-confirm-message]", function (event) {
    var $el = $(this);
    var href = $el.attr("href");
    var message = $el.attr("data-confirm-message");
    var method = ($el.attr("data-method") || "get").toLowerCase();

    if (!href) {
      return;
    }

    if (!message) {
      message = "Are you sure?";
    }

    event.preventDefault();

    confirmAction(message, function () {
      if (method === "post") {
        var $form = $("<form></form>")
          .attr({ method: "post", action: href })
          .css({ display: "none" });
        $("body").append($form);
        $form[0].submit();
        return;
      }
      window.location.href = href;
    });
  });
}

function initConfirmForms() {
  $appDocument.off("submit.confirmForms").on("submit.confirmForms", "form.js-confirm", function (event) {
    var form = this;
    var message = $(form).attr("data-confirm-message");

    if (!message) {
      message = "Do you want to continue?";
    }

    event.preventDefault();

    confirmAction(message, function () {
      form.submit();
    });
  });
}

// --- DATATABLES ---

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

// Registry of DataTable jQuery objects — used by adjustAllDataTables().
var dtRegistry = [];

function registerDataTableForAdjust($table) {
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

function adjustAllDataTables() {
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

// Re-align column widths on window resize and sidebar toggle.
// Uses requestAnimationFrame to debounce the resize event.
var dtAdjustPending = null;

function initDataTableAutoAdjust() {
  $appWindow.off("resize.dataTableAdjust").on("resize.dataTableAdjust", function () {
    if (dtAdjustPending !== null) {
      return;
    }
    if (window.requestAnimationFrame) {
      dtAdjustPending = window.requestAnimationFrame(function () {
        dtAdjustPending = null;
        adjustAllDataTables();
      });
    } else {
      dtAdjustPending = window.setTimeout(function () {
        dtAdjustPending = null;
        adjustAllDataTables();
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

  config = {
    processing: true,
    serverSide: true,
    autoWidth: false,
    scrollX: true,
    pageLength: getDefaultPageLength(),
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
  registerDataTableForAdjust($table);
}

function initTableFromDataUrl(tableSelector, columns, extra) {
  var $table = $(tableSelector);
  var ajaxUrl;

  if (!$table.length) {
    return;
  }

  ajaxUrl = $table.attr("data-table-url");
  if (!ajaxUrl) {
    return;
  }

  initServerTable(tableSelector, ajaxUrl, columns, extra);
}

function dtTruncate(maxLen) {
  return function (data, type, row) {
    if (!data) {
      return "";
    }
    var str = String(data);
    if (str.length <= maxLen) {
      return escapeHtml(str);
    }
    var truncated = str.substring(0, maxLen) + "...";
    return '<span title="' + escapeHtml(str) + '" style="cursor: help; border-bottom: 1px dotted var(--text-muted);">' + escapeHtml(truncated) + '</span>';
  };
}

function initCustomTooltips() {
  var $tooltipEl = null;
  var $activeTarget = null;

  $appDocument.on("mouseenter.appTooltip", "[title]", function () {
    var $target = $(this);
    var text = $target.attr("title");
    if (!text || $.trim(text) === "") return;

    // Swap title to data-tooltip to prevent the default browser popup
    $activeTarget = $target;
    $target.attr("data-tooltip", text).removeAttr("title");

    $tooltipEl = $('<div class="app-tooltip"></div>').text(text);
    $("body").append($tooltipEl);

    var rect = this.getBoundingClientRect();
    var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
    var scrollTop = window.pageYOffset || document.documentElement.scrollTop;

    var tooltipWidth = $tooltipEl.outerWidth();
    var tooltipHeight = $tooltipEl.outerHeight();

    var left = rect.left + scrollLeft + (rect.width / 2) - (tooltipWidth / 2);
    var top = rect.top + scrollTop - tooltipHeight - 8; // default: above target

    // If the tooltip would render off-screen at the top, position it below the target instead
    if (rect.top - tooltipHeight - 8 < 10) {
      top = rect.top + scrollTop + rect.height + 8;
      $tooltipEl.addClass("app-tooltip-below");
    }

    if (left < 10) left = 10;
    if (left + tooltipWidth > $(window).width() - 10) {
      left = $(window).width() - tooltipWidth - 10;
    }

    $tooltipEl.css({
      top: top + "px",
      left: left + "px"
    });

    setTimeout(function () {
      if ($tooltipEl) $tooltipEl.addClass("show");
    }, 10);
  });

  $appDocument.on("mouseleave.appTooltip", "[data-tooltip]", function () {
    var $target = $(this);
    var text = $target.attr("data-tooltip");
    if (text) {
      $target.attr("title", text).removeAttr("data-tooltip");
    }

    if ($tooltipEl) {
      var $temp = $tooltipEl;
      $tooltipEl = null;
      $activeTarget = null;
      $temp.removeClass("show");
      setTimeout(function () {
        $temp.remove();
      }, 150);
    }
  });

  // Automatically dismiss tooltip on scroll to prevent sticky positioning
  $appWindow.on("scroll.appTooltip", function () {
    if ($tooltipEl) {
      $tooltipEl.remove();
      $tooltipEl = null;
      if ($activeTarget) {
        var text = $activeTarget.attr("data-tooltip");
        if (text) {
          $activeTarget.attr("title", text).removeAttr("data-tooltip");
        }
        $activeTarget = null;
      }
    }
  });
}

// -------------------------------------------------------
// Individual server-side tables
// -------------------------------------------------------

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

function initProjectsTable() {
  initTableFromDataUrl(
    "#projectsTable",
    [
      { data: "name", orderable: true },
      {
        data: "description",
        orderable: false,
        render: dtTruncate(70),
      },
      { data: "status", orderable: true },
      { data: "created_by", orderable: false },
      { data: "created_at", orderable: true },
      { data: "actions", orderable: false },
    ],
    { order: [[4, "desc"]] },
  );
}

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

function initServerSideListTables() {
  initUsersTable();
  initProjectsTable();
  initAlertsTable();
  initFlowsTable();
}

// -------------------------------------------------------
// Tickets DataTable
// ticketFilters is module-scoped so initTicketsAjaxFilters
// can mutate it and the DataTable's ajax.data() reads
// the new values on every reload.
// -------------------------------------------------------
var ticketFilters = {};

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
  ticketPageLen = getDefaultPageLength();

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
      { data: "select", orderable: false, className: "ticket-bulk-cell text-center" },
      { data: "alarm_id_html", orderable: true },
      { data: "title_html", orderable: true },
      { data: "severity", orderable: true },
      { data: "priority", orderable: true },
      { data: "state", orderable: true },
      { data: "level", orderable: false },
      { data: "assignee", orderable: false },
      { data: "tat", orderable: false },
      { data: "created_at", orderable: true },
    ],
    drawCallback: function () {
      initTatCountdowns();
      adjustAllDataTables();
    },
  });

  registerDataTableForAdjust($table);
}

function initSimpleTables() {
  if (typeof $.fn.DataTable === "undefined") {
    return;
  }

  $("[data-simple-table='1']").each(function () {
    var table = this;
    var $table = $(table);
    var defaultLen = getDefaultPageLength();
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
    registerDataTableForAdjust($table);
  });
}

// --- SELECT2 ---

function cleanPlaceholderText(text) {
  var value = $.trim(text || "");

  value = value.replace(/\s+/g, " ");
  value = value.replace(/^\-+\s*/, "");
  value = value.replace(/\s*\-+$/, "");
  value = value.replace(/\*/g, "");
  value = value.replace(/\s*\(.*?\)\s*/g, " ");
  value = $.trim(value);

  return value;
}

function getSelectLabelText($field) {
  var fieldId = $field.attr("id");
  var $label;
  var text = "";

  if (fieldId) {
    $label = $('label[for="' + fieldId + '"]').first();
    if ($label.length) {
      text = cleanPlaceholderText($label.text());
      if (text) {
        return text;
      }
    }
  }

  $label = $field.closest("div").find("> label").first();
  if ($label.length) {
    text = cleanPlaceholderText($label.text());
  }

  return text;
}

function getSelectPlaceholder($field) {
  var placeholder;
  var emptyOptionText;
  var labelText;
  var lowerText;

  placeholder = cleanPlaceholderText($field.attr("data-placeholder"));
  if (placeholder) {
    return placeholder;
  }

  emptyOptionText = cleanPlaceholderText($field.find('option[value=""]').first().text());
  if (emptyOptionText) {
    return emptyOptionText;
  }

  labelText = getSelectLabelText($field);
  if (!labelText) {
    return "";
  }

  if ($field.prop("multiple")) {
    return "Select " + labelText;
  }

  lowerText = labelText.toLowerCase();
  if (lowerText.indexOf("select ") === 0) {
    return labelText;
  }

  return "Select " + labelText;
}

function buildSelect2Options($field) {
  var options = { width: "100%" };
  var placeholder = getSelectPlaceholder($field);
  var hasEmptyOption = $field.find('option[value=""]').length > 0;

  if (placeholder) {
    options.placeholder = placeholder;
  }

  if (hasEmptyOption || $field.prop("multiple")) {
    options.allowClear = true;
  }

  return options;
}

function initSelectFields() {
  if (typeof $.fn.select2 === "undefined") {
    return;
  }

  $("select.form-select").each(function () {
    var $field = $(this);

    if ($field.hasClass("select2-hidden-accessible")) {
      return;
    }

    if ($field.attr("data-no-select2") === "1") {
      return;
    }

    $field.select2(buildSelect2Options($field));
  });
}

// -------------------------------------------------------
// AJAX-driven linked selects (e.g. project → flow)
// -------------------------------------------------------

function setSelectMessage($select, text, disabled) {
  $select.html('<option value="">' + escapeHtml(text) + "</option>");
  $select.prop("disabled", disabled);
  $select.trigger("change");
}

function getLoadedOptionText(item, itemType) {
  var text = "";

  if (item && item.name) {
    text = item.name;
  }

  if (itemType === "state") {
    if (toBoolean(item.is_initial)) {
      text = text + " (initial)";
    } else {
      if (toBoolean(item.is_final)) {
        text = text + " (final)";
      }
    }
  }

  return text;
}

function loadLinkedSelect($source) {
  var targetSelector = $source.attr("data-load-target");
  var url = $source.attr("data-load-url");
  var itemType = $source.attr("data-item-type");
  var emptyText = $source.attr("data-empty-text");
  var defaultText = $source.attr("data-default-text");
  var loadingText = $source.attr("data-loading-text");
  var noDataText = $source.attr("data-no-data-text");
  var errorText = $source.attr("data-error-text");
  var selectedValue = $source.val();
  var $target = $(targetSelector);

  if (!itemType) {
    itemType = "";
  }
  if (!emptyText) {
    emptyText = "Select parent first";
  }
  if (!defaultText) {
    defaultText = "Select option";
  }
  if (!loadingText) {
    loadingText = "Loading...";
  }
  if (!noDataText) {
    noDataText = "No data found";
  }
  if (!errorText) {
    errorText = "Failed to load data";
  }

  if (!$target.length) {
    return;
  }
  if (!url) {
    return;
  }

  if (!selectedValue) {
    setSelectMessage($target, emptyText, true);
    return;
  }

  setSelectMessage($target, loadingText, true);

  $.ajax({
    url: url + "/" + selectedValue,
    type: "GET",
    dataType: "json",
    success: function (response) {
      var html = "";
      var i;
      var item;

      if (!response || !response.success || !response.data || response.data.length === 0) {
        setSelectMessage($target, noDataText, true);
        return;
      }

      html = '<option value="">' + escapeHtml(defaultText) + "</option>";

      for (i = 0; i < response.data.length; i++) {
        item = response.data[i];
        html += '<option value="' + escapeHtml(item.id) + '">';
        html += escapeHtml(getLoadedOptionText(item, itemType));
        html += "</option>";
      }

      $target.html(html);
      $target.prop("disabled", false);
      $target.trigger("change");
    },
    error: function () {
      setSelectMessage($target, errorText, true);
    },
  });
}

function initAjaxSelectLoaders() {
  $("[data-load-target][data-load-url]").each(function () {
    var $source = $(this);

    if ($source.data("loader-ready")) {
      return;
    }

    $source.data("loader-ready", true);

    $source.off("change.ajaxSelectLoader").on("change.ajaxSelectLoader", function () {
      loadLinkedSelect($source);
    });

    if ($source.val()) {
      loadLinkedSelect($source);
    }
  });
}

// --- TAT COUNTDOWN TIMER ---

function formatTimePart(number) {
  var value = String(number);
  if (value.length < 2) {
    value = "0" + value;
  }
  return value;
}

function renderTatItem(element) {
  var $element = $(element);
  var expires = $element.attr("data-tat-expires");
  var expiresAt;
  var millisecondsLeft;
  var days;
  var hours;
  var minutes;
  var seconds;
  var cssClass;
  var timeText;
  var isBigDisplay;
  var isNegative;

  if (!expires) {
    $element.html('<span class="tat-pill is-empty"><i class="bi bi-dash"></i></span>');
    return;
  }

  expiresAt = new Date(expires);
  if (isNaN(expiresAt.getTime())) {
    $element.html('<span class="tat-pill is-empty"><i class="bi bi-dash"></i></span>');
    return;
  }

  millisecondsLeft = expiresAt.getTime() - new Date().getTime();
  isBigDisplay = $element.closest(".tat-big").length > 0;
  isNegative = millisecondsLeft < 0;

  millisecondsLeft = Math.abs(millisecondsLeft);
  days = Math.floor(millisecondsLeft / 86400000);
  hours = Math.floor((millisecondsLeft % 86400000) / 3600000);
  minutes = Math.floor((millisecondsLeft % 3600000) / 60000);
  seconds = Math.floor((millisecondsLeft % 60000) / 1000);

  // Percentage-based warning when the server tells us the total TAT
  // window (data-tat-total-ms). At <=25% remaining we flip to the
  // pulsing warning state regardless of L1/L2/L3/L4 — that keeps the
  // visual cue meaningful for long TATs (e.g. 480 min L4) where the
  // old absolute 2-hour threshold would never trigger early. Without
  // the attribute we fall back to the original 2-hour absolute cap.
  var totalMs = parseInt($element.attr("data-tat-total-ms"), 10);
  cssClass = "is-healthy";
  if (isNegative) {
    cssClass = "is-breached";
  } else if (totalMs && totalMs > 0) {
    if (millisecondsLeft <= totalMs * 0.25) {
      cssClass = "is-warning";
    }
  } else if (millisecondsLeft < 7200000) {
    cssClass = "is-warning";
  }

  if (isBigDisplay) {
    timeText = "";
    if (days > 0) {
      timeText = days + "d ";
    }
    timeText = timeText + formatTimePart(hours) + ":" + formatTimePart(minutes) + ":" + formatTimePart(seconds);
    if (isNegative) {
      timeText = "-" + timeText;
    }
  } else {
    if (isNegative) {
      timeText = "-" + days + "d " + formatTimePart(hours) + "h " + formatTimePart(minutes) + "m " + formatTimePart(seconds) + "s";
    } else {
      minutes = minutes + hours * 60 + days * 1440;
      timeText = formatTimePart(minutes) + "m " + formatTimePart(seconds) + "s";
    }
  }

  $element.html('<span class="tat-pill ' + cssClass + '"><i class="bi bi-clock-history"></i> ' + timeText + "</span>");
}

// Cached collection — rebuilt by initTatCountdowns() so the
// 1Hz interval doesn't run a fresh DOM query every tick.
var $tatCountdowns = $();

function updateTatCountdowns() {
  if (!$tatCountdowns.length) {
    return;
  }
  $tatCountdowns.each(function () {
    renderTatItem(this);
  });
}

function initTatCountdowns() {
  $tatCountdowns = $(".tat-countdown");

  if ($tatCountdowns.length === 0) {
    if (tatTimer) {
      clearInterval(tatTimer);
      tatTimer = null;
    }
    return;
  }

  updateTatCountdowns();

  if (tatTimer) {
    clearInterval(tatTimer);
  }
  tatTimer = setInterval(updateTatCountdowns, 1000);
}

// --- CHARTS ---

function setupChartDefaults() {
  if (typeof Chart === "undefined") {
    return;
  }

  Chart.defaults.font.family = "'Inter', -apple-system, sans-serif";
  Chart.defaults.font.size = 12;
  Chart.defaults.color = "#64748B";
  Chart.defaults.borderColor = APP_COLORS.border;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.boxWidth = 8;
  Chart.defaults.plugins.legend.labels.padding = 14;
}

// Holds the live trend Chart instance for in-place range updates.
var trendChart = null;

function getTrendGridColor(isDarkTheme) {
  if (isDarkTheme) {
    return "rgba(148, 163, 184, 0.16)";
  }
  return APP_COLORS.border;
}

function getTrendGridLineWidth(isDarkTheme) {
  if (isDarkTheme) {
    return 0.6;
  }
  return 1;
}

function initTrendCharts() {
  var $canvas;
  var canvas;
  var labels;
  var values;
  var context;
  var gradient;
  var isDarkTheme;

  $canvas = $('[data-chart="trend"]');
  canvas = $canvas.get(0);

  if (!canvas) {
    return;
  }
  if (typeof Chart === "undefined") {
    return;
  }
  if ($canvas.attr("data-chart-ready") === "1") {
    return;
  }

  labels = getArrayFromText($canvas.attr("data-labels"), "||");
  values = getNumberArrayFromText($canvas.attr("data-values"));
  context = canvas.getContext("2d");
  gradient = context.createLinearGradient(0, 0, 0, 240);
  isDarkTheme = $appHtml.attr("data-theme") === "dark";

  gradient.addColorStop(0, "rgba(124,131,255,0.34)");
  gradient.addColorStop(1, "rgba(124,131,255,0.04)");

  trendChart = new Chart(context, {
    type: "line",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Tickets",
          data: values,
          borderColor: APP_COLORS.primary,
          backgroundColor: gradient,
          borderWidth: 2.5,
          fill: true,
          tension: 0.42,
          pointRadius: 4,
          pointBackgroundColor: "#FFFFFF",
          pointBorderColor: APP_COLORS.primary,
          pointBorderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: APP_COLORS.textLight },
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0, color: APP_COLORS.textLight },
          grid: {
            color: getTrendGridColor(isDarkTheme),
            lineWidth: getTrendGridLineWidth(isDarkTheme),
          },
        },
      },
    },
  });

  $canvas.attr("data-chart-ready", "1");
}

// Build legend labels for the severity doughnut — adds the count before each label text.
function buildSeverityLegendLabels(chart) {
  var defaultLabels = Chart.overrides.doughnut.plugins.legend.labels.generateLabels(chart);
  var result = [];
  var i;
  var item;
  var value;

  for (i = 0; i < defaultLabels.length; i++) {
    item = defaultLabels[i];
    value = chart.data.datasets[0].data[item.index] || 0;
    item.text = value + " " + item.text;
    result.push(item);
  }

  return result;
}

// Draw the total count and "TOTAL" label in the centre of the doughnut.
function drawSeverityCenterText(chart, total) {
  var chartContext = chart.ctx;
  var left = chart.chartArea.left;
  var right = chart.chartArea.right;
  var top = chart.chartArea.top;
  var bottom = chart.chartArea.bottom;
  var centerX = (left + right) / 2;
  var centerY = (top + bottom) / 2;

  chartContext.save();
  chartContext.textAlign = "center";
  chartContext.textBaseline = "middle";
  chartContext.fillStyle = "#0F172A";
  chartContext.font = "700 22px Inter, sans-serif";
  chartContext.fillText(String(total), centerX, centerY - 6);
  chartContext.fillStyle = APP_COLORS.textLight;
  chartContext.font = "500 11px Inter, sans-serif";
  chartContext.fillText("TOTAL", centerX, centerY + 14);
  chartContext.restore();
}

function initSeverityCharts() {
  var $canvas;
  var canvas;
  var values;
  var total;

  $canvas = $('[data-chart="severity"]');
  canvas = $canvas.get(0);

  if (!canvas) {
    return;
  }
  if (typeof Chart === "undefined") {
    return;
  }
  if ($canvas.attr("data-chart-ready") === "1") {
    return;
  }

  values = [Number($canvas.attr("data-info") || 0), Number($canvas.attr("data-major") || 0), Number($canvas.attr("data-critical") || 0)];
  total = values[0] + values[1] + values[2];

  new Chart(canvas, {
    type: "doughnut",
    data: {
      labels: ["Info", "Major", "Critical"],
      datasets: [
        {
          data: values,
          backgroundColor: [APP_COLORS.primary, APP_COLORS.major, APP_COLORS.critical],
          borderWidth: 0,
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "68%",
      plugins: {
        legend: {
          position: "right",
          labels: {
            padding: 16,
            color: APP_COLORS.textDark,
            generateLabels: buildSeverityLegendLabels,
          },
        },
      },
    },
    plugins: [
      {
        id: "centerText",
        afterDraw: function (chart) {
          drawSeverityCenterText(chart, total);
        },
      },
    ],
  });

  $canvas.attr("data-chart-ready", "1");
}

// --- AJAX FORM & BUTTON HELPERS ---

function submitNormalForm($form, options) {
  var url = $form.data("url");
  var data;

  if (!url) {
    return;
  }

  data = $form.serialize();

  $.ajax({
    url: url,
    type: "POST",
    data: data,
    dataType: "json",
    success: function (response) {
      if (handleAjaxResponse(response, options.successMessage)) {
        if (options.reloadOnSuccess) {
          window.location.reload();
        }
      }
    },
    error: function () {
      showError(options.errorMessage);
    },
  });
}

function submitFileForm(form, options) {
  var $form = $(form);
  var url = $form.data("url");
  var formData;

  if (!url) {
    return;
  }

  formData = new FormData(form);

  $.ajax({
    url: url,
    type: "POST",
    data: formData,
    processData: false,
    contentType: false,
    success: function (response) {
      if (handleAjaxResponse(response, options.successMessage)) {
        if (options.reloadOnSuccess) {
          window.location.reload();
        }
      }
    },
    error: function () {
      showError(options.errorMessage);
    },
  });
}

function bindPostForm(selector, options) {
  var $form = $(selector);

  if (!$form.length) {
    return;
  }

  if (!options.errorMessage) {
    options.errorMessage = "Request failed";
  }

  $form.off("submit.app").on("submit.app", function (event) {
    event.preventDefault();

    if (options.isFile) {
      submitFileForm(this, options);
    } else {
      submitNormalForm($(this), options);
    }
  });
}

function bindPostButton(selector) {
  var $button = $(selector);

  if (!$button.length) {
    return;
  }

  $button.off("click.app").on("click.app", function () {
    var $clickedButton = $(this);
    var url = $clickedButton.data("url");
    var label = $.trim($clickedButton.text());

    if (!label) {
      label = "Continue";
    }
    if (!url) {
      return;
    }

    if ($clickedButton.prop("disabled")) {
      return;
    }

    confirmAction(label + "?", function () {
      $.ajax({
        url: url,
        type: "POST",
        data: {},
        dataType: "json",
        success: function (response) {
          if (handleAjaxResponse(response, "Action completed")) {
            window.location.reload();
          }
        },
        error: function () {
          showError("Network error");
        },
      });
    });
  });
}

// --- TICKET DETAIL ---

function initPriorityInline() {
  var $field = $("#priorityInline");

  if (!$field.length) {
    return;
  }

  $field.off("change.app").on("change.app", function () {
    var url = $field.data("url");
    var data = {};

    if (!url) {
      return;
    }

    data.type = "priority";
    data.priority = $field.val();

    $.ajax({
      url: url,
      type: "POST",
      data: data,
      dataType: "json",
      success: function (response) {
        if (response && response.success) {
          showSuccess("Priority updated");
        } else {
          showError(getResponseMessage(response, "Failed to update priority"));
        }
      },
      error: function () {
        showError("Network error");
      },
    });
  });
}

function getEditableValue($element, fieldName) {
  var text = $.trim($element.text());
  var lowerText = text.toLowerCase();

  if (fieldName === "description") {
    if (lowerText.indexOf("click to add description") === 0) {
      return "";
    }
  }

  return text;
}

function getEmptyEditableText(fieldName) {
  if (fieldName === "description") {
    return "Click to add description...";
  }
  return "";
}

function buildInlineInput(fieldName, originalValue) {
  var $input;

  if (fieldName === "description") {
    $input = $("<textarea>");
    $input.attr("maxlength", 10000);
    $input.attr("rows", 4);
  } else {
    $input = $("<input>");
  }

  if (fieldName === "title") {
    $input.attr("maxlength", 300);
  }

  $input.addClass("form-control");
  $input.val(originalValue);

  return $input;
}

function saveEditableField($element, $input, fieldName, url, originalValue) {
  var newValue = $input.val();
  var data = {};

  data.type = fieldName;
  data[fieldName] = newValue;

  $.ajax({
    url: url,
    type: "POST",
    data: data,
    dataType: "json",
    success: function (response) {
      $element.removeClass("editing");
      if (response && response.success) {
        showSuccess(getResponseMessage(response, "Saved"));
        $element.text(newValue || getEmptyEditableText(fieldName));
      } else {
        showError(getResponseMessage(response, "Failed to save"));
        $element.text(originalValue || getEmptyEditableText(fieldName));
      }
    },
    error: function () {
      showError("Network error");
      $element.removeClass("editing");
      $element.text(originalValue || getEmptyEditableText(fieldName));
    },
  });
}

function initEditableFields() {
  $(".editable-inline")
    .off("click.app")
    .on("click.app", function () {
      var $element = $(this);
      var fieldName = $element.data("field");
      var url = $element.data("url");
      var originalValue;
      var $input;

      if ($element.hasClass("editing")) {
        return;
      }
      if (!fieldName) {
        return;
      }
      if (!url) {
        return;
      }

      originalValue = getEditableValue($element, fieldName);
      $input = buildInlineInput(fieldName, originalValue);

      $element.addClass("editing");
      $element.empty();
      $element.append($input);
      $input.focus();

      // Save when the user leaves the field.
      $input.on("blur", function () {
        saveEditableField($element, $input, fieldName, url, originalValue);
      });
    });
}

function initCopyButtons() {
  $appDocument.off("click.copyButtons").on("click.copyButtons", "[data-copy]", function () {
    var text = $(this).data("copy");

    if (!text) {
      return;
    }
    if (!navigator.clipboard) {
      return;
    }

    navigator.clipboard.writeText(text).then(function () {
      showSuccess("Copied: " + text);
    });
  });
}

function initTicketActions() {
  bindPostForm("#commentForm", {
    successMessage: "Comment added",
    errorMessage: "Network error",
    reloadOnSuccess: true,
  });

  bindPostForm("#assignForm", {
    successMessage: "Assigned",
    errorMessage: "Network error",
    reloadOnSuccess: true,
  });

  bindPostForm("#attachForm", {
    successMessage: "File attached",
    errorMessage: "Upload failed",
    reloadOnSuccess: true,
    isFile: true,
  });

  bindPostForm("#moveStateForm", {
    successMessage: "State moved",
    errorMessage: "Network error",
    reloadOnSuccess: true,
  });

  bindPostButton("#moveStateBtn");
  bindPostButton("#resolveBtn");
  bindPostButton("#closeBtn");

  initPriorityInline();
  initEditableFields();
}

// --- MERMAID ---

function escapeMermaidLabel(name) {
  var clean = String(name == null ? "" : name);
  clean = clean.replace(/\\/g, "\\\\");
  clean = clean.replace(/"/g, '\\"');
  clean = clean.replace(/[\r\n]+/g, " ");
  return '"' + clean + '"';
}

function buildDesignerMermaidSource($list) {
  var items = [];
  var allIds = {};
  var lines;
  var i;
  var item;
  var nodeId;
  var label;
  var hasParentLinks;

  $list.find(".state-item").each(function () {
    var $item = $(this);
    var id = parseInt($item.attr("data-id"), 10);
    var parentId;
    var name;
    var isInitial;
    var isFinal;

    if (!id) {
      return;
    }

    parentId = parseInt($item.attr("data-parent-id") || "0", 10);
    name = $.trim($item.find("strong").first().text());
    isInitial = $item.find(".badge.bg-success").length > 0;
    isFinal = $item.find(".badge.bg-dark").length > 0;

    items.push({ id: id, parentId: parentId, name: name, isInitial: isInitial, isFinal: isFinal });
    allIds[id] = true;
  });

  if (items.length === 0) {
    return "";
  }

  lines = ["flowchart LR"];

  // Declare each node.
  for (i = 0; i < items.length; i++) {
    item = items[i];
    nodeId = "s" + item.id;
    label = escapeMermaidLabel(item.name);

    if (item.isInitial || item.isFinal) {
      lines.push("  " + nodeId + "([" + label + "])");
    } else {
      lines.push("  " + nodeId + "[" + label + "]");
    }
  }

  // Draw edges using parent_state_id when available, otherwise fall back to sort order.
  hasParentLinks = false;
  for (i = 0; i < items.length; i++) {
    item = items[i];
    if (item.parentId > 0 && allIds[item.parentId]) {
      lines.push("  s" + item.parentId + " --> s" + item.id);
      hasParentLinks = true;
    }
  }

  if (!hasParentLinks) {
    for (i = 0; i < items.length - 1; i++) {
      lines.push("  s" + items[i].id + " --> s" + items[i + 1].id);
    }
  }

  // Apply styles for initial and final states.
  lines.push("  classDef initialState fill:#10b981,stroke:#10b981,color:#fff,stroke-width:2px");
  lines.push("  classDef finalState fill:#374151,stroke:#1f2937,color:#fff,stroke-width:2px");

  for (i = 0; i < items.length; i++) {
    item = items[i];
    if (item.isInitial) {
      lines.push("  class s" + item.id + " initialState");
    } else {
      if (item.isFinal) {
        lines.push("  class s" + item.id + " finalState");
      }
    }
  }

  return lines.join("\n");
}

var mermaidRenderSeq = 0;

function getMermaidThemeConfig() {
  return {
    startOnLoad: false,
    securityLevel: "loose",
    theme: "base",
    themeVariables: {
      fontFamily: "Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif",
      fontSize: "14px",
      primaryColor: "transparent",
      primaryBorderColor: "transparent",
      primaryTextColor: "#e5e7eb",
      lineColor: "#64748b",
      textColor: "#e5e7eb",
    },
    flowchart: {
      curve: "basis",
      htmlLabels: true,
      useMaxWidth: false,
      nodeSpacing: 60,
      rankSpacing: 80,
      padding: 14,
    },
  };
}

function onMermaidRenderDone(svg, done) {
  done(svg || null);
}

function onMermaidRenderFail(err, done) {
  console.error("Mermaid render failed", err);
  done(null);
}

function renderMermaidSvg(source, done) {
  var renderId;
  var maybePromise;

  if (typeof mermaid === "undefined") {
    done(null);
    return;
  }

  mermaidRenderSeq++;
  renderId = "mermaid-render-" + Date.now() + "-" + mermaidRenderSeq;

  try {
    maybePromise = mermaid.render(renderId, source);

    if (maybePromise && typeof maybePromise.then === "function") {
      maybePromise.then(
        function (result) {
          onMermaidRenderDone(result.svg || null, done);
        },
        function (err) {
          onMermaidRenderFail(err, done);
        },
      );
    } else {
      done(String(maybePromise || ""));
    }
  } catch (err) {
    console.error("Mermaid render error", err);
    done(null);
  }
}

function destroyPanZoom($widget) {
  // svg-pan-zoom is deprecated; using native aspect-ratio-preserving zoom/fit handlers.
}

function leftAlignPanZoom(pz) {
  // svg-pan-zoom is deprecated; using native aspect-ratio-preserving zoom/fit handlers.
}

function attachFlowWidgetZoom($widget) {
  var $canvas = $widget.find("[data-flow-canvas]").first();
  var svgEl = $canvas.find("svg")[0];

  if (!svgEl) {
    return;
  }

  // Parse viewBox to find aspect ratio.
  var viewBox = svgEl.getAttribute("viewBox");
  var aspectRatio = null;
  if (viewBox) {
    var parts = viewBox.split(/[\s,]+/);
    if (parts.length >= 4) {
      var naturalWidth = parseFloat(parts[2]);
      var naturalHeight = parseFloat(parts[3]);
      if (naturalWidth > 0 && naturalHeight > 0) {
        aspectRatio = naturalWidth / naturalHeight;
      }
    }
  }

  // Store zoom percentage and aspect ratio in the widget.
  $widget.data("flow-zoom-pct", 100);
  $widget.data("flow-aspect-ratio", aspectRatio);

  // Set initial styles on the SVG to allow natural responsive sizing.
  // flex-shrink:0 is needed because we previously applied display:flex
  // to the wrap, which made the SVG shrink-to-fit on zoom-in (silently
  // ignoring the inline width we set). Keeping the SVG as a block child
  // of an overflow:auto wrap is the simpler, more predictable layout.
  svgEl.style.setProperty("max-width", "none", "important");
  svgEl.style.setProperty("display", "block", "important");
  svgEl.style.setProperty("flex-shrink", "0", "important");

  updateFlowWidgetZoom($widget, 100);
}

function updateFlowWidgetZoom($widget, zoomPct) {
  var $canvas = $widget.find("[data-flow-canvas]").first();
  var svgEl = $canvas.find("svg")[0];

  if (!svgEl) {
    return;
  }

  // Constrain zoom percentage
  if (zoomPct < 40) zoomPct = 40;
  if (zoomPct > 300) zoomPct = 300;

  $widget.data("flow-zoom-pct", zoomPct);
  $widget.find("[data-flow-zoom-pct]").text(zoomPct + "%");

  // Determine standard base height (content area height)
  var baseHeight = 220;
  if ($widget.hasClass("is-fullscreen")) {
    baseHeight = $widget.find(".flow-mermaid-wrap").height() - 20;
  }

  var targetHeight = Math.round(baseHeight * (zoomPct / 100));
  svgEl.style.setProperty("height", targetHeight + "px", "important");

  // Calculate target width using aspect ratio if available
  var aspectRatio = $widget.data("flow-aspect-ratio");
  if (aspectRatio) {
    var targetWidth = Math.round(targetHeight * aspectRatio);
    svgEl.style.setProperty("width", targetWidth + "px", "important");
  } else {
    svgEl.style.setProperty("width", "auto", "important");
  }
}

function renderFlowWidget($widget) {
  var source = $widget.data("flow-mermaid-source");
  var $canvas = $widget.find("[data-flow-canvas]").first();
  var $pre;

  if (!$canvas.length) {
    return;
  }

  if (!source) {
    // First call — snapshot the inline Mermaid source.
    $pre = $canvas.find("pre.mermaid").first();
    if ($pre.length) {
      source = $pre.text();
      $widget.data("flow-mermaid-source", source);
    }
  }

  if (!source) {
    return;
  }

  renderMermaidSvg(source, function (svg) {
    if (svg === null) {
      return;
    }
    $canvas.html(svg);
    attachFlowWidgetZoom($widget);
  });
}

function rerenderAllFlowMermaid() {
  if (typeof mermaid === "undefined") {
    return;
  }

  $(".flow-widget").each(function () {
    renderFlowWidget($(this));
  });
}

function refreshFlowPreview($list) {
  var previewSelector = $list.attr("data-preview-target");
  var $target = $(previewSelector);
  var $widget;
  var newSource;

  if (!$target.length) {
    return;
  }

  $widget = $target.find(".flow-widget").first();
  if (!$widget.length) {
    if ($target.is(".flow-widget")) {
      $widget = $target;
    }
  }
  if (!$widget.length) {
    return;
  }

  newSource = buildDesignerMermaidSource($list);
  if (!newSource) {
    return;
  }

  $widget.data("flow-mermaid-source", newSource);
  renderFlowWidget($widget);
}

function fitFlowWidget($widget) {
  var $canvas = $widget.find("[data-flow-canvas]").first();
  var svgEl = $canvas.find("svg")[0];

  if (!svgEl) {
    return;
  }

  var aspectRatio = $widget.data("flow-aspect-ratio");
  if (!aspectRatio) {
    updateFlowWidgetZoom($widget, 100);
    return;
  }

  var containerWidth = $widget.find(".flow-mermaid-wrap").width() - 40;
  var baseHeight = 220;
  if ($widget.hasClass("is-fullscreen")) {
    baseHeight = $widget.find(".flow-mermaid-wrap").height() - 20;
  }

  // Width to fit container: fitHeight = containerWidth / aspectRatio
  var fitHeight = containerWidth / aspectRatio;
  var fitZoomPct = Math.round((fitHeight / baseHeight) * 100);

  // Apply sensible min/max constraints so it remains perfectly legible
  if (fitZoomPct < 40) fitZoomPct = 40;
  if (fitZoomPct > 120) fitZoomPct = 120;

  updateFlowWidgetZoom($widget, fitZoomPct);
}

function initFlowWidgets() {
  $(".flow-widget").each(function () {
    var $widget = $(this);

    if ($widget.data("flow-widget-ready")) {
      return;
    }
    $widget.data("flow-widget-ready", true);

    $widget.on("click", "[data-flow-fit]", function () {
      fitFlowWidget($widget);
    });

    $widget.on("click", "[data-flow-zoom-in]", function () {
      var currentZoom = $widget.data("flow-zoom-pct") || 100;
      updateFlowWidgetZoom($widget, currentZoom + 15);
    });

    $widget.on("click", "[data-flow-zoom-out]", function () {
      var currentZoom = $widget.data("flow-zoom-pct") || 100;
      updateFlowWidgetZoom($widget, currentZoom - 15);
    });

    $widget.on("click", "[data-flow-fullscreen]", function () {
      var elem = $widget[0];
      if (document.fullscreenElement) {
        document.exitFullscreen();
      } else {
        if (elem.requestFullscreen) {
          elem.requestFullscreen();
        } else {
          if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
          }
        }
      }
    });
  });

  $appDocument.off("fullscreenchange.flowWidget").on("fullscreenchange.flowWidget", function () {
    $(".flow-widget").each(function () {
      var $widget = $(this);

      if (document.fullscreenElement === $widget[0]) {
        $widget.addClass("is-fullscreen");
      } else {
        $widget.removeClass("is-fullscreen");
      }

      // Defer zoom update slightly until the DOM container dimensions settle
      setTimeout(function () {
        var currentZoom = $widget.data("flow-zoom-pct") || 100;
        updateFlowWidgetZoom($widget, currentZoom);
      }, 100);
    });
  });
}

function initFlowMermaid() {
  if (typeof mermaid === "undefined") {
    return;
  }
  mermaid.initialize(getMermaidThemeConfig());
  initFlowWidgets();
  rerenderAllFlowMermaid();
}

// --- FLOW DESIGNER ---

function getStateOrder($list) {
  var order = [];

  $list.find(".state-item").each(function () {
    var id = parseInt($(this).attr("data-id"), 10);
    if (id > 0) {
      order.push(id);
    }
  });

  return order;
}

function saveStateOrder($list) {
  var reorderUrl = $list.attr("data-reorder-url");
  var order = getStateOrder($list);
  var flowId = parseInt($list.attr("data-flow-id"), 10);

  if (!reorderUrl) {
    return;
  }
  if (!flowId) {
    return;
  }

  $.ajax({
    url: reorderUrl,
    type: "POST",
    data: { flow_id: flowId, order: order },
    dataType: "json",
    success: function (response) {
      if (response && response.success) {
        showSuccess("Order saved");
      } else {
        showError(getResponseMessage(response, "Failed to save order"));
      }
    },
    error: function () {
      showError("Network error while saving order");
    },
  });
}

function initStateSorter() {
  var $list = $("#stateList");

  if (!$list.length) {
    return;
  }
  if (typeof $.fn.sortable === "undefined") {
    return;
  }
  if ($list.data("sortable-ready")) {
    return;
  }

  $list.data("sortable-ready", true);

  $list.sortable({
    items: "> .state-item",
    handle: ".bi-grip-vertical, > div",
    placeholder: "state-item",
    forcePlaceholderSize: true,
    cursor: "grabbing",
    update: function () {
      refreshFlowPreview($list);
      saveStateOrder($list);
    },
  });

  $list.disableSelection();
}

// --- PASSWORD SHOW/HIDE TOGGLE ---

function initPasswordToggle() {
  $appDocument.off("click.passwordToggle").on("click.passwordToggle", "[data-toggle-password]", function () {
    var $btn = $(this);
    var targetId = $btn.attr("data-toggle-password");
    var $input;
    var $icon;

    if (!targetId) {
      return;
    }

    $input = $("#" + targetId);
    if (!$input.length) {
      return;
    }

    $icon = $btn.find("i");

    if ($input.attr("type") === "password") {
      $input.attr("type", "text");
      $icon.removeClass("bi-eye").addClass("bi-eye-slash");
    } else {
      $input.attr("type", "password");
      $icon.removeClass("bi-eye-slash").addClass("bi-eye");
    }
  });
}

// --- LOADING FORMS ---

function initLoadingForms() {
  $appDocument.off("submit.loadingForms").on("submit.loadingForms", "form[data-loading-form]", function () {
    var $form = $(this);
    var $btn = $form.find("button[type=submit]");
    var $icon;
    var originalClass;

    if (!$btn.length) {
      return;
    }
    if ($btn.data("loading")) {
      return;
    }

    $btn.data("loading", true);

    $icon = $btn.find("i").first();
    if ($icon.length) {
      originalClass = $icon.attr("class") || "";
      $btn.data("originalIcon", originalClass);
      $icon.attr("class", "spinner-border spinner-border-sm me-1");
    }

    $btn.prop("disabled", true);
  });

  // Restore button if the page is returned from bfcache (browser back button).
  $appWindow.off("pageshow.loadingForms").on("pageshow.loadingForms", function (event) {
    var originalClass;

    if (event.originalEvent && event.originalEvent.persisted) {
      $("form[data-loading-form] button[type=submit]").each(function () {
        var $btn = $(this);

        $btn.prop("disabled", false);
        $btn.removeData("loading");

        originalClass = $btn.data("originalIcon");
        if (originalClass) {
          $btn.find("i").first().attr("class", originalClass);
        }
      });
    }
  });
}

// --- USER-ID LIVE AVAILABILITY CHECK ---

function initUserIdLiveCheck() {
  var $input = $("#userIdInput");
  var $status = $("#userIdStatus");
  var debounceTimer = null;
  var ignoreId;
  var checkUrl;

  if (!$input.length || !$status.length) {
    return;
  }

  ignoreId = $input.attr("data-ignore-id") || "0";
  checkUrl = $input.attr("data-check-url");
  if (!checkUrl) {
    return;
  }

  function setStatus(state, title) {
    $status.attr("title", title || "");

    if (state === "ok") {
      $status.html('<i class="bi bi-check-circle text-success"></i>');
    } else {
      if (state === "bad") {
        $status.html('<i class="bi bi-x-circle text-danger"></i>');
      } else {
        if (state === "wait") {
          $status.html('<span class="spinner-border spinner-border-sm text-muted"></span>');
        } else {
          $status.html('<i class="bi bi-dash text-muted"></i>');
        }
      }
    }
  }

  $input.off("input.userIdCheck").on("input.userIdCheck", function () {
    var val = $.trim($input.val() || "");

    if (val === "") {
      setStatus("idle", "");
      return;
    }

    if (!/^[A-Za-z0-9._-]{3,64}$/.test(val)) {
      setStatus("bad", "3–64 chars; letters, digits, dot, underscore, hyphen");
      return;
    }

    setStatus("wait", "Checking…");

    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(function () {
      $.ajax({
        url: checkUrl,
        type: "GET",
        data: { user_id: val, ignore: ignoreId },
        dataType: "json",
        success: function (response) {
          var msg;
          if (response && response.success) {
            setStatus("ok", "Available");
          } else {
            msg = "Taken";
            if (response && response.message) {
              msg = response.message;
            }
            setStatus("bad", msg);
          }
        },
        error: function (xhr) {
          var msg = "Taken";
          if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
          }
          setStatus("bad", msg);
        },
      });
    }, 250);
  });

  // Re-validate a prefilled value on edit screens.
  if ($.trim($input.val() || "").length > 0) {
    $input.trigger("input");
  }
}

// --- THEME TOGGLE ---

function initThemeToggle() {
  var $toggle = $("#themeToggle");
  if (!$toggle.length) {
    return;
  }

  $toggle.off("click.themeToggle").on("click.themeToggle", function (e) {
    e.preventDefault();
    var currentTheme = $appHtml.attr("data-theme") || "dark";
    var newTheme = (currentTheme === "dark") ? "light" : "dark";

    // Update HTML attribute
    $appHtml.attr("data-theme", newTheme);

    // Persist in localStorage
    writePref("noc-theme", newTheme);

    // Persist in cookie (expires in 1 year)
    document.cookie = "theme=" + newTheme + ";path=/;max-age=31536000;SameSite=Lax";

    // Redraw charts if present
    if (trendChart) {
      var isDark = (newTheme === "dark");
      trendChart.options.scales.y.grid.color = getTrendGridColor(isDark);
      trendChart.options.scales.y.grid.lineWidth = getTrendGridLineWidth(isDark);
      trendChart.update();
    }

    // Persist in user profile via AJAX POST
    var updateUrl = $toggle.attr("data-update-url");
    if (updateUrl) {
      $.ajax({
        url: updateUrl,
        type: "POST",
        data: { theme: newTheme },
        dataType: "json",
        success: function (response) {
          if (response && response.success) {
            // success, silent
          }
        },
        error: function () {
          // silent ignore
        }
      });
    }
  });
}

// --- SIDEBAR TOGGLE ---

function initSidebarToggle() {
  var $toggle = $("#sidebarToggle");
  var $sidebar = $("#appSidebar");
  var $backdrop = $("#sidebarBackdrop");

  if (!$toggle.length || !$sidebar.length) {
    return;
  }

  function isMobile() {
    return window.matchMedia(APP_MOBILE_BREAKPOINT).matches;
  }

  function openDrawer() {
    $sidebar.addClass("is-open");
    $backdrop.addClass("is-active");
    $appBody.addClass("menu-open");
    $toggle.attr("aria-expanded", "true");
  }

  function closeDrawer() {
    $sidebar.removeClass("is-open");
    $backdrop.removeClass("is-active");
    $appBody.removeClass("menu-open");
    $toggle.attr("aria-expanded", "false");
  }

  function attachTooltip($el, label) {
    var existing = bootstrap.Tooltip.getInstance($el[0]);
    if (existing) {
      existing.dispose();
    }
    $el.attr("data-bs-toggle", "tooltip");
    $el.attr("data-bs-placement", "right");
    $el.attr("data-bs-title", label);
    new bootstrap.Tooltip($el[0], { trigger: "hover focus" });
  }

  function destroyNavTooltips() {
    $sidebar.find(".nav-link, .user-chip").each(function () {
      var tt = window.bootstrap && bootstrap.Tooltip.getInstance(this);
      if (tt) {
        tt.dispose();
      }
      $(this).removeAttr("data-bs-toggle");
      $(this).removeAttr("data-bs-placement");
      $(this).removeAttr("data-bs-title");
    });
  }

  function initNavTooltips() {
    var $chip;
    var chipTip;

    if (!window.bootstrap || !bootstrap.Tooltip) {
      return;
    }

    $sidebar.find(".nav-link").each(function () {
      var label = $(this).find("span").text().trim();
      if (!label) {
        return;
      }
      attachTooltip($(this), label);
    });

    $chip = $sidebar.find(".user-chip");
    chipTip = $chip.attr("data-user-tip") || "";
    if ($chip.length && chipTip) {
      attachTooltip($chip, chipTip);
    }
  }

  function setToggleIcon(state) {
    var $icon = $toggle.find("i");
    if (state === "collapsed") {
      $icon.removeClass("bi-list").addClass("bi-layout-sidebar-reverse");
    } else {
      $icon.removeClass("bi-layout-sidebar-reverse").addClass("bi-list");
    }
  }

  function applyDesktopState(state) {
    if (state === "collapsed") {
      $appHtml.attr("data-sidebar", "collapsed");
      $toggle.attr("aria-label", "Expand navigation menu");
      $toggle.attr("title", "Expand menu");
      setToggleIcon("collapsed");
      initNavTooltips();
    } else {
      destroyNavTooltips();
      $appHtml.attr("data-sidebar", "expanded");
      $toggle.attr("aria-label", "Collapse navigation menu");
      $toggle.attr("title", "Collapse menu");
      setToggleIcon("expanded");
    }
  }

  function toggleDesktopCollapse() {
    var current = $appHtml.attr("data-sidebar");
    var next = "collapsed";
    if (current === "collapsed") {
      next = "expanded";
    }
    applyDesktopState(next);
    writePref("pview-sidebar", next);
  }

  // Apply the saved state on init so aria-label is correct immediately.
  if (!isMobile()) {
    if (readPref("pview-sidebar") === "collapsed") {
      applyDesktopState("collapsed");
    } else {
      applyDesktopState("expanded");
    }
  }

  $toggle.off("click.sidebarToggle").on("click.sidebarToggle", function (e) {
    e.preventDefault();
    if (isMobile()) {
      if ($sidebar.hasClass("is-open")) {
        closeDrawer();
      } else {
        openDrawer();
      }
    } else {
      toggleDesktopCollapse();
    }
  });

  $backdrop.off("click.sidebarToggle").on("click.sidebarToggle", closeDrawer);

  $appDocument.off("keydown.sidebarToggle").on("keydown.sidebarToggle", function (e) {
    if (e.key === "Escape" && $sidebar.hasClass("is-open")) {
      closeDrawer();
    }
  });

  // On mobile a nav-link click closes the drawer.
  $sidebar.off("click.sidebarToggle", ".nav-link").on("click.sidebarToggle", ".nav-link", function () {
    if (isMobile()) {
      closeDrawer();
    }
  });

  $appWindow.off("resize.sidebarToggle").on("resize.sidebarToggle", function () {
    if (!isMobile()) {
      closeDrawer();
      if (readPref("pview-sidebar") === "collapsed") {
        applyDesktopState("collapsed");
      } else {
        applyDesktopState("expanded");
      }
    } else {
      // On mobile the collapsed attr is not used — remove it to keep CSS clean.
      $appHtml.removeAttr("data-sidebar");
    }
  });
}

// --- SEARCH SHORTCUT ---

function initSearchShortcut() {
  $appDocument.off("keydown.searchShortcut").on("keydown.searchShortcut", function (event) {
    var tag;
    var $search;

    if (event.key !== "/") {
      return;
    }
    if (event.ctrlKey || event.metaKey || event.altKey) {
      return;
    }

    tag = "";
    if (event.target && event.target.tagName) {
      tag = event.target.tagName.toLowerCase();
    }
    if (tag === "input" || tag === "textarea" || tag === "select") {
      return;
    }
    if (event.target && event.target.isContentEditable) {
      return;
    }

    $search = $(".dataTables_filter input").first();
    if (!$search.length) {
      $search = $(".form-control").first();
    }
    if (!$search.length) {
      return;
    }

    event.preventDefault();
    $search.focus();
  });
}

// --- CHARACTER COUNTER ---

function initCharCounters() {
  $("[data-char-counter='1']").each(function () {
    var $field = $(this);
    var max;
    var $counter;

    if ($field.data("char-counter-ready")) {
      return;
    }

    max = parseInt($field.attr("maxlength"), 10);
    if (!max || max <= 0) {
      return;
    }

    $field.data("char-counter-ready", true);

    $counter = $('<small class="char-counter text-muted"></small>');
    $field.after($counter);

    function refresh() {
      var used = ($field.val() || "").length;
      $counter.text(used + " / " + max);
      if (used >= Math.floor(max * 0.9)) {
        $counter.removeClass("text-muted").addClass("text-danger");
      } else {
        $counter.removeClass("text-danger").addClass("text-muted");
      }
    }

    refresh();
    $field.on("input.charCounter", refresh);
  });
}

// --- DIRTY FORM GUARD ---

function initDirtyFormGuard() {
  $("form[data-dirty-guard='1']").each(function () {
    var $form = $(this);
    var initial;
    var submitted;
    var formId;

    if ($form.data("dirty-guard-ready")) {
      return;
    }
    $form.data("dirty-guard-ready", true);

    initial = $form.serialize();
    submitted = false;
    formId = $form.attr("id") || Math.random();

    $form.on("submit.dirtyGuard", function () {
      submitted = true;
    });

    $appWindow.on("beforeunload.dirtyGuard_" + formId, function () {
      if (submitted) {
        return undefined;
      }
      if ($form.serialize() === initial) {
        return undefined;
      }
      return "You have unsaved changes. Leave anyway?";
    });
  });
}

// --- CAPS LOCK WARNING ---

function initCapsLockWarning() {
  $("input[type=password][data-caps-warn='1']").each(function () {
    var $field = $(this);
    var $warn;

    if ($field.data("caps-warn-ready")) {
      return;
    }
    $field.data("caps-warn-ready", true);

    $warn = $('<small class="caps-warn text-warning" style="display:none;"><i class="bi bi-capslock"></i> Caps Lock is on</small>');
    $field.after($warn);

    function check(event) {
      var on = false;
      if (event && typeof event.getModifierState === "function") {
        on = event.getModifierState("CapsLock");
      }
      if (on) {
        $warn.show();
      } else {
        $warn.hide();
      }
    }

    $field.on("keydown.capsWarn keyup.capsWarn", check);
    $field.on("blur.capsWarn", function () {
      $warn.hide();
    });
  });
}

// --- BELL BADGE ---

var bellRefreshInFlight = false;

function applyBellBadge(counts) {
  var $bell;
  var $badge;
  var total;
  var text;
  var tip;
  var parts;

  $bell = $("#topbarBell");
  if (!$bell.length) {
    return;
  }

  $badge = $bell.find(".bell-badge");
  if (!$badge.length) {
    return;
  }

  total = 0;
  if (counts && counts.total) {
    total = parseInt(counts.total, 10);
  }

  if (total > 99) {
    text = "99+";
  } else {
    text = String(total);
  }

  tip = "No actionable tickets";
  if (total > 0) {
    parts = [];
    if (counts.critical_open > 0) {
      parts.push(counts.critical_open + " critical open");
    }
    if (counts.escalated > 0) {
      parts.push(counts.escalated + " escalated");
    }
    tip = total + " actionable: " + parts.join(" · ");
  }

  $bell.attr("title", tip).attr("aria-label", tip);
  $badge.attr("data-count", total).text(text);

  if (total === 0) {
    $badge.attr("hidden", "hidden").removeClass("is-critical");
  } else {
    $badge.removeAttr("hidden").addClass("is-critical");
    // Replay the pop animation so the user sees the refresh.
    $badge[0].style.animation = "none";
    void $badge[0].offsetWidth; // force reflow
    $badge[0].style.animation = "";
  }
}

// Remember the last seen count so the live-poll path can tell when it
// actually went UP (and only then play the audio cue / show a browser
// notification — re-renders that just keep the same number must stay quiet).
var bellLastSeenTotal = null;
var bellOriginalTitle = null;

function readMetaSetting(name, fallback) {
  var v = $('meta[name="app-setting-' + name + '"]').attr("content");
  if (typeof v === "undefined" || v === null || v === "") {
    return fallback;
  }
  return v;
}

function maybeBeepOnce() {
  // Tiny synthesized beep — no external audio file, works offline / on VPN.
  // Falls through silently in browsers without Web Audio (very old IE etc.).
  try {
    var AudioCtor = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtor) {
      return;
    }
    var ctx = new AudioCtor();
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.type = "sine";
    osc.frequency.value = 660;
    gain.gain.value = 0.0001;
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    // Quick attack-release shape so the cue is short and unobtrusive.
    gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
    osc.stop(ctx.currentTime + 0.4);
  } catch (e) {
    // ignore — audio is a nice-to-have
  }
}

function maybeNotifyOnce(total, counts) {
  // Browser notification — only after the user has explicitly granted
  // permission. We don't *request* permission here (handled separately in
  // initBellLivePoll); silently noop if denied/default so we don't surprise
  // operators with permission popups mid-shift.
  try {
    if (typeof Notification === "undefined") {
      return;
    }
    if (Notification.permission !== "granted") {
      return;
    }
    var parts = [];
    if (counts.critical_open > 0) {
      parts.push(counts.critical_open + " critical open");
    }
    if (counts.escalated > 0) {
      parts.push(counts.escalated + " escalated");
    }
    var body = total + " actionable ticket(s): " + parts.join(" · ");
    var n = new Notification("pView — new actionable ticket", { body: body });
    // Auto-close after 8 seconds so a flurry doesn't pile up on screen.
    setTimeout(function () { try { n.close(); } catch (e) {} }, 8000);
  } catch (e) {
    // ignore
  }
}

function updateTabBadge(total) {
  // Stash the original title once so we can restore it cleanly.
  if (bellOriginalTitle === null) {
    bellOriginalTitle = document.title.replace(/^\(\d+\)\s+/, "");
  }
  if (total > 0) {
    document.title = "(" + total + ") " + bellOriginalTitle;
  } else {
    document.title = bellOriginalTitle;
  }
}

// Favicon badge — draws a red disc with the count when there are
// actionable critical alarms so operators see the alert even when the
// browser tab isn't active. Original favicon href is captured once so we
// can restore it when the count drops to zero. We don't try to overlay
// the badge on the original icon (Chrome can't render .ico into canvas
// reliably); instead we draw a clean red badge that's always legible.
var bellOriginalFaviconHref = null;
var bellLastFaviconCount = -1;

function buildAlertFaviconDataUrl(count) {
  var canvas = document.createElement("canvas");
  canvas.width = 32;
  canvas.height = 32;
  var ctx = canvas.getContext("2d");
  // Filled red circle background
  ctx.fillStyle = "#ef4444";
  ctx.beginPath();
  ctx.arc(16, 16, 15, 0, Math.PI * 2);
  ctx.fill();
  // White outline (sits well in both light and dark browser chrome)
  ctx.strokeStyle = "#ffffff";
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.arc(16, 16, 14, 0, Math.PI * 2);
  ctx.stroke();
  // Count label — 9+ for anything > 9 so it stays legible at 32px.
  var label = count > 9 ? "9+" : String(count);
  ctx.fillStyle = "#ffffff";
  ctx.font = "bold " + (label.length > 1 ? 14 : 18) + "px Arial, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.fillText(label, 16, 17);
  try {
    return canvas.toDataURL("image/png");
  } catch (e) {
    return null;
  }
}

function updateFaviconBadge(criticalCount) {
  var link = document.getElementById("appFavicon");
  if (!link) {
    return;
  }
  if (bellOriginalFaviconHref === null) {
    bellOriginalFaviconHref = link.getAttribute("href");
  }
  var n = parseInt(criticalCount, 10);
  if (isNaN(n) || n < 0) {
    n = 0;
  }
  // No-op if nothing changed — saves churn on every poll tick.
  if (n === bellLastFaviconCount) {
    return;
  }
  bellLastFaviconCount = n;
  if (n === 0) {
    link.setAttribute("href", bellOriginalFaviconHref);
    return;
  }
  var dataUrl = buildAlertFaviconDataUrl(n);
  if (dataUrl) {
    link.setAttribute("href", dataUrl);
  }
}

function refreshBellBadge() {
  var $bell;
  var url;

  $bell = $("#topbarBell");
  if (!$bell.length || bellRefreshInFlight) {
    return null;
  }

  url = $bell.attr("data-actionable-url");
  if (!url) {
    return null;
  }

  bellRefreshInFlight = true;

  $.ajax({
    url: url,
    type: "GET",
    dataType: "json",
    cache: false,
    success: function (response) {
      if (response && response.success && response.data) {
        var data = response.data;
        var total = 0;
        if (data.total) {
          total = parseInt(data.total, 10);
        }

        // Only fire cue + notification when the number actually went UP.
        // First load just records the baseline silently.
        if (bellLastSeenTotal !== null && total > bellLastSeenTotal) {
          var audioOn = readMetaSetting("live_audio_enabled", "1") === "1";
          var notifyOn = readMetaSetting("live_browser_notify", "1") === "1";
          if (audioOn) {
            maybeBeepOnce();
          }
          if (notifyOn) {
            maybeNotifyOnce(total, data);
          }
        }
        bellLastSeenTotal = total;

        updateTabBadge(total);
        // Favicon badge uses critical_open specifically (not total) so the
        // OS-level alert cue is reserved for genuinely actionable critical
        // tickets rather than every escalated or info-tier alarm.
        var crit = 0;
        if (data.critical_open) {
          crit = parseInt(data.critical_open, 10);
        }
        updateFaviconBadge(crit);
        applyBellBadge(data);
      }
    },
    complete: function () {
      bellRefreshInFlight = false;
    },
  });
}

// Expose so inline view scripts can call refreshBellBadge() directly.
window.refreshBellBadge = refreshBellBadge;

// Periodic poll — every N seconds (admin-configurable via app_settings).
// A value of 0 disables the timer entirely. We also fire one immediate
// refresh on init so the user gets a fresh count without waiting a full
// interval; subsequent polls run on the timer.
var bellPollTimer = null;
function initBellLivePoll() {
  if (!$("#topbarBell").length) {
    return;
  }
  var n = parseInt(readMetaSetting("live_poll_seconds", "15"), 10);
  if (isNaN(n) || n <= 0) {
    return;
  }
  if (n < 5) {
    n = 5;  // safety floor — don't hammer the DB
  }
  if (n > 120) {
    n = 120;
  }

  // Ask for browser-notification permission once, on first dashboard load,
  // only if the user hasn't already decided. We do this lazily so we don't
  // pop a dialog on the login screen.
  try {
    var notifyOn = readMetaSetting("live_browser_notify", "1") === "1";
    if (notifyOn && typeof Notification !== "undefined" && Notification.permission === "default") {
      Notification.requestPermission();
    }
  } catch (e) {
    // ignore
  }

  // Initial refresh primes bellLastSeenTotal so the very first transition
  // (page load) doesn't blast a notification at the operator.
  refreshBellBadge();

  if (bellPollTimer) {
    clearInterval(bellPollTimer);
  }
  bellPollTimer = setInterval(refreshBellBadge, n * 1000);
}

// --- BELL DROPDOWN ---

var bellListLoaded = false;
var bellListInFlight = false;

function renderBellList(items, mentions) {
  var $body = $("#bellDropdownBody");
  var html = "";
  var i;
  var n;
  var sevClass;
  var sevLabel;
  var statusLabel;
  var hasMentions = mentions && mentions.length;
  var hasItems = items && items.length;

  if (!$body.length) {
    return;
  }

  if (!hasItems && !hasMentions) {
    $body.html('<div class="text-center text-muted py-3 small"><i class="bi bi-check2-circle text-success"></i> Nothing actionable right now.</div>');
    return;
  }

  // Mentions section — rendered above actionable tickets so an @ from a
  // teammate isn't buried under unrelated escalations. Each row links
  // straight to the ticket detail.
  if (hasMentions) {
    html += '<div class="bell-section-label"><i class="bi bi-at"></i> Mentions</div>';
    for (i = 0; i < mentions.length; i++) {
      var m = mentions[i];
      var mSev = m.alert_type || "info";
      var mSevClass = "bell-row-" + mSev;
      html += '<a class="bell-row bell-row-mention ' + mSevClass + '" href="' + escapeHtml(m.url || "#") + '">';
      html += '<div class="bell-row-top">';
      html += '<span class="bell-row-alarm">';
      html += '<i class="bi bi-at"></i> ';
      html += escapeHtml(m.alarm_id || "—");
      html += "</span>";
      html += '<span class="bell-row-when small text-muted">' + escapeHtml(m.when || "") + "</span>";
      html += "</div>";
      html += '<div class="bell-row-title">' + escapeHtml(m.title || "(no title)") + "</div>";
      if (m.from_name) {
        html += '<div class="bell-row-meta"><span class="text-muted small">from ' + escapeHtml(m.from_name) + '</span></div>';
      }
      html += "</a>";
    }
    if (hasItems) {
      html += '<div class="bell-section-label"><i class="bi bi-inbox"></i> Actionable</div>';
    }
  }

  if (!hasItems) {
    $body.html(html);
    return;
  }

  for (i = 0; i < items.length; i++) {
    n = items[i];

    // Severity → left-border colour + dot. Defaults are info.
    sevClass = "bell-row-info";
    sevLabel = "INFO";
    if (n.alert_type === "major") {
      sevClass = "bell-row-major";
      sevLabel = "MAJOR";
    }
    if (n.alert_type === "critical") {
      sevClass = "bell-row-critical";
      sevLabel = "CRITICAL";
    }

    // Status pill — only render for the two states that warrant action.
    statusLabel = "";
    if (n.ticket_status === "escalated") {
      statusLabel = '<span class="bell-row-pill is-escalated"><i class="bi bi-graph-up-arrow"></i> ESCALATED</span>';
    } else if (n.alert_type === "critical" && (n.ticket_status === "open" || n.ticket_status === "in_progress")) {
      statusLabel = '<span class="bell-row-pill is-critical-open"><i class="bi bi-exclamation-octagon-fill"></i> CRITICAL OPEN</span>';
    }

    html += '<a class="bell-row ' + sevClass + '" href="' + escapeHtml(n.url || "#") + '">';
    html += '<div class="bell-row-top">';
    html += '<span class="bell-row-alarm">';
    html += '<span class="bell-row-dot" aria-hidden="true"></span>';
    html += escapeHtml(n.alarm_id || "—");
    html += "</span>";
    html += '<span class="bell-row-when small text-muted">' + escapeHtml(n.when || "") + "</span>";
    html += "</div>";
    html += '<div class="bell-row-title">' + escapeHtml(n.title || "(no title)") + "</div>";
    html += '<div class="bell-row-meta">';
    html += '<span class="bell-row-sev bell-row-sev--' + n.alert_type + '">' + sevLabel + "</span>";
    if (n.level) {
      html += '<span class="bell-row-level">L' + escapeHtml(String(n.level)) + "</span>";
    }
    if (n.state_name) {
      html += '<span class="bell-row-state">' + escapeHtml(n.state_name) + "</span>";
    }
    html += statusLabel;
    html += "</div>";
    html += "</a>";
  }

  $body.html(html);
}

function loadBellList(force) {
  var $bell;
  var url;

  if (bellListLoaded && !force) {
    return;
  }
  if (bellListInFlight) {
    return;
  }

  $bell = $("#topbarBell");
  if (!$bell.length) {
    return;
  }

  url = $bell.attr("data-recent-url");
  if (!url) {
    return;
  }

  bellListInFlight = true;

  $.ajax({
    url: url,
    type: "GET",
    dataType: "json",
    cache: false,
    success: function (response) {
      if (response && response.success && response.data) {
        renderBellList(response.data.items || [], response.data.mentions || []);
        bellListLoaded = true;
      } else {
        renderBellList([], []);
      }
    },
    error: function () {
      $("#bellDropdownBody").html('<div class="text-center text-danger py-3 small">Failed to load notifications.</div>');
    },
    complete: function () {
      bellListInFlight = false;
    },
  });
}

function initBellDropdown() {
  var bell = document.getElementById("topbarBell");

  if (!bell) {
    return;
  }

  // Bootstrap fires 'show.bs.dropdown' on the toggle element.
  bell.addEventListener("show.bs.dropdown", function () {
    loadBellList(false);
  });

  // Force-refresh the list whenever the badge is updated by other AJAX flows.
  $appDocument.on("bell:refresh", function () {
    bellListLoaded = false;
    if (bell.getAttribute("aria-expanded") === "true") {
      loadBellList(true);
    }
  });
}

// --- BULK ACTIONS ---

var bulkSelected = {}; // ticket id -> true

function getSelectedIds() {
  var ids = [];
  var key;

  for (key in bulkSelected) {
    if (bulkSelected.hasOwnProperty(key)) {
      ids.push(parseInt(key, 10));
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

  confirmAction(verb + " " + ids.length + " ticket(s)?", function () {
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

          // Reload the DataTable in-place so the user sees new statuses without losing filters.
          $table = $("#ticketsTable");
          if ($.fn.DataTable && $.fn.DataTable.isDataTable($table[0])) {
            $table.DataTable().ajax.reload(null, false);
          }

          if (typeof refreshBellBadge === "function") {
            refreshBellBadge();
          }
        } else {
          showError(getResponseMessage(response, "Bulk action failed"));
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

  // After every DataTables redraw, re-apply checked state so selections persist across pages.
  $appDocument.off("draw.dt.bulkRestore").on("draw.dt.bulkRestore", function () {
    $(".bulk-select").each(function () {
      var id = $(this).attr("data-bulk-id");
      if (id && bulkSelected[id]) {
        this.checked = true;
      }
    });
  });
}

// --- SAVED FILTERS ---

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
          showError(getResponseMessage(response, "Failed to save filter"));
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

    confirmAction("Remove this saved filter?", function () {
      $.ajax({
        url: url,
        type: "POST",
        dataType: "json",
        success: function (response) {
          if (response && response.success) {
            showSuccess(response.message || "Removed");
            // Hide the row inline so the dropdown doesn't snap shut.
            var $dropdown = $btn.closest(".saved-filters-dropdown");
            $btn.closest(".saved-filter-row").remove();

            // Keep the trigger button's count badge in sync. If the row
            // we just removed was the last one, also restore the empty-
            // state text so the dropdown doesn't end up just showing the
            // divider + Save button.
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
            showError(getResponseMessage(response, "Failed to remove filter"));
          }
        },
        error: function () {
          showError("Network error");
        },
      });
    });
  });
}

// --- TREND RANGE PICKER ---

function initTrendRangePicker() {
  $appDocument.off("click.trendRange").on("click.trendRange", ".trend-range-picker .filter-pill", function (event) {
    var $pill = $(this);
    var href = $pill.attr("href") || "";
    var match = href.match(/[?&]range=(\d+)/);
    var range;
    var titleHtml;
    var newPath;

    if (!match) {
      return;
    }
    if (!trendChart) {
      return;
    }

    event.preventDefault();
    range = parseInt(match[1], 10);

    $pill.closest(".trend-range-picker").find(".filter-pill").removeClass("is-active");
    $pill.addClass("is-active");

    titleHtml = '<i class="bi bi-graph-up text-primary"></i> Ticket Trend - Last ' + range + " days";
    $pill.closest(".chart-card").find(".chart-title h6").html(titleHtml);

    // Update the URL so the view is shareable / refreshable.
    if (window.history && window.history.pushState) {
      newPath = window.location.pathname + "?range=" + range;
      window.history.pushState({ trendRange: range }, "", newPath);
    }

    $.ajax({
      url: "dashboard/trend",
      type: "GET",
      data: { range: range },
      dataType: "json",
      cache: false,
      success: function (response) {
        if (response && response.success && response.data) {
          trendChart.data.labels = response.data.labels || [];
          trendChart.data.datasets[0].data = response.data.values || [];
          trendChart.update();
        } else {
          showError(getResponseMessage(response, "Failed to load trend"));
        }
      },
      error: function () {
        showError("Network error loading trend");
      },
    });
  });
}

// --- TICKETS AJAX FILTERS ---

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

function syncTicketsFilterUI(params) {
  var status;
  var $form;
  var $hiddenStatus;
  var count;
  var $summary;
  var $saveBtn;
  var rebuilt;
  var keysInOrder;
  var k;
  var key;
  var v;

  // Highlight active status pill.
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

  // Update filter form inputs.
  $form = $("#ticketsFilterForm");
  if ($form.length) {
    $form.find("[name='q']").val(params.q || "");
    $form.find("[name='project_id']").val(params.project_id || "");
    $form.find("[name='flow_id']").val(params.flow_id || "");
    $form.find("[name='alert_type']").val(params.alert_type || "");
    $form.find("[name='priority']").val(params.priority || "");

    // Keep the hidden status field in sync.
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

    // Re-render Select2 widgets after programmatic .val() change.
    $form.find("select").each(function () {
      if ($(this).hasClass("select2-hidden-accessible")) {
        $(this).trigger("change.select2");
      }
    });
  }

  // Update "N filters active" pill.
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

  $summary = $("#filterActiveSummary");
  if ($summary.length) {
    $("#filterActiveCount").text(count);

    if (count === 1) {
      $("#filterActiveLabel").text("filter");
    } else {
      $("#filterActiveLabel").text("filters");
    }

    if (count === 0) {
      $summary.attr("hidden", "hidden");
    } else {
      $summary.removeAttr("hidden");
    }
  }

  // Keep "Save current filter" button's data-current-qs in sync, and
  // swap between the button and the "Apply a filter to save it." hint
  // based on whether anything is actually narrowing the list right now.
  $saveBtn = $("#savedFilterAddBtn");
  if ($saveBtn.length) {
    rebuilt = [];
    keysInOrder = ["status", "q", "project_id", "flow_id", "alert_type", "priority"];

    for (k = 0; k < keysInOrder.length; k++) {
      key = keysInOrder[k];
      v = params[key] || "";
      if (v !== "" && v !== "0") {
        rebuilt.push(encodeURIComponent(key) + "=" + encodeURIComponent(v));
      }
    }

    $saveBtn.attr("data-current-qs", rebuilt.join("&"));

    // The button counts the same five "real filter" fields as the badge;
    // the leading status pill on its own doesn't unlock save unless it
    // narrows to something other than "All".
    var hasFilter = (count > 0) || ((params.status || "") !== "");
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

  syncTicketsFilterUI(params);
  $table.DataTable().ajax.reload(null, false);

  if (doPushState && window.history && window.history.pushState) {
    window.history.pushState({ ticketsUrl: url }, "", url);
  }
}

function initTicketsAjaxFilters() {
  var clickSelector;

  if (!$("#ticketsTable").length) {
    return;
  }

  clickSelector = ".filter-pills .filter-pill, .saved-filter-link, .tickets-filter-reset, .filter-active-summary .clear-link";

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

// --- @MENTION AUTOCOMPLETE ---
//
// Attaches to any textarea with `data-mentions="1"`. As the user types, when
// they hit `@` followed by ≥1 word character, a small dropdown of active
// users renders below the caret. Arrow keys navigate, Tab/Enter inserts,
// Escape closes. No external library — plain jQuery, ~80 lines.
//
// The user list is fetched once per page load from /users/active_json
// and cached client-side. Server-side parsing in ticket_action() is the
// source of truth for who actually gets notified; this UI just helps
// the operator type the right username.

var mentionUsers = null;
var mentionUsersLoading = false;
var $mentionDropdown = null;
var mentionTarget = null; // jQuery wrapped textarea currently being assisted
var mentionMatchStart = -1; // index of the `@` we're completing
var mentionFiltered = []; // filtered user list shown in the dropdown
var mentionActiveIndex = 0;

function ensureMentionUsersLoaded($ta, callback) {
  if (mentionUsers !== null) {
    callback();
    return;
  }
  if (mentionUsersLoading) {
    return;
  }
  // The textarea carries the endpoint URL via data-mention-source — same
  // pattern the bell badge / DataTables use to know their server URLs.
  var url = $ta.attr("data-mention-source");
  if (!url) {
    mentionUsers = [];
    callback();
    return;
  }
  mentionUsersLoading = true;
  $.ajax({
    url: url,
    type: "GET",
    dataType: "json",
    cache: false,
    success: function (response) {
      if (response && response.success && response.data) {
        mentionUsers = response.data;
      } else {
        mentionUsers = [];
      }
      callback();
    },
    error: function () {
      mentionUsers = [];
      callback();
    },
    complete: function () {
      mentionUsersLoading = false;
    },
  });
}

function ensureMentionDropdown() {
  if ($mentionDropdown && $mentionDropdown.length) {
    return $mentionDropdown;
  }
  $mentionDropdown = $('<div class="mention-dropdown" hidden></div>');
  $("body").append($mentionDropdown);
  return $mentionDropdown;
}

function closeMentionDropdown() {
  if ($mentionDropdown && $mentionDropdown.length) {
    $mentionDropdown.attr("hidden", "hidden").empty();
  }
  mentionTarget = null;
  mentionMatchStart = -1;
  mentionFiltered = [];
  mentionActiveIndex = 0;
}

function renderMentionDropdown($ta, items) {
  var $dd = ensureMentionDropdown();
  if (!items.length) {
    closeMentionDropdown();
    return;
  }
  var html = "";
  var i;
  for (i = 0; i < items.length; i++) {
    var cls = "mention-item";
    if (i === mentionActiveIndex) {
      cls += " is-active";
    }
    html += '<div class="' + cls + '" data-idx="' + i + '" data-user-id="' + escapeHtml(items[i].user_id) + '">';
    html += '<strong>@' + escapeHtml(items[i].user_id) + "</strong> ";
    html += '<span class="text-muted small">' + escapeHtml(items[i].name) + "</span>";
    html += "</div>";
  }
  $dd.html(html);

  // Position the dropdown just under the textarea — close enough for the
  // operator to associate it with what they're typing without needing to
  // compute exact caret coordinates (which is browser-flaky).
  var off = $ta.offset();
  var height = $ta.outerHeight();
  $dd.css({
    position: "absolute",
    top: (off.top + height + 2) + "px",
    left: off.left + "px",
    minWidth: $ta.outerWidth() + "px",
    maxHeight: "180px",
    overflowY: "auto",
    zIndex: 9999,
  }).removeAttr("hidden");
}

function findMentionTrigger($ta) {
  // Look backwards from the caret for an `@` that starts a word — anything
  // between that `@` and the caret becomes the search term.
  var el = $ta[0];
  var caret = el.selectionStart;
  if (typeof caret !== "number") {
    return null;
  }
  var text = $ta.val();
  var i = caret - 1;
  while (i >= 0) {
    var ch = text.charAt(i);
    if (ch === "@") {
      // `@` must be at start of string OR preceded by whitespace
      if (i === 0 || /\s/.test(text.charAt(i - 1))) {
        var term = text.substring(i + 1, caret);
        if (/^[a-zA-Z0-9._-]*$/.test(term)) {
          return { start: i, term: term };
        }
      }
      return null;
    }
    if (/\s/.test(ch)) {
      return null;
    }
    i--;
  }
  return null;
}

function insertMention($ta, userId) {
  if (mentionMatchStart < 0) {
    closeMentionDropdown();
    return;
  }
  var el = $ta[0];
  var caret = el.selectionStart;
  var before = $ta.val().substring(0, mentionMatchStart);
  var after = $ta.val().substring(caret);
  var insert = "@" + userId + " ";
  var newVal = before + insert + after;
  $ta.val(newVal);
  var newCaret = before.length + insert.length;
  el.setSelectionRange(newCaret, newCaret);
  closeMentionDropdown();
  $ta.focus();
}

function initMentionAutocomplete() {
  $appDocument.off("input.mentions").on("input.mentions", "textarea[data-mentions]", function () {
    var $ta = $(this);
    ensureMentionUsersLoaded($ta, function () {
      var trig = findMentionTrigger($ta);
      if (!trig) {
        closeMentionDropdown();
        return;
      }
      mentionTarget = $ta;
      mentionMatchStart = trig.start;
      var term = trig.term.toLowerCase();
      var matched = [];
      var i;
      for (i = 0; i < mentionUsers.length && matched.length < 8; i++) {
        var u = mentionUsers[i];
        if (term === "" || u.user_id.toLowerCase().indexOf(term) !== -1 || u.name.toLowerCase().indexOf(term) !== -1) {
          matched.push(u);
        }
      }
      mentionFiltered = matched;
      mentionActiveIndex = 0;
      renderMentionDropdown($ta, matched);
    });
  });

  $appDocument.off("keydown.mentions").on("keydown.mentions", "textarea[data-mentions]", function (e) {
    if (!$mentionDropdown || $mentionDropdown.attr("hidden")) {
      return;
    }
    if (e.key === "ArrowDown") {
      e.preventDefault();
      mentionActiveIndex = Math.min(mentionActiveIndex + 1, mentionFiltered.length - 1);
      renderMentionDropdown(mentionTarget, mentionFiltered);
      return;
    }
    if (e.key === "ArrowUp") {
      e.preventDefault();
      mentionActiveIndex = Math.max(mentionActiveIndex - 1, 0);
      renderMentionDropdown(mentionTarget, mentionFiltered);
      return;
    }
    if (e.key === "Enter" || e.key === "Tab") {
      if (mentionFiltered[mentionActiveIndex]) {
        e.preventDefault();
        insertMention(mentionTarget, mentionFiltered[mentionActiveIndex].user_id);
      }
      return;
    }
    if (e.key === "Escape") {
      e.preventDefault();
      closeMentionDropdown();
    }
  });

  $appDocument.off("click.mentions").on("click.mentions", ".mention-dropdown .mention-item", function () {
    var uid = $(this).attr("data-user-id");
    if (uid && mentionTarget) {
      insertMention(mentionTarget, uid);
    }
  });

  // Click anywhere outside the textarea or dropdown closes it.
  $appDocument.off("click.mentionsClose").on("click.mentionsClose", function (e) {
    if ($(e.target).closest(".mention-dropdown, textarea[data-mentions]").length === 0) {
      closeMentionDropdown();
    }
  });
}

// --- SETTINGS: SEND TEST EMAIL ---
//
// Tiny wrapper around the /settings/send_test_email POST endpoint. Wired
// once on document.ready; lives at the bottom of the file alongside the
// other admin-page handlers.
function initSendTestEmail() {
  $appDocument.off("click.sendTestEmail").on("click.sendTestEmail", "#sendTestEmailBtn", function (e) {
    e.preventDefault();
    var $btn = $(this);
    var url = $btn.attr("data-url");
    if (!url) {
      return;
    }
    $btn.attr("disabled", "disabled");
    $.ajax({
      url: url,
      type: "POST",
      dataType: "json",
      success: function (response) {
        if (response && response.success) {
          showSuccess(response.message || "Test email sent");
        } else {
          showError(getResponseMessage(response, "Test email failed"));
        }
      },
      error: function () {
        showError("Network error sending test email");
      },
      complete: function () {
        $btn.removeAttr("disabled");
      },
    });
  });
}

// One-click cache-buster for app.css / app.js. POSTs to the bump
// endpoint, updates the asset_version field in place with the new
// number, and shows a toast nudging the admin to refresh once so they
// can see the new files load.
function initBumpAssetVersion() {
  $appDocument.off("click.bumpAssetVersion").on("click.bumpAssetVersion", "#bumpAssetVersionBtn", function (e) {
    e.preventDefault();
    var $btn = $(this);
    var url = $btn.attr("data-url");
    if (!url) {
      return;
    }
    $btn.attr("disabled", "disabled");
    $.ajax({
      url: url,
      type: "POST",
      dataType: "json",
      success: function (response) {
        if (response && response.success) {
          if (response.data && response.data.value) {
            // Sync the visible input so the admin sees the new value
            // without needing to reload the Settings page.
            $("#set_asset_version").val(response.data.value);
          }
          showSuccess(response.message || "Asset version bumped");
        } else {
          showError(getResponseMessage(response, "Bump failed"));
        }
      },
      error: function () {
        showError("Network error bumping asset version");
      },
      complete: function () {
        $btn.removeAttr("disabled");
      },
    });
  });
}

// Activity Log viewer (read-only audit feed). Initialises the server-side
// DataTable, wires the filter form so filter values ride along on every
// ajax reload, and pops a Bootstrap modal when the row's meta icon is
// clicked so the admin can read the JSON details.
function initActivityLogsTable() {
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
    pageLength: getDefaultPageLength(),
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
    ],
    ajax: {
      url: ajaxUrl,
      type: "GET",
      dataSrc: "data",
      data: function (d) {
        d.f_user   = $("#filterUser").val() || "";
        d.f_module = $("#filterModule").val() || "";
        d.f_action = $("#filterAction").val() || "";
        d.f_from   = $("#filterFrom").val() || "";
        d.f_to     = $("#filterTo").val() || "";
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
  });
  registerDataTableForAdjust($table);

  $("#activityApplyBtn").on("click", function () {
    dt.ajax.reload();
  });

  // CSV export — assemble the same filter payload the table uses and
  // navigate to the streaming endpoint. Browser handles the download.
  $("#activityExportBtn").on("click", function () {
    var url = $(this).attr("data-export-url");
    if (!url) {
      return;
    }
    var params = $.param({
      f_user:   $("#filterUser").val() || "",
      f_module: $("#filterModule").val() || "",
      f_action: $("#filterAction").val() || "",
      f_from:   $("#filterFrom").val() || "",
      f_to:     $("#filterTo").val() || "",
      q:        ($(".dataTables_filter input").val() || ""),
    });
    var sep = url.indexOf("?") === -1 ? "?" : "&";
    window.location.href = url + sep + params;
  });

  $("#activityResetBtn").on("click", function () {
    $("#filterUser").val("");
    $("#filterModule").val("");
    $("#filterAction").val("");
    // Restore the From/To inputs to their server-rendered default
    // (today's date) rather than blanking them, so Reset matches the
    // initial page-load behaviour instead of switching to "show all".
    $("#filterFrom").val($("#filterFrom").attr("data-default") || "");
    $("#filterTo").val($("#filterTo").attr("data-default") || "");
    dt.ajax.reload();
  });

  // Click the info icon to see full JSON meta in a custom overlay
  // (vanilla, not a Bootstrap modal — see the view template for why).
  //
  // The overlay lives inside the page content by default, which is a
  // descendant of elements with `transform:` declarations. CSS spec says
  // any transformed ancestor creates a new containing block, which makes
  // `position: fixed` resolve against that ancestor instead of the
  // viewport — that's why the overlay was anchoring to the bottom of the
  // page. Reparenting it to <body> once on init keeps it viewport-fixed.
  var $overlayEl = $("#activityMetaOverlay");
  if ($overlayEl.length && $overlayEl.parent("body").length === 0) {
    $overlayEl.appendTo("body");
  }

  function openActivityMetaOverlay(pretty) {
    var $overlay = $("#activityMetaOverlay");
    if (!$overlay.length) {
      return;
    }
    $("#activityMetaBody").text(pretty);
    $overlay.removeAttr("hidden");
  }
  function closeActivityMetaOverlay() {
    $("#activityMetaOverlay").attr("hidden", "hidden");
  }

  $appDocument.on("click", ".activity-meta-toggle", function () {
    var raw = $(this).attr("data-meta") || "";
    var pretty = raw;
    try {
      pretty = JSON.stringify(JSON.parse(raw), null, 2);
    } catch (e) {
      // Not valid JSON — show raw.
    }
    openActivityMetaOverlay(pretty);
  });

  // X button + footer Close button both carry data-activity-meta-close.
  $appDocument.on("click", "[data-activity-meta-close]", function () {
    closeActivityMetaOverlay();
  });

  // Click on the backdrop (the overlay itself, NOT the inner dialog) closes.
  $appDocument.on("click", "#activityMetaOverlay", function (e) {
    if (e.target === this) {
      closeActivityMetaOverlay();
    }
  });

  // Escape key closes when the overlay is visible.
  $appDocument.on("keydown.activityMetaOverlay", function (e) {
    if (e.key === "Escape" && !$("#activityMetaOverlay").attr("hidden")) {
      closeActivityMetaOverlay();
    }
  });
}

// Persist sidebar scroll position across navigation so clicking a link
// near the bottom of the menu (e.g. Settings) doesn't drop the user back
// at the top on the next render. sessionStorage scopes to the current
// tab only — opening a new tab gives a fresh state.
//
// The actual scrolling container is `.sidebar .nav` (CSS sets
// overflow-y:auto there), NOT #appSidebar itself which is flex-column
// fixed-positioned and doesn't scroll. Listening on the wrong element
// is why a previous attempt silently no-op'd.
function initSidebarScrollPersist() {
  var sidebar = document.getElementById("appSidebar");
  if (!sidebar) {
    return;
  }
  var scroller = sidebar.querySelector(".nav");
  if (!scroller) {
    return;
  }
  var storageKey = "pview-sidebar-scroll";

  // Restore on next tick so the browser's own scroll restoration has
  // already finished and we don't fight it.
  setTimeout(function () {
    var saved = null;
    try {
      saved = sessionStorage.getItem(storageKey);
    } catch (e) {
      // Storage may be unavailable in some privacy modes — silently skip.
    }
    if (saved !== null) {
      var top = parseInt(saved, 10);
      if (!isNaN(top) && top > 0) {
        scroller.scrollTop = top;
      }
    }
  }, 0);

  // Save on scroll — debounced via rAF so we coalesce rapid events into
  // one write per frame.
  var pending = false;
  scroller.addEventListener("scroll", function () {
    if (pending) {
      return;
    }
    pending = true;
    window.requestAnimationFrame(function () {
      pending = false;
      try {
        sessionStorage.setItem(storageKey, String(scroller.scrollTop));
      } catch (e) {
        // ignore
      }
    });
  });

  // Save on nav-link click too — guarantees the latest position is
  // committed even if the user clicks before the scroll handler runs.
  $(scroller).on("click", "a.nav-link", function () {
    try {
      sessionStorage.setItem(storageKey, String(scroller.scrollTop));
    } catch (e) {
      // ignore
    }
  });
}
