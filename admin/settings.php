<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="plugin-dashboard">
    <div class="plugin-dashboard-card">
        <center>
            <a href="https://turboblogger.io/">
                <img src="<?php echo esc_url(plugins_url('assets/images/Turbo-Blogger-Logo.png', dirname(__FILE__))); ?>" class="turbo-blogger-logo" alt="Turbo Blogger Logo">
            </a>
        </center>
        <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="blog-form">
            <?php wp_nonce_field('turbo-blogger-settings-page'); ?>
            <input type="hidden" name="action" value="generate_content">
            <div class="form-group">
                <label for="title">Blog Title<span class="required">*</span></label>
                <input type="text" name="title" id="title" placeholder="Ex. 5 Things You MUST Do When Writing A Blog" required>
            </div>
            <div class="form-group">
            <label for="category">Category<span class="required">*</span></label>
            <select name="category" id="category" required>
                <?php
                $categories = get_categories(array(
                    'hide_empty' => false, // Include empty categories
                    'orderby' => 'name', // Order by category name
                    'parent' => 0, // Get only parent categories
                ));
                foreach ($categories as $category) {
                    echo '<option value="' . esc_html($category->term_id) . '">' . esc_html($category->name) . '</option>';

                    // Get child categories
                    $child_categories = get_categories(array(
                        'hide_empty' => false,
                        'orderby' => 'name',
                        'parent' => $category->term_id,
                    ));
                    foreach ($child_categories as $child_category) {
                        echo '<option value="' . esc_html($child_category->term_id) . '">-- ' . esc_html($child_category->name) . '</option>';
                    }
                }
                ?>
            </select>
            </div>
            <div class="form-group">
                <label for="writing-style">Writing Style<span class="required">*</span></label>
                <select name="writing-style" id="writing-style" required>
                    <?php
                    $writing_styles = array(
                        'Article',
                        'Pros and Cons',
                        'Review',
                        'Tutorial',
                        'How-to',
                        'Opinion',
                        'Analysis',
                        'Interviews',
                        'Case Study',
                        'Guide',
                        'FAQ'
                    );

                    foreach ($writing_styles as $style) {
                        echo '<option value="' . esc_html($style) . '">' . esc_html($style) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="keywords">SEO Keywords<span class="required">*</span></label>
                <input type="text" name="keywords" id="keywords" placeholder="Ex. Wordpress, Blogging, etc." required>
            </div>
            <div class="form-group">
                <label for="target-audience">Target Audience<span class="required">*</span></label>
                <input type="text" name="target-audience" id="target-audience" placeholder="Ex. New Bloggers" required>
            </div>
            <div class="form-group">
                <label for="call-to-action">Call to Action (Optional)</label>
                <textarea name="call-to-action" id="call-to-action" placeholder="Ex. Follow Us @TurboBlogger"></textarea>
            </div>
            <div class="form-group">
                <label for="additional-requests">Additional Requests (Optional)</label>
                <textarea name="additional-requests" id="additional-requests" placeholder="Ex. Use a Professional tone, Make the blog 500 words or more, etc." maxlength="200"></textarea>
            </div>
            <input type="submit" value="Create Blog Post" class="btn btn-primary" id="create-blog-post">
            <div class="progress-bar-container" style="display: none;">
                <div class="progress-bar"></div>
                <div class="countdown-timer">The blog is generating and will be ready in <span id="countdown">60</span> seconds</div>
            </div>
        </form>
    </div>
</div>

<script>
    (function($) {
        $(document).ready(function() {
            var blogForm = $('#blog-form');
            var bulkUploadForm = $('#bulk-upload-form');
            var progressBarContainer = $('.progress-bar-container');
            var progressBar = $('.progress-bar');
            var countdownTimer = $('.countdown-timer');

            blogForm.on('submit', function(e) {
                e.preventDefault();
                progressBarContainer.show();
                progressBar.animate({ width: '100%' }, 60000);
                $('.btn').prop('disabled', true);

                var seconds = 60;
                var countdownElement = $('#countdown');
                var countdownInterval = setInterval(function() {
                    seconds--;
                    countdownElement.text(seconds);
                    if (seconds <= 0) {
                        clearInterval(countdownInterval);
                        countdownElement.text('0');
                    }
                }, 1000);

                setTimeout(function() {
                    progressBar.css('width', '0');
                    $('.btn').prop('disabled', false);
                    progressBarContainer.hide();
                }, 150000);

                // Submit the form
                blogForm.unbind('submit').submit();
            });

            bulkUploadForm.on('submit', function(e) {
                e.preventDefault();
                progressBarContainer.show();
                progressBar.animate({ width: '100%' }, 60000);
                $('.btn').prop('disabled', true);

                var seconds = 60;
                var countdownElement = $('#countdown');
                var countdownInterval = setInterval(function() {
                    seconds--;
                    countdownElement.text(seconds);
                    if (seconds <= 0) {
                        clearInterval(countdownInterval);
                        countdownElement.text('0');
                    }
                }, 1000);

                setTimeout(function() {
                    progressBar.css('width', '0');
                    $('.btn').prop('disabled', false);
                    progressBarContainer.hide();
                }, 150000);

                // Submit the form
                bulkUploadForm.unbind('submit').submit();
            });
        });
    })(jQuery);
</script>