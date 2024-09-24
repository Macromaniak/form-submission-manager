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
}
add_action('wp_ajax_fsc_filter_submissions', array('FSC_Ajax_Handler', 'handle_form_submission_filter'));
add_action( 'wp_ajax_fsc_delete_submission', array( 'FSC_Ajax_Handler', 'handle_submission_deletion' ) );
