<?php
/*
Plugin Name: PageSpeed Lighthouse Test
Description: Überprüft die PageSpeed mit Lighthouse
Version: 1.0
Author: Ihr Name
Author URI: Ihre Autoren-URI
*/

$API_KEY = "API KEY";
$URL_TO_TEST = "https://www.gesundheitsregion-euregio.eu/";

add_action('admin_menu', 'my_plugin_menu');
add_action('admin_init', 'my_plugin_register_settings');
add_action('admin_post_run_pagespeed_test', 'runPageSpeedTest');

function my_plugin_menu(){
    add_menu_page('My Plugin Settings', 'My Plugin', 'manage_options', 'my-plugin-settings', 'my_plugin_settings_page');
    add_submenu_page('my-plugin-settings', 'PageSpeed Ergebnisse', 'PageSpeed Ergebnisse', 'manage_options', 'page-speed-results', 'custom_result_page');
}

function my_plugin_register_settings(){
    register_setting('my_plugin_settings_group', 'my_plugin_settings');
}

function my_plugin_settings_page(){
    $options = get_option('my_plugin_settings');
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    $url_to_test = isset($options['url_to_test']) ? $options['url_to_test'] : '';
?>
    <div class="wrap">
        <h2>My Plugin Settings</h2>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php
            settings_fields('my_plugin_settings_group');
            do_settings_sections('my-plugin-settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API-KEY</th>
                    <td>
                        <input type="text" name="my_plugin_settings[api_key]" value="<?php echo esc_attr($api_key); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">URL to test</th>
                    <td>
                        <input type="text" name="my_plugin_settings[url_to_test]" value="<?php echo esc_attr($url_to_test); ?>" />
                    </td>
                </tr>
            </table>
            <?php submit_button('Start PageSpeed-Test'); ?>
        </form>
    </div>
    <?php
}

function create_custom_table(){
    global $wpdb;

  $connect = new mysqli($servername, $username, $password, $dbname);

  if($connect->connect_error){
    die("Connection failed: " . $connect->connect_error);
  }

    $table_name = $wpdb->prefix . 'my_plugin_data';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            data_json text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

register_activation_hook(__FILE__, 'my_plugin_activate');

function my_plugin_activate(){
  create_custom_table();
}

function save_json_data_to_db($json_data){
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_plugin_data';

    $result = $wpdb->insert($table_name, array (
        'data_json' => $json_data,
        'created_at' => current_time('mysql'),
    ));

    if($result === false){
        echo 'Fehler beim Einfügen in die Datenbank: ' . $wpdb->last_error;
    }
}

function get_json_data_from_db(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_plugin_data';

    $results = $wpdb->get_results("SELECT data_json FROM $table_name ORDER BY created_at DESC LIMIT 1");
    if ($results) {
        return $results[0]->data_json;
    }
    return false;
}

function runPageSpeedTest() {
    global $API_KEY, $URL_TO_TEST;

    if (isset($_POST['my_plugin_settings'])) {
        $options = $_POST['my_plugin_settings'];
        $API_KEY = $options['api_key'];
        $URL_TO_TEST = $options['url_to_test'];
    }

    try {
        $response = file_get_contents("https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=$URL_TO_TEST&key=$API_KEY");
        $data = json_decode($response, true);

        $dataToStore = array(
            'id' => $data['id'],
            'cruxMetrics' => array(
                'First Contentful Paint' => $data['loadingExperience']['metrics']['FIRST_CONTENTFUL_PAINT_MS']['category'] ?? 'N/A',
                'First Input Delay' => $data['loadingExperience']['metrics']['FIRST_INPUT_DELAY_MS']['category'] ?? 'N/A'
            ),
            'lighthouseMetrics' => array(
                'First Contentful Paint' => $data['lighthouseResult']['audits']['first-contentful-paint']['displayValue'] ?? 'N/A',
                'Speed Index' => $data['lighthouseResult']['audits']['speed-index']['displayValue'] ?? 'N/A',
                'Largest Contentful Paint' => $data['lighthouseResult']['audits']['largest-contentful-paint']['displayValue'] ?? 'N/A',
                'Total Blocking Time' => $data['lighthouseResult']['audits']['total-blocking-time']['displayValue'] ?? 'N/A',
                'Unused CSS Rules' => $data['lighthouseResult']['audits']['unused-css-rules']['displayValue'] ?? 'N/A',
                'Uses Optimnzed Images' => $data['lighthouseResult']['audits']['uses-optimized-image']['displayValue'] ?? 'N/A',
                'Uses rel Preload' => $data['lighthouseResult']['audits']['uses-rel-preload']['displayValue'] ?? 'N/A',
                'SEO Mobile' => $data['lighthouseResult']['categoryGroups']['seo-mobile']['displayValue'] ?? 'N/A',
                'DOM Size' => $data['lighthouseResult']['audits']['dom-size']['displayValue'] ?? 'N/A',
                'First Meaningful Paint' => $data['lighthouseResult']['audits']['first-meaningful-paint']['displayValue'] ?? 'N/A'
            )
        );
        $jsonData = json_encode($dataToStore, JSON_PRETTY_PRINT);
        save_json_data_to_db($jsonData);
        wp_redirect(admin_url('admin.php?page=page-speed-results'));

    } catch (Exception $error) {
        echo 'Fehler: ' . $error->getMessage() . PHP_EOL;
    }
}

function custom_result_page(){
    echo '<div class="wrap">';
    echo '<h2>PageSpeed Test Ergebnisse</h2>';

    $json_data = get_json_data_from_db();

    if ($json_data) {
        $json_data = json_decode($json_data, true);
        echo '<pre>';
        print_r($json_data);
    } else {
        echo 'Keine Ergebnisse verfügbar';
    }
    echo '</div>';
}




