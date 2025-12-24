<?php
/**
 * Add "Sign Up as Fundraiser" button to demo campaign pages
 */

// Add button to campaign content
add_filter('the_content', function($content) {
    // Only on single campaign posts and demo campaign
    if (is_singular('fundraiser_campaign')) {
        $post_id = get_the_ID();
        $is_demo = get_post_meta($post_id, '_fp_is_demo', true);

        if ($is_demo == '1') {
            $signup_button = '
            <div class="demo-signup-banner" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 30px 0; text-align: center;">
                <h3 style="color: white; margin-top: 0;">Want to create your own fundraising campaign?</h3>
                <p style="font-size: 16px; margin: 15px 0;">Sign up as a fundraiser organizer and access powerful tools to manage campaigns, raffles, and donations.</p>
                <a href="' . wp_registration_url() . '" class="demo-signup-button" style="display: inline-block; background: white; color: #667eea; padding: 15px 40px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 18px; margin-top: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: transform 0.2s;">
                    Sign Up as Fundraiser →
                </a>
                <p style="font-size: 14px; margin: 15px 0 0 0; opacity: 0.9;">Already have an account? <a href="' . wp_login_url() . '" style="color: white; text-decoration: underline;">Log in here</a></p>
            </div>
            ';

            // Add the button after the main content
            $content .= $signup_button;
        }
    }

    return $content;
}, 20);

// Add CSS for the button
add_action('wp_head', function() {
    if (is_singular('fundraiser_campaign')) {
        echo '
        <style>
            .demo-signup-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.3) !important;
            }

            @media (max-width: 768px) {
                .demo-signup-banner {
                    padding: 20px 15px !important;
                }

                .demo-signup-banner h3 {
                    font-size: 20px !important;
                }

                .demo-signup-button {
                    font-size: 16px !important;
                    padding: 12px 30px !important;
                }
            }
        </style>
        ';
    }
});

?>
