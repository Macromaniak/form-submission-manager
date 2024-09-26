<?php
class FSC_Ajax_Handler
{
    public static function handle_form_submission_filter() {
        check_ajax_referer( 'fsc_nonce', 'nonce' );

        // Capture filtering parameters
        $_POST['form_plugin'] = sanitize_text_field( $_POST['form_plugin'] );
        $_POST['form_id']     = intval( $_POST['form_id'] );

        // Call the display method to fetch and output submissions
        FSC_Form_Submission::fetch_and_display_submissions();
        
        wp_die();
    }
    public static function handle_submission_deletion()
    {
        check_ajax_referer('fsc_nonce', 'nonce');

        global $wpdb;
        $submission_id = intval($_POST['submission_id']);

        $result = $wpdb->delete($wpdb->prefix . 'form_submissions', array('id' => $submission_id), array('%d'));

        if ($result) {
            wp_send_json_success(__('Submission deleted successfully.', 'form-submission-capture'));
        } else {
            wp_send_json_error(__('Failed to delete submission.', 'form-submission-capture'));
        }
    }

    /**
     * Handle sending an email with submission data
     */
    public static function handle_send_email() {
        check_ajax_referer( 'fsc_nonce', 'nonce' );

        $submission_id = intval($_POST['submission_id']);
        $email_addresses = sanitize_text_field($_POST['email_addresses']);

        if (empty($submission_id) || empty($email_addresses)) {
            wp_send_json_error('Invalid data provided.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Retrieve the submission data
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $submission_id), ARRAY_A);
        
        if (!$submission) {
            wp_send_json_error('Submission not found.');
        }

        $form_data = maybe_unserialize($submission['submission_data']);
        $formatted_data = '';

        if ($submission['form_plugin'] === 'wpforms') {
            foreach ($form_data as $field) {
                if (isset($field['name']) && isset($field['value'])) {
                    $formatted_data .= $field['name'] . ": " . (is_array($field['value']) ? implode(', ', $field['value']) : $field['value']) . "\n";
                }
            }
        } else {
            foreach ($form_data as $key => $value) {
                $formatted_data .= ucfirst(str_replace('_', ' ', $key)) . ": " . (is_array($value) ? implode(', ', $value) : $value) . "\n";
            }
        }

        // Prepare and send the email
        $subject = 'Form Submission Details';
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $email_addresses_array = array_map('trim', explode(',', $email_addresses));

        if (wp_mail($email_addresses_array, $subject, $formatted_data, $headers)) {
            wp_send_json_success('Email sent successfully!');
        } else {
            wp_send_json_error('Failed to send email.');
        }
    }
}
add_action('wp_ajax_fsc_filter_submissions', array('FSC_Ajax_Handler', 'handle_form_submission_filter'));
add_action( 'wp_ajax_fsc_delete_submission', array( 'FSC_Ajax_Handler', 'handle_submission_deletion' ) );
add_action('wp_ajax_fsc_send_email', array('FSC_Ajax_Handler', 'handle_send_email'));
