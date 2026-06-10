document.addEventListener("DOMContentLoaded", function () {
  // 1. Client-side Logo Preview and Validation
  var logoInput = document.getElementById("set_app_logo");
  var logoPreview = document.getElementById("logoPreview");
  var logoDefault = document.getElementById("logoPreviewDefault");

  if (logoInput) {
    logoInput.addEventListener("change", function (e) {
      var file = e.target.files[0];
      if (file) {
        // Size validation: 2MB
        if (file.size > 2 * 1024 * 1024) {
          alert("Logo file size exceeds the 2MB limit.");
          logoInput.value = "";
          return;
        }

        // MIME validation
        var allowedTypes = ["image/png", "image/jpeg", "image/jpg", "image/svg+xml", "image/webp"];
        if (allowedTypes.indexOf(file.type) === -1) {
          alert("Invalid file type. Only PNG, JPG, SVG, and WEBP are supported.");
          logoInput.value = "";
          return;
        }

        var reader = new FileReader();
        reader.onload = function (evt) {
          if (logoPreview) {
            logoPreview.src = evt.target.result;
            logoPreview.classList.remove("d-none");
          }
          if (logoDefault) {
            logoDefault.classList.add("d-none");
          }
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // 2. Client-side Favicon Preview and Validation
  var favInput = document.getElementById("set_app_favicon");
  var favPreview = document.getElementById("faviconPreview");
  var favDefault = document.getElementById("faviconPreviewDefault");

  if (favInput) {
    favInput.addEventListener("change", function (e) {
      var file = e.target.files[0];
      if (file) {
        // Size validation: 500KB
        if (file.size > 500 * 1024) {
          alert("Favicon file size exceeds the 500KB limit.");
          favInput.value = "";
          return;
        }

        // MIME validation
        var allowedTypes = ["image/png", "image/jpeg", "image/jpg", "image/x-icon", "image/vnd.microsoft.icon", "image/svg+xml", "image/webp"];
        if (allowedTypes.indexOf(file.type) === -1) {
          alert("Invalid file type. Only PNG, JPG, ICO, SVG, and WEBP are supported.");
          favInput.value = "";
          return;
        }

        var reader = new FileReader();
        reader.onload = function (evt) {
          if (favPreview) {
            favPreview.src = evt.target.result;
            favPreview.classList.remove("d-none");
          }
          if (favDefault) {
            favDefault.classList.add("d-none");
          }
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // 3. Theme Color Pickers Sync
  var colorTextInputs = document.querySelectorAll(".color-text-input");
  colorTextInputs.forEach(function (input) {
    var pickerId = input.getAttribute("data-picker-id");
    var picker = document.getElementById(pickerId);

    if (picker) {
      // Sync from picker to text input
      picker.addEventListener("input", function () {
        input.value = picker.value;
      });

      // Sync from text input to picker (only when valid 6-char hex code)
      input.addEventListener("input", function () {
        var val = input.value.trim();
        var hexPattern = /^#[0-9A-Fa-f]{6}$/;
        if (hexPattern.test(val)) {
          picker.value = val;
        }
      });
    }
  });

  // 4. Initialize Settings API actions
  initSendTestEmail();
  initBumpAssetVersion();
  initClearSettingsCache();
});

// ============================================================
// 5. SETTINGS ACTIONS — Test email, asset version bump, clear cache
// ============================================================

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
          showError(extractErrorMessage(response, "Test email failed"));
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
          showError(extractErrorMessage(response, "Bump failed"));
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

function initClearSettingsCache() {
  $appDocument.off("click.clearSettingsCache").on("click.clearSettingsCache", "#clearSettingsCacheBtn", function (e) {
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
          showSuccess(response.message || "Settings cache cleared");
        } else {
          showError(extractErrorMessage(response, "Clear cache failed"));
        }
      },
      error: function () {
        showError("Network error clearing settings cache");
      },
      complete: function () {
        $btn.removeAttr("disabled");
      },
    });
  });
}
