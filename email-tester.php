<?php
/*
Plugin Name: Daily Email Tester
Plugin URI: https://github.com/nanopost/daily-email-tester.php
Description: Sends a scheduled test email to a specified address with customizable frequency.
Version: 0.0.4
Author: nanoPost
Text Domain: daily-email-tester
Author URI: https://nanopo.st/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// 1. ACTIVATION: Schedule with default settings
register_activation_hook( __FILE__, 'dailytester_activate' );
function dailytester_activate() {
    dailytester_reschedule_cron();
}

// 2. DEACTIVATION: Clear schedule
register_deactivation_hook( __FILE__, 'dailytester_deactivate' );
function dailytester_deactivate() {
    wp_clear_scheduled_hook( 'dailytester_send_daily_email' );
}

// 3. ADD CUSTOM CRON SCHEDULES
// WordPress has hourly, twicedaily, daily. We need weekly/monthly/custom.
add_filter( 'cron_schedules', 'dailytester_add_cron_intervals' );
function dailytester_add_cron_intervals( $schedules ) {
    
    // Weekly
    $schedules['weekly'] = array(
        'interval' => 604800, // 7 * 24 * 60 * 60
        'display'  => __( 'Once Weekly' )
    );
    
    // Monthly (approx 30 days)
    $schedules['monthly'] = array(
        'interval' => 2592000, // 30 * 24 * 60 * 60
        'display'  => __( 'Once Monthly' )
    );

    // Custom Days (Dynamic)
    $custom_days = get_option( 'dailytester_custom_days', 3 ); // Default to 3 days if not set
    if ( $custom_days > 0 ) {
        $schedules['dailytester_custom'] = array(
            'interval' => $custom_days * 86400, // days * 24 * 60 * 60
            'display'  => __( 'Every ' . $custom_days . ' days' )
        );
    }

    return $schedules;
}

// 4. ACTION HOOK
add_action( 'dailytester_send_daily_email', 'dailytester_send_email' );

// 5. ADMIN MENU
function dailytester_add_options_page() {
    add_submenu_page(
        'tools.php',
        'Email Tester Settings',
        'Email Tester',
        'manage_options',
        'dailytester_options',
        'dailytester_render_options_page'
    );
}
add_action( 'admin_menu', 'dailytester_add_options_page' );

// 6. RENDER OPTIONS PAGE
function dailytester_render_options_page() {    
    ?>    
    <div class="wrap">    
        <h2>Email Tester Settings</h2>

        <?php 
        // --- LOGICA VOOR TEST MAIL ---
        if ( isset( $_POST['dailytester_send_test_email'] ) ) {    
            if ( check_admin_referer( 'dailytester_send_test_email', 'dailytester_send_test_email_nonce' ) ) {    
                
                $target_email = get_option( 'dailytester_email_address' );

                if ( empty( $target_email ) ) {
                    ?>
                    <div class="notice notice-warning is-dismissible">
                        <p><strong>Warning:</strong> No email address set. Please save an address first.</p>
                    </div>
                    <?php
                } else {
                    $sent = dailytester_send_email( true );        
                    if ( $sent ){
                        ?>
                        <div class="notice notice-success is-dismissible">
                            <p>Test email sent successfully to <?php echo esc_html($target_email); ?>!</p>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="notice notice-error is-dismissible">
                            <p>Error sending test email. Check server logs.</p>
                        </div>
                        <?php
                    }
                }
            }
        }
        
        // --- CHECK SCHEDULER ---
        // We tonen de volgende geplande tijd ter info
        $next_run = wp_next_scheduled( 'dailytester_send_daily_email' );
        if ( $next_run ) {
            $time_string = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run );
            ?>
            <div class="notice notice-info inline">
                <p>Next automatic email scheduled for: <strong><?php echo $time_string; ?></strong></p>
            </div>
            <?php
        } else {
             ?>
            <div class="notice notice-warning inline">
                <p>No active schedule found. Save settings to activate.</p>
            </div>
            <?php
        }
        ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'dailytester_options' ); ?>    
            <?php do_settings_sections( 'dailytester_options' ); ?>    
            
            <table class="form-table">    
                <tr>    
                    <th scope="row"><label for="dailytester_email_address">Email Address</label></th>    
                    <td>
                        <input type="email" id="dailytester_email_address" name="dailytester_email_address" value="<?php echo esc_attr( get_option( 'dailytester_email_address' ) ); ?>" class="regular-text" placeholder="e.g. info@example.com" required />
                    </td>    
                </tr>

                <?php $current_freq = get_option( 'dailytester_frequency', 'daily' ); ?>
                <tr>
                    <th scope="row"><label for="dailytester_frequency">Frequency</label></th>
                    <td>
                        <select name="dailytester_frequency" id="dailytester_frequency">
                            <option value="daily" <?php selected( $current_freq, 'daily' ); ?>>Daily</option>
                            <option value="weekly" <?php selected( $current_freq, 'weekly' ); ?>>Weekly</option>
                            <option value="monthly" <?php selected( $current_freq, 'monthly' ); ?>>Monthly</option>
                            <option value="dailytester_custom" <?php selected( $current_freq, 'dailytester_custom' ); ?>>Custom (Days)</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="dailytester_custom_days">Custom Interval (Days)</label></th>
                    <td>
                        <input type="number" min="1" step="1" id="dailytester_custom_days" name="dailytester_custom_days" value="<?php echo esc_attr( get_option( 'dailytester_custom_days', 3 ) ); ?>" class="small-text" />
                        <p class="description">Only used if "Custom (Days)" is selected above.</p>
                    </td>
                </tr>
            </table>    
            <?php submit_button( 'Save Changes' ); ?>    
        </form>

        <hr />    

        <form method="post">
            <h3>Test Email</h3>    
            <p>Send a manual test immediately:</p>    
            <?php wp_nonce_field( 'dailytester_send_test_email', 'dailytester_send_test_email_nonce' ); ?>    
            <?php submit_button( 'Send Test Email Now', 'secondary', 'dailytester_send_test_email', false ); ?>    
        </form>    
    </div>    
    <?php    
}

// 7. REGISTER SETTINGS
add_action( 'admin_init', 'dailytester_register_settings' );    
function dailytester_register_settings() {    
    // Register settings
    register_setting( 'dailytester_options', 'dailytester_email_address', 'sanitize_email' );
    
    // Register Frequency with a callback to reschedule immediately upon save
    register_setting( 'dailytester_options', 'dailytester_frequency', array(
        'sanitize_callback' => 'sanitize_text_field',
    ));

    register_setting( 'dailytester_options', 'dailytester_custom_days', array(
        'sanitize_callback' => 'absint'
    ));
}   

// 8. HOOK INTO OPTION UPDATE TO RESCHEDULE
// Wanneer de opties worden opgeslagen in options.php, moeten we de cron job herplannen.
add_action( 'update_option_dailytester_frequency', 'dailytester_reschedule_cron', 10, 0 );
add_action( 'update_option_dailytester_custom_days', 'dailytester_reschedule_cron', 10, 0 );

function dailytester_reschedule_cron() {
    // Verwijder oude schedule
    wp_clear_scheduled_hook( 'dailytester_send_daily_email' );

    // Haal instellingen op
    $freq = get_option( 'dailytester_frequency', 'daily' );
    
    // Als er geen email is ingesteld, plannen we niets in
    $email = get_option( 'dailytester_email_address' );
    if ( empty( $email ) ) return;

    // Schedule nieuwe event
    // Note: Als het custom is, moet de 'cron_schedules' filter (stap 3) al gedraaid hebben.
    wp_schedule_event( time(), $freq, 'dailytester_send_daily_email' );
}

// 9. SEND EMAIL FUNCTION
function dailytester_send_email( $interactive = false ) {
  
    $to = get_option( 'dailytester_email_address' );
    if ( empty( $to ) ) return false;
    
    $freq = get_option( 'dailytester_frequency', 'daily' );
    
    if( $interactive ){
        $subject = 'Test message from '. get_bloginfo( 'name' ) . ' (manual)';
        $message = 'This is a manually-initiated test email.';
    } else {
        $subject = 'Scheduled test message from '. get_bloginfo( 'name' ) . ' (' . $freq . ')';
        $message = 'This is a scheduled email test. Current frequency setting: ' . $freq;
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Email Tester] Sending scheduled email to ' . $to );
        }
    }
  
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    return wp_mail( $to, $subject, $message, $headers );
}