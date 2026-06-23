// Initializes the global date range filter dropdown and calendars.
function initGlobalDateFilter() {
	var $store = $("#globalDateStateStore");
	if (!$store.length) {
		return;
	}

	var currentPreset = $store.attr("data-preset") || "7d";
	var currentStart = $store.attr("data-start") || "";
	var currentEnd = $store.attr("data-end") || "";
	var updateUrl = $store.attr("data-update-url") || "";
	var resetUrl = $store.attr("data-reset-url") || "";

	var tempPreset = currentPreset;
	var tempStart = currentStart;
	var tempEnd = currentEnd;

	var originalLabel = $("#globalDateRangeLabel").text();
	var isApplied = false;

	// Formats a YYYY-MM-DD date string into a display format: dd-M-yyyy (e.g. 11-Jun-2026).
	var formatDateLabel = function (dateStr) {
		if (!dateStr) {
			return "";
		}
		var parts = dateStr.split("-");
		var yyyy = parts[0];
		var mm = parseInt(parts[1], 10) - 1;
		var dd = parseInt(parts[2], 10);
		var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
		return String(dd).padStart(2, "0") + "-" + months[mm] + "-" + yyyy;
	};

	// Updates the topbar preview label in real-time as the user selects/hovers.
	var updateLiveLabelPreview = function () {
		if (tempPreset !== "custom") {
			var $activePreset = $(".picker-presets .preset-btn.active");
			if ($activePreset.length) {
				$("#globalDateRangeLabel").text($activePreset.text().trim());
			}
		} else {
			if (tempStart && tempEnd) {
				if (tempStart === tempEnd) {
					$("#globalDateRangeLabel").text(formatDateLabel(tempStart));
				} else {
					$("#globalDateRangeLabel").text(formatDateLabel(tempStart) + " - " + formatDateLabel(tempEnd));
				}
			} else if (tempStart) {
				$("#globalDateRangeLabel").text(formatDateLabel(tempStart));
			} else {
				$("#globalDateRangeLabel").text("Custom Range");
			}
		}
	};

	// Binds parent dropdown events to track and restore original label text.
	var $dropdownParent = $("#globalDateRangeToggle").parent();
	$dropdownParent.off("show.bs.dropdown.preview").on("show.bs.dropdown.preview", function () {
		originalLabel = $("#globalDateRangeLabel").text();
		isApplied = false;
	});
	$dropdownParent.off("hidden.bs.dropdown.preview").on("hidden.bs.dropdown.preview", function () {
		if (!isApplied) {
			$("#globalDateRangeLabel").text(originalLabel);
		}
	});

	var initialDate = new Date();
	if (tempStart) {
		initialDate = new Date(tempStart);
	}
	var visibleLeftYear = initialDate.getFullYear();
	var visibleLeftMonth = initialDate.getMonth();

	// Formats a Date object as a YYYY-MM-DD string.
	var formatDateStr = function (d) {
		var yyyy = d.getFullYear();
		var mm = String(d.getMonth() + 1).padStart(2, "0");
		var dd = String(d.getDate()).padStart(2, "0");
		return yyyy + "-" + mm + "-" + dd;
	};

	var todayStr = formatDateStr(new Date());

	var monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

	// Computes the start and end dates for a given preset identifier.
	var getPresetDates = function (preset) {
		var start, end;
		var today = new Date();
		end = formatDateStr(today);

		if (preset === "today") {
			start = end;
		} else if (preset === "yesterday") {
			var yesterday = new Date();
			yesterday.setDate(today.getDate() - 1);
			start = formatDateStr(yesterday);
			end = start;
		} else if (preset === "7d") {
			var d7 = new Date();
			d7.setDate(today.getDate() - 6);
			start = formatDateStr(d7);
		} else if (preset === "30d") {
			var d30 = new Date();
			d30.setDate(today.getDate() - 29);
			start = formatDateStr(d30);
		} else if (preset === "90d") {
			var d90 = new Date();
			d90.setDate(today.getDate() - 89);
			start = formatDateStr(d90);
		} else if (preset === "this_month") {
			var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
			start = formatDateStr(firstDay);
			end = formatDateStr(today);
		} else if (preset === "last_month") {
			var firstDayLast = new Date(today.getFullYear(), today.getMonth() - 1, 1);
			var lastDayLast = new Date(today.getFullYear(), today.getMonth(), 0);
			start = formatDateStr(firstDayLast);
			end = formatDateStr(lastDayLast);
		}
		return { start: start, end: end };
	};

	// Renders the side-by-side left and right monthly calendars.
	var renderCalendars = function () {
		var leftYear = visibleLeftYear;
		var leftMonth = visibleLeftMonth;
		var leftPanel = $(".calendar-panel.left-calendar");
		leftPanel.find(".month-year-label").text(monthNames[leftMonth] + " " + leftYear);

		var rightYear = leftYear;
		var rightMonth = leftMonth + 1;
		if (rightMonth > 11) {
			rightMonth = 0;
			rightYear += 1;
		}
		var rightPanel = $(".calendar-panel.right-calendar");
		rightPanel.find(".month-year-label").text(monthNames[rightMonth] + " " + rightYear);

		var todayDate = new Date();
		var todayYear = todayDate.getFullYear();
		var todayMonth = todayDate.getMonth();
		if (leftYear > todayYear || (leftYear === todayYear && leftMonth >= todayMonth)) {
			rightPanel.find(".next-month").css("visibility", "hidden");
		} else {
			rightPanel.find(".next-month").css("visibility", "visible");
		}

		renderSingleCalendar(leftPanel, leftYear, leftMonth);
		renderSingleCalendar(rightPanel, rightYear, rightMonth);
	};

	// Renders a single month's grid layout inside a panel.
	var renderSingleCalendar = function (panel, year, month) {
		var $daysContainer = panel.find(".calendar-days").empty();

		var firstDayOfWeek = new Date(year, month, 1).getDay();
		var currentMonthDays = new Date(year, month + 1, 0).getDate();
		var prevMonthDays = new Date(year, month, 0).getDate();

		for (var i = firstDayOfWeek - 1; i >= 0; i--) {
			var dayNum = prevMonthDays - i;
			var siblingYear = year;
			var siblingMonth = month - 1;
			if (siblingMonth < 0) {
				siblingMonth = 11;
				siblingYear -= 1;
			}
			var dateStr = siblingYear + "-" + String(siblingMonth + 1).padStart(2, "0") + "-" + String(dayNum).padStart(2, "0");
			appendDayCell($daysContainer, dayNum, dateStr, true);
		}

		for (var d = 1; d <= currentMonthDays; d++) {
			var dateStr = year + "-" + String(month + 1).padStart(2, "0") + "-" + String(d).padStart(2, "0");
			appendDayCell($daysContainer, d, dateStr, false);
		}

		var totalCells = firstDayOfWeek + currentMonthDays;
		var remainingCells = 42 - totalCells;
		for (var n = 1; n <= remainingCells; n++) {
			var siblingYear = year;
			var siblingMonth = month + 1;
			if (siblingMonth > 11) {
				siblingMonth = 0;
				siblingYear += 1;
			}
			var dateStr = siblingYear + "-" + String(siblingMonth + 1).padStart(2, "0") + "-" + String(n).padStart(2, "0");
			appendDayCell($daysContainer, n, dateStr, true);
		}
	};

	// Appends a single day's cell DOM element with selection classes to the container.
	var appendDayCell = function ($container, label, dateStr, isSibling) {
		var $cell = $("<span></span>").addClass("cal-day").text(label).attr("data-date", dateStr);

		if (isSibling) {
			$cell.addClass("is-sibling-month");
		}

		if (dateStr === todayStr) {
			$cell.addClass("is-today");
		}

		if (dateStr > todayStr) {
			$cell.addClass("is-disabled");
		}

		if (tempStart && dateStr === tempStart) {
			$cell.addClass("is-start");
		}
		if (tempEnd && dateStr === tempEnd) {
			$cell.addClass("is-end");
		}
		if (tempStart && tempEnd && dateStr >= tempStart && dateStr <= tempEnd) {
			$cell.addClass("is-in-range");
		}

		$container.append($cell);
	};

	$(".picker-presets")
		.off("click.globalDateFilter", ".preset-btn")
		.on("click.globalDateFilter", ".preset-btn", function () {
			var preset = $(this).attr("data-preset");
			$(".preset-btn").removeClass("active");
			$(this).addClass("active");

			tempPreset = preset;
			if (preset !== "custom") {
				var bounds = getPresetDates(preset);
				tempStart = bounds.start;
				tempEnd = bounds.end;

				if (tempStart) {
					var startD = new Date(tempStart);
					visibleLeftYear = startD.getFullYear();
					visibleLeftMonth = startD.getMonth();
				}
			} else {
				tempStart = "";
				tempEnd = "";
			}
			renderCalendars();
			updateLiveLabelPreview();
		});

	$(".calendars-wrapper")
		.off("click.globalDateFilter", ".cal-day")
		.on("click.globalDateFilter", ".cal-day", function () {
			var clickedDate = $(this).attr("data-date");

			tempPreset = "custom";
			$(".preset-btn").removeClass("active");
			$(".preset-btn[data-preset='custom']").addClass("active");

			if (!tempStart || (tempStart && tempEnd) || clickedDate < tempStart) {
				tempStart = clickedDate;
				tempEnd = "";
			} else {
				tempEnd = clickedDate;
			}
			renderCalendars();
			updateLiveLabelPreview();
		});

	$(".calendars-wrapper")
		.off("mouseenter.globalDateFilter", ".cal-day")
		.on("mouseenter.globalDateFilter", ".cal-day", function () {
			if (tempStart && !tempEnd) {
				var hoverDate = $(this).attr("data-date");
				if (hoverDate >= tempStart) {
					$(".cal-day").each(function () {
						var date = $(this).attr("data-date");
						if (date > tempStart && date < hoverDate) {
							$(this).addClass("is-in-range");
						} else if (date === hoverDate) {
							$(this).addClass("is-end");
						} else {
							if (date !== tempStart) {
								$(this).removeClass("is-in-range is-end");
							}
						}
					});

					// Live update the preview label during hover
					if (hoverDate === tempStart) {
						$("#globalDateRangeLabel").text(formatDateLabel(tempStart));
					} else {
						$("#globalDateRangeLabel").text(formatDateLabel(tempStart) + " - " + formatDateLabel(hoverDate));
					}
				}
			}
		});

	$(".calendars-wrapper")
		.off("mouseleave.globalDateFilter")
		.on("mouseleave.globalDateFilter", function () {
			if (tempStart && !tempEnd) {
				renderCalendars();
				$("#globalDateRangeLabel").text(formatDateLabel(tempStart));
			}
		});

	$(".left-calendar")
		.off("click.globalDateFilter", ".prev-month")
		.on("click.globalDateFilter", ".prev-month", function () {
			visibleLeftMonth -= 1;
			if (visibleLeftMonth < 0) {
				visibleLeftMonth = 11;
				visibleLeftYear -= 1;
			}
			renderCalendars();
		});

	$(".right-calendar")
		.off("click.globalDateFilter", ".next-month")
		.on("click.globalDateFilter", ".next-month", function () {
			visibleLeftMonth += 1;
			if (visibleLeftMonth > 11) {
				visibleLeftMonth = 0;
				visibleLeftYear += 1;
			}
			renderCalendars();
		});

	$("#globalDateCancelBtn")
		.off("click.globalDateFilter")
		.on("click.globalDateFilter", function () {
			$("#globalDateRangeToggle").dropdown("hide");
		});

	$("#globalDateResetBtn")
		.off("click.globalDateFilter")
		.on("click.globalDateFilter", function () {
			isApplied = true;
			var $btn = $(this);
			$btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Reset');

			var data = {};
			$.post(resetUrl, data, function (res) {
				if (res && res.success) {
					/* START PJAX DYNAMIC RELOAD */
					reloadPageContentDynamic();
					/* END PJAX DYNAMIC RELOAD */
				} else {
					$btn.prop("disabled", false).html("Reset");
					toastr.error("Failed to reset date range.");
				}
			}).fail(function () {
				$btn.prop("disabled", false).html("Reset");
				toastr.error("An error occurred. Please try again.");
			});
		});

	$("#globalDateApplyBtn")
		.off("click.globalDateFilter")
		.on("click.globalDateFilter", function () {
			if (!tempStart) {
				toastr.warning("Please select a valid date range.");
				return;
			}
			if (!tempEnd) {
				tempEnd = tempStart;
			}

			isApplied = true;
			var $btn = $(this);
			$btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Apply');

			var data = {
				preset: tempPreset,
				start: tempStart,
				end: tempEnd,
			};
			$.post(updateUrl, data, function (res) {
				if (res && res.success) {
					/* START PJAX DYNAMIC RELOAD */
					reloadPageContentDynamic();
					/* END PJAX DYNAMIC RELOAD */
				} else {
					$btn.prop("disabled", false).html("Apply");
					toastr.error("Failed to update date range.");
				}
			}).fail(function () {
				$btn.prop("disabled", false).html("Apply");
				toastr.error("An error occurred. Please try again.");
			});
		});

	renderCalendars();
}

/* START PJAX DYNAMIC RELOAD HELPER */
// Reloads the main content and date dropdown dynamically using the existing spinner loader style.
function reloadPageContentDynamic() {
	$("#globalDateRangeToggle").dropdown("hide");

	$.ajax({
		url: window.location.href,
		type: "GET",
		dataType: "html",
		cache: false,
		success: function (html) {
			var $html = $("<div></div>").append($.parseHTML(html, document, true));
			var $newDateDropdown = $html.find(".global-date-dropdown").first();
			var $newMainContent = $html.find("#mainContent").first();

			if ($newDateDropdown.length) {
				$(".global-date-dropdown").html($newDateDropdown.html());
			}

			if ($newMainContent.length) {
				$("#mainContent").html($newMainContent.html());
			}

			if (typeof initDynamicContent === "function") {
				initDynamicContent();
			} else {
				initGlobalDateFilter();
			}
		},
		error: function () {
			if (typeof toastr !== "undefined") {
				toastr.error("Failed to reload page content.");
			}
			window.location.reload();
		},
	});
}
/* END PJAX DYNAMIC RELOAD HELPER */
