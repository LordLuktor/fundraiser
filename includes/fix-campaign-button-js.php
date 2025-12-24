<?php
/**
 * Fix Start Campaign Button with JavaScript
 * This ensures the button redirects to registration regardless of caching
 */

add_action('wp_footer', function() {
    if (is_front_page() || is_home()) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Find all links that point to the campaign creation page
            const links = document.querySelectorAll('a[href*="post-new.php?post_type=fundraiser_campaign"]');

            links.forEach(function(link) {
                // Update the href to point to registration
                link.setAttribute('href', '<?php echo wp_registration_url(); ?>');
            });
        });
        </script>
        <?php
    }
}, 999);

?>
