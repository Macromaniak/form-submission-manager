<?php
/*
Plugin Name: Form Submissions Manager
Description: Capture and display form submissions from popular form plugins like Contact Form 7, Gravity Forms, and WPForms.
Version: 1.0
Requires at least: 6.3
Requires PHP: 7.2.24
Author: Anandhu Nadesh
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
Text Domain: form-submissions-manager
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define constants
define( 'FSCMNGR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSCMNGR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FSCMNGR_PLUGIN_VERSION', '1.0.0' );

// Include necessary files
require_once FSCMNGR_PLUGIN_DIR . 'includes/class-form-detection.php';
require_once FSCMNGR_PLUGIN_DIR . 'includes/class-form-submission.php';
require_once FSCMNGR_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once FSCMNGR_PLUGIN_DIR . 'includes/shortcode-handler.php';

use FSCMNGR\Includes\FSCMNGR_Form_Submission;

// Enqueue admin styles and scripts
function fscmngr_enqueue_admin_assets() {
    wp_enqueue_style( 'fsc-admin-style', FSCMNGR_PLUGIN_URL . 'assets/css/admin-styles.css' );
    wp_enqueue_script( 'fsc-admin-scripts', FSCMNGR_PLUGIN_URL . 'assets/js/admin-scripts.js', array( 'jquery' ), FSCMNGR_PLUGIN_VERSION, true );

    // Localize script for AJAX
    wp_localize_script( 'fsc-admin-scripts', 'fscmngr_ajax_object', array( 
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'fscmngr_nonce' )
    ));
}
add_action( 'admin_enqueue_scripts', 'fscmngr_enqueue_admin_assets' );

// Create Admin Menu for Form Submissions
function fscmngr_admin_menu() {
    add_menu_page(
        esc_html( 'Form Submissions', 'form-submissions-manager' ),
        esc_html( 'Form Submissions', 'form-submissions-manager' ),
        'manage_options',
        'fsc-form-submissions',
        'fscmngr_display_submissions_page'
    );
}
add_action( 'admin_menu', 'fscmngr_admin_menu' );

// Display Admin Page
function fscmngr_display_submissions_page() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html( 'Form Submissions', 'form-submissions-manager' ) . '</h1>';
    // Output table of form submissions
    FSCMNGR_Form_Submission::fscmngr_display_submissions_table();
    echo '</div>';
}

register_activation_hook( __FILE__, 'fscmngr_create_submissions_table' );

function fscmngr_create_submissions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        form_plugin VARCHAR(50) NOT NULL,
        form_id BIGINT(20) NOT NULL,
        submission_data LONGTEXT NOT NULL,
        date_submitted DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}


