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

  $(".fscmngr-admin-wrap #fsc-select-all").on("change", function () {
    $(".fsc-select-item").prop("checked", this.checked);
  });

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
  });

  // Handle form submission filtering
  $(".fscmngr-admin-wrap #fsc-filter-form").on("submit", function (e) {
    // Check if the export button was clicked
    if ($('button[name="fsc_export_csv"]').is(":focus")) {
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
      $(".fscmngr-admin-wrap #fsc-submissions-table").html(response);
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

  // Handle email form submission
  $(".fscmngr-admin-wrap #fsc-email-form").on("submit", function (e) {
    e.preventDefault();

    var data = {
      action: "fscmngr_send_email",
      submission_id: $(".fscmngr-admin-wrap #fsc-email-submission-id").val(),
      email_addresses: $(".fscmngr-admin-wrap #fsc-email-addresses").val(),
      nonce: fscmngr_ajax_object.nonce,
    };

    $(".fscmngr-admin-wrap #fsc-loader").show();

    // Send AJAX request
    $.post(fscmngr_ajax_object.ajax_url, data, function (response) {
      $(".fscmngr-admin-wrap #fsc-loader").hide();
      if (response.success) {
        showNotification(response.data, "success");
        $(".fscmngr-admin-wrap #fsc-email-modal").fadeOut();
      } else {
        showNotification(response.data, "error");
        $(".fscmngr-admin-wrap #fsc-email-modal").fadeOut();
      }
    }).fail(function () {
      // Hide the loader
      $(".fscmngr-admin-wrap #fsc-loader").hide();

      // Show error notification
      showNotification("Failed to send email. Please try again.", "error");
    });
  });

  // Bulk delete functionality
  $(".fscmngr-admin-wrap #fsc-bulk-delete").on("click", function () {
    var selected = $(".fscmngr-admin-wrap .fsc-select-item:checked")
      .map(function () {
        return $(this).val();
      })
      .get();

    if (selected.length === 0) {
      showNotification("No submissions selected.", "warning");
      return;
    }

    if (confirm("Are you sure you want to delete selected submissions?")) {
      // Show loader
      $(".fscmngr-admin-wrap #fsc-loader").show();

      $.post(
        fscmngr_ajax_object.ajax_url,
        {
          action: "fscmngr_bulk_delete",
          submission_ids: selected,
          nonce: fscmngr_ajax_object.nonce,
        },
        function (response) {
          $(".fscmngr-admin-wrap #fsc-loader").hide();

          if (response.success) {
            showNotification("Submissions deleted successfully.", "success");
            location.reload();
          } else {
            showNotification("Failed to delete submissions.", "error");
          }
        }
      ).fail(function () {
        $(".fscmngr-admin-wrap #fsc-loader").hide();
        showNotification("Error occurred while deleting submissions.", "error");
      });
    }
  });

  // Bulk export functionality
  $(".fscmngr-admin-wrap #fsc-bulk-export").on("click", function () {
    var selected = $(".fscmngr-admin-wrap .fsc-select-item:checked")
      .map(function () {
        return $(this).val();
      })
      .get();

    if (selected.length === 0) {
      showNotification("No submissions selected for export.", "warning");
      return;
    }

    // Show loader
    $(".fscmngr-admin-wrap #fsc-loader").show();

    // Trigger export via form submission
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

    $(".fscmngr-admin-wrap #fsc-loader").hide(); // Hide the loader after form submission
  });

  // Bulk email functionality
  $(".fscmngr-admin-wrap #fsc-bulk-email").on("click", function () {
    var selected = $(".fscmngr-admin-wrap .fsc-select-item:checked")
      .map(function () {
        return $(this).val();
      })
      .get();

    if (selected.length === 0) {
      showNotification("No submissions selected for emailing.", "warning");
      return;
    }

    // Show the email modal
    $(".fscmngr-admin-wrap #fsc-email-submission-id").val(selected.join(","));
    $(".fscmngr-admin-wrap #fsc-email-modal").fadeIn();
  });

  // Handle bulk email form submission
  $(".fscmngr-admin-wrap #fsc-email-form").on("submit", function (e) {
    e.preventDefault();

    var submission_ids = $(".fscmngr-admin-wrap #fsc-email-submission-id").val();
    var email_addresses = $(".fscmngr-admin-wrap #fsc-email-addresses").val();

    // Show loader
    $(".fscmngr-admin-wrap #fsc-loader").show();

    var data = {
      action: "fscmngr_bulk_email",
      submission_ids: submission_ids,
      email_addresses: email_addresses,
      nonce: fscmngr_ajax_object.nonce,
    };

    // Send AJAX request
    $.post(fscmngr_ajax_object.ajax_url, data, function (response) {
      $(".fscmngr-admin-wrap #fsc-loader").hide();

      if (response.success) {
        showNotification(response.data, "success");
        $(".fscmngr-admin-wrap #fsc-email-modal").fadeOut();
      } else {
        showNotification(response.data, "error");
      }
    }).fail(function () {
      $(".fscmngr-admin-wrap #fsc-loader").hide();
      showNotification("Failed to send bulk email. Please try again.", "error");
    });
  });

  //Clear all filters
  $('#clear-filters').on('click', function (e) {
    location.reload();
  });
});
