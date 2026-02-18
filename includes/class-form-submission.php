<?php

namespace FSCMNGR\Includes;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use FSCMNGR\Includes\FSCMNGR_Form_Detection;

class FSCMNGR_Form_Submission
{
    /**
     * Display the submissions table in the admin page
     */
    public static function fscmngr_display_submissions_table()
    {
        // Fetch all available forms
        $available_forms = FSCMNGR_Form_Detection::fscmngr_get_available_forms();
        $installed_plugins = FSCMNGR_Form_Detection::fscmngr_detect_form_plugins(); ?>
        <form id="fsc-filter-form" method="post" action="">

            <!-- Nonce field for security -->
            <?php wp_nonce_field('fscmngr_export_csv_nonce', 'fscmngr_export_csv_nonce'); ?>

            <div class="fsc-row">
                <!-- First Row -->
                <div class="fsc-field">
                    <label for="form_plugin"><?php esc_html_e('Select Form Plugin:', 'form-submissions-manager'); ?></label>
                    <select name="form_plugin" id="form_plugin">
                        <option value=""><?php esc_html_e('All Form Plugins', 'form-submissions-manager'); ?></option>
                        <?php foreach ($installed_plugins as $plugin_slug => $plugin_name): ?>
                            <option value="<?php echo esc_attr($plugin_slug); ?>"><?php echo esc_html($plugin_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fsc-field">
                    <label for="form_id"><?php esc_html_e('Select Form:', 'form-submissions-manager'); ?></label>
                    <select name="form_id" id="form_id">
                        <option value=""><?php esc_html_e('All Forms', 'form-submissions-manager'); ?></option>
                        <?php foreach ($available_forms as $plugin_slug => $forms): ?>
                            <?php foreach ($forms as $form): ?>
                                <option value="<?php echo esc_attr($form['id']); ?>" data-plugin="<?php echo esc_attr($plugin_slug); ?>"><?php echo esc_html($form['title']); ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fsc-field">
                    <label for="start_date"><?php esc_html_e('Start Date:', 'form-submissions-manager'); ?></label>
                    <input type="date" name="start_date" id="start_date" />
                </div>

                <div class="fsc-field">
                    <label for="end_date"><?php esc_html_e('End Date:', 'form-submissions-manager'); ?></label>
                    <input type="date" name="end_date" id="end_date" />
                </div>
                <div class="fsc-field">
                    <label for="keyword"><?php esc_html_e('Keyword:', 'form-submissions-manager'); ?></label>
                    <input type="text" name="keyword" id="keyword" placeholder="<?php esc_attr_e('Search keyword...', 'form-submissions-manager'); ?>" />
                </div>
                
                <div class="fsc-buttons">
                    <button type="submit"><?php esc_html_e('Filter', 'form-submissions-manager'); ?></button>
                    <button type="submit" name="fscmngr_export_csv" value="1"><?php esc_html_e('Export CSV', 'form-submissions-manager'); ?></button>
                    <button type="button" id="clear-filters"><?php esc_html_e('Clear All Filters', 'form-submissions-manager'); ?></button>
                </div>
            </div>
        </form>


        <?php 
        //alert box
        echo '<div id="fsc-notification" class="fsc-notification" style="display:none;"></div>';

        //loader
        echo '<div id="fsc-loader" class="fsc-loader" style="display:none;"></div>';

        // Display the table of submissions
        echo '<div id="fsc-submissions-table">';
        self::fscmngr_fetch_and_display_submissions();
        echo '</div>';
?>

        <!-- Email Modal -->
        <div id="fsc-email-modal" class="fsc-modal" style="display:none;">
            <div class="fsc-modal-content">
                <span class="fsc-modal-close">&times;</span>
                <h2><?php esc_html_e('Send Submission via Email', 'form-submissions-manager'); ?></h2>
                <form id="fsc-email-form">
                    <label for="fsc-email-addresses"><?php esc_html_e('Enter email addresses (comma separated):', 'form-submissions-manager'); ?></label>
                    <input type="text" id="fsc-email-addresses" name="email_addresses" style="width: 100%; padding: 8px;" required />
                    <input type="hidden" id="fsc-email-submission-id" name="submission_id" />
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Send Email', 'form-submissions-manager'); ?>
                    </button>

                </form>
            </div>
        </div>
<?php
    }

    /**
     * Fetch and display the filtered submissions
     */
    public static function fscmngr_fetch_and_display_submissions()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Get pagination parameters
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 10; // Number of items per page
        $offset = ($page - 1) * $per_page;

        // Get filtering parameters
        $form_plugin = isset($_POST['form_plugin']) ? sanitize_text_field($_POST['form_plugin']) : '';
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

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

        if (!empty($start_date)) {
            $query .= " AND date_submitted >= %s";
            $query_params[] = $start_date . ' 00:00:00'; // Start of the day
        }

        if (!empty($end_date)) {
            $query .= " AND date_submitted <= %s";
            $query_params[] = $end_date . ' 23:59:59'; // End of the day
        }

        // Add keyword filtering
        if (!empty($keyword)) {
            $query .= " AND submission_data LIKE %s";
            $query_params[] = '%' . $wpdb->esc_like($keyword) . '%';
        }

        $query .= " LIMIT %d OFFSET %d";
        $query_params[] = $per_page;
        $query_params[] = $offset;

        // Execute the query
        $submissions = $wpdb->get_results($wpdb->prepare("{$query}", $query_params), ARRAY_A);

        // Display the data in a table
        if (!empty($submissions)) {
            echo '<form id="fsc-bulk-actions-form" method="post">';
            echo '<table>';
            echo '<thead><tr>';
            echo '<th><input type="checkbox" id="fsc-select-all"></th>';
            echo '<th>' . esc_html__('ID', 'form-submissions-manager') . '</th>';
            echo '<th>' . esc_html__('Form Plugin', 'form-submissions-manager') . '</th>';
            echo '<th>' . esc_html__('Form ID', 'form-submissions-manager') . '</th>';
            echo '<th>' . esc_html__('Submission Data', 'form-submissions-manager') . '</th>';
            echo '<th>' . esc_html__('Date Submitted', 'form-submissions-manager') . '</th>';
            echo '<th>' . esc_html__('Actions', 'form-submissions-manager') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($submissions as $submission) {
                echo '<tr>';
                echo '<td><input type="checkbox" class="fsc-select-item" name="selected_submissions[]" value="' . esc_attr($submission['id']) . '"></td>';
                echo '<td>' . esc_html($submission['id']) . '</td>';
                echo '<td>' . esc_html($submission['form_plugin']) . '</td>';
                echo '<td>' . esc_html($submission['form_id']) . '</td>';
                $form_data = maybe_unserialize($submission['submission_data']);

                // Check if it's a valid array
                if (is_array($form_data) && !empty($form_data)) {
                    echo '<td><div class="fsc-submission-data-wrapper"><table class="fsc-submission-data-table">';

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
                    echo '</div></table></td>';
                } else {
                    echo '<td>' . esc_html__('No data available', 'form-submissions-manager') . '</td>';
                }


                echo '<td>' . esc_html($submission['date_submitted']) . '</td>';
                echo '<td>
        <button class="fsc-email-submission" data-id="' . esc_attr($submission['id']) . '">
            <span class="dashicons dashicons-email"></span> ' . esc_html__('Email', 'form-submissions-manager') . '
        </button>
        <button class="fsc-delete-submission" data-id="' . esc_attr($submission['id']) . '">
            <span class="dashicons dashicons-trash"></span> ' . esc_html__('Delete', 'form-submissions-manager') . '
        </button>
      </td>';


                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</form>';
        } else {
            echo '<p>' . esc_html__('No submissions found', 'form-submissions-manager') . '</p>';
        }

        self::fscmngr_display_pagination($page, $per_page);
    }

    /**
     * Display pagination links
     */
    public static function fscmngr_display_pagination($current_page, $per_page)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Get total number of submissions
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        $total_pages = ceil($total_items / $per_page);

        // Display pagination links
        if ($total_pages > 1) {
            echo '<div class="tablenav-pages">';
            echo wp_kses_post(paginate_links(array(
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'current' => $current_page,
                'total'   => $total_pages,
                'prev_text' => esc_html__('&laquo; Previous', 'form-submissions-manager'),
                'next_text' => esc_html__('Next &raquo;', 'form-submissions-manager'),
            )));
            echo '</div>';
        }
    }


    /**
     * Handle the CSV export functionality
     */
    public static function fscmngr_handle_csv_export()
    {
        // Check if the CSV export was requested
        if (isset($_POST['fscmngr_export_csv']) && $_POST['fscmngr_export_csv'] == '1') {

            // Verify nonce for security
            if (
                !isset($_POST['fscmngr_export_csv_nonce']) ||
                !is_string($_POST['fscmngr_export_csv_nonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fscmngr_export_csv_nonce'])), 'fscmngr_export_csv_nonce')
            ) {
                wp_die(__('Nonce verification failed.', 'form-submissions-manager'));
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
            $submissions = $wpdb->get_results($wpdb->prepare("{$query}", $query_params), ARRAY_A);

            if (empty($submissions)) {
                wp_die(esc_html__('No submissions found for export.', 'form-submissions-manager'));
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

    /**
     * Get submissions data for shortcode usage
     *
     * @param string $form_plugin Form plugin slug
     * @param int    $form_id     Form ID
     * @return array Array of submission data
     */
    public static function get_submissions_data($form_plugin = '', $form_id = 0)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Build query
        $query = "SELECT * FROM $table_name WHERE 1=1";
        $query_params = array();

        if (!empty($form_plugin)) {
            $query .= " AND form_plugin = %s";
            $query_params[] = sanitize_text_field($form_plugin);
        }

        if (!empty($form_id)) {
            $query .= " AND form_id = %d";
            $query_params[] = intval($form_id);
        }

        $query .= " ORDER BY date_submitted DESC";

        // Execute the query
        $submissions = $wpdb->get_results($wpdb->prepare("{$query}", $query_params), ARRAY_A);

        $results = array();
        foreach ($submissions as $submission) {
            $form_data = maybe_unserialize($submission['submission_data']);
            $results[] = array(
                'id' => $submission['id'],
                'form_plugin' => $submission['form_plugin'],
                'form_id' => $submission['form_id'],
                'data' => $form_data,
                'date_submitted' => $submission['date_submitted']
            );
        }

        return $results;
    }
}
// Register the CSV export handler
add_action('admin_init', array('FSCMNGR\Includes\FSCMNGR_Form_Submission', 'fscmngr_handle_csv_export'));
