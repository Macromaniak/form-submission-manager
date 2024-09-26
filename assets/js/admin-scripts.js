jQuery(document).ready(function($) {

    // Handle form plugin change event to filter forms
    $('#form_plugin').on('change', function() {
        var selectedPlugin = $(this).val();

        // Show all options initially
        $('#form_id option').each(function() {
            var formPlugin = $(this).data('plugin');

            if (!selectedPlugin || formPlugin === selectedPlugin || $(this).val() === '') {
                $(this).show(); // Show the option if it matches the selected plugin
            } else {
                $(this).hide(); // Hide options that don't match
            }
        });

        // Reset the selected value of the forms dropdown
        $('#form_id').val('');
    });
    
    // Handle form submission filtering
    $('#fsc-filter-form').on('submit', function(e) {

        // Check if the export button was clicked
        if ($('button[name="fsc_export_csv"]').is(":focus")) {
            return; // Allow the form to be submitted normally for CSV export
        }

        e.preventDefault();

        var data = {
            action: 'fsc_filter_submissions',
            form_plugin: $('#form_plugin').val(),
            form_id: $('#form_id').val(),
            nonce: fsc_ajax_object.nonce
        };

        // Send AJAX request
        $.post(fsc_ajax_object.ajax_url, data, function(response) {
            $('#fsc-submissions-table').html(response);
        });
    });

    $(document).on('click', '.fsc-delete-submission', function() {
        var submissionID = $(this).data('id');
        if (confirm('Are you sure you want to delete this submission?')) {
            $.post(fsc_ajax_object.ajax_url, {
                action: 'fsc_delete_submission',
                submission_id: submissionID,
                nonce: fsc_ajax_object.nonce
            }, function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload(); // Refresh the page
                } else {
                    alert(response.data);
                }
            });
        }
    });

    // Show the email modal
    $(document).on('click', '.fsc-email-submission', function() {
        var submissionId = $(this).data('id');
        $('#fsc-email-submission-id').val(submissionId);
        $('#fsc-email-modal').fadeIn();
    });

    // Close the modal
    $('.fsc-modal-close').on('click', function() {
        $('#fsc-email-modal').fadeOut();
    });

    // Handle email form submission
    $('#fsc-email-form').on('submit', function(e) {
        e.preventDefault();

        var data = {
            action: 'fsc_send_email',
            submission_id: $('#fsc-email-submission-id').val(),
            email_addresses: $('#fsc-email-addresses').val(),
            nonce: fsc_ajax_object.nonce
        };

        // Send AJAX request
        $.post(fsc_ajax_object.ajax_url, data, function(response) {
            if (response.success) {
                alert(response.data);
                $('#fsc-email-modal').fadeOut();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
});
