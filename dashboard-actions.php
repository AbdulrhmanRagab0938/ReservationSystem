<?php
if (!defined('ABSPATH')) exit; // Security

// --- DELETE RESERVATION ---
function rr_delete_reservation() {
    if (!current_user_can('manage_options')) wp_die('Not allowed');
    if (!isset($_GET['id']) || !wp_verify_nonce($_GET['_wpnonce'], 'rr_delete_reservation')) wp_die('Invalid request');

    global $wpdb;
    $table = $wpdb->prefix . 'reservations';
    $id = intval($_GET['id']);

    $wpdb->delete($table, ['id' => $id]);

    wp_redirect(admin_url('admin.php?page=rr_reservations'));
    exit;
}
add_action('admin_post_rr_delete_reservation', 'rr_delete_reservation');

// --- ADD RESERVATION ---
function rr_add_reservation() {
    if (!current_user_can('manage_options')) wp_die('Not allowed');
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'rr_add_reservation')) wp_die('Invalid request');

    global $wpdb;
    $table = $wpdb->prefix . 'reservations';

    // ✅ DEFINE VARIABLES FIRST
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $date = sanitize_text_field($_POST['date']);
    $time = sanitize_text_field($_POST['time']);
    $guests = intval($_POST['guests']);
    $notes = sanitize_textarea_field($_POST['notes']);

    // Insert into DB
    $wpdb->insert($table, [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'guests' => $guests,
        'reservation_date' => $date,
        'reservation_time' => $time,
        'notes' => $notes,
    ]);

    // ✅ NOW variables exist → email will work
    rr_send_email($name, $email, $date, $time, $guests, $phone, $notes);

    wp_redirect(admin_url('admin.php?page=rr_reservations&added=1'));
    exit;
}
add_action('admin_post_rr_add_reservation', 'rr_add_reservation');