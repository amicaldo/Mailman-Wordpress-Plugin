<?php
/*
Plugin Name: mailman wordpress connector
Description: Simple subscribe form to a mailman mailing list
Version: 1.0
Author: amicaldo GmbH
Author URI: https://www.amicaldo.de
Text Domain: mm-connector
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Create Settings Page
add_action('admin_menu', 'mm_plugin_settings');
add_action('admin_init', 'mm_admin_init');
add_action('plugins_loaded', 'init');


function init() {
    $plugin_dir = basename(dirname(__FILE__));

    load_plugin_textdomain( 'mm-connector', false, $plugin_dir );
}

function mm_plugin_settings() {
	add_options_page('Mailman Settings', 'Mailman Settings', 'manage_options', 'mailman-setting-page', 'mm_display_settings');
}

function mm_admin_init() {
	register_setting('mailman-settings-group','mm_mailman_settings');
	add_settings_section('mailman-section','Mailman Settings','mm_mailman_section','mailman-setting-page');
	add_settings_field('list-name','Mailman List Name','mm_list_name','mailman-setting-page','mailman-section');
	add_settings_field('mailman-url','Mailman List URL','mm_mailman_url','mailman-setting-page','mailman-section');
	add_settings_field('list-password','Mailman List Password','mm_list_password','mailman-setting-page','mailman-section');
}

function mm_mailman_section() {
	echo '<p>Please fill in the following settings. All are required for this plugin to work correctly. If you need assistance please contact your mailing list provider.</p>';
	echo '<p>Add the short code [mailman_subscribe_form] to any page or post you would like the <strong>subscribe</strong> form to display on.</p>';
	echo '<p>Add the short code [mailman_unsubscribe_form] to any page or post you would like the <strong>unsubscribe</strong> form to display on.</p>';
}

function mm_list_name() {
	$setting = get_option('mm_mailman_settings');
	echo '<input type="text" name="mm_mailman_settings[mm_list_name]" value="'.$setting['mm_list_name'].'" />';
}

function mm_mailman_url() {
	$setting = get_option('mm_mailman_settings');
	echo '<input type="text" name="mm_mailman_settings[mm_mailman_url]" value="'.$setting['mm_mailman_url'].'" />';
}

function mm_list_password() {
	$setting = get_option('mm_mailman_settings');
	echo '<input type="text" name="mm_mailman_settings[mm_list_password]" value="'.$setting['mm_list_password'].'" />';
}

function mm_display_settings() {
	?>
	<div class="wrap">
		<h2>Mailman Settings</h2>
		<form action="options.php" method="post">
			<?php settings_fields( 'mailman-settings-group' ); ?>
			<?php do_settings_sections( 'mailman-setting-page' ); ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// Prevent direct script access
if(!defined('WPINC')){ die(); }

function mm_html_subscribe_form_code($response, $text) {
	$tmp = mm_get_config();
    // display subscribe from
    // div's and form's ids and classes is not mandatory you can change or remove them as you like
    echo '<div id="form-wrapper" class="newsletter newsletter-subscription">';
    echo '<form action="' . esc_url( $_SERVER['REQUEST_URI'] ) . '" method="post">';
    echo '<p>';
    echo $text['label'].' ';
    echo '<input type="text" name="mm-email" value="' . ( isset( $_POST["mm-email"] ) ? esc_attr( $_POST["mm-email"] ) : '' ) . '" size="40" class="newsletter-email input-text"/>';
    echo '</p>';
    echo '<p><input type="submit" name="mm-submitted" value="'.$text['button'].'" class="sendbtn"/></p>';
    echo '</form>';
    echo '</div>';

    //return response to user
    if(!empty($response)){
	    if($response['code'] == 200) {
		echo '<div>';
		echo '<p style="text-align: center"><b>'.$text['success'].'</b></p>';
		echo '</div>';
		return true;
	    } else {
		echo '<div>';
		echo '<p style="text-align: center"><b>'.$text['failure'].'</b></p>';
		echo '</div>';
		return false;
	    }
    }

}

function mm_get_config() {
	$setting = get_option('mm_mailman_settings');
    $config['list_name'] = $setting['mm_list_name']; // eg. mylist
    $config['mailman_url'] = $setting['mm_mailman_url']; // eg. http://lists.domain.com/mailman/admin/
    $config['list_password'] = $setting['mm_list_password']; // Password you use to login to mailman admin
    return $config;
}

function mm_subscribe_handler() {
    // if the submit button is clicked
    if ( isset( $_POST['mm-submitted'] ) ) {

        // sanitize form values
        $email   = sanitize_email( $_POST["mm-email"] );

        return mm_subscribe_to_list($email);
        unset($_POST['mm-submitted']);
        unset($_POST['mm-email']);
    }
    return false;
}

function mm_unsubscribe_handler() {
    // if the submit button is clicked
    if ( isset( $_POST['mm-submitted'] ) ) {

        // sanitize form values
        $email   = sanitize_email( $_POST["mm-email"] );

        return mm_unsubscribe_from_list($email);
        unset($_POST['mm-submitted']);
        unset($_POST['mm-email']);
    }
    return false;
}

function mm_subscribe_to_list($email) {
    $config = mm_get_config();

    $path = '/members/add?subscribe_or_invite=0&send_welcome_msg_to_this_batch=1&notification_to_list_owner=0&subscribees_upload='.$email.'&adminpw='.$config['list_password'];
    $url = $config['mailman_url'] . $config['list_name'] . $path;
    $response = mm_get_data($url);
    return $response;
}

function mm_unsubscribe_from_list($email) {
    $config = mm_get_config();

    $path = '/members/remove?send_unsub_ack_to_this_batch=1&send_unsub_notifications_to_list_owner=0&unsubscribees_upload='.$email.'&adminpw='.$config['list_password'];
    $url = $config['mailman_url'] . $config['list_name'] . $path;

    $response = mm_get_data($url);
    return $response;
}

function mm_cf_subscribe_shortcode() {
    ob_start();
    $response = mm_subscribe_handler();
    mm_html_subscribe_form_code($response, [
        'label' => __('Your E-Mail', 'mm-connector'),
        'button' => __('Subscribe', 'mm-connector'),
        'success' => __('successfully subscribed', 'mm-connector'),
        'failure' => __('subscribe not successful', 'mm-connector')
    ]);

    return ob_get_clean();
}

function mm_cf_unsubscribe_shortcode() {
    ob_start();
    $response = mm_unsubscribe_handler();

    mm_html_subscribe_form_code($response, [
        'label' => __('Your E-Mail', 'mm-connector'),
        'button' => __('Unsubscribe', 'mm-connector'),
        'success' => __('unsubscribe successfully', 'mm-connector'),
        'failure' => __('unsubscribe not succesful', 'mm-connector')
    ]);


    return ob_get_clean();
}

function mm_get_data($url) {
	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	//html data
	$data = curl_exec($ch);
	//to get the http response
	$response_code = curl_getinfo ( $ch , CURLINFO_RESPONSE_CODE );
	curl_close($ch);
	return array ('html'=>$data, 'code' => $response_code);
}

add_shortcode( 'mailman_subscribe_form', 'mm_cf_subscribe_shortcode' );
add_shortcode( 'mailman_unsubscribe_form', 'mm_cf_unsubscribe_shortcode' );

?>
