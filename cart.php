<?php
/*
Template Name: Cart
*/

if (!is_user_logged_in()) {
    wp_redirect(esc_url(site_url('/wp-login.php')));
    exit;
}

get_header();
?>

<h1 class="heading-primary cart-heading">Your Cart</h1>

<section class="cart-details container">
    <h2 class="heading-secondary" id="cart">Your Order Summary</h2>
    <div class="cart-items" id="cart-items">
        <?php $userItems = new WP_Query(array(
            'post_type' => 'cart',
            'posts_per_page' => -1,
            'author' => get_current_user_id()
        ));

        while($userItems->have_posts()) {
            $userItems->the_post();
            // Output your cart item details here
        } ?>
    </div>

    <div class="cart-checkout">
        <div class="cart-total">
            <p id="cart-total"></p>
            <!-- JavaScript will dynamically update the total here -->
        </div>
        <!-- Form that posts to the checkout.php -->
      
            <button type="submit" class="pay-button">Pay</button>
      
    </div>
</section>

<?php get_footer(); ?>
