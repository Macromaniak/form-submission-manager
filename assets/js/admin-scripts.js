jQuery(document).ready(function ($) {
  function showNotification(message, type) {
    var $notification = $(".fscmngr-admin-wrap #fsc-notification");
    $notification
      .removeClass("success error warning")
      .addClass(type)
      .html('<span class="close-notice">&times;</span>' + message)
      .slideDown();

    // Auto-hide notification after 5 seconds
    setTimeout(function () {
      $notification.slideUp();
    }, 5000);
  }

  // Handle closing the notification manually
  $(document).on("click", ".fscmngr-admin-wrap .close-notice", function () {
    $("#fsc-notification").slideUp();
  });

  // Handle select all checkbox (use event delegation for dynamically loaded content)
  $(document).on("change", ".fscmngr-admin-wrap #fsc-select-all", function () {
    $(".fscmngr-admin-wrap .fsc-select-item").prop("checked", this.checked);
    updateSelectedActions();
  });

  // Handle individual checkbox changes
  $(document).on("change", ".fscmngr-admin-wrap .fsc-select-item", function () {
    updateSelectedActions();
    // Update select all checkbox state
    var totalCheckboxes = $(".fscmngr-admin-wrap .fsc-select-item").length;
    var checkedCheckboxes = $(".fscmngr-admin-wrap .fsc-select-item:checked").length;
    $("#fsc-select-all").prop("checked", totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
  });

  // Function to update selected actions UI
  function updateSelectedActions() {
    var selected = $(".fscmngr-admin-wrap .fsc-select-item:checked").length;
    var $actionsBar = $(".fscmngr-admin-wrap .fsc-selected-actions");
    var $countSpan = $(".fscmngr-admin-wrap .fsc-selected-count");
    
    if (selected > 0) {
      $actionsBar.show();
      $countSpan.text(selected + " " + (selected === 1 ? "item selected" : "items selected"));
    } else {
      $actionsBar.hide();
      // Close dropdown if open
      $(".fscmngr-admin-wrap #fsc-actions-menu").hide();
    }
  }

  // Toggle actions dropdown
  $(document).on("click", ".fscmngr-admin-wrap #fsc-actions-button", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(".fscmngr-admin-wrap #fsc-actions-menu").toggle();
  });

  // Close dropdown when clicking outside
  $(document).on("click", function (e) {
    if (!$(e.target).closest(".fsc-actions-dropdown").length) {
      $(".fscmngr-admin-wrap #fsc-actions-menu").hide();
    }
  });

  // Handle action menu item clicks
  $(document).on("click", ".fscmngr-admin-wrap .fsc-action-item", function (e) {
    e.preventDefault();
    var action = $(this).data("action");
    $(".fscmngr-admin-wrap #fsc-actions-menu").hide();
    
    var selected = $(".fscmngr-admin-wrap .fsc-select-item:checked")
      .map(function () {
        return $(this).val();
      })
      .get();

    if (selected.length === 0) {
      showNotification("No items selected.", "warning");
      return;
    }

    switch (action) {
      case "delete":
        handleSelectedDelete(selected);
        break;
      case "export":
        handleSelectedExport(selected);
        break;
      case "email":
        handleSelectedEmail(selected);
        break;
    }
  });

  // Function to handle selected delete
  function handleSelectedDelete(selected) {
    if (confirm("Are you sure you want to delete " + selected.length + " selected submission(s)?")) {
      $(".fscmngr-admin-wrap #fsc-loader").show();

      var bulkNonce = $("#fsc-bulk-actions-form input[name='fscmngr_bulk_actions_nonce']").val();
      $.post(
        fscmngr_ajax_object.ajax_url,
        {
          action: "fscmngr_bulk_delete",
          submission_ids: selected,
          nonce: fscmngr_ajax_object.nonce,
          fscmngr_bulk_actions_nonce: bulkNonce,
        },
        function (response) {
          $(".fscmngr-admin-wrap #fsc-loader").hide();

          if (response.success) {
            showNotification(response.data || "Submissions deleted successfully.", "success");
            location.reload();
          } else {
            showNotification(response.data || "Failed to delete submissions.", "error");
          }
        }
      ).fail(function () {
        $(".fscmngr-admin-wrap #fsc-loader").hide();
        showNotification("Error occurred while deleting submissions.", "error");
      });
    }
  }

  // Function to handle selected export
  function handleSelectedExport(selected) {
    $(".fscmngr-admin-wrap #fsc-loader").show();

    var form = $("<form>", {
      action: fscmngr_ajax_object.ajax_url,
      method: "post",
    })
      .append(
        $("<input>", {
          type: "hidden",
          name: "action",
          value: "fsc_bulk_export",
        })
      )
      .append(
        $("<input>", {
          type: "hidden",
          name: "submission_ids",
          value: selected.join(","),
        })
      )
      .append(
        $("<input>", {
          type: "hidden",
          name: "nonce",
          value: fscmngr_ajax_object.nonce,
        })
      );

    $("body").append(form);
    form.submit();

    $(".fscmngr-admin-wrap #fsc-loader").hide();
  }

  // Function to handle selected email
  function handleSelectedEmail(selected) {
    $(".fscmngr-admin-wrap #fsc-email-submission-id").val(selected.join(","));
    $(".fscmngr-admin-wrap #fsc-email-addresses").val("");
    $(".fscmngr-admin-wrap #fsc-email-modal").fadeIn();
  }

  // Handle form plugin change event to filter forms
  $(".fscmngr-admin-wrap #form_plugin").on("change", function () {
    var selectedPlugin = $(this).val();

    // Show all options initially
    $(".fscmngr-admin-wrap #form_id option").each(function () {
      var formPlugin = $(this).data("plugin");

      if (
        !selectedPlugin ||
        formPlugin === selectedPlugin ||
        $(this).val() === ""
      ) {
        $(this).show(); // Show the option if it matches the selected plugin
      } else {
        $(this).hide(); // Hide options that don't match
      }
    });

    // Reset the selected value of the forms dropdown
    $(".fscmngr-admin-wrap #form_id").val("");
    updateFormDataExportButton();
  });

  // Handle form ID change to enable/disable form data export
  $(".fscmngr-admin-wrap #form_id").on("change", function () {
    updateFormDataExportButton();
  });

  // Function to update form data export button state
  function updateFormDataExportButton() {
    var formId = $("#form_id").val();
    var $exportBtn = $("#fsc-export-form-data");
    var $fieldCount = $("#fsc-field-count");

    if (formId && formId !== "") {
      $exportBtn.prop("disabled", false);
      $exportBtn.removeClass("disabled");
      // We'll update field count after loading submissions
      $fieldCount.hide();
    } else {
      $exportBtn.prop("disabled", true);
      $exportBtn.addClass("disabled");
      $fieldCount.hide();
    }
  }

  // Handle form data export button click
  $(document).on("click", "#fsc-export-form-data", function (e) {
    e.preventDefault();
    var formId = $("#form_id").val();

    if (!formId || formId === "") {
      showNotification("Please select a specific form to export form data.", "warning");
      return;
    }

    // Get current filter values
    var form_plugin = $("#form_plugin").val();
    var start_date = $("#start_date").val();
    var end_date = $("#end_date").val();
    var keyword = $("#keyword").val();

    // Show confirmation for large forms (we'll check field count on backend)
    if (confirm("This will export form data with fields as columns. Continue?")) {
      $(".fscmngr-admin-wrap #fsc-loader").show();

      // Create form for export
      var form = $("<form>", {
        action: fscmngr_ajax_object.ajax_url,
        method: "post",
      })
        .append(
          $("<input>", {
            type: "hidden",
            name: "action",
            value: "fscmngr_export_form_data_csv",
          })
        )
        .append(
          $("<input>", {
            type: "hidden",
            name: "form_id",
            value: formId,
          })
        )
        .append(
          $("<input>", {
            type: "hidden",
            name: "form_plugin",
            value: form_plugin || "",
          })
        )
        .append(
          $("<input>", {
            type: "hidden",
            name: "start_date",
            value: start_date || "",
          })
        )
        .append(
          $("<input>", {
            type: "hidden",
            name: "end_date",
            value: end_date || "",
          })
        )
        .append(
          $("<input>", {
            type: "hidden",
            name: "keyword",
            value: keyword || "",
          })
        )
        .append(
          $("<input>", {
            type: "hidden",
            name: "nonce",
            value: fscmngr_ajax_object.nonce,
          })
        );

      $("body").append(form);
      form.submit();

      // Hide loader after a delay (since form submit is async)
      setTimeout(function () {
        $(".fscmngr-admin-wrap #fsc-loader").hide();
      }, 1000);
    }
  });

  // Handle form submission filtering
  $(".fscmngr-admin-wrap #fsc-filter-form").on("submit", function (e) {
    // Check if the export button was clicked
    if ($('button[name="fscmngr_export_csv"]').is(":focus")) {
      return; // Allow the form to be submitted normally for CSV export
    }

    e.preventDefault();

    $(".fscmngr-admin-wrap #fsc-loader").show();

    var data = {
      action: "fscmngr_filter_submissions",
      form_plugin: $("#form_plugin").val(),
      form_id: $("#form_id").val(),
      start_date: $("#start_date").val(), // Include start date
      end_date: $("#end_date").val(), // Include end date
      keyword: $("#keyword").val(),
      nonce: fscmngr_ajax_object.nonce,
    };

    // Send AJAX request
    $.post(fscmngr_ajax_object.ajax_url, data, function (response) {
      $(".fscmngr-admin-wrap #fsc-loader").hide();
      // Replace the entire submissions table content
      $(".fscmngr-admin-wrap #fsc-submissions-table").html(response);
      // Reset select all checkbox state
      $(".fscmngr-admin-wrap #fsc-select-all").prop("checked", false);
      // Reset selected actions UI after filtering
      updateSelectedActions();
      // Update form data export button state
      updateFormDataExportButton();
      // Update URL with filter parameters and remove pagination when filtering
      if (window.history && window.history.pushState) {
        var url = new URL(window.location);
        url.searchParams.delete('paged');
        // Update filter parameters in URL
        if (data.form_plugin) {
          url.searchParams.set('form_plugin', data.form_plugin);
        } else {
          url.searchParams.delete('form_plugin');
        }
        if (data.form_id) {
          url.searchParams.set('form_id', data.form_id);
        } else {
          url.searchParams.delete('form_id');
        }
        if (data.start_date) {
          url.searchParams.set('start_date', data.start_date);
        } else {
          url.searchParams.delete('start_date');
        }
        if (data.end_date) {
          url.searchParams.set('end_date', data.end_date);
        } else {
          url.searchParams.delete('end_date');
        }
        if (data.keyword) {
          url.searchParams.set('keyword', data.keyword);
        } else {
          url.searchParams.delete('keyword');
        }
        window.history.pushState({}, '', url);
      }
      showNotification("Submissions filtered successfully!", "success");
    }).fail(function () {
      // In case of error, hide the loader and show an alert
      $(".fscmngr-admin-wrap #fsc-loader").hide();
      showNotification(
        "There was an error loading the data. Please try again.",
        "error"
      );
    });
  });

  // Handle delete submission with confirmation and notifications
  $(document).on("click", ".fscmngr-admin-wrap .fsc-delete-submission", function (e) {
    e.preventDefault();
    var submissionID = $(this).data("id");
    if (confirm("Are you sure you want to delete this submission?")) {
      $(".fscmngr-admin-wrap #fsc-loader").show(); // Show the loader
      $.post(
        fscmngr_ajax_object.ajax_url,
        {
          action: "fscmngr_delete_submission",
          submission_id: submissionID,
          nonce: fscmngr_ajax_object.nonce,
        },
        function (response) {
          $(".fscmngr-admin-wrap #fsc-loader").hide(); // Hide the loader
          if (response.success) {
            showNotification("Submission deleted successfully.", "success");
            location.reload(); // Refresh the page
          } else {
            showNotification("Failed to delete submission.", "error");
          }
        }
      ).fail(function () {
        $(".fscmngr-admin-wrap #fsc-loader").hide(); // Hide the loader

        // Show error notification
        showNotification("Error occurred while deleting submission.", "error");
      });
    }
  });

  // Show the email modal
  $(document).on("click", ".fscmngr-admin-wrap .fsc-email-submission", function (e) {
    e.preventDefault();
    var submissionId = $(this).data("id");
    $(".fscmngr-admin-wrap #fsc-email-submission-id").val(submissionId);
    $(".fscmngr-admin-wrap #fsc-email-modal").fadeIn();
  });

  // Close the modal
  $(".fscmngr-admin-wrap .fsc-modal-close").on("click", function () {
    $(".fscmngr-admin-wrap #fsc-email-modal").fadeOut();
  });

  // Handle email form submission (single email)
  $(".fscmngr-admin-wrap #fsc-email-form").on("submit", function (e) {
    e.preventDefault();

    var submission_ids = $(".fscmngr-admin-wrap #fsc-email-submission-id").val();
    var email_addresses = $(".fscmngr-admin-wrap #fsc-email-addresses").val();

    // Check if it's a single submission (no comma) or bulk (has comma)
    var isBulk = submission_ids.indexOf(',') !== -1;

    $(".fscmngr-admin-wrap #fsc-loader").show();

    var bulkNonce = $("#fsc-bulk-actions-form input[name='fscmngr_bulk_actions_nonce']").val();
    var data;
    if (isBulk) {
      // Bulk email
      data = {
        action: "fscmngr_bulk_email",
        submission_ids: submission_ids,
        email_addresses: email_addresses,
        nonce: fscmngr_ajax_object.nonce,
        fscmngr_bulk_actions_nonce: bulkNonce,
      };
    } else {
      // Single email
      data = {
        action: "fscmngr_send_email",
        submission_id: submission_ids,
        email_addresses: email_addresses,
        nonce: fscmngr_ajax_object.nonce,
      };
    }

    // Send AJAX request
    $.post(fscmngr_ajax_object.ajax_url, data, function (response) {
      $(".fscmngr-admin-wrap #fsc-loader").hide();
      if (response.success) {
        showNotification(response.data, "success");
        $(".fscmngr-admin-wrap #fsc-email-modal").fadeOut();
        $(".fscmngr-admin-wrap #fsc-email-addresses").val(''); // Clear email field
      } else {
        showNotification(response.data, "error");
      }
    }).fail(function () {
      // Hide the loader
      $(".fscmngr-admin-wrap #fsc-loader").hide();

      // Show error notification
      showNotification("Failed to send email. Please try again.", "error");
    });
  });


  //Clear all filters
  $('#clear-filters').on('click', function (e) {
    e.preventDefault();
    // Clear all form field values
    $("#form_plugin").val('');
    $("#form_id").val('');
    $("#start_date").val('');
    $("#end_date").val('');
    $("#keyword").val('');
    // Trigger form submit - the form submit handler will handle clearing URL parameters
    // since all values are now empty, it will delete all filter parameters from URL
    $('#fsc-filter-form').submit();
  });
  
  // Handle pagination link clicks via AJAX to preserve filters
  $(document).on('click', '.fscmngr-admin-wrap .fsc-tablenav-pages a', function(e) {
    e.preventDefault();
    var href = $(this).attr('href');
    if (!href) return;
    
    // Extract page number and filter parameters from URL
    var url = new URL(href);
    var paged = url.searchParams.get('paged') || 1;
    
    // Get filter values from URL or form
    var form_plugin = url.searchParams.get('form_plugin') || $("#form_plugin").val() || '';
    var form_id = url.searchParams.get('form_id') || $("#form_id").val() || '';
    var start_date = url.searchParams.get('start_date') || $("#start_date").val() || '';
    var end_date = url.searchParams.get('end_date') || $("#end_date").val() || '';
    var keyword = url.searchParams.get('keyword') || $("#keyword").val() || '';
    
    // Update form values to match URL
    $("#form_plugin").val(form_plugin);
    $("#form_id").val(form_id);
    $("#start_date").val(start_date);
    $("#end_date").val(end_date);
    $("#keyword").val(keyword);
    
    // Submit filter form with pagination via AJAX
    $(".fscmngr-admin-wrap #fsc-loader").show();
    
    var data = {
      action: "fscmngr_filter_submissions",
      form_plugin: form_plugin,
      form_id: form_id,
      start_date: start_date,
      end_date: end_date,
      keyword: keyword,
      paged: paged,
      nonce: fscmngr_ajax_object.nonce,
    };
    
    $.post(fscmngr_ajax_object.ajax_url, data, function (response) {
      $(".fscmngr-admin-wrap #fsc-loader").hide();
      // Replace the entire submissions table content
      $(".fscmngr-admin-wrap #fsc-submissions-table").html(response);
      // Reset select all checkbox state
      $(".fscmngr-admin-wrap #fsc-select-all").prop("checked", false);
      // Reset selected actions UI after pagination
      updateSelectedActions();
      // Update form data export button state
      updateFormDataExportButton();
      // Update URL to reflect current state
      if (window.history && window.history.pushState) {
        window.history.pushState({}, '', href);
      }
    }).fail(function () {
      $(".fscmngr-admin-wrap #fsc-loader").hide();
      showNotification("There was an error loading the data. Please try again.", "error");
    });
  });

  // Initialize form data export button state on page load
  updateFormDataExportButton();
});
