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
                <?php
                // Get current filter values from GET (for pagination) or POST (for initial filter)
                $current_form_plugin = isset($_GET['form_plugin']) ? sanitize_text_field($_GET['form_plugin']) : (isset($_POST['form_plugin']) ? sanitize_text_field($_POST['form_plugin']) : '');
                $current_form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : (isset($_POST['form_id']) ? intval($_POST['form_id']) : 0);
                $current_start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : (isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '');
                $current_end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : (isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '');
                $current_keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : (isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '');
                ?>
                <div class="fsc-field">
                    <label for="form_plugin"><?php esc_html_e('Select Form Plugin:', 'form-submissions-manager'); ?></label>
                    <select name="form_plugin" id="form_plugin">
                        <option value=""><?php esc_html_e('All Form Plugins', 'form-submissions-manager'); ?></option>
                        <?php foreach ($installed_plugins as $plugin_slug => $plugin_name): ?>
                            <option value="<?php echo esc_attr($plugin_slug); ?>" <?php selected($current_form_plugin, $plugin_slug); ?>><?php echo esc_html($plugin_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fsc-field">
                    <label for="form_id"><?php esc_html_e('Select Form:', 'form-submissions-manager'); ?></label>
                    <select name="form_id" id="form_id">
                        <option value=""><?php esc_html_e('All Forms', 'form-submissions-manager'); ?></option>
                        <?php foreach ($available_forms as $plugin_slug => $forms): ?>
                            <?php foreach ($forms as $form): ?>
                                <option value="<?php echo esc_attr($form['id']); ?>" data-plugin="<?php echo esc_attr($plugin_slug); ?>" <?php selected($current_form_id, $form['id']); ?>><?php echo esc_html($form['title']); ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fsc-field">
                    <label for="start_date"><?php esc_html_e('Start Date:', 'form-submissions-manager'); ?></label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($current_start_date); ?>" />
                </div>

                <div class="fsc-field">
                    <label for="end_date"><?php esc_html_e('End Date:', 'form-submissions-manager'); ?></label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($current_end_date); ?>" />
                </div>
                <div class="fsc-field">
                    <label for="keyword"><?php esc_html_e('Keyword:', 'form-submissions-manager'); ?></label>
                    <input type="text" name="keyword" id="keyword" placeholder="<?php esc_attr_e('Search keyword...', 'form-submissions-manager'); ?>" value="<?php echo esc_attr($current_keyword); ?>" />
                </div>
                
                <div class="fsc-buttons">
                    <button type="submit"><?php esc_html_e('Filter', 'form-submissions-manager'); ?></button>
                    <button type="submit" name="fscmngr_export_csv" value="1"><?php esc_html_e('Export CSV', 'form-submissions-manager'); ?></button>
                    <button type="button" id="fsc-export-form-data" class="button" disabled title="<?php esc_attr_e('Select a specific form to export form data as columns', 'form-submissions-manager'); ?>">
                        <?php esc_html_e('Export Form Data CSV', 'form-submissions-manager'); ?>
                        <span id="fsc-field-count" style="display: none; margin-left: 5px; font-size: 11px; opacity: 0.7;"></span>
                    </button>
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

        // Get filtering parameters from both POST (AJAX/filter submit) and GET (pagination)
        $form_plugin = isset($_POST['form_plugin']) ? sanitize_text_field($_POST['form_plugin']) : (isset($_GET['form_plugin']) ? sanitize_text_field($_GET['form_plugin']) : '');
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : (isset($_GET['form_id']) ? intval($_GET['form_id']) : 0);
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : (isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '');
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : (isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '');
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : (isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '');
        
        // Check if this is a new filter request (not pagination)
        // A new filter request is when POST data is sent but no paged parameter is in POST
        // Pagination requests will have paged in POST, so we should use that page number
        $is_new_filter_request = (isset($_POST['form_plugin']) || isset($_POST['form_id']) || isset($_POST['start_date']) || isset($_POST['end_date']) || isset($_POST['keyword'])) && !isset($_POST['paged']);
        
        // Get pagination parameters
        // If paged is in POST (from AJAX pagination), use it
        // If paged is in GET (from direct URL), use it
        // Otherwise default to page 1
        if (isset($_POST['paged'])) {
            $page = max(1, intval($_POST['paged']));
        } elseif (isset($_GET['paged'])) {
            $page = max(1, intval($_GET['paged']));
        } else {
            $page = $is_new_filter_request ? 1 : (isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1);
        }
        $per_page = 10; // Number of items per page
        $offset = ($page - 1) * $per_page;

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

        $query .= " ORDER BY date_submitted DESC LIMIT %d OFFSET %d";
        $query_params[] = $per_page;
        $query_params[] = $offset;

        // Execute the query
        $submissions = $wpdb->get_results($wpdb->prepare("{$query}", $query_params), ARRAY_A);

        // Display the data in a table
        if (!empty($submissions)) {
            echo '<form id="fsc-bulk-actions-form" method="post">';
            wp_nonce_field('fscmngr_bulk_actions_nonce', 'fscmngr_bulk_actions_nonce');
            echo '<div class="fsc-selected-actions" style="margin-bottom: 15px; display: none;">';
            echo '<span class="fsc-selected-count" style="margin-right: 10px; font-weight: bold;"></span>';
            echo '<div class="fsc-actions-dropdown" style="display: inline-block; position: relative;">';
            echo '<button type="button" id="fsc-actions-button" class="button button-primary" style="position: relative; padding-right: 25px;">';
            echo esc_html__('Actions', 'form-submissions-manager') . ' <span style="margin-left: 5px;">▼</span>';
            echo '</button>';
            echo '<div id="fsc-actions-menu" class="fsc-actions-menu" style="display: none; position: absolute; top: 100%; left: 0; background: #fff; border: 1px solid #ccc; border-radius: 3px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; min-width: 150px; margin-top: 2px;">';
            echo '<a href="#" class="fsc-action-item" data-action="delete" style="display: block; padding: 8px 12px; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">';
            echo '<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-right: 5px;"></span> ' . esc_html__('Delete Selected', 'form-submissions-manager');
            echo '</a>';
            echo '<a href="#" class="fsc-action-item" data-action="export" style="display: block; padding: 8px 12px; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">';
            echo '<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span> ' . esc_html__('Export Selected', 'form-submissions-manager');
            echo '</a>';
            echo '<a href="#" class="fsc-action-item" data-action="email" style="display: block; padding: 8px 12px; text-decoration: none; color: #333;">';
            echo '<span class="dashicons dashicons-email" style="vertical-align: middle; margin-right: 5px;"></span> ' . esc_html__('Email Selected', 'form-submissions-manager');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
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

                // Validate unserialized data
                if ($form_data === false && $submission['submission_data'] !== serialize(false)) {
                    $form_data = array(); // Set to empty array if unserialize failed
                }

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

        // Get filtering parameters from both POST and GET (same as in fscmngr_fetch_and_display_submissions)
        $form_plugin = isset($_POST['form_plugin']) ? sanitize_text_field($_POST['form_plugin']) : (isset($_GET['form_plugin']) ? sanitize_text_field($_GET['form_plugin']) : '');
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : (isset($_GET['form_id']) ? intval($_GET['form_id']) : 0);
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : (isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '');
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : (isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '');
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : (isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '');

        // Build count query with same filters
        $count_query = "SELECT COUNT(*) FROM $table_name WHERE 1=1";
        $count_params = array();

        if ($form_plugin) {
            $count_query .= " AND form_plugin = %s";
            $count_params[] = $form_plugin;
        }

        if ($form_id) {
            $count_query .= " AND form_id = %d";
            $count_params[] = $form_id;
        }

        if (!empty($start_date)) {
            $count_query .= " AND date_submitted >= %s";
            $count_params[] = $start_date . ' 00:00:00';
        }

        if (!empty($end_date)) {
            $count_query .= " AND date_submitted <= %s";
            $count_params[] = $end_date . ' 23:59:59';
        }

        if (!empty($keyword)) {
            $count_query .= " AND submission_data LIKE %s";
            $count_params[] = '%' . $wpdb->esc_like($keyword) . '%';
        }

        // Get total number of submissions with filters applied
        if (!empty($count_params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $count_params));
        } else {
            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        }

        $total_pages = ceil($total_items / $per_page);

        // Display pagination links
        if ($total_pages > 1) {
            // Build base URL with filter parameters
            $base_args = array();
            if (!empty($form_plugin)) {
                $base_args['form_plugin'] = $form_plugin;
            }
            if (!empty($form_id)) {
                $base_args['form_id'] = $form_id;
            }
            if (!empty($start_date)) {
                $base_args['start_date'] = $start_date;
            }
            if (!empty($end_date)) {
                $base_args['end_date'] = $end_date;
            }
            if (!empty($keyword)) {
                $base_args['keyword'] = $keyword;
            }
            $base_args['paged'] = '%#%';
            
            $base_url = add_query_arg($base_args, admin_url('admin.php?page=fsc-form-submissions'));
            
            echo '<div class="fsc-tablenav">';
            echo '<div class="fsc-tablenav-pages">';
            echo '<span class="fsc-displaying-num">' . sprintf(
                esc_html__('Displaying %1$d-%2$d of %3$d items', 'form-submissions-manager'),
                ($current_page - 1) * $per_page + 1,
                min($current_page * $per_page, $total_items),
                $total_items
            ) . '</span>';
            echo wp_kses_post(paginate_links(array(
                'base'    => $base_url,
                'format'  => '',
                'current' => $current_page,
                'total'   => $total_pages,
                'prev_text' => esc_html__('&laquo;', 'form-submissions-manager'),
                'next_text' => esc_html__('&raquo;', 'form-submissions-manager'),
            )));
            echo '</div>';
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
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
            $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

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

            if (!empty($start_date)) {
                $query .= " AND date_submitted >= %s";
                $query_params[] = $start_date . ' 00:00:00';
            }

            if (!empty($end_date)) {
                $query .= " AND date_submitted <= %s";
                $query_params[] = $end_date . ' 23:59:59';
            }

            if (!empty($keyword)) {
                $query .= " AND submission_data LIKE %s";
                $query_params[] = '%' . $wpdb->esc_like($keyword) . '%';
            }

            $query .= " ORDER BY date_submitted DESC";

            // Retrieve the submissions
            if (!empty($query_params)) {
                $submissions = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
            } else {
                $submissions = $wpdb->get_results($query, ARRAY_A);
            }

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

                // Validate unserialized data
                if ($form_data === false && $submission['submission_data'] !== serialize(false)) {
                    continue; // Skip invalid data
                }

                if (is_array($form_data) && !empty($form_data)) {
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
        if (!empty($query_params)) {
            $submissions = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
        } else {
            $submissions = $wpdb->get_results($query, ARRAY_A);
        }

        $results = array();
        foreach ($submissions as $submission) {
            $form_data = maybe_unserialize($submission['submission_data']);
            
            // Validate unserialized data
            if ($form_data === false && $submission['submission_data'] !== serialize(false)) {
                $form_data = array(); // Set to empty array if unserialize failed
            }
            
            $results[] = array(
                'id' => $submission['id'],
                'form_plugin' => $submission['form_plugin'],
                'form_id' => $submission['form_id'],
                'data' => is_array($form_data) ? $form_data : array(),
                'date_submitted' => $submission['date_submitted']
            );
        }

        return $results;
    }

    /**
     * Handle bulk CSV export functionality
     */
    public static function fscmngr_handle_bulk_csv_export()
    {
        // Check if the bulk CSV export was requested
        if (isset($_POST['action']) && $_POST['action'] === 'fsc_bulk_export') {

            // Verify nonce for security
            if (
                !isset($_POST['nonce']) ||
                !is_string($_POST['nonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fscmngr_nonce')
            ) {
                wp_die(__('Nonce verification failed.', 'form-submissions-manager'));
            }

            // Check user capability
            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions.', 'form-submissions-manager'));
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'form_submissions';

            // Get submission IDs
            $submission_ids = isset($_POST['submission_ids']) ? sanitize_text_field($_POST['submission_ids']) : '';
            
            if (empty($submission_ids)) {
                wp_die(esc_html__('No submissions selected for export.', 'form-submissions-manager'));
            }

            // Convert comma-separated string to array and validate
            $ids_array = array_filter(
                array_map('intval', explode(',', $submission_ids)),
                function($id) { return $id > 0; }
            );

            if (empty($ids_array)) {
                wp_die(esc_html__('Invalid submission IDs.', 'form-submissions-manager'));
            }

            // Build query to get selected submissions
            $placeholders = implode(',', array_fill(0, count($ids_array), '%d'));
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id IN ($placeholders) ORDER BY date_submitted DESC",
                $ids_array
            );
            $submissions = $wpdb->get_results($query, ARRAY_A);

            if (empty($submissions)) {
                wp_die(esc_html__('No submissions found for export.', 'form-submissions-manager'));
            }

            // Set headers to force download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=form_submissions_bulk_export_' . date('Y-m-d_His') . '.csv');

            // Open output stream
            $output = fopen('php://output', 'w');

            // Add CSV column headers
            fputcsv($output, array('ID', 'Form Plugin', 'Form ID', 'Field Name', 'Field Value', 'Date Submitted'));

            // Iterate through each submission and add rows
            foreach ($submissions as $submission) {
                $form_data = maybe_unserialize($submission['submission_data']);

                // Validate unserialized data
                if ($form_data === false && $submission['submission_data'] !== serialize(false)) {
                    continue; // Skip invalid data
                }

                if (is_array($form_data) && !empty($form_data)) {
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
     * Handle form data CSV export (fields as columns)
     */
    public static function fscmngr_handle_form_data_csv_export()
    {
        // Check if the form data CSV export was requested
        if (isset($_POST['action']) && $_POST['action'] === 'fscmngr_export_form_data_csv') {

            // Verify nonce for security
            if (
                !isset($_POST['nonce']) ||
                !is_string($_POST['nonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fscmngr_nonce')
            ) {
                wp_die(__('Nonce verification failed.', 'form-submissions-manager'));
            }

            // Check user capability
            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions.', 'form-submissions-manager'));
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'form_submissions';

            // Get form ID - required for this export
            $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
            $form_plugin = isset($_POST['form_plugin']) ? sanitize_text_field($_POST['form_plugin']) : '';

            if (empty($form_id)) {
                wp_die(esc_html__('Please select a specific form to export form data.', 'form-submissions-manager'));
            }

            // Get filter parameters
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
            $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

            // Build query to get submissions for this form
            $query = "SELECT * FROM $table_name WHERE form_id = %d";
            $query_params = array($form_id);

            if (!empty($form_plugin)) {
                $query .= " AND form_plugin = %s";
                $query_params[] = $form_plugin;
            }

            if (!empty($start_date)) {
                $query .= " AND date_submitted >= %s";
                $query_params[] = $start_date . ' 00:00:00';
            }

            if (!empty($end_date)) {
                $query .= " AND date_submitted <= %s";
                $query_params[] = $end_date . ' 23:59:59';
            }

            if (!empty($keyword)) {
                $query .= " AND submission_data LIKE %s";
                $query_params[] = '%' . $wpdb->esc_like($keyword) . '%';
            }

            $query .= " ORDER BY date_submitted DESC";

            // Get all submissions for this form
            $submissions = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);

            if (empty($submissions)) {
                wp_die(esc_html__('No submissions found for export.', 'form-submissions-manager'));
            }

            // Discover all unique fields from submissions
            $all_fields = array();
            foreach ($submissions as $submission) {
                $form_data = maybe_unserialize($submission['submission_data']);

                // Validate unserialized data
                if ($form_data === false && $submission['submission_data'] !== serialize(false)) {
                    continue;
                }

                if (is_array($form_data) && !empty($form_data)) {
                    if ($submission['form_plugin'] === 'wpforms') {
                        // WPForms: extract field names
                        foreach ($form_data as $field) {
                            if (isset($field['name']) && !empty($field['name'])) {
                                $field_name = sanitize_text_field($field['name']);
                                if (!in_array($field_name, $all_fields)) {
                                    $all_fields[] = $field_name;
                                }
                            }
                        }
                    } else {
                        // Contact Form 7 and Gravity Forms: use keys as field names
                        foreach ($form_data as $key => $value) {
                            if (!in_array($key, $all_fields)) {
                                $all_fields[] = $key;
                            }
                        }
                    }
                }
            }

            // Check field limit (30 fields + 2 metadata = 32 total)
            $field_count = count($all_fields);
            if ($field_count > 30) {
                // Log warning but continue
                error_log(sprintf(
                    'Form Submissions Manager: Large form export detected. Form ID: %d has %d fields.',
                    $form_id,
                    $field_count
                ));
            }

            // Sort fields alphabetically for consistency
            sort($all_fields);

            // Get form name for filename
            $form_name = self::fscmngr_get_form_name($form_plugin, $form_id);
            $safe_form_name = sanitize_file_name($form_name);
            $filename = 'form_data_' . $safe_form_name . '_' . date('Y-m-d_His') . '.csv';

            // Set headers to force download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            // Open output stream
            $output = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Build header row: ID, Date, then all fields
            $headers = array('Submission ID', 'Date Submitted');
            foreach ($all_fields as $field) {
                $headers[] = self::fscmngr_sanitize_csv_header($field);
            }
            fputcsv($output, $headers);

            // Add data rows
            foreach ($submissions as $submission) {
                $form_data = maybe_unserialize($submission['submission_data']);

                // Validate unserialized data
                if ($form_data === false && $submission['submission_data'] !== serialize(false)) {
                    $form_data = array();
                }

                // Start row with metadata
                $row = array(
                    $submission['id'],
                    $submission['date_submitted']
                );

                // Add field values in the same order as headers
                foreach ($all_fields as $field) {
                    $value = self::fscmngr_get_field_value($form_data, $field, $submission['form_plugin']);
                    $row[] = $value;
                }

                fputcsv($output, $row);
            }

            // Close the output stream
            fclose($output);

            // Ensure no other output
            exit;
        }
    }

    /**
     * Get form name for filename
     */
    private static function fscmngr_get_form_name($form_plugin, $form_id)
    {
        $form_name = 'form-' . $form_id;

        if ($form_plugin === 'contact-form-7' && defined('WPCF7_VERSION')) {
            $form = get_post($form_id);
            if ($form) {
                $form_name = sanitize_file_name($form->post_title);
            }
        } elseif ($form_plugin === 'wpforms' && class_exists('WPForms')) {
            $form = wpforms()->form->get($form_id);
            if ($form && isset($form->post_title)) {
                $form_name = sanitize_file_name($form->post_title);
            }
        } elseif ($form_plugin === 'gravity-forms' && class_exists('GFForms')) {
            $form = GFAPI::get_form($form_id);
            if ($form && isset($form['title'])) {
                $form_name = sanitize_file_name($form['title']);
            }
        }

        return $form_name;
    }

    /**
     * Get field value from submission data
     */
    private static function fscmngr_get_field_value($form_data, $field_name, $form_plugin)
    {
        if (!is_array($form_data) || empty($form_data)) {
            return '';
        }

        if ($form_plugin === 'wpforms') {
            // WPForms: search for field by name
            foreach ($form_data as $field) {
                if (isset($field['name']) && $field['name'] === $field_name) {
                    if (isset($field['value'])) {
                        return self::fscmngr_normalize_field_value($field['value']);
                    }
                }
            }
        } else {
            // Contact Form 7 and Gravity Forms: use key directly
            if (isset($form_data[$field_name])) {
                return self::fscmngr_normalize_field_value($form_data[$field_name]);
            }
        }

        return '';
    }

    /**
     * Normalize field value for CSV export
     */
    private static function fscmngr_normalize_field_value($value)
    {
        if (is_array($value)) {
            // Handle multi-value fields (checkboxes, multi-select)
            return implode(', ', array_map('trim', $value));
        }

        if (is_object($value)) {
            // Handle objects (convert to string)
            return json_encode($value);
        }

        // Convert to string and trim
        return trim((string) $value);
    }

    /**
     * Sanitize field name for CSV header
     */
    private static function fscmngr_sanitize_csv_header($field_name)
    {
        // Replace underscores with spaces and capitalize words
        $header = str_replace('_', ' ', $field_name);
        $header = ucwords(strtolower($header));
        return $header;
    }
}
// Register the CSV export handlers
add_action('admin_init', array('FSCMNGR\Includes\FSCMNGR_Form_Submission', 'fscmngr_handle_csv_export'));
add_action('admin_init', array('FSCMNGR\Includes\FSCMNGR_Form_Submission', 'fscmngr_handle_bulk_csv_export'));
add_action('admin_init', array('FSCMNGR\Includes\FSCMNGR_Form_Submission', 'fscmngr_handle_form_data_csv_export'));