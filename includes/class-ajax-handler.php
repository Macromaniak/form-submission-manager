<?php
class FSC_Ajax_Handler
{
    public static function handle_form_submission_filter()
    {
        check_ajax_referer('fsc_nonce', 'nonce');

        // Capture filtering parameters
        $_POST['form_plugin'] = sanitize_text_field($_POST['form_plugin']);
        $_POST['form_id']     = intval($_POST['form_id']);

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
            wp_send_json_success(__('Submission deleted successfully.', 'form-submissions-manager'));
        } else {
            wp_send_json_error(__('Failed to delete submission.', 'form-submissions-manager'));
        }
    }

    /**
     * Handle sending an email with submission data
     */
    public static function handle_send_email()
    {
        check_ajax_referer('fsc_nonce', 'nonce');

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

    public static function handle_bulk_delete()
    {
        check_ajax_referer('fsc_nonce', 'nonce');

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

    // public static function handle_bulk_export() {
    //     check_admin_referer( 'fsc_nonce', 'nonce' );

    //     // Sanitize and validate submission IDs
    //     $submission_ids = isset( $_POST['submission_ids'] ) ? array_map( 'intval', explode( ',', sanitize_text_field( $_POST['submission_ids'] ) ) ) : array();

    //     if ( empty( $submission_ids ) ) {
    //         wp_die( esc_html__( 'No submissions selected for export.', 'form-submissions-manager' ) );
    //     }

    //     global $wpdb, $wp_filesystem;
    //     $table_name = $wpdb->prefix . 'form_submissions';

    //     // Initialize WP_Filesystem if not already done
    //     if ( ! function_exists( 'WP_Filesystem' ) ) {
    //         require_once( ABSPATH . 'wp-admin/includes/file.php' );
    //     }
    //     WP_Filesystem();

    //     // Prepare the query using placeholders directly for secure SQL construction
    //     $placeholders = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );
    //     $query = $wpdb->prepare( "SELECT * FROM $table_name WHERE id IN ($placeholders)", $submission_ids );
    //     $submissions = $wpdb->get_results("{$query}", ARRAY_A );

    //     if ( empty( $submissions ) ) {
    //         wp_die( esc_html__( 'No submissions found for export.', 'form-submissions-manager' ) );
    //     }

    //     // Create a temporary file path for the CSV
    //     $temp_file = wp_tempnam( 'bulk-form-submissions.csv' );

    //     if ( $temp_file ) {
    //         // Initialize CSV content with headers, ensuring safe output
    //         $csv_content = esc_html( "ID,Form Plugin,Form ID,Field Name,Field Value,Date Submitted\n" );

    //         // Iterate through each submission and add rows to the CSV content
    //         foreach ( $submissions as $submission ) {
    //             $form_data = maybe_unserialize( $submission['submission_data'] );

    //             if ( is_array( $form_data ) ) {
    //                 if ( $submission['form_plugin'] === 'wpforms' ) {
    //                     // Handle WPForms submissions
    //                     foreach ( $form_data as $field ) {
    //                         if ( isset( $field['name'] ) && isset( $field['value'] ) ) {
    //                             $row = array(
    //                                 esc_html( $submission['id'] ),
    //                                 esc_html( $submission['form_plugin'] ),
    //                                 esc_html( $submission['form_id'] ),
    //                                 esc_html( $field['name'] ),
    //                                 esc_html( is_array( $field['value'] ) ? implode( ', ', $field['value'] ) : $field['value'] ),
    //                                 esc_html( $submission['date_submitted'] )
    //                             );
    //                             $csv_content .= implode( ',', $row ) . "\n";
    //                         }
    //                     }
    //                 } else {
    //                     // Handle other form plugins like Contact Form 7
    //                     foreach ( $form_data as $key => $value ) {
    //                         $row = array(
    //                             esc_html( $submission['id'] ),
    //                             esc_html( $submission['form_plugin'] ),
    //                             esc_html( $submission['form_id'] ),
    //                             esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ),
    //                             esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ),
    //                             esc_html( $submission['date_submitted'] )
    //                         );
    //                         $csv_content .= implode( ',', $row ) . "\n";
    //                     }
    //                 }
    //             }
    //         }

    //         // Write the CSV content to the temporary file using WP_Filesystem
    //         if ( $wp_filesystem->put_contents( $temp_file, $csv_content, FS_CHMOD_FILE ) ) {
    //             // Send headers for file download
    //             header( 'Content-Type: text/csv; charset=utf-8' );
    //             header( 'Content-Disposition: attachment; filename="bulk-form-submissions.csv"' );

    //             // Output the contents of the file to the browser safely
    //             echo wp_kses_post( $wp_filesystem->get_contents( $temp_file ) );

    //             // Delete the temporary file
    //             $wp_filesystem->delete( $temp_file );

    //             exit; // Terminate after file download
    //         } else {
    //             wp_die( esc_html__( 'Failed to write to temporary file.', 'form-submissions-manager' ) );
    //         }
    //     } else {
    //         wp_die( esc_html__( 'Failed to create temporary file.', 'form-submissions-manager' ) );
    //     }
    // }
    // public static function handle_bulk_export() {
    //     check_admin_referer( 'fsc_nonce', 'nonce' );

    //     // Sanitize and validate submission IDs
    //     $submission_ids = isset( $_POST['submission_ids'] ) ? array_map( 'intval', explode( ',', sanitize_text_field( $_POST['submission_ids'] ) ) ) : array();

    //     if ( empty( $submission_ids ) ) {
    //         wp_die( esc_html__( 'No submissions selected for export.', 'form-submissions-manager' ) );
    //     }

    //     global $wpdb;
    //     $table_name = $wpdb->prefix . 'form_submissions';

    //     // Prepare the query using placeholders for secure SQL construction
    //     $placeholders = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );
    //     $query = $wpdb->prepare( "SELECT * FROM $table_name WHERE id IN ($placeholders)", $submission_ids );
    //     $submissions = $wpdb->get_results("{$query}", ARRAY_A );

    //     if ( empty( $submissions ) ) {
    //         wp_die( esc_html__( 'No submissions found for export.', 'form-submissions-manager' ) );
    //     }

    //     // Set headers to prompt file download
    //     header( 'Content-Type: text/csv; charset=utf-8' );
    //     header( 'Content-Disposition: attachment; filename="bulk-form-submissions.csv"' );

    //     // Open output stream
    //     $output = fopen( 'php://output', 'w' );

    //     // Add CSV column headers, ensuring safe output
    //     fputcsv( $output, array( 'ID', 'Form Plugin', 'Form ID', 'Field Name', 'Field Value', 'Date Submitted' ) );

    //     // Iterate through each submission and add rows to the CSV
    //     foreach ( $submissions as $submission ) {
    //         $form_data = maybe_unserialize( $submission['submission_data'] );

    //         if ( is_array( $form_data ) ) {
    //             if ( $submission['form_plugin'] === 'wpforms' ) {
    //                 // Handle WPForms submissions
    //                 foreach ( $form_data as $field ) {
    //                     if ( isset( $field['name'] ) && isset( $field['value'] ) ) {
    //                         $row = array(
    //                             esc_html( $submission['id'] ),
    //                             esc_html( $submission['form_plugin'] ),
    //                             esc_html( $submission['form_id'] ),
    //                             esc_html( $field['name'] ),
    //                             esc_html( is_array( $field['value'] ) ? implode( ', ', $field['value'] ) : $field['value'] ),
    //                             esc_html( $submission['date_submitted'] )
    //                         );
    //                         fputcsv( $output, $row );
    //                     }
    //                 }
    //             } else {
    //                 // Handle other form plugins like Contact Form 7
    //                 foreach ( $form_data as $key => $value ) {
    //                     $row = array(
    //                         esc_html( $submission['id'] ),
    //                         esc_html( $submission['form_plugin'] ),
    //                         esc_html( $submission['form_id'] ),
    //                         esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ),
    //                         esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ),
    //                         esc_html( $submission['date_submitted'] )
    //                     );
    //                     fputcsv( $output, $row );
    //                 }
    //             }
    //         }
    //     }

    //     // Close the output stream
    //     fclose( $output );

    //     exit; // Terminate after file download
    // }




    public static function handle_bulk_email()
    {
        check_ajax_referer('fsc_nonce', 'nonce');

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
add_action('wp_ajax_fsc_filter_submissions', array('FSC_Ajax_Handler', 'handle_form_submission_filter'));
add_action('wp_ajax_fsc_delete_submission', array('FSC_Ajax_Handler', 'handle_submission_deletion'));
add_action('wp_ajax_fsc_send_email', array('FSC_Ajax_Handler', 'handle_send_email'));
add_action('wp_ajax_fsc_bulk_delete', array('FSC_Ajax_Handler', 'handle_bulk_delete'));
add_action('wp_ajax_fsc_bulk_export', array('FSC_Ajax_Handler', 'handle_bulk_export'));
add_action('wp_ajax_fsc_bulk_email', array('FSC_Ajax_Handler', 'handle_bulk_email'));
