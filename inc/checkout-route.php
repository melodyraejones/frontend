<?php
require_once __DIR__ . '/../vendor/autoload.php';
$envPath = __DIR__ . '/../.env';

// Check if the .env file exists
if (file_exists($envPath)) {
    // Open the .env file
    $envFile = fopen($envPath, 'r');

    if ($envFile) {
        // Read each line of the .env file
        while (($line = fgets($envFile)) !== false) {
            // Check if the line contains an 'equals' sign and isn't a comment
            if (strpos($line, '=') !== false && substr(trim($line), 0, 1) !== '#') {
                // Remove whitespace and split the line into name and value
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remove potential surrounding quotes
                $value = trim($value, "'\"");

                // Set the environment variable
                putenv("$name=$value");
            }
        }
        // Close the .env file
        fclose($envFile);
    }
}

// Retrieve environment variables
$environment = getenv('ENVIRONMENT');
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
$webhookSecretKey = getenv('WEBHOOK_SECRET_KEY');
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Function to get the cart URL based on the environment
function get_cart_url() {
    global $environment;
    if ($environment === 'production') {
        return 'https://yourlivewebsite.com/cart/';
    } else {
        return 'http://melodyraejones.local/shop/cart/';
    }
}

// Function to create a Stripe Checkout Session
function mrj_create_stripe_checkout_session(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $user_info = get_userdata($user_id);
    if (!$user_info) {
        return new WP_REST_Response(['error' => 'User not logged in'], 401);
    }

    $validated_data = $request->get_json_params();
    $product_names = array_map(function($item) {
        return $item['name']; // Only capture the product name
    }, $validated_data['items']);

    $line_items = array_map(function($item) {
        return [
            'price_data' => [
                'currency' => 'usd',
                'product_data' => ['name' => $item['name']],
                'unit_amount' => $item['price'] * 100,
            ],
            'quantity' => $item['quantity'],
        ];
    }, $validated_data['items']);

    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $line_items,
            'mode' => 'payment',
            'success_url' => home_url('/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => $environment === 'production' ? 'https://yourlivewebsite.com/cancel' : 'https://171c-2607-fea8-b5e-8b00-1c0c-9521-85e0-e76f.ngrok-free.app/cancel',
            'metadata' => [
                'user_id' => $user_id,
                'username' => $user_info->user_login,
                'email' => $user_info->user_email,
                'product_names' => json_encode($product_names)
            ]
        ]);
        return new WP_REST_Response(['url' => $session->url], 200);
    } catch (Exception $e) {
        return new WP_REST_Response(['error' => $e->getMessage()], 500);
    }
}

// Register the /checkout route
add_action('rest_api_init', function () {
    register_rest_route('mrj/v1', '/checkout', array(
        'methods' => 'POST',
        'callback' => 'mrj_create_stripe_checkout_session',
        'permission_callback' => '__return_true'
    ));
});

function mrj_handle_stripe_webhook() {
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

    // Your webhook secret from Stripe dashboard
    $webhook_secret = getenv('WEBHOOK_SECRET_KEY');

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload, $sig_header, $webhook_secret
        );
    } catch(\UnexpectedValueException $e) {
        // Invalid payload
        http_response_code(400);
        exit();
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
        // Invalid signature
        http_response_code(400);
        exit();
    }

    // Handle the checkout.session.completed event
    if ($event->type == 'checkout.session.completed') {
        $session = $event->data->object;
        $user_id = $session->metadata->user_id;
        $product_names = json_decode($session->metadata->product_names);

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_program_access';

        // Update database based on $user_id and $product_names
        foreach ($product_names as $product_name) {
            $wpdb->update(
                $table_name,
                ['access_granted' => 1], // Set access granted to true
                [
                    'user_id' => $user_id,
                    'program_name' => $product_name 
                ]
            );
        }
        
        http_response_code(200); // Acknowledge receipt of the event
    } else {
        http_response_code(400);
        exit();
    }
}

// Add a route to listen for the webhook events
add_action('rest_api_init', function () {
    register_rest_route('mrj/v1', '/webhook', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'mrj_handle_stripe_webhook',
        'permission_callback' => '__return_true'
    ));
});
