<?php
/**
 * Campaign Button Handler
 * Redirects "Start a Campaign" clicks appropriately based on user status
 */

// Filter homepage content to update campaign creation links
add_filter('the_content', function($content) {
    if (is_front_page() || is_home()) {
        // Replace admin campaign creation URL with smart redirect
        $content = str_replace(
            '/wp-admin/post-new.php?post_type=fundraiser_campaign',
            wp_registration_url(),
            $content
        );
    }
    return $content;
}, 15);

// Enable user registration if not already enabled
add_action('init', function() {
    if (get_option('users_can_register') != '1') {
        update_option('users_can_register', '1');
    }

    // Set default role to fundraiser for new registrations
    if (get_option('default_role') != 'fundraiser') {
        update_option('default_role', 'fundraiser');
    }
});

// Redirect after registration to campaign creation
add_filter('registration_redirect', function($redirect_to) {
    // After successful registration, redirect to campaign creation
    return admin_url('post-new.php?post_type=fundraiser_campaign');
});

?>
