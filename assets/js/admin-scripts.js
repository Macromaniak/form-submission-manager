jQuery(document).ready(function($) {
    // Handle form submission filtering
    $('#fsc-filter-form').on('submit', function(e) {
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
});
