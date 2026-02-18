<?php
namespace FSCMNGR\Includes;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use FSCMNGR\Includes\FSCMNGR_Form_Submission;

class FSCMNGR_Ajax_Handler
{
    public static function fscmngr_handle_form_submission_filter()
    {
        check_ajax_referer('fscmngr_nonce', 'nonce');

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'form-submissions-manager'));
            return;
        }

        // Capture filtering parameters
        $_POST['form_plugin'] = isset($_POST['form_plugin']) ? sanitize_text_field($_POST['form_plugin']) : '';
        $_POST['form_id']     = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $_POST['start_date']  = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $_POST['end_date']    = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $_POST['keyword']     = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        
        // Handle pagination from AJAX
        if (isset($_POST['paged'])) {
            $_GET['paged'] = intval($_POST['paged']);
        }

        // Call the display method to fetch and output submissions
        FSCMNGR_Form_Submission::fscmngr_fetch_and_display_submissions();

        wp_die();
    }
    public static function fscmngr_handle_submission_deletion()
    {
        check_ajax_referer('fscmngr_nonce', 'nonce');

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'form-submissions-manager'));
            return;
        }

        global $wpdb;
        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;

        if (empty($submission_id)) {
            wp_send_json_error(__('Invalid submission ID.', 'form-submissions-manager'));
            return;
        }

        $result = $wpdb->delete($wpdb->prefix . 'form_submissions', array('id' => $submission_id), array('%d'));

        if ($result !== false) {
            wp_send_json_success(__('Submission deleted successfully.', 'form-submissions-manager'));
        } else {
            wp_send_json_error(__('Failed to delete submission.', 'form-submissions-manager'));
        }
    }

    /**
     * Handle sending an email with submission data
     */
    public static function fscmngr_handle_send_email()
    {
        check_ajax_referer('fscmngr_nonce', 'nonce');

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'form-submissions-manager'));
            return;
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
        $email_addresses = isset($_POST['email_addresses']) ? sanitize_text_field($_POST['email_addresses']) : '';

        if (empty($submission_id) || empty($email_addresses)) {
            wp_send_json_error(__('Invalid data provided.', 'form-submissions-manager'));
            return;
        }

        // Validate email addresses
        $email_array = array_map('trim', explode(',', $email_addresses));
        $valid_emails = array();
        foreach ($email_array as $email) {
            if (is_email($email)) {
                $valid_emails[] = $email;
            }
        }

        if (empty($valid_emails)) {
            wp_send_json_error(__('No valid email addresses provided.', 'form-submissions-manager'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Retrieve the submission data
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $submission_id), ARRAY_A);

        if (!$submission) {
            wp_send_json_error(__('Submission not found.', 'form-submissions-manager'));
            return;
        }

        $form_data = maybe_unserialize($submission['submission_data']);
        
        // Validate unserialized data
        if ($form_data === false && $submission['submission_data'] !== serialize(false)) {
            wp_send_json_error(__('Invalid submission data.', 'form-submissions-manager'));
            return;
        }
        
        if (!is_array($form_data) || empty($form_data)) {
            wp_send_json_error(__('No data found in submission.', 'form-submissions-manager'));
            return;
        }
        
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
        $subject = __('Form Submission Details', 'form-submissions-manager');
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        if (wp_mail($valid_emails, $subject, $formatted_data, $headers)) {
            wp_send_json_success(__('Email sent successfully!', 'form-submissions-manager'));
        } else {
            wp_send_json_error(__('Failed to send email.', 'form-submissions-manager'));
        }
    }

    public static function fscmngr_handle_bulk_delete()
    {
        check_ajax_referer('fscmngr_nonce', 'nonce');

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'form-submissions-manager'));
            return;
        }

        $submission_ids = isset($_POST['submission_ids']) ? array_map('intval', $_POST['submission_ids']) : array();
        $submission_ids = array_filter($submission_ids, function($id) { return $id > 0; });

        if (empty($submission_ids)) {
            wp_send_json_error(__('No submissions selected.', 'form-submissions-manager'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        $deleted_count = 0;
        foreach ($submission_ids as $submission_id) {
            $result = $wpdb->delete($table_name, array('id' => $submission_id), array('%d'));
            if ($result !== false) {
                $deleted_count++;
            }
        }

        if ($deleted_count > 0) {
            wp_send_json_success(sprintf(__('Successfully deleted %d submission(s).', 'form-submissions-manager'), $deleted_count));
        } else {
            wp_send_json_error(__('Failed to delete submissions.', 'form-submissions-manager'));
        }
    }

    public static function fscmngr_handle_bulk_email()
    {
        check_ajax_referer('fscmngr_nonce', 'nonce');

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'form-submissions-manager'));
            return;
        }

        // Verify bulk actions nonce if provided
        if (isset($_POST['fscmngr_bulk_actions_nonce']) && 
            !wp_verify_nonce($_POST['fscmngr_bulk_actions_nonce'], 'fscmngr_bulk_actions_nonce')) {
            wp_send_json_error(__('Security check failed.', 'form-submissions-manager'));
            return;
        }

        $submission_ids = isset($_POST['submission_ids'])
            ? array_filter(
                array_map('intval', explode(',', $_POST['submission_ids'])),
                function($id) { return $id > 0; }
            )
            : array();

        $email_addresses_raw = isset($_POST['email_addresses']) ? sanitize_text_field($_POST['email_addresses']) : '';
        $email_addresses = array();
        if (!empty($email_addresses_raw)) {
            $email_array = array_map('trim', explode(',', $email_addresses_raw));
            foreach ($email_array as $email) {
                if (is_email($email)) {
                    $email_addresses[] = $email;
                }
            }
        }

        if (empty($submission_ids) || empty($email_addresses)) {
            wp_send_json_error(__('No submissions or email addresses provided.', 'form-submissions-manager'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Retrieve the submissions using proper prepared statement
        $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id IN ($placeholders) ORDER BY date_submitted DESC",
            $submission_ids
        );
        $submissions = $wpdb->get_results($query, ARRAY_A);

        if (empty($submissions)) {
            wp_send_json_error(__('No submissions found for emailing.', 'form-submissions-manager'));
        }

        // Prepare email data
        $formatted_data = '';
        foreach ($submissions as $submission) {
            $form_data = maybe_unserialize($submission['submission_data']);
            
            // Validate unserialized data
            if ($form_data === false && $submission['submission_data'] !== serialize(false)) {
                $form_data = array(); // Set to empty array if unserialize failed
            }

            $formatted_data .= "Submission ID: " . $submission['id'] . "\n";
            $formatted_data .= "Form Plugin: " . $submission['form_plugin'] . "\n";
            $formatted_data .= "Form ID: " . $submission['form_id'] . "\n";

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

            $formatted_data .= "Date Submitted: " . $submission['date_submitted'] . "\n\n";
        }

        // Send the email
        $subject = __('Bulk Form Submissions', 'form-submissions-manager');
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        if (wp_mail($email_addresses, $subject, $formatted_data, $headers)) {
            wp_send_json_success(__('Bulk email sent successfully!', 'form-submissions-manager'));
        } else {
            wp_send_json_error(__('Failed to send bulk email.', 'form-submissions-manager'));
        }
    }

}
add_action('wp_ajax_fscmngr_filter_submissions', array('FSCMNGR\Includes\FSCMNGR_Ajax_Handler', 'fscmngr_handle_form_submission_filter'));
add_action('wp_ajax_fscmngr_delete_submission', array('FSCMNGR\Includes\FSCMNGR_Ajax_Handler', 'fscmngr_handle_submission_deletion'));
add_action('wp_ajax_fscmngr_send_email', array('FSCMNGR\Includes\FSCMNGR_Ajax_Handler', 'fscmngr_handle_send_email'));
add_action('wp_ajax_fscmngr_bulk_delete', array('FSCMNGR\Includes\FSCMNGR_Ajax_Handler', 'fscmngr_handle_bulk_delete'));
add_action('wp_ajax_fscmngr_bulk_email', array('FSCMNGR\Includes\FSCMNGR_Ajax_Handler', 'fscmngr_handle_bulk_email'));
