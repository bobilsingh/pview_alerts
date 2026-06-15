// ============================================================
// 01. GLOBALS & CONFIG
// ============================================================

// Shared colour tokens — used by Chart.js and SVG utilities.
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

// Page-level jQuery handles and shared state vars.
var tatTimer = null;
var $appDocument = $(document);
var $appWindow = $(window);
var $appHtml = $("html");
var $appBody = $("body");
var APP_MOBILE_BREAKPOINT = "(max-width: 992px)";

// ============================================================
// 02. UTILITY HELPERS
// ============================================================

// localStorage wrappers — silent in private-mode / cross-origin iframes.
function getLocalPref(key) {
  try {
    var val = localStorage.getItem(key);
    console.log("[Preferences] Get pref key: " + key + " = " + val);
    return val;
  } catch (error) {
    console.error("[Preferences] Error getting key: " + key, error);
    return null;
  }
}

function saveLocalPref(key, value) {
  try {
    localStorage.setItem(key, value);
    console.log("[Preferences] Save pref key: " + key + " = " + value);
  } catch (error) {
    console.error("[Preferences] Error saving key: " + key + " = " + value, error);
  }
}

// General utility functions used across all modules.
function escapeHtml(text) {
  if (text == null) {
    return "";
  }
  return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

function getArrayFromText(value, separator) {
  if (!value) {
    return [];
  }
  return value.split(separator);
}

function getNumberArrayFromText(value) {
  var separator = value && value.indexOf("||") !== -1 ? "||" : ",";
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

function extractErrorMessage(response, fallback) {
  if (response && response.message) {
    return response.message;
  }
  if (response && response.error) {
    return response.error;
  }
  return fallback || "An error occurred";
}

// Shared AJAX response handler.
function handleResponse(response, successMessage) {
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
  showError(extractErrorMessage(response, "Request failed"));
  return false;
}

// Reads an integer from a <meta name="app-setting-*"> tag set by the PHP header.
function getSettingInt(key) {
  var $el = $("meta[name='app-setting-" + key + "']");
  if ($el.length) {
    return parseInt($el.attr("content"), 10) || 0;
  }
  return 0;
}

// Converts "1", "true", true, 1 → true; everything else → false.
function toBoolean(val) {
  if (val === true || val === 1 || val === "1" || val === "true") {
    return true;
  }
  return false;
}

// ============================================================
// 03. TOAST NOTIFICATIONS
// ============================================================

// Applies saved theme + sidebar state before the page renders (no flash).
// Default is collapsed; expanded only when the user has explicitly chosen it.
function applyUserPreferences() {
  var sidebarPref = getLocalPref("pview-sidebar");
  if (sidebarPref === "collapsed") {
    $appHtml.attr("data-sidebar", "collapsed");
  } else {
    $appHtml.attr("data-sidebar", "expanded");
  }

  var t = getLocalPref("noc-theme") || "dark";
  console.log("[Preferences] Applying preferences - Sidebar: " + (sidebarPref || "expanded") + ", Theme: " + t);
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

// ============================================================
// 27. PAGE INIT
// ============================================================

// Run immediately (before DOM ready) to prevent flash of wrong theme/sidebar.
applyUserPreferences();
setupToastr();
setupChartDefaults();

// Wire up every module after the DOM is ready.
$appDocument.ready(function () {
  // Apply DataTables global language defaults (defined in datatable.js).
  setupDataTablesDefaults();

  // Auto-dismiss flash alerts (success / danger / warning) after 30 seconds.
  var $flashAlerts = $(".alert-success, .alert-danger, .alert-warning").filter(".alert-dismissible");
  if ($flashAlerts.length) {
    setTimeout(function () {
      $flashAlerts.each(function () {
        var $a = $(this);
        if ($a.is(":visible")) {
          $a.fadeTo(500, 0, function () {
            $(this).alert("close");
          });
        }
      });
    }, 15000);
  }

  // --- Layout ---
  initThemeSwitch();
  initSidebarMenu();
  initSidebarScrollSave();
  initSearchHotkey();

  // --- Date range widget ---
  initDateRangeWidgets();

  // --- Forms & UI ---
  initFormValidation();
  initConfirmForms();
  initConfirmLinks();
  initCustomTooltips();
  initLoadingForms();
  initCharCount();
  initUnsavedFormWarning();
  initCapsLockAlert();
  initPasswordToggle();

  // --- Dropdowns ---
  initSelectFields();
  initAjaxSelectLoaders();

  // --- Data Tables ---
  initSimpleTables();
  initListTables();
  initTicketsTable();

  // --- Dashboard ---
  initDashCustomize();
  initTrendCharts();
  initSeverityCharts();
  initTatCountdowns();

  // --- Tickets ---
  initCopyButtons();
  initTicketCreatePage();
  initTicketDetailPage();
  // DEMO: initBulkActions(); — hidden
  initListReopenButtons();
  initSavedFilters();
  initTrendRangePicker();
  initTicketsAjaxFilters();

  // --- Flows ---
  initFlowVis();
  initStateSorter();
  initTransitionsDesigner();
  initMoveStateTypedForms();

  // --- Notifications ---
  initBellDropdown();
  startBellPoll();

  // --- Users ---
  initUserIdLiveCheck();

  // --- Cron Panel ---
  initCronRunsTable();

  // --- Activity Logs ---
  initAuditLogTable();
  initAnalyticsTab();

  // --- Mentions ---
  initMentionAutocomplete();

  // --- Auto logout on idle ---
  initAutoLogout();
});

// ============================================================
// 04. CONFIRM DIALOGS
// ============================================================

function confirmDialog(message, callback) {
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

    confirmDialog(message, function () {
      if (method === "post") {
        var $form = $("<form></form>").attr({ method: "post", action: href }).css({ display: "none" });
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

    confirmDialog(message, function () {
      form.submit();
    });
  });
}

// ============================================================
// 05. TOOLTIPS — custom positioned tooltip (replaces browser default)
// ============================================================
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

    var left = rect.left + scrollLeft + rect.width / 2 - tooltipWidth / 2;
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
      left: left + "px",
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

// ============================================================
// 07. DROPDOWNS & SELECT — Select2, AJAX-linked child selects
// ============================================================

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

// ============================================================
// 13. TAT COUNTDOWN TIMERS — SLA expiry display, auto-tick
// ============================================================
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

function initDashCustomize() {
  var $toggle = $("#dashCustomizeToggle");
  var $panel = $("#dashCustomizePanel");
  var $close = $("#dashCustomizeClose");
  var $cancel = $("#dashCustomizeCancel");

  if ($toggle.length === 0) {
    return;
  }

  $toggle.on("click", function () {
    $panel.removeClass("d-none");
    $toggle.attr("aria-expanded", "true");
  });

  $close.on("click", function () {
    $panel.addClass("d-none");
    $toggle.attr("aria-expanded", "false");
  });

  $cancel.on("click", function () {
    $panel.addClass("d-none");
    $toggle.attr("aria-expanded", "false");
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

// ============================================================
// 14. DASHBOARD — Charts (trend line + severity donut)
// ============================================================
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

// ============================================================
// 08. FORM HELPERS — AJAX submit, file upload, post buttons
// ============================================================
function submitNormalForm($form, options) {
  var url = $form.data("url");
  var data;

  if (!url) {
    return;
  }

  console.log("[Form AJAX] Submitting normal form targeting URL: " + url);
  data = $form.serialize();

  $.ajax({
    url: url,
    type: "POST",
    data: data,
    dataType: "json",
    success: function (response) {
      if (handleResponse(response, options.successMessage)) {
        console.log("[Form AJAX] Form submitted successfully: " + url);
        if (typeof options.onSuccess === "function") {
          options.onSuccess(response, $form);
        } else if (options.reloadOnSuccess) {
          window.location.reload();
        }
      } else {
        console.warn("[Form AJAX] Form submission returned failure status: " + url);
      }
    },
    error: function (xhr) {
      console.error("[Form AJAX] Network error during form submission for URL: " + url, xhr);
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

  console.log("[Form AJAX] Submitting form with attachments targeting URL: " + url);
  formData = new FormData(form);

  $.ajax({
    url: url,
    type: "POST",
    data: formData,
    processData: false,
    contentType: false,
    success: function (response) {
      if (handleResponse(response, options.successMessage)) {
        console.log("[Form AJAX] File form submitted successfully: " + url);
        if (typeof options.onSuccess === "function") {
          options.onSuccess(response, $form);
        } else if (options.reloadOnSuccess) {
          window.location.reload();
        }
      } else {
        console.warn("[Form AJAX] File form submission returned failure status: " + url);
      }
    },
    error: function (xhr) {
      console.error("[Form AJAX] Network error during file form submission for URL: " + url, xhr);
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

    if (!validateForm($(this))) {
      return;
    }

    if (options.isFile) {
      submitFileForm(this, options);
    } else {
      submitNormalForm($(this), options);
    }
  });
}

function bindPostButton(selector, onSuccess) {
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

    confirmDialog(label + "?", function () {
      console.log("[Post Button Action] Initiating action: " + label + " targeting: " + url);
      $.ajax({
        url: url,
        type: "POST",
        data: {},
        dataType: "json",
        success: function (response) {
          if (handleResponse(response, "Action completed")) {
            console.log("[Post Button Action] Action completed successfully: " + label);
            if (typeof onSuccess === "function") {
              onSuccess(response, $clickedButton);
            } else {
              window.location.reload();
            }
          }
        },
        error: function () {
          console.error("[Post Button Action] Network error occurred during action: " + label);
          showError("Network error");
        },
      });
    });
  });
}

function escapeHtml(string) {
  return String(string)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function appendTimelineItem(commentText, iconHtml) {
  var $feed = $(".activity-feed");
  if (!$feed.length) {
    return;
  }

  // Remove "No activity yet" if present
  $feed.find(".text-muted.small").remove();

  // Format current date time as Y-m-d H:i:s
  var now = new Date();
  var year = now.getFullYear();
  var month = ("0" + (now.getMonth() + 1)).slice(-2);
  var day = ("0" + now.getDate()).slice(-2);
  var hours = ("0" + now.getHours()).slice(-2);
  var minutes = ("0" + now.getMinutes()).slice(-2);
  var seconds = ("0" + now.getSeconds()).slice(-2);
  var dateTimeStr = year + "-" + month + "-" + day + " " + hours + ":" + minutes + ":" + seconds;

  // Read logged-in user name from the DOM (.topbar-user .name)
  var performerName = $(".topbar-user .name").text().trim() || "You";

  var html = '<li class="activity-item new-activity-item" style="display:none;">';
  html += '  <div class="activity-icon">' + iconHtml + '</div>';
  html += '  <div class="activity-body">';
  html += '    <div class="activity-meta">';
  html += '      <strong>' + escapeHtml(performerName) + '</strong> ';
  html += '      <span class="text-muted">' + dateTimeStr + '</span>';
  html += '    </div>';
  html += '    <div class="activity-text">' + commentText + '</div>';
  html += '  </div>';
  html += '</li>';

  var $item = $(html);
  $feed.prepend($item);
  $item.slideDown(200);
}

function refreshTicketDetailsAndActions(onDone) {
  console.log("[Ticket Refresh] Initiating background content refresh...");
  $.get(window.location.href, function (html) {
    console.log("[Ticket Refresh] Successfully fetched fresh HTML page content.");
    var $html = $(html);

    // 1. Replace the header badges
    var $newHeaderBadges = $html.find("#ticketHeaderBadges");
    if ($newHeaderBadges.length) {
      $("#ticketHeaderBadges").replaceWith($newHeaderBadges);
      console.log("[Ticket Refresh] Swapped ticket header badges.");
    }

    // 2. Replace the details card
    var $newDetails = $html.find("#ticketDetailsCard");
    if ($newDetails.length) {
      $("#ticketDetailsCard").replaceWith($newDetails);
      console.log("[Ticket Refresh] Swapped ticket details card.");
    }

    // 3. Preserve active tab and replace the take action card
    var activeTabId = $("#ticketActionCard .nav-tabs .nav-link.active").attr("href");
    var $newAction = $html.find("#ticketActionCard");
    if ($newAction.length) {
      $("#ticketActionCard").replaceWith($newAction);
      console.log("[Ticket Refresh] Swapped take action card.");
      if (activeTabId) {
        console.log("[Ticket Refresh] Restoring active tab focus: " + activeTabId);
        var $newActionCard = $("#ticketActionCard");
        $newActionCard.find(".nav-tabs .nav-link").removeClass("active");
        $newActionCard.find(".tab-content .tab-pane").removeClass("show active");

        var $tabLink = $newActionCard.find('.nav-tabs .nav-link[href="' + activeTabId + '"]');
        if ($tabLink.length) {
          $tabLink.addClass("active");
          $newActionCard.find(activeTabId).addClass("show active");
        } else {
          $newActionCard.find(".nav-tabs .nav-link").first().addClass("active");
          $newActionCard.find(".tab-content .tab-pane").first().addClass("show active");
        }
      }
    }

    // 4. Replace timeline container
    var $newTimeline = $html.find("#timelineList");
    if ($newTimeline.length) {
      $("#timelineList").replaceWith($newTimeline);
      console.log("[Ticket Refresh] Swapped activity timeline list.");
    }

    // 5. Replace and re-initialize flow visualizer
    var $newFlow = $html.find(".flow-widget");
    if ($newFlow.length) {
      $(".flow-widget").replaceWith($newFlow);
      console.log("[Ticket Refresh] Swapped workflow visualizer widget.");
      initFlowVis();
    }

    // 6. Re-run necessary initializers on replaced elements
    console.log("[Ticket Refresh] Re-initializing dynamic form elements and counters.");
    initSelectFields();
    initTatCountdowns();

    // Re-bind details page forms and buttons
    bindPostForm("#commentForm", {
      successMessage: "Comment added",
      errorMessage: "Network error",
      reloadOnSuccess: false,
      onSuccess: function (response, $form) {
        console.log("[Comment Form] Submit success, adding comment to timeline and refreshing details...");
        var $commentArea = $form.find("textarea[name='comment']");
        var commentVal = $commentArea.val() || "";
        appendTimelineItem(escapeHtml(commentVal), '<i class="bi bi-chat-left-text text-primary"></i>');
        $commentArea.val("");
        refreshTicketDetailsAndActions();
      }
    });

    bindPostForm("#assignForm", {
      successMessage: "Assigned",
      errorMessage: "Network error",
      reloadOnSuccess: false,
      onSuccess: function (response, $form) {
        console.log("[Assign Form] Submit success, updating assignee display and refreshing details...");
        var $select = $form.find("select[name='user_id']");
        var selectedText = $select.find("option:selected").text();
        var name = selectedText.split(" - ")[0];

        $("#assigneeValue").text(name);
        appendTimelineItem("Assigned ticket to " + escapeHtml(name), '<i class="bi bi-person-check text-primary"></i>');
        $select.val("");
        refreshTicketDetailsAndActions();
      }
    });

    bindPostForm("#attachForm", {
      successMessage: "File attached",
      errorMessage: "Upload failed",
      reloadOnSuccess: true,
      isFile: true,
    });

    bindPostButton("#resolveBtn", function () {
      console.log("[Action Button] Resolve action completed successfully. Refreshing UI...");
      refreshTicketDetailsAndActions();
    });
    bindPostButton("#closeBtn", function () {
      console.log("[Action Button] Close action completed successfully. Refreshing UI...");
      refreshTicketDetailsAndActions();
    });
    bindPostButton("#reopenBtn", function () {
      console.log("[Action Button] Reopen action completed successfully. Refreshing UI...");
      refreshTicketDetailsAndActions();
    });

    initPriorityInline();
    initEditableFields();

    console.log("[Ticket Refresh] Background content refresh complete.");

    if (onDone) {
      onDone();
    }
  });
}

// ============================================================
// 18. TICKET DETAIL — Actions, inline editing, copy alarm ID
// ============================================================
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
          var prioVal = $field.val() || "";
          var prioLabel = prioVal.charAt(0).toUpperCase() + prioVal.slice(1);
          appendTimelineItem("Priority changed to " + escapeHtml(prioLabel), '<i class="bi bi-tag text-warning"></i>');
        } else {
          showError(extractErrorMessage(response, "Failed to update priority"));
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
        showSuccess(extractErrorMessage(response, "Saved"));
        $element.text(newValue || getEmptyEditableText(fieldName));
      } else {
        showError(extractErrorMessage(response, "Failed to save"));
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

function initTicketCreatePage() {
  var $flowSelect = $("#flowSelect");
  var $assigneeSelect = $("#assigneeSelect");

  if (!$flowSelect.length) {
    return;
  }

  // When flow is cleared, reset assignee back to placeholder.
  $flowSelect.on("change.ticketCreate", function () {
    if (!$(this).val()) {
      $assigneeSelect.html('<option value="">Select flow first</option>');
      $assigneeSelect.prop("disabled", true);
    }
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

function initTicketDetailPage() {
  bindPostForm("#commentForm", {
    successMessage: "Comment added",
    errorMessage: "Network error",
    reloadOnSuccess: false,
    onSuccess: function (response, $form) {
      var $commentArea = $form.find("textarea[name='comment']");
      var commentVal = $commentArea.val() || "";
      appendTimelineItem(escapeHtml(commentVal), '<i class="bi bi-chat-left-text text-primary"></i>');
      $commentArea.val("");
      refreshTicketDetailsAndActions();
    }
  });

  bindPostForm("#assignForm", {
    successMessage: "Assigned",
    errorMessage: "Network error",
    reloadOnSuccess: false,
    onSuccess: function (response, $form) {
      var $select = $form.find("select[name='user_id']");
      var selectedText = $select.find("option:selected").text();
      var name = selectedText.split(" - ")[0];

      $("#assigneeValue").text(name);
      appendTimelineItem("Assigned ticket to " + escapeHtml(name), '<i class="bi bi-person-check text-primary"></i>');
      $select.val("");
      refreshTicketDetailsAndActions();
    }
  });

  bindPostForm("#attachForm", {
    successMessage: "File attached",
    errorMessage: "Upload failed",
    reloadOnSuccess: true,
    isFile: true,
  });

  // Single-target forward button: sends target_state_id + transition_type via AJAX.
  $appDocument.on("click", "#moveStateBtn", function () {
    var $btn = $(this);
    var url = $btn.data("url");
    var targetId = $btn.data("targetId");
    var transType = $btn.data("transitionType") || "forward";
    if ($btn.prop("disabled") || !url || !targetId) {
      return;
    }
    var label = $.trim($btn.text());
    if (!label) {
      label = "Move state";
    }
    confirmDialog(label + "?", function () {
      console.log("[Move State Button] User confirmed forward transition to state ID: " + targetId);
      $btn.prop("disabled", true);
      $.ajax({
        url: url,
        type: "POST",
        data: { target_state_id: targetId, transition_type: transType, reason: "" },
        dataType: "json",
        success: function (res) {
          $btn.prop("disabled", false);
          if (res && res.success) {
            console.log("[Move State Button] State moved successfully. Server response: " + (res.message || "State moved"));
            showSuccess(res.message || "State moved");
            refreshTicketDetailsAndActions();
          } else {
            console.error("[Move State Button] Failed to move state: " + (res && res.message ? res.message : "unknown error"));
            showError(res && res.message ? res.message : "Failed to move state.");
          }
        },
        error: function () {
          $btn.prop("disabled", false);
          console.error("[Move State Button] Network error occurred during state transition.");
          showError("Network error.");
        },
      });
    });
  });

  bindPostButton("#resolveBtn", function () {
    console.log("[Resolve Button] Resolve action completed. Refreshing UI...");
    refreshTicketDetailsAndActions();
  });
  bindPostButton("#closeBtn", function () {
    console.log("[Close Button] Close action completed. Refreshing UI...");
    refreshTicketDetailsAndActions();
  });
  bindPostButton("#reopenBtn", function () {
    console.log("[Reopen Button] Reopen action completed. Refreshing UI...");
    refreshTicketDetailsAndActions();
  });

  initPriorityInline();
  initEditableFields();
}

// ============================================================
// 19. FLOWS — VIS NETWORK DIAGRAM
//     Node colours, edges, zoom, canvas animation, click-to-edit
// ============================================================
var flowNetworks = {};
var flowNetMeta = {};
var flowNetworkSeq = 0;
var flowAnimRAF = null;
var flowAnimLast = 0;

function getVisNodeStyle(status) {
  var map = {
    initial: { bg: "#10b981", border: "#059669", fg: "#ffffff" },
    process: { bg: "#7c5cf2", border: "#6d4ee6", fg: "#ffffff" },
    final: { bg: "#64748b", border: "#475569", fg: "#ffffff" },
    passed: { bg: "#10b981", border: "#059669", fg: "#ffffff" },
    current: { bg: "#0ea5e9", border: "#0284c7", fg: "#ffffff" },
    pending: { bg: "#cbd5e1", border: "#94a3b8", fg: "#334155" },
  };
  return map[status] || map.process;
}

function buildVisNodes(rawNodes) {
  var i, n, status, style, isCurrent, shadow;
  var result = [];
  for (i = 0; i < rawNodes.length; i++) {
    n = rawNodes[i];
    status = n.status || n.type || "process";
    style = getVisNodeStyle(status);
    isCurrent = status === "current";
    if (isCurrent) {
      shadow = { enabled: true, color: "rgba(14,165,233,0.55)", size: 20, x: 0, y: 0 };
    } else {
      shadow = { enabled: true, color: "rgba(2,6,23,0.35)", size: 8, x: 0, y: 3 };
    }
    result.push({
      id: n.id,
      // Wrap label in <b> so vis-network's html multi-font renders it bold.
      // escapeHtml() handles any < or > in state names before the <b> wrap.
      label: "<b>" + escapeHtml(n.label) + "</b>",
      shape: "box",
      shapeProperties: { borderRadius: 10 },
      color: {
        background: style.bg,
        border: style.border,
        highlight: { background: style.bg, border: "#e2e8f0" },
        hover: { background: style.bg, border: "#e2e8f0" },
      },
      borderWidth: isCurrent ? 3 : 1.5,
      borderWidthSelected: isCurrent ? 3 : 2,
      font: { face: "Inter, system-ui, sans-serif", size: 15, color: style.fg, multi: "html" },
      widthConstraint: { minimum: 110 },
      heightConstraint: { minimum: 46 },
      margin: { top: 11, right: 16, bottom: 11, left: 16 },
      shadow: shadow,
    });
  }
  return result;
}

// Pull the live node id + edge list out of the raw data so the animation
// loop knows what to pulse and where to send the flowing dots.
function setFlowNetMeta(netId, rawData) {
  var i,
    currentId = null,
    edges = [];
  var nodes = rawData.nodes || [];
  var rawEdges = rawData.edges || [];
  for (i = 0; i < nodes.length; i++) {
    if (nodes[i].status === "current") {
      currentId = nodes[i].id;
    }
  }
  for (i = 0; i < rawEdges.length; i++) {
    // Only animate flowing dots along forward edges; skip backward arcs.
    if ((rawEdges[i].transition_type || "forward") !== "backward") {
      edges.push({ from: rawEdges[i].from, to: rawEdges[i].to });
    }
  }
  flowNetMeta[netId] = {
    currentId: currentId,
    edges: edges,
    animate: false,
  };
}

var TRANSITION_STYLES = {
  forward: { color: "rgba(100,116,139,0.55)", dash: false, roundness: 0.45, type: "cubicBezier" },
  backward: { color: "rgba(239,68,68,0.9)", dash: true, roundness: 0.4, type: "curvedCCW" },
};

function buildVisEdges(rawEdges) {
  var i,
    edge,
    style,
    transType,
    smooth,
    result = [];
  for (i = 0; i < rawEdges.length; i++) {
    edge = rawEdges[i];
    transType = edge.transition_type || "forward";
    style = TRANSITION_STYLES[transType] || TRANSITION_STYLES.forward;
    // Forward edges use forced-horizontal bezier to align with the LR layout.
    // Backward edges drop forceDirection so the arc hugs the forward path
    // rather than sweeping far below the canvas.
    if (transType === "forward") {
      smooth = { enabled: true, type: style.type, forceDirection: "horizontal", roundness: style.roundness };
    } else {
      smooth = { enabled: true, type: style.type, roundness: style.roundness };
    }
    result.push({
      from: edge.from,
      to: edge.to,
      title: transType.charAt(0).toUpperCase() + transType.slice(1) + " transition",
      arrows: { to: { enabled: true, scaleFactor: 0.55, type: "arrow" } },
      smooth: smooth,
      color: { color: style.color, highlight: "#0ea5e9", hover: "#0ea5e9" },
      dashes: style.dash,
      width: 3,
      selectionWidth: 1,
    });
  }
  return result;
}

function getVisOptions() {
  return {
    layout: {
      hierarchical: {
        enabled: true,
        direction: "LR",
        sortMethod: "directed",
        shakeTowards: "roots",
        levelSeparation: 230,
        nodeSpacing: 110,
        treeSpacing: 130,
        blockShifting: true,
        edgeMinimization: true,
        parentCentralization: true,
      },
    },
    physics: { enabled: false },
    interaction: {
      dragNodes: true,
      dragView: true,
      zoomView: true,
      hover: true,
      zoomSpeed: 0.4,
      minZoomLevel: 0.25,
      maxZoomLevel: 2.0,
      navigationButtons: false,
      keyboard: false,
      selectConnectedEdges: false,
    },
    nodes: {
      shape: "box",
      shapeProperties: { borderRadius: 10 },
      borderWidth: 1.5,
      font: { face: "Inter, system-ui, sans-serif", size: 15, color: "#ffffff", multi: "html" },
      margin: { top: 11, right: 16, bottom: 11, left: 16 },
    },
    edges: {
      arrows: { to: { enabled: true, scaleFactor: 0.55 } },
      color: { color: "rgba(100,116,139,0.5)", highlight: "#0ea5e9", hover: "#0ea5e9" },
      width: 1.5,
      smooth: { enabled: true, type: "cubicBezier", forceDirection: "horizontal", roundness: 0.45 },
    },
  };
}

function getWidgetNetworkId($widget) {
  var id = $widget.data("flow-net-id");
  if (!id) {
    flowNetworkSeq++;
    id = "fnet-" + flowNetworkSeq;
    $widget.data("flow-net-id", id);
  }
  return id;
}

function updateZoomPctDisplay($widget) {
  var netId = $widget.data("flow-net-id");
  var net = netId ? flowNetworks[netId] : null;
  var pct;
  if (!net) {
    return;
  }
  pct = Math.round(net.getScale() * 100);
  $widget.data("flow-zoom-pct", pct);
  $widget.find("[data-flow-zoom-pct]").text(pct + "%");
}

function renderFlowWidget($widget) {
  var $canvas = $widget.find("[data-flow-canvas]").first();
  var $container, $dataScript, rawData, netId, net;
  var allEdgesRaw, fwdEdgesRaw, bwdEdgesRaw, nodes, edges;

  if (!$canvas.length) {
    return;
  }

  $container = $canvas.find(".flow-vis-container").first();
  $dataScript = $canvas.find(".flow-vis-data").first();

  if (!$container.length || !$dataScript.length) {
    return;
  }

  try {
    rawData = JSON.parse($dataScript.text());
  } catch (e) {
    return;
  }

  if (!rawData || !rawData.nodes) {
    return;
  }

  // Split edges by transition_type: forward edges define the LR hierarchy;
  // backward edges are overlaid as curved arcs after the layout is locked.
  allEdgesRaw = rawData.edges || [];
  fwdEdgesRaw = allEdgesRaw.filter(function (e) {
    return (e.transition_type || "forward") !== "backward";
  });
  bwdEdgesRaw = allEdgesRaw.filter(function (e) {
    return e.transition_type === "backward";
  });

  nodes = new vis.DataSet(buildVisNodes(rawData.nodes));
  edges = new vis.DataSet(buildVisEdges(fwdEdgesRaw));

  netId = getWidgetNetworkId($widget);
  if (flowNetworks[netId]) {
    flowNetworks[netId].destroy();
    delete flowNetworks[netId];
  }

  net = new vis.Network($container[0], { nodes: nodes, edges: edges }, getVisOptions());
  flowNetworks[netId] = net;
  setFlowNetMeta(netId, rawData);

  $widget.data("flow-zoom-pct", 100);
  $widget.find("[data-flow-zoom-pct]").text("100%");

  net.once("afterDrawing", function () {
    // The hierarchical LR layout is now stable with forward-only edges.
    // Before touching anything else:
    //   1. storePositions() writes the computed x,y back into the DataSet.
    //   2. Disable hierarchical layout so that adding backward edges does NOT
    //      trigger a re-run of the algorithm (which would collapse back to TB).
    //   3. Add backward edges — vis-network now treats them as free curved
    //      overlays on top of the locked LR positions.
    net.storePositions();
    net.setOptions({ layout: { hierarchical: { enabled: false } }, physics: { enabled: false } });
    if (bwdEdgesRaw.length > 0) {
      edges.add(buildVisEdges(bwdEdgesRaw));
    }
    // fit() only accounts for node bounding boxes; backward arcs curve below
    // the nodes (curvedCW). Extra padding keeps those arcs within the viewport.
    net.fit({ animation: false, padding: bwdEdgesRaw.length > 0 ? 90 : 20 });
    var fitScale = Math.max(net.getScale(), 0.1);
    net.setOptions({ interaction: { minZoomLevel: fitScale } });
    $widget.data("flow-min-scale", fitScale);
    updateZoomPctDisplay($widget);
  });

  net.on("afterDrawing", function (ctx) {
    drawFlowAnimation(netId, ctx);
  });

  // Hard-enforce the zoom floor on every zoom event.
  // When the user tries to zoom out past the fit level, call net.fit()
  // instead of moveTo() so the graph stays centred — moveTo() sets scale
  // only and lets the position drift, which pushes the tree to one side.
  net.on("zoom", function (params) {
    var minScale = $widget.data("flow-min-scale") || 0.25;
    if (params.scale < minScale) {
      net.fit({ animation: false });
      updateZoomPctDisplay($widget);
      return;
    }
    updateZoomPctDisplay($widget);
  });

  // Node interaction: clicking a node on the designer preview scrolls to
  // and highlights the matching state row in the editor list below.
  // Only active on the states designer page (where #stateList exists).
  net.on("click", function (params) {
    var clickedId, $row;
    if (!params.nodes || !params.nodes.length) {
      return;
    }
    // params.nodes[0] is a number; coerce to string for the attribute selector.
    clickedId = String(params.nodes[0]);
    $row = $("#stateList .state-item[data-id='" + clickedId + "']");
    if (!$row.length) {
      return;
    }
    // Remove any existing focus, add to clicked row, scroll it into view.
    $(".state-item--focus").removeClass("state-item--focus");
    $row.addClass("state-item--focus");
    $row[0].scrollIntoView({ behavior: "smooth", block: "center" });
    setTimeout(function () {
      $row.removeClass("state-item--focus");
    }, 2000);
  });

  // Show a pointer cursor when hovering a node so the click is discoverable.
  net.on("hoverNode", function () {
    $container.css("cursor", "pointer");
  });
  net.on("blurNode", function () {
    $container.css("cursor", "grab");
  });

  ensureFlowAnim();
}

function fitFlowWidget($widget) {
  var netId = $widget.data("flow-net-id");
  var net = netId ? flowNetworks[netId] : null;
  if (!net) {
    return;
  }
  net.fit({ animation: { duration: 250, easingFunction: "easeInOutQuad" } });
  setTimeout(function () {
    var fitScale = Math.max(net.getScale(), 0.1);
    net.setOptions({ interaction: { minZoomLevel: fitScale } });
    $widget.data("flow-min-scale", fitScale);
    updateZoomPctDisplay($widget);
  }, 300);
}

function zoomFlowWidget($widget, direction) {
  var netId = $widget.data("flow-net-id");
  var net = netId ? flowNetworks[netId] : null;
  var scale, newScale;
  if (!net) {
    return;
  }
  scale = net.getScale();
  newScale = direction > 0 ? scale * 1.15 : scale / 1.15;
  var minScale = $widget.data("flow-min-scale") || 0.25;
  if (newScale < minScale) {
    newScale = minScale;
  }
  if (newScale > 2.0) {
    newScale = 2.0;
  }
  net.moveTo({ scale: newScale, animation: { duration: 150, easingFunction: "linear" } });
  setTimeout(function () {
    updateZoomPctDisplay($widget);
  }, 200);
}

function buildDesignerVisData($list) {
  var items = [],
    allIds = {},
    edges = [],
    i,
    item,
    hasParentLinks;

  $list.find(".state-item").each(function () {
    var $item = $(this);
    var id = parseInt($item.attr("data-id"), 10);
    var parentId, name, type;
    if (!id) {
      return;
    }
    parentId = parseInt($item.attr("data-parent-id") || "0", 10);
    name = $.trim($item.find("strong").first().text());
    type = $item.find(".badge.bg-success").length ? "initial" : $item.find(".badge.bg-dark").length ? "final" : "process";
    items.push({ id: id, label: name, type: type, parentId: parentId });
    allIds[id] = true;
  });

  if (items.length === 0) {
    return null;
  }

  hasParentLinks = false;
  for (i = 0; i < items.length; i++) {
    item = items[i];
    if (item.parentId > 0 && allIds[item.parentId]) {
      edges.push({ from: item.parentId, to: item.id });
      hasParentLinks = true;
    }
  }
  if (!hasParentLinks) {
    for (i = 0; i < items.length - 1; i++) {
      edges.push({ from: items[i].id, to: items[i + 1].id });
    }
  }

  return { nodes: items, edges: edges };
}

function refreshFlowPreview($list) {
  var previewSelector = $list.attr("data-preview-target");
  var $target = $(previewSelector);
  var $widget, $dataScript, newData, netId, net, nodes, edges;

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

  newData = buildDesignerVisData($list);
  if (!newData) {
    return;
  }

  $dataScript = $widget.find(".flow-vis-data").first();
  if ($dataScript.length) {
    $dataScript.text(JSON.stringify(newData));
  }

  netId = $widget.data("flow-net-id");
  net = netId ? flowNetworks[netId] : null;

  if (net) {
    nodes = new vis.DataSet(buildVisNodes(newData.nodes));
    edges = new vis.DataSet(buildVisEdges(newData.edges || []));
    net.setOptions({ layout: { hierarchical: { enabled: true, direction: "LR", sortMethod: "directed" } } });
    net.setData({ nodes: nodes, edges: edges });
    net.once("afterDrawing", function () {
      net.storePositions();
      net.setOptions({ layout: { hierarchical: { enabled: false } }, physics: { enabled: false } });
    });
    setFlowNetMeta(netId, newData);
    net.fit({ animation: { duration: 200, easingFunction: "easeInOutQuad" } });
    ensureFlowAnim();
  } else {
    renderFlowWidget($widget);
  }
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
      zoomFlowWidget($widget, 1);
    });

    $widget.on("click", "[data-flow-zoom-out]", function () {
      zoomFlowWidget($widget, -1);
    });

    $widget.on("click", "[data-flow-fullscreen]", function () {
      var elem = $widget[0];
      if (document.fullscreenElement) {
        document.exitFullscreen();
      } else {
        if (elem.requestFullscreen) {
          elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
          elem.webkitRequestFullscreen();
        }
      }
    });
  });

  $appDocument.off("fullscreenchange.flowWidget").on("fullscreenchange.flowWidget", function () {
    $(".flow-widget").each(function () {
      var $widget = $(this);
      var netId, net;

      if (document.fullscreenElement === $widget[0]) {
        $widget.addClass("is-fullscreen");
      } else {
        $widget.removeClass("is-fullscreen");
      }

      netId = $widget.data("flow-net-id");
      net = netId ? flowNetworks[netId] : null;
      if (net) {
        setTimeout(function () {
          net.redraw();
          net.fit({ animation: false });
          updateZoomPctDisplay($widget);
        }, 150);
      }
    });
  });
}

// --- Flow canvas animation (flowing edge dots + pulsing live node) -------
// vis-network renders to <canvas>, so motion cannot come from CSS. We hook
// each network's afterDrawing event (its ctx is already transformed into
// network coordinates) and drive a single rAF loop that redraws every
// active widget so the dots and pulse animate smoothly.

function flowBezierPoint(p0, p1, p2, p3, t) {
  var mt = 1 - t;
  var a = mt * mt * mt;
  var b = 3 * mt * mt * t;
  var c = 3 * mt * t * t;
  var d = t * t * t;
  return {
    x: a * p0.x + b * p1.x + c * p2.x + d * p3.x,
    y: a * p0.y + b * p1.y + c * p2.y + d * p3.y,
  };
}

function drawFlowAnimation(netId, ctx) {
  var net = flowNetworks[netId];
  var meta = flowNetMeta[netId];
  var now, positions, i, e, p0, p3, cp1, cp2, base, k, t, pt, box, cx, cy, rx, ry, pulse, alpha;

  if (!net || !meta || !meta.animate) {
    return;
  }

  now = window.performance && performance.now ? performance.now() : Date.now();
  positions = net.getPositions();

  // Flowing dots that ride each edge from parent -> child. The control
  // points mirror vis-network's horizontal cubicBezier so the dots track
  // the rendered line exactly, even after a node is dragged.
  ctx.save();
  for (i = 0; i < meta.edges.length; i++) {
    e = meta.edges[i];
    p0 = positions[e.from];
    p3 = positions[e.to];
    if (!p0 || !p3) {
      continue;
    }
    cp1 = { x: (p0.x + p3.x) / 2, y: p0.y };
    cp2 = { x: (p0.x + p3.x) / 2, y: p3.y };
    base = (now / 1600 + i * 0.13) % 1;
    for (k = 0; k < 2; k++) {
      t = (base + k * 0.5) % 1;
      t = 0.14 + t * 0.72;
      pt = flowBezierPoint(p0, cp1, cp2, p3, t);
      ctx.beginPath();
      ctx.arc(pt.x, pt.y, 2.6, 0, Math.PI * 2);
      ctx.fillStyle = "rgba(56,189,248,0.95)";
      ctx.shadowColor = "rgba(14,165,233,0.85)";
      ctx.shadowBlur = 8;
      ctx.fill();
    }
  }
  ctx.restore();

  // Pulsing ring around the live (current) node on the ticket view.
  if (meta.currentId !== null && positions[meta.currentId]) {
    box = net.getBoundingBox(meta.currentId);
    if (box) {
      cx = (box.left + box.right) / 2;
      cy = (box.top + box.bottom) / 2;
      rx = (box.right - box.left) / 2;
      ry = (box.bottom - box.top) / 2;
      pulse = (Math.sin(now / 420) + 1) / 2;
      alpha = 0.55 - pulse * 0.4;
      ctx.save();
      ctx.beginPath();
      ctx.strokeStyle = "rgba(14,165,233," + alpha.toFixed(3) + ")";
      ctx.lineWidth = 2.5;
      ctx.ellipse(cx, cy, rx + 7 + pulse * 9, ry + 7 + pulse * 9, 0, 0, Math.PI * 2);
      ctx.stroke();
      ctx.restore();
    }
  }
}

function flowAnimTick(ts) {
  var id,
    meta,
    anyAnimated = false,
    doRedraw;

  if (!ts) {
    ts = 0;
  }

  // Throttle redraws to ~30fps — smooth enough, far lighter than 60fps.
  doRedraw = ts - flowAnimLast >= 32;
  if (doRedraw) {
    flowAnimLast = ts;
  }

  for (id in flowNetworks) {
    if (flowNetworks.hasOwnProperty(id)) {
      meta = flowNetMeta[id];
      if (meta && meta.animate) {
        anyAnimated = true;
        if (doRedraw) {
          flowNetworks[id].redraw();
        }
      }
    }
  }

  if (anyAnimated && !document.hidden) {
    flowAnimRAF = window.requestAnimationFrame(flowAnimTick);
  } else {
    flowAnimRAF = null;
  }
}

function ensureFlowAnim() {
  if (flowAnimRAF === null && window.requestAnimationFrame) {
    flowAnimRAF = window.requestAnimationFrame(flowAnimTick);
  }
}

function initFlowVis() {
  if (typeof vis === "undefined" || !vis.Network) {
    return;
  }
  initFlowWidgets();
  $(".flow-widget").each(function () {
    renderFlowWidget($(this));
  });

  // Resume the animation loop when the tab becomes visible again — the
  // loop parks itself (flowAnimRAF = null) whenever the page is hidden.
  $appDocument.off("visibilitychange.flowAnim").on("visibilitychange.flowAnim", function () {
    if (!document.hidden) {
      ensureFlowAnim();
    }
  });
}

// ============================================================
// 20. FLOWS — STATE DESIGNER — drag-sort, order save, live preview
// ============================================================

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
        showError(extractErrorMessage(response, "Failed to save order"));
      }
    },
    error: function () {
      showError("Network error while saving order");
    },
  });
}

// ============================================================
// TRANSITIONS DESIGNER — add/delete transitions in flow states page
// ============================================================

function initTransitionsDesigner() {
  var $form = $("#addTransitionForm");
  if (!$form.length) {
    return;
  }

  var flowId = $form.attr("data-flow-id");
  var saveUrl = $form.attr("data-url");

  $form.on("submit", function (e) {
    e.preventDefault();
    var fromId = $form.find("[name='from_state_id']").val();
    var toId = $form.find("[name='to_state_id']").val();
    var type = $form.find("[name='transition_type']").val();
    var reqCmt = $form.find("[name='requires_comment']").is(":checked") ? 1 : 0;

    if (!fromId || !toId) {
      showError("Select both From and To states.");
      return;
    }
    if (fromId === toId) {
      showError("From and To must be different states.");
      return;
    }

    $.ajax({
      url: saveUrl,
      type: "POST",
      data: { flow_id: flowId, from_state_id: fromId, to_state_id: toId, transition_type: type, requires_comment: reqCmt },
      dataType: "json",
      success: function (res) {
        if (res && res.success) {
          showSuccess("Transition saved. Reloading…");
          setTimeout(function () {
            window.location.reload();
          }, 800);
        } else {
          showError(res && res.message ? res.message : "Failed to save transition.");
        }
      },
      error: function () {
        showError("Network error saving transition.");
      },
    });
  });

  $appDocument.on("click", ".delete-transition-btn", function () {
    var $btn = $(this);
    var url = $btn.attr("data-url");
    confirmDialog("Delete this transition?", function () {
      $.ajax({
        url: url,
        type: "POST",
        dataType: "json",
        success: function (res) {
          if (res && res.success) {
            $btn.closest("tr").fadeOut(300, function () {
              $(this).remove();
            });
            showSuccess("Transition removed.");
          } else {
            showError(res && res.message ? res.message : "Failed to delete.");
          }
        },
        error: function () {
          showError("Network error.");
        },
      });
    });
  });
}

// ============================================================
// MOVE-STATE TYPED FORMS — show/hide reason field, handle submit
// ============================================================

function initMoveStateTypedForms() {
  // Show/hide reason field for forward forms based on requires_comment flag.
  // Backward forms always show the reason field (rendered visible in PHP).
  $appDocument.on("change", ".move-state-typed-form select[name='target_state_id']", function () {
    var $select = $(this);
    var $form = $select.closest(".move-state-typed-form");
    var $wrap = $form.find(".transition-reason-wrap");
    var type = $form.find("input[name='transition_type']").val();
    var req = $select.find("option:selected").attr("data-requires-comment");
    if (type === "backward" || req === "1") {
      $wrap.show();
      $wrap.find("input[name='reason']").attr("required", true);
    } else {
      $wrap.hide();
      $wrap.find("input[name='reason']").removeAttr("required").val("");
    }
  });

  $appDocument.on("submit", ".move-state-typed-form", function (e) {
    e.preventDefault();
    var $form = $(this);

    if (!validateForm($form)) {
      return;
    }

    var url = $form.attr("data-url");
    var targetId = $form.find("[name='target_state_id']").val();
    var transType = $form.find("[name='transition_type']").val();
    var reason = $form.find("[name='reason']").val() || "";

    var stateName = $form.find("select[name='target_state_id'] option:selected").text().trim();
    var confirmMsg = "Move to " + stateName + "?";
    if (transType === "backward") {
      confirmMsg = "Send back to " + stateName + "?";
    }

    confirmDialog(confirmMsg, function () {
      console.log("[Move State Typed Form] User confirmed " + transType + " transition to state: " + stateName + " (ID: " + targetId + "), reason length: " + reason.length);
      var $btn = $form.find("button[type='submit']");
      $btn.prop("disabled", true);

      $.ajax({
        url: url,
        type: "POST",
        data: { target_state_id: targetId, transition_type: transType, reason: reason },
        dataType: "json",
        success: function (res) {
          $btn.prop("disabled", false);
          if (res && res.success) {
            console.log("[Move State Typed Form] Transition succeeded. Server message: " + (res.message || "Done"));
            showSuccess(res.message || "Done");
            refreshTicketDetailsAndActions();
          } else {
            console.error("[Move State Typed Form] Transition failed: " + (res && res.message ? res.message : "unknown error"));
            showError(res && res.message ? res.message : "Failed to move state.");
          }
        },
        error: function () {
          $btn.prop("disabled", false);
          console.error("[Move State Typed Form] Network error occurred during state transition.");
          showError("Network error.");
        },
      });
    });
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

// ============================================================
// 12. FORMS — VALIDATION UI (password, loading, counters, guards)
// ============================================================
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

// Shows a spinner on the submit button so the user knows the form is saving.
function initLoadingForms() {
  $appDocument.off("submit.loadingForms").on("submit.loadingForms", "form[data-loading-form], .modal form", function (event) {
    var $form = $(this);

    if (!validateForm($form)) {
      event.preventDefault();
      return;
    }

    var $btn = $form.find("button[type=submit]");
    var $icon;
    var originalClass;

    if (!$btn.length) {
      return;
    }
    if ($btn.data("loading")) {
      event.preventDefault();
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
      $("form[data-loading-form] button[type=submit], .modal form button[type=submit]").each(function () {
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

// ============================================================
// 23. USERS — Username availability live check
// ============================================================
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

// ============================================================
// 09. LAYOUT — THEME SWITCH
// ============================================================
function initThemeSwitch() {
  var $toggle = $("#themeToggle");
  if (!$toggle.length) {
    return;
  }

  $toggle.off("click.themeToggle").on("click.themeToggle", function (e) {
    e.preventDefault();
    var currentTheme = $appHtml.attr("data-theme") || "dark";
    var newTheme = currentTheme === "dark" ? "light" : "dark";

    console.log("[Theme Toggle] Switch clicked. Current: " + currentTheme + ", target: " + newTheme);

    // Update HTML attribute
    $appHtml.attr("data-theme", newTheme);

    // Persist in localStorage
    saveLocalPref("noc-theme", newTheme);

    // Persist in cookie (expires in 1 year)
    document.cookie = "theme=" + newTheme + ";path=/;max-age=31536000;SameSite=Lax";

    // Redraw charts if present
    if (trendChart) {
      console.log("[Theme Toggle] Redrawing trend chart grid lines for: " + newTheme + " theme.");
      var isDark = newTheme === "dark";
      trendChart.options.scales.y.grid.color = getTrendGridColor(isDark);
      trendChart.options.scales.y.grid.lineWidth = getTrendGridLineWidth(isDark);
      trendChart.update();
    }

    // Persist in user profile via AJAX POST
    var updateUrl = $toggle.attr("data-update-url");
    if (updateUrl) {
      console.log("[Theme Toggle] Syncing theme selection to database URL: " + updateUrl);
      $.ajax({
        url: updateUrl,
        type: "POST",
        data: { theme: newTheme },
        dataType: "json",
        success: function (res) {
          if (res && res.success) {
            console.log("[Theme Toggle] Database sync succeeded.");
          } else {
            console.warn("[Theme Toggle] Database sync failed: " + (res && res.message ? res.message : "unknown"));
          }
        },
        error: function (xhr) {
          console.error("[Theme Toggle] Database sync failed due to network error.", xhr);
        }
      });
    }
  });
}

// ============================================================
// 10. LAYOUT — SIDEBAR — collapse, mobile drawer, scroll persist
// ============================================================
function initSidebarMenu() {
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
    console.log("[Sidebar Toggle] Collapsing/Expanding desktop sidebar. Current: " + current + ", next: " + next);
    applyDesktopState(next);
    saveLocalPref("pview-sidebar", next);
  }

  // Apply the saved state on init so aria-label is correct immediately.
  if (!isMobile()) {
    if (getLocalPref("pview-sidebar") === "collapsed") {
      applyDesktopState("collapsed");
    } else {
      applyDesktopState("expanded");
    }
  }

  $toggle.off("click.sidebarToggle").on("click.sidebarToggle", function (e) {
    e.preventDefault();
    console.log("[Sidebar Toggle] Toggle clicked. Mobile view: " + isMobile());
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
      if (getLocalPref("pview-sidebar") === "collapsed") {
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

// ============================================================
// 11. LAYOUT — SEARCH HOTKEY (press / to focus search)
// ============================================================

function initSearchHotkey() {
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

// Character counter — shows "N / max" below any input with data-char-counter.

function initCharCount() {
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

// Warns before navigating away from a form that has unsaved changes.

function initUnsavedFormWarning() {
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

// Shows a warning under password fields when Caps Lock is on.

function initCapsLockAlert() {
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

// ============================================================
// 21. NOTIFICATIONS — BELL BADGE & LIVE POLL
// ============================================================

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

function playAlertBeep() {
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

function sendPushNotification(total, counts) {
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
    setTimeout(function () {
      try {
        n.close();
      } catch (e) {}
    }, 8000);
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
  console.log("[Bell Poll] Fetching live notification count...");

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

        console.log("[Bell Poll] Count update: " + total + " total alarms (baseline: " + bellLastSeenTotal + ")");

        // Only fire cue + notification when the number actually went UP.
        // First load just records the baseline silently.
        if (bellLastSeenTotal !== null && total > bellLastSeenTotal) {
          var audioOn = readMetaSetting("live_audio_enabled", "1") === "1";
          var notifyOn = readMetaSetting("live_browser_notify", "1") === "1";
          console.log("[Bell Poll] New alarms detected! Audio: " + audioOn + ", Push Notify: " + notifyOn);
          if (audioOn) {
            playAlertBeep();
          }
          if (notifyOn) {
            sendPushNotification(total, data);
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
    error: function (xhr) {
      console.error("[Bell Poll] Network error fetching notification badge counts.", xhr);
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
function startBellPoll() {
  if (!$("#topbarBell").length) {
    return;
  }
  var n = parseInt(readMetaSetting("live_poll_seconds", "15"), 10);
  if (isNaN(n) || n <= 0) {
    return;
  }
  if (n < 5) {
    n = 5; // safety floor — don't hammer the DB
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

// ============================================================
// 22. NOTIFICATIONS — BELL DROPDOWN (list of actionable tickets)
// ============================================================

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
        html += '<div class="bell-row-meta"><span class="text-muted small">from ' + escapeHtml(m.from_name) + "</span></div>";
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

// ============================================================
// 14. DASHBOARD — TREND RANGE PICKER
// ============================================================

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
          showError(extractErrorMessage(response, "Failed to load trend"));
        }
      },
      error: function () {
        showError("Network error loading trend");
      },
    });
  });
}

// ============================================================
// 26. MENTIONS — @mention autocomplete in comment textarea
// ============================================================
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
    html += "<strong>@" + escapeHtml(items[i].user_id) + "</strong> ";
    html += '<span class="text-muted small">' + escapeHtml(items[i].name) + "</span>";
    html += "</div>";
  }
  $dd.html(html);

  // Position the dropdown just under the textarea — close enough for the
  // operator to associate it with what they're typing without needing to
  // compute exact caret coordinates (which is browser-flaky).
  var off = $ta.offset();
  var height = $ta.outerHeight();
  $dd
    .css({
      position: "absolute",
      top: off.top + height + 2 + "px",
      left: off.left + "px",
      minWidth: $ta.outerWidth() + "px",
      maxHeight: "180px",
      overflowY: "auto",
      zIndex: 9999,
    })
    .removeAttr("hidden");
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

// ============================================================
// 27. DATE RANGE WIDGET — global reusable date-range picker
// ============================================================
//
// Usage: drop <div class="date-range-widget" data-date-range> into any
// filter form. Call initDateRangeWidgets() once on page load (already
// wired in page init). Use getDateRange($widget) to read {from, to}.

function drwDateStr(d) {
  var y = d.getFullYear();
  var m = ("0" + (d.getMonth() + 1)).slice(-2);
  var day = ("0" + d.getDate()).slice(-2);
  return y + "-" + m + "-" + day;
}

function setDateRangePreset($widget, preset) {
  var today = new Date();
  var from = new Date();
  var to = new Date();

  $widget.find(".drw-preset").removeClass("active");
  $widget.find('.drw-preset[data-preset="' + preset + '"]').addClass("active");

  switch (preset) {
    case "today":
      break;
    case "yesterday":
      from.setDate(from.getDate() - 1);
      to.setDate(to.getDate() - 1);
      break;
    case "7d":
      from.setDate(from.getDate() - 6);
      break;
    case "30d":
      from.setDate(from.getDate() - 29);
      break;
    case "month":
      from = new Date(today.getFullYear(), today.getMonth(), 1);
      break;
    case "all":
      $widget.find("[data-date-range-from]").val("");
      $widget.find("[data-date-range-to]").val("");
      return;
    default:
      return;
  }

  $widget.find("[data-date-range-from]").val(drwDateStr(from));
  $widget.find("[data-date-range-to]").val(drwDateStr(to));
}

// Returns {from, to} strings from the nearest [data-date-range] ancestor
// or from the supplied widget element.
function getDateRange($widget) {
  if (!$widget || !$widget.length) {
    $widget = $("[data-date-range]").first();
  }
  return {
    from: $widget.find("[data-date-range-from]").val() || "",
    to: $widget.find("[data-date-range-to]").val() || "",
  };
}

function initDateRangeWidgets() {
  $("[data-date-range]").each(function () {
    var $widget = $(this);

    // Apply the active preset to ensure the date inputs start at the right values.
    var $active = $widget.find(".drw-preset.active").first();
    if ($active.length) {
      setDateRangePreset($widget, $active.data("preset"));
    }

    // Preset button click.
    $widget.find(".drw-preset").on("click", function () {
      setDateRangePreset($widget, $(this).data("preset"));
    });

    // Manual edit clears the active preset marker and validates the range.
    $widget.find("[data-date-range-from], [data-date-range-to]").on("change", function () {
      $widget.find(".drw-preset").removeClass("active");
      var from = $widget.find("[data-date-range-from]").val();
      var to = $widget.find("[data-date-range-to]").val();
      var $err = $widget.find(".drw-error");
      if (from && to && from > to) {
        $err.text("From date cannot be after To date.").show();
      } else {
        $err.hide();
      }
    });
  });
}

// ============================================================
// AUTO LOGOUT — idle detection + countdown warning
// ============================================================

function initAutoLogout() {
  var timeoutMins = parseInt($('meta[name="app-setting-session_idle_timeout_minutes"]').attr("content") || "0", 10);
  if (isNaN(timeoutMins) || timeoutMins <= 0) {
    return;
  }

  var timeoutMs = timeoutMins * 60 * 1000;
  // Warn 2 minutes before logout, or 20% of the timeout if it is very short.
  var warnLeadMs = Math.min(120000, Math.max(30000, timeoutMs * 0.2));
  var warnMs = timeoutMs - warnLeadMs;

  var logoutTimer = null;
  var warnTimer = null;
  var countdownInt = null;
  var warnOpen = false;

  function doLogout() {
    // POST to /logout reusing the CSRF token from the logout form in the sidebar.
    var $logoutForm = $('form[action*="logout"]').first();
    var $csrfInput = $logoutForm.find('input[type="hidden"]').first();
    var $f = $('<form method="post" style="display:none">').attr("action", $logoutForm.attr("action"));
    if ($csrfInput.length) {
      $f.append($('<input type="hidden">').attr("name", $csrfInput.attr("name")).val($csrfInput.val()));
    }
    $("body").append($f);
    $f[0].submit();
  }

  function showWarning() {
    if (warnOpen) {
      return;
    }
    warnOpen = true;
    var remaining = Math.round(warnLeadMs / 1000);

    if (typeof Swal === "undefined") {
      // Fallback: no SweetAlert2 — just logout immediately.
      doLogout();
      return;
    }

    Swal.fire({
      title: "Session expiring",
      html: 'You will be logged out in <strong id="idleCountdown">' + remaining + "</strong> seconds due to inactivity.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Stay logged in",
      cancelButtonText: "Logout now",
      allowOutsideClick: false,
      allowEscapeKey: false,
      confirmButtonColor: "#0ea5e9",
      cancelButtonColor: "#ef4444",
    }).then(function (result) {
      clearInterval(countdownInt);
      warnOpen = false;
      if (result.isConfirmed) {
        resetTimers();
      } else {
        doLogout();
      }
    });

    countdownInt = setInterval(function () {
      remaining--;
      var $el = $("#idleCountdown");
      if ($el.length) {
        $el.text(remaining);
      }
      if (remaining <= 0) {
        clearInterval(countdownInt);
      }
    }, 1000);
  }

  function resetTimers() {
    clearTimeout(logoutTimer);
    clearTimeout(warnTimer);
    clearInterval(countdownInt);

    if (warnOpen) {
      Swal.close();
      warnOpen = false;
    }

    warnTimer = setTimeout(showWarning, warnMs);
    logoutTimer = setTimeout(doLogout, timeoutMs);
  }

  // Treat any user interaction as activity.
  $(document).on("mousemove keydown mousedown touchstart scroll click", function () {
    if (!warnOpen) {
      resetTimers();
    }
  });

  // Start the timers.
  resetTimers();
}

// ============================================================
// Persists sidebar scroll via sessionStorage. Listens on .sidebar .nav
// (the overflow-y:auto container), not #appSidebar which is fixed and doesn't scroll.
function initSidebarScrollSave() {
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

// ============================================================
// 28. INLINE FORM VALIDATION
// ============================================================

function markInvalid($field, message) {
  $field.addClass("is-invalid").removeClass("is-valid");
  var $feedback = $field.next(".invalid-feedback");
  if (!$feedback.length) {
    $feedback = $('<div class="invalid-feedback"></div>');
    $field.after($feedback);
  }
  $feedback.text(message);
}

function markValid($field) {
  $field.removeClass("is-invalid").addClass("is-valid");
  $field.next(".invalid-feedback").remove();
}

function validateField($field) {
  var val = $.trim($field.val());
  var type = ($field.attr("type") || "text").toLowerCase();
  var tag = $field.prop("tagName").toLowerCase();
  var minLen = parseInt($field.attr("minlength") || "0", 10);
  var customMsg = $field.data("error-msg") || "";
  var error = "";

  if (type === "file") {
    if (!$field[0].files || $field[0].files.length === 0) {
      error = customMsg || "Please select a file";
    }
  } else if (tag === "select") {
    if (!val) {
      error = customMsg || "Please select an option";
    }
  } else if (type === "email") {
    if (!val) {
      error = customMsg || "This field is required";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
      error = customMsg || "Enter a valid email address";
    }
  } else {
    if (!val) {
      error = customMsg || "This field is required";
    } else if (minLen > 0 && val.length < minLen) {
      error = customMsg || "Must be at least " + minLen + " characters";
    }
  }

  if (error) {
    markInvalid($field, error);
    return false;
  }
  markValid($field);
  return true;
}

function validateForm($form) {
  var valid = true;
  $form.find("[required]").each(function () {
    if (!validateField($(this))) {
      valid = false;
    }
  });
  if (!valid) {
    var $first = $form.find(".is-invalid").first();
    if ($first.length) {
      $first[0].scrollIntoView({ behavior: "smooth", block: "center" });
      $first.focus();
    }
  }
  return valid;
}

function initFormValidation() {
  // Live feedback on blur — only after the user has touched the field
  $appDocument.on("blur.validation", "form [required]", function () {
    var $field = $(this);
    if ($field.hasClass("is-invalid") || $field.hasClass("is-valid") || $.trim($field.val()) !== "") {
      validateField($field);
    }
  });

  // Clear error as the user types/selects a valid value
  $appDocument.on("input.validation change.validation", "form [required].is-invalid", function () {
    validateField($(this));
  });
}
