<?php 
/*
Template Name: Shop
*/

get_header(); 
?>

<div class="wrapper">
    <div class="main-content">
        <section class="section-programs">
            <div class="header-cart">
                <a href="http://melodyraejones.local/shop/cart/">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-badge">0</span>
                </a>
            </div>
            
            <div class="shop-section">
                <div class="shop-text-box">
                    <h1 class="heading-primary">STORE ITEMS</h1>
                    <div>
                        <div>
                            <h3 class="shop-list">Soul Notes Meditations</h3>
                            <a href="https://melodyraejones.com/z_sample-pages/mels-faves.html" class="first-link">Daily Practices Series's</a>       
                        </div>
                        <a href="https://melodyraejones.com/z_sample-pages/mels-faves.html" class="second-link">Mel's Faves</a>       
                        <div>
                            <a href="https://melodyraejones.com/z_sample-pages/mels-faves.html" class="third-link">Daily Practices Series's</a>
                        </div>
                        <a href="https://melodyraejones.com/z_sample-pages/mels-faves.html">Mel's Faves</a>       
                        <div>
                            <h3 class="shop-list">Online Programs</h3>
                            <a href="https://melodyraejones.com/z_sample-pages/mels-faves.html">Expand Your Wisdom Toolkit</a>       
                        </div>
                    </div>
                    <div class="site-header__util">
                        <?php if(is_user_logged_in()) { ?>
                            <a href="<?php echo wp_logout_url(); ?>" class="btn btn--small btn-out">
                                <span class="btn__text logout-btn">Log Out</span>
                            </a>
                        <?php } else { ?>
                            <a href="<?php echo wp_login_url(); ?>" class="btn btn--small btn--purple login-btn">Login</a>
                            <a href="<?php echo wp_registration_url(); ?>" class="btn btn--small btn--dark-purple signup-btn">Sign Up</a>
                        <?php } ?>
                    </div>
                </div>
                <div class="shop-items container grid grid--3-cols">
                    <?php 
                    $args = array(
                        'post_type' => 'program',
                        'posts_per_page' => -1  // Retrieve all posts
                    );
                    $program_query = new WP_Query($args);

                    if ($program_query->have_posts()) : 
                        while ($program_query->have_posts()) : $program_query->the_post();
                            $program_image = get_the_post_thumbnail_url(get_the_ID(), 'full');
                            $program_price = get_post_meta(get_the_ID(), 'program_price', true); 
                            ?>
                            <div class="program" data-id="<?php echo get_the_ID(); ?>">
                                <img src="<?php echo esc_url($program_image); ?>" class="program-img" alt="<?php the_title(); ?>" />
                                <div class="program-content">
                                    <p class="program-title"><?php the_title(); ?></p>
                                    <p class="program-price">Price: $<?php echo esc_html(number_format((float)$program_price, 2, '.', '')); ?></p>
                                    <div class="button-container">
                                        <div class="icons">
                                            <a href="#" class="btn add_to_cart" data-id="<?php echo get_the_ID(); ?>">+</a>
                                            <span class="product-quantity">0</span>
                                            <a href="#" class="btn remove_from_cart" data-id="<?php echo get_the_ID(); ?>">-</a>
                                        </div>
                                        <a href="<?php the_permalink(); ?>" class="btn btn--full details">More Details &darr;</a>
                                    </div>
                                </div>
                            </div>
                            <?php 
                        endwhile;
                        wp_reset_postdata(); 
                    endif;
                    ?>
                </div>
            </div>
        </section>
    </div>
</div>

<?php get_footer(); ?>
