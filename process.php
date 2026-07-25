<?php 
function rr_process_reservation(){ 
    if(!isset($_POST['rr_submit'])) return; 

    global $wpdb; 
    $table = $wpdb->prefix . 'reservations'; 

    $name = sanitize_text_field($_POST['name']); 
    $email = sanitize_email($_POST['email']); 
    $phone = sanitize_text_field($_POST['phone']); 
    $date = sanitize_text_field($_POST['date']); 
    $time = sanitize_text_field($_POST['time']); 
    $guests = intval($_POST['guests']); 
    $notes = sanitize_textarea_field($_POST['notes']); 

    $current_guests = $wpdb->get_var( 
        $wpdb->prepare( 
            "SELECT SUM(guests) FROM $table WHERE reservation_date=%s AND reservation_time=%s", 
            $date, 
            $time 
        ) 
    ); 

    if(!$current_guests){ 
        $current_guests = 0; 
    } 

    if(($current_guests + $guests) > 80){ 
        $error_msg = urlencode("Sorry, this time slot is fully booked."); 
        wp_redirect(add_query_arg('rr_error', $error_msg, wp_get_referer()));
        exit;
    } 

    $wpdb->insert($table, array( 
        'name'=>$name, 
        'email'=>$email, 
        'phone'=>$phone, 
        'guests'=>$guests, 
        'reservation_date'=>$date, 
        'reservation_time'=>$time, 
        'notes'=>$notes 
    )); 

    
    rr_send_email($name,$email,$date,$time,$guests,$phone,$notes); 
    // Redirect back with success flag
    wp_redirect(add_query_arg('rr_success', '1', wp_get_referer()));
    exit;
} 

add_action('init','rr_process_reservation'); 

function rr_send_email($name, $email, $date, $time, $guests, $phone = '', $notes = '') { 
    $manager_email = "munrvpt@gmail.com"; 
    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Manager email
    $manager_message = "<h2>New Reservation</h2>";
    $manager_message .= "<p><strong>Name:</strong> $name</p>";
    $manager_message .= "<p><strong>Date:</strong> $date</p>";
    $manager_message .= "<p><strong>Time:</strong> $time</p>";
    $manager_message .= "<p><strong>Guests:</strong> $guests</p>";
    if($phone) $manager_message .= "<p><strong>Phone:</strong> $phone</p>";
    $manager_message .= "<p><strong>Email:</strong> $email</p>";
    if($notes) $manager_message .= "<p><strong>Notes:</strong> $notes</p>";

    wp_mail($manager_email, "New Reservation", $manager_message, $headers); 

    // Customer email
    $customer_message = "<p>Hello $name,</p>";
    $customer_message .= "<p>Your reservation has been confirmed:</p>";
    $customer_message .= "<li><strong>Date:</strong> $date</li>";
    $customer_message .= "<li><strong>Time:</strong> $time</li>";
    $customer_message .= "<li><strong>Guests:</strong> $guests</li>";
    $customer_message .= "<li><strong>Notes:</strong> $notes</li>";
    $customer_message .= "<p>Thank you for booking with us!</p>";

    wp_mail($email, "Reservation Confirmation", $customer_message, $headers); 
}