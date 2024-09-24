<?php
class FSC_Shortcode_Handler {
    public static function generate_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'form_plugin' => '',
            'form_id' => ''
        ), $atts );

        $submissions = FSC_Form_Submission::get_submissions_data( $atts['form_plugin'], $atts['form_id'] );

        ob_start();
        if ( ! empty( $submissions ) ) {
            echo '<table>';
            echo '<thead><tr><th>' . __( 'ID', 'form-submission-capture' ) . '</th><th>' . __( 'Submission Data', 'form-submission-capture' ) . '</th></tr></thead>';
            echo '<tbody>';
            foreach ( $submissions as $submission ) {
                echo '<tr>';
                echo '<td>' . esc_html( $submission['id'] ) . '</td>';
                echo '<td>' . esc_html( json_encode( $submission['data'] ) ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . __( 'No submissions found', 'form-submission-capture' ) . '</p>';
        }
        return ob_get_clean();
    }
}
add_shortcode( 'fsc_form_submissions', array( 'FSC_Shortcode_Handler', 'generate_shortcode' ) );
