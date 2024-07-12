<?php
/**
 * Plugin Name: WooCommerce CVR Godkendelse
 * Description: Tilføjer et CVR-nummer felt på WooCommerce betalingsside og validerer det mod CVR.
 * Version: 1.3
 * Author: Yassin Ayoub (Nimbus Nordic IT)
 * Author URI: https://www.nimbusnordic.dk
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add CVR Number field to checkout
add_action('woocommerce_after_order_notes', 'add_cvr_field_to_checkout');

function add_cvr_field_to_checkout($checkout)
{
    echo '<div id="cvr_field"><h5>' . __('CVR Information') . '</h5>';

    woocommerce_form_field('cvr_number', array(
        'type'          => 'text',
        'class'         => array('form-row-wide'),
        'label'         => __('CVR Nummer'),
        'placeholder'   => __('Indtast dit CVR nummer'),
        'required'      => true,
    ), $checkout->get_value('cvr_number'));

    echo '</div>';
}

// Validate CVR Number
add_action('woocommerce_checkout_process', 'validate_cvr_field');

function validate_cvr_field()
{
    if (!$_POST['cvr_number']) {
        wc_add_notice(__('Indtast venligst din CVR Nr.'), 'error');
    } else {
        $cvr_number = sanitize_text_field($_POST['cvr_number']);
        $cvr_valid = check_cvr_number($cvr_number);

        if (!$cvr_valid) {
            $error_message = get_option('woocvr_error_message', __('Dit CVR Nr. er ikke registreret inden for den valgte branche. Kontakt os, hvis du mener dette er en fejl.', 'woocommerce-cvr-validation'));
            wc_add_notice($error_message, 'error');
        }
    }
}

// Save CVR Number
add_action('woocommerce_checkout_update_order_meta', 'save_cvr_field');

function save_cvr_field($order_id)
{
    if (!empty($_POST['cvr_number'])) {
        update_post_meta($order_id, '_cvr_number', sanitize_text_field($_POST['cvr_number']));
    }
}

// Display CVR Number on Order Admin Page
add_action('woocommerce_admin_order_data_after_billing_address', 'display_cvr_number_in_admin', 10, 1);

function display_cvr_number_in_admin($order)
{
    $cvr_number = get_post_meta($order->get_id(), '_cvr_number', true);
    if ($cvr_number) {
        echo '<p><strong>' . __('CVR Nummer') . ':</strong> ' . $cvr_number . '</p>';
    }
}

// Add WooCVR menu in admin
add_action('admin_menu', 'woocvr_menu');

function woocvr_menu()
{
    add_menu_page(
        'WooCVR', 
        'WooCVR', 
        'manage_options', 
        'woocvr', 
        'woocvr_settings_page', 
        'dashicons-admin-settings', 
        56
    );
}

function woocvr_settings_page()
{
    ?>
    <div class="wrap">
        <h1><?php _e('WooCVR Indstillinger', 'woocommerce-cvr-validation'); ?></h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Tilføj Branchekode', 'woocommerce-cvr-validation'); ?></th>
                    <td>
                        <input type="text" name="new_industry_code" value="" placeholder="<?php _e('Indtast branchekode', 'woocommerce-cvr-validation'); ?>" />
                        <input type="text" name="new_industry_name" value="" placeholder="<?php _e('Indtast branchenavn', 'woocommerce-cvr-validation'); ?>" />
                        <?php submit_button(__('Tilføj', 'woocommerce-cvr-validation'), 'primary', 'add_industry_code'); ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Fejlbesked', 'woocommerce-cvr-validation'); ?></th>
                    <td>
                        <textarea name="woocvr_error_message" rows="4" cols="50" placeholder="<?php _e('Indtast fejlbesked', 'woocommerce-cvr-validation'); ?>"><?php echo esc_attr(get_option('woocvr_error_message', __('Dit CVR Nr. er ikke registreret inden for den valgte branche. Kontakt os, hvis du mener dette er en fejl.', 'woocommerce-cvr-validation'))); ?></textarea>
                        <?php submit_button(__('Gem Besked', 'woocommerce-cvr-validation'), 'primary', 'save_error_message'); ?>
                    </td>
                </tr>
            </table>
        </form>
        <h2><?php _e('Eksisterende Branchekoder', 'woocommerce-cvr-validation'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Branchekode', 'woocommerce-cvr-validation'); ?></th>
                    <th><?php _e('Branchenavn', 'woocommerce-cvr-validation'); ?></th>
                    <th><?php _e('Handling', 'woocommerce-cvr-validation'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $industry_codes = get_option('woocvr_industry_codes', array());
                $industry_names = get_option('woocvr_industry_names', array());
                if (!empty($industry_codes)) {
                    foreach ($industry_codes as $index => $code) {
                        $name = isset($industry_names[$index]) ? $industry_names[$index] : '';
                        echo '<tr>';
                        echo '<td>' . esc_html($code) . '</td>';
                        echo '<td>' . esc_html($name) . '</td>';
                        echo '<td><form method="post" action="" style="display:inline;"><input type="hidden" name="delete_code" value="' . esc_attr($code) . '"/><button type="submit" name="delete_industry_code" class="button-link-delete" style="color:red;">&#10060;</button></form></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="3">' . __('Ingen branchekoder fundet', 'woocommerce-cvr-validation') . '</td></tr>';
                }
                ?>
            </tbody>
        </table>
        <p><?php _e('Udviklet af', 'woocommerce-cvr-validation'); ?> <a href="https://www.nimbusnordic.dk" target="_blank">Yassin Ayoub (Nimbus Nordic IT)</a></p>
    </div>
    <?php
}

add_action('admin_init', 'woocvr_handle_form_submit');

function woocvr_handle_form_submit()
{
    if (isset($_POST['add_industry_code']) && !empty($_POST['new_industry_code']) && !empty($_POST['new_industry_name'])) {
        $new_code = sanitize_text_field($_POST['new_industry_code']);
        $new_name = sanitize_text_field($_POST['new_industry_name']);
        $industry_codes = get_option('woocvr_industry_codes', array());
        $industry_names = get_option('woocvr_industry_names', array());
        if (!in_array($new_code, $industry_codes)) {
            $industry_codes[] = $new_code;
            $industry_names[] = $new_name;
            update_option('woocvr_industry_codes', $industry_codes);
            update_option('woocvr_industry_names', $industry_names);
        }
    }

    if (isset($_POST['delete_industry_code']) && !empty($_POST['delete_code'])) {
        $delete_code = sanitize_text_field($_POST['delete_code']);
        $industry_codes = get_option('woocvr_industry_codes', array());
        $industry_names = get_option('woocvr_industry_names', array());
        if (($key = array_search($delete_code, $industry_codes)) !== false) {
            unset($industry_codes[$key]);
            unset($industry_names[$key]);
            update_option('woocvr_industry_codes', array_values($industry_codes));
            update_option('woocvr_industry_names', array_values($industry_names));
        }
    }

    if (isset($_POST['save_error_message']) && !empty($_POST['woocvr_error_message'])) {
        $error_message = sanitize_text_field($_POST['woocvr_error_message']);
        update_option('woocvr_error_message', $error_message);
    }
}

// Check CVR Number through API
function check_cvr_number($cvr_number)
{
    $api_url = 'https://cvrapi.dk/api?search=' . $cvr_number . '&country=dk';
    $response = wp_remote_get($api_url, array(
        'headers' => array(
            'User-Agent' => 'DitFirmanavn - WooCommerce Plugin - DitNavn +45 42424242'
        )
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (isset($data->error)) {
        return false;
    }

    $industry_codes = get_option('woocvr_industry_codes', array());
    if (isset($data->industrycode) && in_array($data->industrycode, $industry_codes)) {
        return true;
    } else {
        return false;
    }
}
?>
