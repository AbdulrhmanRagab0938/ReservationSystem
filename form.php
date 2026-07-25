<?php 
function rr_reservation_form(){ 
    ob_start(); 
    
      $success = isset($_GET['rr_success']) && $_GET['rr_success'] == 1;
      $error   = isset($_GET['rr_error']) ? urldecode($_GET['rr_error']) : '';
       
    if($success){
        echo '<p class="rr-success" style="
            font-size: 22px;
            font-weight: bold;
            text-align: center;
            margin: 40px 0;
            color: #C8A96A;
        ">Reservation confirmed!</p>';
        return ob_get_clean(); // Stop form from rendering
    }
    
        if($error){
        echo '<p class="rr-error" style="
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            color: #d96c6c;
        ">'.$error.'</p>';
    }
    
    ?> 
    <form method="POST" class="rr-form"> 
        <!-- Name & Phone row --> 
        <div class="rr-row"> 
            <input type="text" name="name" placeholder="Name" required> 
            <input type="tel" name="phone" placeholder="Phone Number" required> 
        </div> 

        <!-- Email row (alone) --> 
        <input type="email" name="email" placeholder="Email" required> 

        <!-- Date & Time row --> 
        <div class="rr-row"> 
            <input type="date" name="date" min="<?php echo date('Y-m-d'); ?>" required> 
            <select name="time" required> 
                <?php 
                for($hour = 11; $hour <= 22; $hour++){ 
                    $h = str_pad($hour,2,"0",STR_PAD_LEFT); 
                    echo "<option value='$h:00'>$h:00</option>"; 
                    echo "<option value='$h:30'>$h:30</option>"; 
                } 
                ?> 
            </select> 
        </div> 

        <!-- Guests row (alone) --> 
        <input type="number" name="guests" min="1" max="35" placeholder="Number of Guests" required> 

        <!-- Notes row --> 
        <textarea name="notes" placeholder="Notes"></textarea> 

        <button type="submit" name="rr_submit">Reserve Table</button> 
    </form> 
    <?php 
    return ob_get_clean(); 
} 

add_shortcode('res','rr_reservation_form');