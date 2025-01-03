<?php
namespace FSCMNGR\Includes;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FSCMNGR_Shortcode_Handler
{
    public static function fscmngr_generate_shortcode( $atts )
    {
        $atts = shortcode_atts(
            array(
            'form_plugin' => '',
            'form_id' => ''
            ), $atts 
        );

        $submissions = FSC_Form_Submission::get_submissions_data($atts['form_plugin'], $atts['form_id']);

        ob_start();
        if (! empty($submissions) ) {
            echo '<table>';
            echo '<thead><tr><th>' . esc_html('ID', 'form-submissions-manager') . '</th><th>' . esc_html('Submission Data', 'form-submissions-manager') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ( $submissions as $submission ) {
                echo '<tr>';
                echo '<td>' . esc_html($submission['id']) . '</td>';
                echo '<td>' . esc_html(wp_json_encode($submission['data'])) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . esc_html('No submissions found', 'form-submissions-manager') . '</p>';
        }
        return ob_get_clean();
    }
}
add_shortcode('fsc_form_submissions', array( 'FSCMNGR_Shortcode_Handler', 'fscmngr_generate_shortcode' ));
