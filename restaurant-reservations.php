<?php
/*
Plugin Name: restaurantResSystem
Description: Simple reservation system with capacity control and manager calendar.
Version: 1.0
Author: Abdalrahman Ragab
*/

if (!defined('ABSPATH')) exit;

global $rr_db_version;
$rr_db_version = '1.0';



// Create reservations table on plugin activation
function rr_activate_plugin() {
    global $wpdb;
    $table = $wpdb->prefix . 'reservations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(50) NOT NULL,
        guests int NOT NULL,
        reservation_date date NOT NULL,
        reservation_time time NOT NULL,
        notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'rr_activate_plugin');

// Include form, process, and calendar files
require_once plugin_dir_path(__FILE__) . 'form.php';
require_once plugin_dir_path(__FILE__) . 'process.php';
require_once plugin_dir_path(__FILE__) . 'dashboard.php';
require_once plugin_dir_path(__FILE__) . 'dashboard-actions.php';

// Enqueue plugin styles and scripts
function rr_enqueue_assets() {
    // Enqueue style with live refresh
    wp_enqueue_style('rr-style', plugin_dir_url(__FILE__) . 'style.css', array(), time());

    // Enqueue script with live refresh
    wp_enqueue_script('rr-script', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), time(), true);
}
add_action('wp_enqueue_scripts', 'rr_enqueue_assets');

add_action('admin_init', function() {
    $user = wp_get_current_user();
    if (in_array('dashboard-viewer', $user->roles) && is_admin()) {
        if (($_GET['page'] ?? '') !== 'rr_reservations') {
            wp_redirect(admin_url('admin.php?page=rr_reservations'));
            exit;
        }
    }
});

function rr_add_custom_capability() {
    $role = get_role('administrator');
    $role->add_cap('view_reservations');
}
add_action('init', 'rr_add_custom_capability');
