jQuery(document).ready(function ($) {
  function showNotification(message, type) {
    var $notification = $("#fsc-notification");
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
  $(document).on("click", ".close-notice", function () {
    $("#fsc-notification").slideUp();
  });

  $("#fsc-select-all").on("change", function () {
    $(".fsc-select-item").prop("checked", this.checked);
  });

  // Handle form plugin change event to filter forms
  $("#form_plugin").on("change", function () {
    var selectedPlugin = $(this).val();

    // Show all options initially
    $("#form_id option").each(function () {
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
    $("#form_id").val("");
  });

  // Handle form submission filtering
  $("#fsc-filter-form").on("submit", function (e) {
    // Check if the export button was clicked
    if ($('button[name="fsc_export_csv"]').is(":focus")) {
      return; // Allow the form to be submitted normally for CSV export
    }

    e.preventDefault();

    $("#fsc-loader").show();

    var data = {
      action: "fsc_filter_submissions",
      form_plugin: $("#form_plugin").val(),
      form_id: $("#form_id").val(),
      start_date: $("#start_date").val(), // Include start date
      end_date: $("#end_date").val(), // Include end date
      keyword: $("keyword").val(),
      nonce: fsc_ajax_object.nonce,
    };

    // Send AJAX request
    $.post(fsc_ajax_object.ajax_url, data, function (response) {
      $("#fsc-loader").hide();
      $("#fsc-submissions-table").html(response);
      showNotification("Submissions filtered successfully!", "success");
    }).fail(function () {
      // In case of error, hide the loader and show an alert
      $("#fsc-loader").hide();
      showNotification(
        "There was an error loading the data. Please try again.",
        "error"
      );
    });
  });

  // Handle delete submission with confirmation and notifications
  $(document).on("click", ".fsc-delete-submission", function () {
    var submissionID = $(this).data("id");
    if (confirm("Are you sure you want to delete this submission?")) {
      $("#fsc-loader").show(); // Show the loader
      $.post(
        fsc_ajax_object.ajax_url,
        {
          action: "fsc_delete_submission",
          submission_id: submissionID,
          nonce: fsc_ajax_object.nonce,
        },
        function (response) {
          $("#fsc-loader").hide(); // Hide the loader
          if (response.success) {
            showNotification("Submission deleted successfully.", "success");
            location.reload(); // Refresh the page
          } else {
            showNotification("Failed to delete submission.", "error");
          }
        }
      ).fail(function () {
        $("#fsc-loader").hide(); // Hide the loader

        // Show error notification
        showNotification("Error occurred while deleting submission.", "error");
      });
    }
  });

  // Show the email modal
  $(document).on("click", ".fsc-email-submission", function () {
    var submissionId = $(this).data("id");
    $("#fsc-email-submission-id").val(submissionId);
    $("#fsc-email-modal").fadeIn();
  });

  // Close the modal
  $(".fsc-modal-close").on("click", function () {
    $("#fsc-email-modal").fadeOut();
  });

  // Handle email form submission
  $("#fsc-email-form").on("submit", function (e) {
    e.preventDefault();

    var data = {
      action: "fsc_send_email",
      submission_id: $("#fsc-email-submission-id").val(),
      email_addresses: $("#fsc-email-addresses").val(),
      nonce: fsc_ajax_object.nonce,
    };

    $("#fsc-loader").show();

    // Send AJAX request
    $.post(fsc_ajax_object.ajax_url, data, function (response) {
      $("#fsc-loader").hide();
      if (response.success) {
        showNotification(response.data, "success");
        $("#fsc-email-modal").fadeOut();
      } else {
        showNotification(response.data, "error");
      }
    }).fail(function () {
      // Hide the loader
      $("#fsc-loader").hide();

      // Show error notification
      showNotification("Failed to send email. Please try again.", "error");
    });
  });

  // Bulk delete functionality
  $("#fsc-bulk-delete").on("click", function () {
    var selected = $(".fsc-select-item:checked")
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
      $("#fsc-loader").show();

      $.post(
        fsc_ajax_object.ajax_url,
        {
          action: "fsc_bulk_delete",
          submission_ids: selected,
          nonce: fsc_ajax_object.nonce,
        },
        function (response) {
          $("#fsc-loader").hide();

          if (response.success) {
            showNotification("Submissions deleted successfully.", "success");
            location.reload();
          } else {
            showNotification("Failed to delete submissions.", "error");
          }
        }
      ).fail(function () {
        $("#fsc-loader").hide();
        showNotification("Error occurred while deleting submissions.", "error");
      });
    }
  });

  // Bulk export functionality
  $("#fsc-bulk-export").on("click", function () {
    var selected = $(".fsc-select-item:checked")
      .map(function () {
        return $(this).val();
      })
      .get();

    if (selected.length === 0) {
      showNotification("No submissions selected for export.", "warning");
      return;
    }

    // Show loader
    $("#fsc-loader").show();

    // Trigger export via form submission
    var form = $("<form>", {
      action: fsc_ajax_object.ajax_url,
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
          value: fsc_ajax_object.nonce,
        })
      );

    $("body").append(form);
    form.submit();

    $("#fsc-loader").hide(); // Hide the loader after form submission
  });

  // Bulk email functionality
  $("#fsc-bulk-email").on("click", function () {
    var selected = $(".fsc-select-item:checked")
      .map(function () {
        return $(this).val();
      })
      .get();

    if (selected.length === 0) {
      showNotification("No submissions selected for emailing.", "warning");
      return;
    }

    // Show the email modal
    $("#fsc-email-submission-id").val(selected.join(","));
    $("#fsc-email-modal").fadeIn();
  });

  // Handle bulk email form submission
  $("#fsc-email-form").on("submit", function (e) {
    e.preventDefault();

    var submission_ids = $("#fsc-email-submission-id").val();
    var email_addresses = $("#fsc-email-addresses").val();

    // Show loader
    $("#fsc-loader").show();

    var data = {
      action: "fsc_bulk_email",
      submission_ids: submission_ids,
      email_addresses: email_addresses,
      nonce: fsc_ajax_object.nonce,
    };

    // Send AJAX request
    $.post(fsc_ajax_object.ajax_url, data, function (response) {
      $("#fsc-loader").hide();

      if (response.success) {
        showNotification(response.data, "success");
        $("#fsc-email-modal").fadeOut();
      } else {
        showNotification(response.data, "error");
      }
    }).fail(function () {
      $("#fsc-loader").hide();
      showNotification("Failed to send bulk email. Please try again.", "error");
    });
  });
});
