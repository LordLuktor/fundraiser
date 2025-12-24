<?php
/**
 * Demo Login Handler
 * Adds a custom rewrite rule for /demo-login/ to auto-login as demo user
 */

// Add rewrite rule
add_action('init', function() {
    add_rewrite_rule('^demo-login/?$', 'index.php?demo_login=1', 'top');
});

// Add query var
add_filter('query_vars', function($vars) {
    $vars[] = 'demo_login';
    return $vars;
});

// Handle demo login
add_action('template_redirect', function() {
    if (get_query_var('demo_login')) {
        // Get demo user
        $demo_user = get_user_by('login', 'demo_fundraiser');

        if ($demo_user) {
            // Log in the user
            wp_set_current_user($demo_user->ID);
            wp_set_auth_cookie($demo_user->ID);
            do_action('wp_login', $demo_user->user_login, $demo_user);

            // Redirect to campaign dashboard
            wp_redirect(home_url('/campaign-dashboard/'));
            exit;
        } else {
            // If demo user doesn't exist, redirect to home
            wp_redirect(home_url('/'));
            exit;
        }
    }
});

// Flush rewrite rules on activation (run once)
register_activation_hook(__FILE__, 'flush_rewrite_rules');

?>
