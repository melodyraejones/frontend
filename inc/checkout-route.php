<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Access the Stripe secret keys from the wp-config.php
$stripeSecretKey = defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '';
$webhookSecretKey = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';

// Set the Stripe API key
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Function to create a Stripe Checkout Session
function mrj_create_stripe_checkout_session(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $user_info = get_userdata($user_id);
    if (!$user_info) {
        return new WP_REST_Response(['error' => 'User not logged in'], 401);
    }

    $validated_data = $request->get_json_params();
    $product_names = array_map(function($item) {
        return $item['name'];
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
            'cancel_url' => home_url('/cancel'),
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

// Register the /webhook route
add_action('rest_api_init', function () {
    register_rest_route('mrj/v1', '/webhook', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'mrj_handle_stripe_webhook',
        'permission_callback' => '__return_true'
    ));
});

// Function to handle Stripe webhooks
function mrj_handle_stripe_webhook() {
    global $webhookSecretKey;

    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhookSecretKey);
    } catch(\UnexpectedValueException $e) {
        http_response_code(400);
        exit();
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
        http_response_code(400);
        exit();
    }

    if ($event->type == 'checkout.session.completed') {
        $session = $event->data->object;
        $user_id = $session->metadata->user_id;
        $product_names = json_decode($session->metadata->product_names);

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_program_access';

        foreach ($product_names as $product_name) {
            $wpdb->update(
                $table_name,
                ['access_granted' => 1],
                [
                    'user_id' => $user_id,
                    'program_name' => $product_name 
                ]
            );
        }

        http_response_code(200);
    } else {
        http_response_code(400);
        exit();
    }
}
