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

        // Capture filtering parameters
        $_POST['form_plugin'] = sanitize_text_field($_POST['form_plugin']);
        $_POST['form_id']     = intval($_POST['form_id']);

        // Call the display method to fetch and output submissions
        FSCMNGR_Form_Submission::fscmngr_fetch_and_display_submissions();

        wp_die();
    }
    public static function fscmngr_handle_submission_deletion()
    {
        check_ajax_referer('fscmngr_nonce', 'nonce');

        global $wpdb;
        $submission_id = intval($_POST['submission_id']);

        $result = $wpdb->delete($wpdb->prefix . 'form_submissions', array('id' => $submission_id), array('%d'));

        if ($result) {
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

    public static function fscmngr_handle_bulk_delete()
    {
        check_ajax_referer('fscmngr_nonce', 'nonce');

        $submission_ids = isset($_POST['submission_ids']) ? array_map('intval', $_POST['submission_ids']) : array();

        if (empty($submission_ids)) {
            wp_send_json_error('No submissions selected.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        foreach ($submission_ids as $submission_id) {
            $wpdb->delete($table_name, array('id' => $submission_id), array('%d'));
        }

        wp_send_json_success('Selected submissions deleted successfully.');
    }

    public static function fscmngr_handle_bulk_email()
    {
        check_ajax_referer('fscmngr_nonce', 'nonce');

        $submission_ids = isset($_POST['submission_ids'])
            ? array_filter(
                array_map('intval', explode(',', $_POST['submission_ids'])),
                fn($id) => $id > 0
            )
            : array();

        $email_addresses = isset($_POST['email_addresses'])
            ? array_filter(
                array_map('sanitize_email', explode(',', $_POST['email_addresses'])),
                'is_email'
            )
            : array();


        if (empty($submission_ids) || empty($email_addresses)) {
            wp_send_json_error(__('No submissions or email addresses provided.', 'form-submissions-manager'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Retrieve the submissions
        $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));
        $query = $wpdb->prepare("SELECT * FROM $table_name WHERE id IN ($placeholders)", $submission_ids);
        $submissions = $wpdb->get_results("{$query}", ARRAY_A);

        if (empty($submissions)) {
            wp_send_json_error(__('No submissions found for emailing.', 'form-submissions-manager'));
        }

        // Prepare email data
        $formatted_data = '';
        foreach ($submissions as $submission) {
            $form_data = maybe_unserialize($submission['submission_data']);

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
        $subject = 'Bulk Form Submissions';
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $email_addresses_array = array_map('trim', explode(',', $email_addresses));

        if (wp_mail($email_addresses_array, $subject, $formatted_data, $headers)) {
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
