<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FSC_Form_Submission
{
    /**
     * Display the submissions table in the admin page
     */
    public static function display_submissions_table()
    {
        // Fetch all available forms
        $available_forms = FSC_Form_Detection::get_available_forms();
        $installed_plugins = FSC_Form_Detection::detect_form_plugins();

        echo '<form id="fsc-filter-form" method="post" action="">';

        // Nonce field for security
        wp_nonce_field('fsc_export_csv_nonce', 'fsc_export_csv_nonce');

        // Form plugin selection dropdown
        echo '<label for="form_plugin">' . __('Select Form Plugin:', 'form-submission-capture') . '</label>';
        echo '<select name="form_plugin" id="form_plugin">';
        echo '<option value="">' . __('All Form Plugins', 'form-submission-capture') . '</option>';
        foreach ($installed_plugins as $plugin_slug => $plugin_name) {
            echo '<option value="' . esc_attr($plugin_slug) . '">' . esc_html($plugin_name) . '</option>';
        }
        echo '</select>';

        // Form selection dropdown
        // echo '<label for="form_id">' . __('Select Form:', 'form-submission-capture') . '</label>';
        // echo '<select name="form_id" id="form_id">';
        // echo '<option value="">' . __('All Forms', 'form-submission-capture') . '</option>';
        // foreach ($available_forms as $plugin_slug => $forms) {
        //     foreach ($forms as $form) {
        //         echo '<option value="' . esc_attr($form['id']) . '" data-plugin="' . esc_attr($plugin_slug) . '">' . esc_html($form['title']) . '</option>';
        //     }
        // }
        // echo '</select>';
        // Form selection dropdown
        echo '<label for="form_id">' . __('Select Form:', 'form-submission-capture') . '</label>';
        echo '<select name="form_id" id="form_id">';
        echo '<option value="">' . __('All Forms', 'form-submission-capture') . '</option>';
        foreach ($available_forms as $plugin_slug => $forms) {
            foreach ($forms as $form) {
                echo '<option value="' . esc_attr($form['id']) . '" data-plugin="' . esc_attr($plugin_slug) . '">' . esc_html($form['title']) . '</option>';
            }
        }
        echo '</select>';


        echo '<button type="submit">' . __('Filter', 'form-submission-capture') . '</button>';

        // Export CSV button
        echo '<button type="submit" name="fsc_export_csv" value="1">' . __('Export CSV', 'form-submission-capture') . '</button>';

        echo '</form>';

        // Display the table of submissions
        echo '<div id="fsc-submissions-table">';
        self::fetch_and_display_submissions();
        echo '</div>';
?>

        <!-- Email Modal -->
        <div id="fsc-email-modal" class="fsc-modal" style="display:none;">
            <div class="fsc-modal-content">
                <span class="fsc-modal-close">&times;</span>
                <h2><?php _e('Send Submission via Email', 'form-submission-capture'); ?></h2>
                <form id="fsc-email-form">
                    <label for="fsc-email-addresses"><?php _e('Enter email addresses (comma separated):', 'form-submission-capture'); ?></label>
                    <input type="text" id="fsc-email-addresses" name="email_addresses" style="width: 100%; padding: 8px;" required />
                    <input type="hidden" id="fsc-email-submission-id" name="submission_id" />
                    <button type="submit" class="button button-primary"><?php _e('Send Email', 'form-submission-capture'); ?></button>
                </form>
            </div>
        </div>
<?php
    }

    /**
     * Fetch and display the filtered submissions
     */
    public static function fetch_and_display_submissions()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Get filtering parameters
        $form_plugin = isset($_POST['form_plugin']) ? sanitize_text_field($_POST['form_plugin']) : '';
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        // Build query
        $query = "SELECT * FROM $table_name WHERE 1=1";
        $query_params = array();

        if ($form_plugin) {
            $query .= " AND form_plugin = %s";
            $query_params[] = $form_plugin;
        }

        if ($form_id) {
            $query .= " AND form_id = %d";
            $query_params[] = $form_id;
        }

        // Execute the query
        $submissions = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);

        // Display the data in a table
        if (!empty($submissions)) {
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>' . __('ID', 'form-submission-capture') . '</th>';
            echo '<th>' . __('Form Plugin', 'form-submission-capture') . '</th>';
            echo '<th>' . __('Form ID', 'form-submission-capture') . '</th>';
            echo '<th>' . __('Submission Data', 'form-submission-capture') . '</th>';
            echo '<th>' . __('Date Submitted', 'form-submission-capture') . '</th>';
            echo '<th>' . __('Actions', 'form-submission-capture') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($submissions as $submission) {
                echo '<tr>';
                echo '<td>' . esc_html($submission['id']) . '</td>';
                echo '<td>' . esc_html($submission['form_plugin']) . '</td>';
                echo '<td>' . esc_html($submission['form_id']) . '</td>';
                //echo '<td><pre>' . esc_html( print_r( maybe_unserialize( $submission['submission_data'] ), true ) ) . '</pre></td>';
                // Unserialize the submission data
                // Unserialize the submission data
                $form_data = maybe_unserialize($submission['submission_data']);

                // Check if it's a valid array
                if (is_array($form_data) && !empty($form_data)) {
                    echo '<td><table class="fsc-submission-data-table">';

                    // Check if the data is from WPForms
                    if ($submission['form_plugin'] === 'wpforms') {
                        // WPForms data handling
                        foreach ($form_data as $field) {
                            // Display only fields that have a label and value
                            if (isset($field['name']) && isset($field['value'])) {
                                echo '<tr>';
                                echo '<th style="text-align: left; padding-right: 10px;">' . esc_html($field['name']) . ':</th>';

                                // Handle multi-value fields (arrays) gracefully
                                if (is_array($field['value'])) {
                                    echo '<td>' . esc_html(implode(', ', $field['value'])) . '</td>';
                                } else {
                                    echo '<td>' . esc_html($field['value']) . '</td>';
                                }
                                echo '</tr>';
                            }
                        }
                    } else {
                        // Handle Contact Form 7 and other simple forms
                        foreach ($form_data as $key => $value) {
                            echo '<tr>';
                            echo '<th style="text-align: left; padding-right: 10px;">' . esc_html(ucfirst(str_replace('_', ' ', $key))) . ':</th>';

                            // Handle multi-value fields (arrays) gracefully
                            if (is_array($value)) {
                                echo '<td>' . esc_html(implode(', ', $value)) . '</td>';
                            } else {
                                echo '<td>' . esc_html($value) . '</td>';
                            }
                            echo '</tr>';
                        }
                    }
                    echo '</table></td>';
                } else {
                    echo '<td>' . __('No data available', 'form-submission-capture') . '</td>';
                }


                echo '<td>' . esc_html($submission['date_submitted']) . '</td>';
                echo '<td>
        <button class="fsc-email-submission" data-id="' . esc_attr($submission['id']) . '">
            <span class="dashicons dashicons-email"></span> ' . __('Email', 'form-submission-capture') . '
        </button>
        <button class="fsc-delete-submission" data-id="' . esc_attr($submission['id']) . '">
            <span class="dashicons dashicons-trash"></span> ' . __('Delete', 'form-submission-capture') . '
        </button>
      </td>';


                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . __('No submissions found', 'form-submission-capture') . '</p>';
        }
    }

    /**
     * Handle the CSV export functionality
     */
    public static function handle_csv_export()
    {
        // Check if the CSV export was requested
        if (isset($_POST['fsc_export_csv']) && $_POST['fsc_export_csv'] == '1') {

            // Verify nonce for security
            if (!isset($_POST['fsc_export_csv_nonce']) || !wp_verify_nonce($_POST['fsc_export_csv_nonce'], 'fsc_export_csv_nonce')) {
                return;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'form_submissions';

            // Get filtering parameters
            $form_plugin = isset($_POST['form_plugin']) ? sanitize_text_field($_POST['form_plugin']) : '';
            $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

            // Build the query
            $query = "SELECT * FROM $table_name WHERE 1=1";
            $query_params = array();

            if ($form_plugin) {
                $query .= " AND form_plugin = %s";
                $query_params[] = $form_plugin;
            }

            if ($form_id) {
                $query .= " AND form_id = %d";
                $query_params[] = $form_id;
            }

            // Retrieve the submissions
            $submissions = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);

            if (empty($submissions)) {
                wp_die(__('No submissions found for export.', 'form-submission-capture'));
            }

            // Set headers to force download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=form_submissions.csv');

            // Open output stream
            $output = fopen('php://output', 'w');

            // Add CSV column headers
            fputcsv($output, array('ID', 'Form Plugin', 'Form ID', 'Field Name', 'Field Value', 'Date Submitted'));

            // Iterate through each submission and add rows
            foreach ($submissions as $submission) {
                $form_data = maybe_unserialize($submission['submission_data']);

                if (is_array($form_data)) {
                    if ($submission['form_plugin'] === 'wpforms') {
                        // WPForms handling
                        foreach ($form_data as $field) {
                            if (isset($field['name']) && isset($field['value'])) {
                                $row = array(
                                    $submission['id'],
                                    $submission['form_plugin'],
                                    $submission['form_id'],
                                    $field['name'],
                                    is_array($field['value']) ? implode(', ', $field['value']) : $field['value'],
                                    $submission['date_submitted']
                                );
                                fputcsv($output, $row);
                            }
                        }
                    } else {
                        // Handling for Contact Form 7 or other forms
                        foreach ($form_data as $key => $value) {
                            $row = array(
                                $submission['id'],
                                $submission['form_plugin'],
                                $submission['form_id'],
                                ucfirst(str_replace('_', ' ', $key)),
                                is_array($value) ? implode(', ', $value) : $value,
                                $submission['date_submitted']
                            );
                            fputcsv($output, $row);
                        }
                    }
                }
            }

            // Close the output stream
            fclose($output);

            // Ensure no other output
            exit;
        }
    }
}
// Register the CSV export handler
add_action('admin_init', array('FSC_Form_Submission', 'handle_csv_export'));
