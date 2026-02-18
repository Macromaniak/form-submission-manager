<?php
namespace FSCMNGR\Includes;

// Prevent direct access
if (! defined('ABSPATH') ) {
    exit;
}

class FSCMNGR_Form_Detection
{

    /**
     * Constructor - Adds hooks for form submission capture
     */
    public function __construct()
    {
        // Hook into Contact Form 7 submissions
        add_action('wpcf7_mail_sent', array( $this, 'fscmngr_capture_cf7_submission' ));

        // Hook into Gravity Forms submissions
        add_action('gform_after_submission', array( $this, 'fscmngr_capture_gf_submission' ), 10, 2);

        // Hook into WPForms submissions
        add_action('wpforms_process_complete', array( $this, 'fscmngr_capture_wpforms_submission' ), 10, 4);
    }

    /**
     * Capture Contact Form 7 submissions
     *
     * @param WPCF7_ContactForm $contact_form The contact form instance
     */
    public function fscmngr_capture_cf7_submission( $contact_form )
    {
        global $wpdb;

        $submission = \WPCF7_Submission::get_instance(); 
        if ($submission ) {
            $form_data = $submission->get_posted_data();
            $form_id   = $contact_form->id();

            // Store submission data in the custom table
            $result = $wpdb->insert(
                $wpdb->prefix . 'form_submissions',
                array(
                    'form_plugin'     => 'contact-form-7',
                    'form_id'         => $form_id,
                    'submission_data' => maybe_serialize($form_data),
                ),
                array( '%s', '%d', '%s' )
            );

            // Log error if insert failed
            if ($result === false) {
                error_log('Form Submissions Manager: Failed to save CF7 submission. Error: ' . $wpdb->last_error);
            }
        }
    }

    /**
     * Capture Gravity Forms submissions
     *
     * @param array $entry The form entry data
     * @param array $form  The form data
     */
    public function fscmngr_capture_gf_submission( $entry, $form )
    {
        global $wpdb;

        $form_id   = $form['id'];
        $form_data = $entry;

        // Store submission data in the custom table
        $result = $wpdb->insert(
            $wpdb->prefix . 'form_submissions',
            array(
                'form_plugin'     => 'gravity-forms',
                'form_id'         => $form_id,
                'submission_data' => maybe_serialize($form_data),
            ),
            array( '%s', '%d', '%s' )
        );

        // Log error if insert failed
        if ($result === false) {
            error_log('Form Submissions Manager: Failed to save Gravity Forms submission. Error: ' . $wpdb->last_error);
        }
    }

    /**
     * Capture WPForms submissions
     *
     * @param array $fields    Submitted form fields
     * @param array $entry     Form entry metadata
     * @param array $form_data Form data
     * @param int   $entry_id  Entry ID
     */
    public function fscmngr_capture_wpforms_submission( $fields, $entry, $form_data, $entry_id )
    {
        global $wpdb;

        $form_id = $form_data['id'];

        // Store submission data in the custom table
        $result = $wpdb->insert(
            $wpdb->prefix . 'form_submissions',
            array(
                'form_plugin'     => 'wpforms',
                'form_id'         => $form_id,
                'submission_data' => maybe_serialize($fields),
            ),
            array( '%s', '%d', '%s' )
        );

        // Log error if insert failed
        if ($result === false) {
            error_log('Form Submissions Manager: Failed to save WPForms submission. Error: ' . $wpdb->last_error);
        }
    }

    /**
     * Get the list of all available forms
     *
     * @return array List of available forms grouped by plugin
     */
    public static function fscmngr_get_available_forms()
    {
        $forms = array();

        // Contact Form 7 forms
        if (defined('WPCF7_VERSION') ) {
            $cf7_forms = get_posts(array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => -1 ));
            if (is_array($cf7_forms)) {
                foreach ( $cf7_forms as $form ) {
                    $forms['contact-form-7'][] = array( 'id' => $form->ID, 'title' => $form->post_title );
                }
            }
        }

        // Gravity Forms forms
        if (class_exists('GFForms') ) {
            $gf_forms = GFAPI::get_forms();
            if (is_array($gf_forms)) {
                foreach ( $gf_forms as $form ) {
                    $forms['gravity-forms'][] = array( 'id' => $form['id'], 'title' => $form['title'] );
                }
            }
        }

        // WPForms forms
        if (class_exists('WPForms') ) {
            $wpforms_forms = wpforms()->form->get();
            if (is_array($wpforms_forms)) {
                foreach ( $wpforms_forms as $form ) {
                    $forms['wpforms'][] = array( 'id' => $form->ID, 'title' => $form->post_title );
                }
            }
        }

        return $forms;
    }

    /**
     * Detect installed form plugins
     *
     * @return array List of active form plugins
     */
    public static function fscmngr_detect_form_plugins()
    {
        $installed_plugins = array();

        // Check for Contact Form 7
        if (defined('WPCF7_VERSION') ) {
            $installed_plugins['contact-form-7'] = 'Contact Form 7';
        }

        // Check for Gravity Forms
        if (class_exists('GFForms') ) {
            $installed_plugins['gravity-forms'] = 'Gravity Forms';
        }

        // Check for WPForms
        if (class_exists('WPForms') ) {
            $installed_plugins['wpforms'] = 'WPForms';
        }

        return $installed_plugins;
    }
}

// Instantiate the FSCMNGR_Form_Detection class
new FSCMNGR_Form_Detection();
