<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FSC_Form_Submission {
    /**
     * Display the submissions table in the admin page
     */
    public static function display_submissions_table() {
        // Fetch all available forms
        $available_forms = FSC_Form_Detection::get_available_forms();
        $installed_plugins = FSC_Form_Detection::detect_form_plugins();

        echo '<form id="fsc-filter-form">';
        
        // Form plugin selection dropdown
        echo '<label for="form_plugin">' . __( 'Select Form Plugin:', 'form-submission-capture' ) . '</label>';
        echo '<select name="form_plugin" id="form_plugin">';
        echo '<option value="">' . __( 'All Form Plugins', 'form-submission-capture' ) . '</option>';
        foreach ( $installed_plugins as $plugin_slug => $plugin_name ) {
            echo '<option value="' . esc_attr( $plugin_slug ) . '">' . esc_html( $plugin_name ) . '</option>';
        }
        echo '</select>';
        
        // Form selection dropdown
        echo '<label for="form_id">' . __( 'Select Form:', 'form-submission-capture' ) . '</label>';
        echo '<select name="form_id" id="form_id">';
        echo '<option value="">' . __( 'All Forms', 'form-submission-capture' ) . '</option>';
        foreach ( $available_forms as $plugin_slug => $forms ) {
            foreach ( $forms as $form ) {
                echo '<option value="' . esc_attr( $form['id'] ) . '" data-plugin="' . esc_attr( $plugin_slug ) . '">' . esc_html( $form['title'] ) . '</option>';
            }
        }
        echo '</select>';

        echo '<button type="submit">' . __( 'Filter', 'form-submission-capture' ) . '</button>';
        echo '</form>';

        // Display the table of submissions
        echo '<div id="fsc-submissions-table">';
        self::fetch_and_display_submissions();
        echo '</div>';
    }

    /**
     * Fetch and display the filtered submissions
     */
    public static function fetch_and_display_submissions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';

        // Get filtering parameters
        $form_plugin = isset($_POST['form_plugin']) ? sanitize_text_field($_POST['form_plugin']) : '';
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        // Build query
        $query = "SELECT * FROM $table_name WHERE 1=1";
        $query_params = array();

        if ( $form_plugin ) {
            $query .= " AND form_plugin = %s";
            $query_params[] = $form_plugin;
        }

        if ( $form_id ) {
            $query .= " AND form_id = %d";
            $query_params[] = $form_id;
        }

        // Execute the query
        $submissions = $wpdb->get_results( $wpdb->prepare( $query, $query_params ), ARRAY_A );

        // Display the data in a table
        if ( ! empty( $submissions ) ) {
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>' . __( 'ID', 'form-submission-capture' ) . '</th>';
            echo '<th>' . __( 'Form Plugin', 'form-submission-capture' ) . '</th>';
            echo '<th>' . __( 'Form ID', 'form-submission-capture' ) . '</th>';
            echo '<th>' . __( 'Submission Data', 'form-submission-capture' ) . '</th>';
            echo '<th>' . __( 'Date Submitted', 'form-submission-capture' ) . '</th>';
            echo '<th>' . __( 'Actions', 'form-submission-capture' ) . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ( $submissions as $submission ) {
                echo '<tr>';
                echo '<td>' . esc_html( $submission['id'] ) . '</td>';
                echo '<td>' . esc_html( $submission['form_plugin'] ) . '</td>';
                echo '<td>' . esc_html( $submission['form_id'] ) . '</td>';
                echo '<td><pre>' . esc_html( print_r( maybe_unserialize( $submission['submission_data'] ), true ) ) . '</pre></td>';
                echo '<td>' . esc_html( $submission['date_submitted'] ) . '</td>';
                echo '<td><button class="fsc-delete-submission" data-id="' . esc_attr( $submission['id'] ) . '">' . __( 'Delete', 'form-submission-capture' ) . '</button></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . __( 'No submissions found', 'form-submission-capture' ) . '</p>';
        }
    }
}
