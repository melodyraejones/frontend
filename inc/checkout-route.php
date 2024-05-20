<?php
require_once __DIR__ . '/../vendor/autoload.php';
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $envFile = fopen($envPath, 'r');
    if ($envFile) {
        while (($line = fgets($envFile)) !== false) {
            if (strpos($line, '=') !== false && substr(trim($line), 0, 1) !== '#') {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                $value = trim($value, "'\"");
                putenv("$name=$value");
            }
        }
        fclose($envFile);
    }
}

$stripeSecretKey = "sk_test_51Nxe1kA8vWmHrQR2oh0vDM1uOzzzSollqNtxCVrPeUhthXUtE5tkh4NqbJ1182B8dJpYg7AC6Dy4ZssWSZtAOOIy00WFtiOhDc";
$webhookSecretKey = "whsec_330e637ee22ca56085a232cbb9d913ee00097af1e7758bc91e558feabd773a22";
\Stripe\Stripe::setApiKey($stripeSecretKey);

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
            'cancel_url' => 'https://171c-2607-fea8-b5e-8b00-1c0c-9521-85e0-e76f.ngrok-free.app/cancel',
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

add_action('rest_api_init', function () {
    register_rest_route('mrj/v1', '/checkout', array(
        'methods' => 'POST',
        'callback' => 'mrj_create_stripe_checkout_session',
        'permission_callback' => '__return_true'
    ));
});

add_action('rest_api_init', function () {
    register_rest_route('mrj/v1', '/webhook', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'mrj_handle_stripe_webhook',
        'permission_callback' => '__return_true'
    ));
});

function mrj_handle_stripe_webhook() {
    global $webhookSecretKey;

    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

    error_log('Webhook received');
    error_log('Payload: ' . $payload);
    error_log('Signature: ' . $sig_header);

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhookSecretKey);
    } catch(\UnexpectedValueException $e) {
        error_log('Invalid payload: ' . $e->getMessage());
        http_response_code(400);
        exit();
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
        error_log('Invalid signature: ' . $e->getMessage());
        http_response_code(400);
        exit();
    }

    if ($event->type == 'checkout.session.completed') {
        $session = $event->data->object;
        $user_id = $session->metadata->user_id;
        $product_names = json_decode($session->metadata->product_names);

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_program_access';

        error_log('Checkout session completed for user ID: ' . $user_id);
        error_log('Product names: ' . json_encode($product_names));

        foreach ($product_names as $product_name) {
            $result = $wpdb->update(
                $table_name,
                ['access_granted' => 1],
                [
                    'user_id' => $user_id,
                    'program_name' => $product_name 
                ]
            );

            error_log('SQL Query: ' . $wpdb->last_query);
            if (false === $result) {
                error_log('Failed to update database for user ID: ' . $user_id . ' and program name: ' . $product_name);
            } else {
                error_log('Database updated successfully for user ID: ' . $user_id . ' and program name: ' . $product_name);
            }
        }

        http_response_code(200);
    } else {
        error_log('Unhandled event type: ' . $event->type);
        http_response_code(400);
        exit();
    }
}
