<?php 
require_once get_template_directory() . '/vendor/autoload.php';

//cart url
function get_cart_url() {
    if (WP_ENV === 'production') {
        return 'https://melodyraejones.com/contact/cart/';
    } else {
        return 'http://melodyraejones.local/shop/cart/';
    }
}




// Custom wp_mail function for logging
add_filter('wp_mail', 'custom_wp_mail');
function custom_wp_mail($args) {
    // Set default headers if not present
    if (empty($args['headers'])) {
        $args['headers'] = array(
            'From: Your Name <your-email@example.com>',
            'Content-Type: text/html; charset=UTF-8'
        );
    }

    // Log email arguments for debugging
    error_log(print_r($args, true));

    // Return modified arguments
    return $args;
}

// Custom new user notification function
if (!function_exists('wp_new_user_notification')) {
    function wp_new_user_notification($user_id, $notify = 'both') {
        global $wpdb, $wp_hasher;

        // Get user data
        $user = get_userdata($user_id);
        if (!$user) {
            error_log("Failed to get user data for user ID: $user_id");
            return;
        }

        $user_login = stripslashes($user->user_login);
        $user_email = stripslashes($user->user_email);

        // Generate password reset key
        $key = wp_generate_password(20, false);
        do_action('retrieve_password_key', $user->user_login, $key);

        // Send the password reset link email
        $message = sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
        $message .= __('To set your password, visit the following address:') . "\r\n\r\n";
        $message .= network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . "\r\n\r\n";

        // Email headers
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        // Log email details for debugging
        error_log("Sending new user notification to: $user_email");

        // Use wp_mail to send the email
        $sent = wp_mail($user_email, sprintf(__('[%s] Login Details'), get_option('blogname')), $message, $headers);

        if ($sent) {
            error_log("New user notification email sent to: $user_email");
        } else {
            error_log("Failed to send new user notification email to: $user_email");
        }
    }
}

// Hook into user registration
add_action('user_register', 'wp_new_user_notification');

// Disable default password change notification
if (!function_exists('wp_password_change_notification')) {
    function wp_password_change_notification($user) {
        return; // Disable the default notification for password change
    }
}




//direct checkout route:

function mrj_direct_stripe_checkout(WP_REST_Request $request) {
    // Set your Stripe API key
    \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));

    $product_id = $request->get_param('product_id');
    $product_price = get_post_meta($product_id, 'program_price', true) * 100; // Convert to cents
    $product_name = get_the_title($product_id);

    // Create a Stripe Checkout session for the product
    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => ['name' => $product_name],
                    'unit_amount' => $product_price,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => home_url('/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => home_url('/cancel'),
        ]);

        return new WP_REST_Response(['id' => $session->id, 'url' => $session->url], 200);
    } catch (Exception $e) {
        return new WP_REST_Response(['error' => $e->getMessage()], 500);
    }
}

add_action('rest_api_init', function() {
    register_rest_route('mrj/v1', '/direct-checkout', [
        'methods' => 'POST',
        'callback' => 'mrj_direct_stripe_checkout',
        'permission_callback' => '__return_true'
    ]);
});



function handle_custom_contact_form_submission() {
    if (isset($_POST['contact_form_nonce']) && wp_verify_nonce($_POST['contact_form_nonce'], 'custom_contact_form_action')) {
        $name = sanitize_text_field($_POST['full-name']);
        $email = sanitize_email($_POST['email']);
        $source = sanitize_text_field($_POST['select-where']);
        $message = sanitize_textarea_field($_POST['message']);
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->Username = 'akshaysharma581995@gmail.com';
            $mail->Password = 'feulvpnfltokqjkd';
            $mail->setFrom($email, $name);
            $mail->addAddress('akshaysharma581995@gmail.com', 'Akshay');
            $mail->Subject = 'New Contact Form Submission';
            $mail->Body = "From: $name\nEmail: $email\nSource: $source\nMessage: $message";
            $mail->send();
           

            wp_redirect(home_url('/sent'));
            exit;
        } catch (Exception $e) {
            // Output error message
            wp_die('Mailer Error: ' . $mail->ErrorInfo);
        }
    } else {
        // Nonce check failed
        wp_die('Security check failed', 'Error', array( 'response' => 403 ));
    }
}
add_action('admin_post_nopriv_custom_contact_form', 'handle_custom_contact_form_submission');
add_action('admin_post_custom_contact_form', 'handle_custom_contact_form_submission');


//

function enqueue_dashicons_front_end() {
    wp_enqueue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'enqueue_dashicons_front_end');


function modify_audio_query($query) {
    // Check if it's the correct query to modify
    if (!is_admin() && $query->is_main_query() && $query->get('post_type') === 'audio') {
        // Set the author to 1
        $query->set('author', 1);
    }
}

add_action('pre_get_posts', 'modify_audio_query');



//cart-total-route
require get_theme_file_path('/inc/cart-total-route.php');
require get_theme_file_path('/inc/checkout-route.php');



function handle_add_to_cart_request(WP_REST_Request $request) {
    $params = $request->get_params();
    $title = sanitize_text_field($params['title']);
    $price = floatval($params['price']);
    $productId = sanitize_text_field($params['productId']);
    $userId = get_current_user_id();
    $existing_cart_items = get_posts([
        'post_type' => 'cart',
        'meta_query' => [
            [
                'key' => 'product_id',
                'value' => $productId,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);

    if (count($existing_cart_items) > 0) {
        $cart_post_id = $existing_cart_items[0]->ID;
    } else {
        $cart_post_id = wp_insert_post([
            'post_title' => $title,
            'post_type' => 'cart',
            'post_status' => 'publish',
        ]);

        if ($cart_post_id === 0 || is_wp_error($cart_post_id)) {
            // Handle the error appropriately
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Failed to create cart item.'
            ], 500);
        }

        update_post_meta($cart_post_id, 'program_price', $price);
        update_post_meta($cart_post_id, 'program_quantity', 1);
        update_post_meta($cart_post_id, 'product_id', $productId);
        update_post_meta($cart_post_id, 'user_id', $userId);
    }

    return new WP_REST_Response([
        'success' => true,
        'cartItemId' => $cart_post_id,
        'productId' => $productId,
        'quantity' => get_post_meta($cart_post_id, 'program_quantity', true)
    ], 200);
}






function my_register_cart_meta() {
    register_rest_field('cart', 'program_price', [
        'get_callback' => function ($object) {
            // Return the post meta
            return get_post_meta($object['id'], 'program_price', true);
        },
        'update_callback' => function ($value, $object) {
            // Update the post meta
            return update_post_meta($object->ID, 'program_price', $value);
        },
        'schema' => null,
    ]);

    register_rest_field('cart', 'program_quantity', [
        'get_callback' => function ($object) {
            // Return the post meta
            return get_post_meta($object['id'], 'program_quantity', true);
        },
        'update_callback' => function ($value, $object) {
            // Update the post meta
            return update_post_meta($object->ID, 'program_quantity', $value);
        },
        'schema' => null,
    ]);
    register_rest_field('cart', 'product_id', [
        'get_callback' => function ($object) {
            return get_post_meta($object['id'], 'product_id', true);
        },
        'update_callback' => function ($value, $object) {
            if (!empty($value)) {
                return update_post_meta($object->ID, 'product_id', sanitize_text_field($value));
            }
        },
        'schema' => null,
    ]);
    register_rest_field('cart', 'user_id', [
        'get_callback' => function ($object) {
            // Return the post meta that contains the user ID
            return get_post_meta($object['id'], 'user_id', true);
        },
        'update_callback' => null, // Assuming you don't want this to be updatable via REST
        'schema' => null, // Define the schema if needed
    ]);
      // Register user_login (username) in the REST field
      register_rest_field('cart', 'username', [
        'get_callback' => function ($object) {
            $user_id = get_post_meta($object['id'], 'user_id', true);
            $user_data = get_userdata($user_id);
            return $user_data ? $user_data->user_login : null;
        },
        'update_callback' => null, // Assuming you don't want this to be updatable via REST
        'schema' => null, // Define the schema if needed
    ]);
}

add_action('rest_api_init', 'my_register_cart_meta');

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/cart', [
        'methods' => 'POST',
        'callback' => 'handle_add_to_cart_request',
      'permission_callback' => function () {
    return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('edit_posts'));
}

    ]);
});

function get_user_cart_items() {
    if (!is_user_logged_in()) {
        return [];  // Return empty if user is not logged in
    }
    $args = [
        'post_type' => 'cart',
        'post_status' => ['publish', 'private'],
        'author' => get_current_user_id(),  // Ensure it fetches only the current user's items
        'numberposts' => -1
    ];
    return get_posts($args);
}

add_action('rest_api_init', function () {
    register_rest_route('mrj/v1', '/cart-total', array(
        'methods' => WP_REST_SERVER::READABLE,
        'callback' => 'mrjCartTotal',
        'permission_callback' => function () {
            return is_user_logged_in(); // Ensure only logged-in users can access
        }
    ));
});
//force cart post to be private
function makeCartPrivate($data, $postarr) {
    if ($data['post_type'] == 'cart' && $data['post_status'] != 'trash') {
        // Set the post status to 'private' if it's a cart post and not being moved to trash
        $data['post_status'] = 'private';
    }
    return $data;
}
add_filter('wp_insert_post_data', 'makeCartPrivate', 10, 2);

add_filter('wp_insert_post_data', 'makeCartPrivate', 10, 2);
//to make cart private
// Modify the WP_Query arguments in the REST request for 'cart' post type to include private posts for logged-in users.
add_filter('rest_cart_query', function ($args, $request) {
    if (is_user_logged_in()) {
        $args['post_status'] = ['publish', 'private'];
        $args['author'] = get_current_user_id();
    }
    return $args;
}, 10, 2);



add_filter('the_title', function($title, $id = null) {
    if (get_post_type($id) == 'cart') {
        return preg_replace('/^Private:\s*/', '', $title);
    }
    return $title;
}, 10, 2);

// First, use the correct path to include the Composer autoloader.
// The __DIR__ constant ensures you get the directory of the current file.
// Adjust the path if your 'vendor' directory is elsewhere.
// require_once(__DIR__ . '/vendor/autoload.php');

function mrj_files() {
    // Enqueue a CSS file
    wp_enqueue_style('mrj_extra_styles', get_theme_file_uri('/css/main.css'));

    // This script adds support for various browsers that don't support ES modules or certain modern JavaScript features.
    add_action('wp_footer', function () {
        echo '<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>';
        echo '<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>';
    }, 100);
}

add_action('wp_enqueue_scripts', 'mrj_files');



function mrj_features() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
}

add_action('after_setup_theme', 'mrj_features');

function mrj_enqueue_scripts() {
    // Enqueue Google Fonts
    wp_enqueue_style('google-font-courgette', 'https://fonts.googleapis.com/css2?family=Courgette&display=swap', [], null);
    wp_enqueue_style('google-font-arimo', 'https://fonts.googleapis.com/css2?family=Arimo:ital,wght@0,400..700;1,400..700&display=swap', [], null);
    
    // Enqueue the latest Font Awesome
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css', [], null);

    // Enqueue other styles and scripts
    wp_enqueue_style('mrj_extra_styles', get_theme_file_uri('/css/main.css'));
    wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
    wp_enqueue_script('mrj-index-js', get_theme_file_uri('./build/index.js'), array('jquery'), '1.0', true);
    wp_script_add_data('mrj-index-js', 'type', 'module');
    wp_localize_script('mrj-index-js', 'mrjData', array(
        'root_url' => get_site_url(),
        'nonce' => wp_create_nonce('wp_rest')
    ));
}

add_action('wp_enqueue_scripts', 'mrj_enqueue_scripts');



//redirect subscriber account out of admin to the homepage
add_action('admin_init','redirectSubsToFrontend');

function redirectSubsToFrontend(){
$currentUser = wp_get_current_user();
    if(count($currentUser -> roles) == 1 AND $currentUser-> roles[0] == 'subscriber' ){
            wp_redirect(site_url('/'));
            exit;
}
}
//hide dashboard for users
add_action('wp_loaded','noSubsAdminBar');

function noSubsAdminBar(){
$currentUser = wp_get_current_user();
    if(count($currentUser -> roles) == 1 AND $currentUser-> roles[0] == 'subscriber' ){
           show_admin_bar(false);
}
}
//Customize login screen
add_filter('login_headerurl','headerUrl');

function headerUrl(){
    return esc_url(site_url('/'));
}
add_action('login_enqueue_scripts','loginCSS');

function loginCSS(){
    wp_enqueue_style('mrj_extra_styles', get_theme_file_uri('/css/main.css'));
}
add_filter('login_headertitle', 'loginTitle');

function loginTitle(){
return get_bloginfo('name');
}

//user program access
// function mrj_on_user_register($user_id) {
//     global $wpdb;
//     $user_info = get_userdata($user_id);
//     $table_name = $wpdb->prefix . 'user_program_access';

//     // Fetch all programs
//     $programs = get_posts([
//         'post_type' => 'program',
//         'posts_per_page' => -1
//     ]);

//     // Prepare the programs access data
//     $programs_access = [];
//     foreach ($programs as $program) {
//         $programs_access[] = [
//             'program_id' => $program->ID,
//             'program_name' => $program->post_title,
//             'access' => false  // Default to no access
//         ];
//     }

//     // Insert data into custom table
//     $wpdb->insert($table_name, [
//         'user_id' => $user_id,
//         'user_email' => $user_info->user_email,
//         'programs_access' => json_encode($programs_access)  // Store as JSON
//     ]);
// }
// add_action('user_register', 'mrj_on_user_register');
function mrj_on_user_register($user_id) {
    global $wpdb;
    $user_info = get_userdata($user_id);
    
    if (!$user_info) {
        error_log('Failed to get user data for user ID: ' . $user_id);
        return;
    }

    $table_name = $wpdb->prefix . 'user_program_access';
    error_log('Registering new user with ID: ' . $user_id);

    $programs = get_posts([
        'post_type' => 'program',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids'  // Only get the IDs to reduce memory usage
    ]);

    if (empty($programs)) {
        error_log('No programs found to register for user.');
        return;
    }

    foreach ($programs as $program_id) {
        $program = get_post($program_id);  // Get the program post object
        error_log('Inserting program: ' . $program->post_title); 

        // Check if the program already has a row for this user
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND program_name = %s",
            $user_id,
            $program->post_title
        ));

        // Only insert if it does not exist
        if (!$exists) {
            $result = $wpdb->insert($table_name, [
                'user_id' => $user_id,
                'program_id' => $program_id, 
                'user_email' => $user_info->user_email,
                'program_name' => $program->post_title,
                'access_granted' => 0,
                'created_at' => current_time('mysql', 1)
            ]);

            if ($result === false) {
                error_log('Failed to insert program access for user. Error: ' . $wpdb->last_error);
            } else {
                error_log('Program access for user ' . $user_id . ' to program ' . $program->post_title . ' added.');
            }
        }
    }
}

add_action('user_register', 'mrj_on_user_register');


// Hook into user account deletion and clean up custom data
function mrj_on_user_delete($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_program_access';

    // Delete the records where the user ID matches the ID of the user being deleted
    $result = $wpdb->delete($table_name, ['user_id' => $user_id]);

    if (false === $result) {
        error_log("Failed to delete user program access records for user ID: $user_id");
    } else {
        error_log("Deleted user program access records for user ID: $user_id");
    }
}

// Add the hook into WordPress
add_action('delete_user', 'mrj_on_user_delete');


