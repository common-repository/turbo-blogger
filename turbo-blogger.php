<?php
/**
 * Plugin Name: Turbo Blogger
 * Description: Our AI powered plugin creates and publishes blogs to your website automatically. Simply enter the title, blog category, and SEO keywords you want to rank for and generate a blog instantly!
 * Version: 1.80
 * Author: <a href="https://turboblogger.io" target="_blank">TurboBlogger.io</a>
 * Icon: Turbo-Blogger-Dash-Icon.svg
 **/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Add the menu item for the plugin settings
add_action('admin_menu', 'turbo_blogger_admin_menu');

function turbo_blogger_admin_menu() {
    add_menu_page(
        'Turbo Blogger',
        'Turbo Blogger',
        'manage_options',
        'turbo-blogger',
        'turbo_blogger_admin_page_display',
        'data:image/svg+xml;base64,' . base64_encode(file_get_contents(plugin_dir_path(__FILE__) . '/assets/images/Turbo-Blogger-Dash-Icon.svg'))
    );

    add_submenu_page(
        'turbo-blogger',
        'Create a Blog',
        'Create a Blog',
        'manage_options',
        'turbo-blogger',
        'turbo_blogger_admin_page_display'
    );

    add_submenu_page(
        'turbo-blogger',
        'Bulk Upload',
        'Bulk Upload',
        'manage_options',
        'turbo-blogger-bulk-upload',
        'turbo_blogger_bulk_upload_page'
    );

    add_submenu_page(
        'turbo-blogger',
        'Resources',
        'Resources',
        'manage_options',
        'turbo-blogger-resources',
        'turbo_blogger_resources_page'
    );

    add_submenu_page(
        'turbo-blogger',
        'Settings',
        'Settings',
        'manage_options',
        'turbo-blogger-settings',
        'turbo_blogger_settings_page'
    );

    add_submenu_page(
        'turbo-blogger',
        'Follow Me',
        'Follow Me',
        'manage_options',
        'turbo-blogger-follow-me',
        'turbo_blogger_follow_me_page'
    );
}

// Render the bulk upload page
function turbo_blogger_bulk_upload_page() {

    if (isset($_POST['upload-file-submit'])) {

        $api_key = get_option('turbo-blogger-api-key');
        if (empty($api_key)) {
            esc_url(wp_redirect(admin_url('admin.php?page=turbo-blogger-bulk-upload&error=no_api_key')));
            exit;
        }

        if(!isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST['_wpnonce'] ) ) , 'turbo-blogger-bulk-upload' ) )  {
            $nonce  = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING);
            echo '<div class="error"><p>Sorry, your nonce did not verify./p></div>';
        }

        if ( empty( $_FILES ) ) {
            return;
        }

        // Check if there was an error in file upload
        if ($_FILES['upload-file']['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="error"><p>Error uploading the file. Please try again.</p></div>';
        } else {

            $file_extension = pathinfo(sanitize_file_name($_FILES['upload-file']['name']), PATHINFO_EXTENSION);
            // Check if the file extension is 'csv' (case-insensitive)
            if (strtolower($file_extension) === 'csv') {

                $file_path = sanitize_text_field($_FILES['upload-file']['tmp_name']);
                $handle = fopen($file_path, 'r');

                // Check if the file could be opened
                if ($handle) {
                    $success_count = 0;
                    $error_count = 0;

                    // Skip the header row
                    $header_row = fgetcsv($handle);

                    // Initialize an array to store processed titles
                    $processed_titles = array();

                    error_log('Looping through csv file');

                    while (($data = fgetcsv($handle)) !== false) {
                        // Extract the data from the CSV row
                        $title = isset($data[0]) ? sanitize_text_field($data[0]) : '';
                        $category = isset($data[1]) ? sanitize_text_field($data[1]) : '';
                        $writing_styles = isset($data[2]) ? sanitize_text_field($data[2]) : '';
                        $keywords = isset($data[3]) ? sanitize_text_field($data[3]) : '';
                        $target_audience = isset($data[4]) ? sanitize_text_field($data[4]) : '';
                        $call_to_action = isset($data[5]) ? sanitize_text_field($data[5]) : '';
                        $additional_requests = isset($data[6]) ? sanitize_textarea_field($data[6]) : '';

                        // Capture the domain
                        $domain = $_SERVER['HTTP_HOST'];                

                        // Prepare form data for saving to database
                        $formData = array (
                        'title' => $title,
                        'writing_style' => $writing_styles,
                        'keywords' => $keywords,
                        'target_audience' => $target_audience,
                        'call_to_action' => $call_to_action,
                        'additional_requests' => $additional_requests,
                        'domain' => $domain
                        );

// Save the data to the database
turbo_blogger_save_form_and_csv_data_to_db($formData);

                        // Check if the post with the same title has already been processed
                        if (in_array($title, $processed_titles)) {
                            // Post with the same title has already been processed, increment the error count
                            $error_count++;
                        } else {
                            // Check if the post with the same title already exists
                            $existing_post = get_page_by_title($title, OBJECT, 'post');
                            //error_log('CSV title to create: ' . print_r($title, true));

                            if (!$existing_post) {
                                try {
                                    // Generate the blog content
                                    $generated_content = turbo_blogger_generate_content($title, $keywords, $additional_requests, $target_audience, $call_to_action, $writing_styles);
                                    //error_log('Generate content title to create: ' . print_r($generated_content['title'], true));

                                    // Get the selected post status from the settings
                                    $post_status_option = get_option('turbo-blogger-post-status-option');

                                    // Get the category names from the CSV
                                    $category_names = explode(',', $category);
                                    $primary_category_name = trim($category_names[0]);
                                    $additional_category_names = array_map('trim', array_slice($category_names, 1));

                                    // Get or create the primary category
                                    $primary_category = get_category_by_slug(sanitize_title($primary_category_name));

                                    if (!$primary_category) {
                                        // Primary category doesn't exist, create a new category
                                        $primary_category_id = wp_create_category($primary_category_name);
                                        $primary_category = get_category($primary_category_id);
                                    }

                                    // Add additional categories
                                    $additional_categories = array();
                                    foreach ($additional_category_names as $additional_category_name) {
                                        $additional_category = get_category_by_slug(sanitize_title($additional_category_name));

                                        if (!$additional_category) {
                                            // Additional category doesn't exist, create a new category
                                            $additional_category_id = wp_create_category($additional_category_name);
                                            $additional_category = get_category($additional_category_id);
                                        }

                                        $additional_categories[] = $additional_category->term_id;
                                    }

                                    // Set the category array with the primary category and additional categories
                                    $post_categories = array($primary_category->term_id);
                                    $post_categories = array_merge($post_categories, $additional_categories);

                                    // Create the new post
                                    $new_post_id = wp_insert_post(array(
                                        'post_title' => $title,
                                        'post_content' => $generated_content['content'],
                                        'post_name' => sanitize_title_with_dashes($title),
                                        'post_status' => $post_status_option,
                                        'post_category' => $post_categories,
                                    ));

                                    // Set the first category as primary
                                    if ($post_categories) {
                                        $first_category_id = $post_categories[0];
                                        $first_category = get_term($first_category_id, 'category');

                                        $first_category->count = 0; // Reset category count to ensure it becomes the primary category
                                        wp_update_term($first_category_id, 'category', (array) $first_category);
                                    }

                                    if ($new_post_id) {
                                        $success_count++;
                                    } else {
                                        $error_count++;
                                    }
                                } catch (Exception $e) {
                                    turbo_blogger_log_error($e->getMessage());
                                    $error_count++;
                                }
                            } else {
                                // Post with the same title already exists, increment the error count
                                $error_count++;
                            }

                            // Add the processed title to the array
                            $processed_titles[] = $title;
                        }
                    }

                    fclose($handle);

                    echo '<div class="updated" id="blog-processing-status"><p>';
                    echo 'Blogs generated successfully: <span id="success-count">' . esc_html($success_count) . '</span><br>';
                    echo 'Blogs failed to generate: <span id="error-count">' . esc_html($error_count) . '</span>';
                    echo '</p></div>';
                } else {
                    echo '<div class="error"><p>Error opening the uploaded file.</p></div>';
                }
            } else {
                echo '<div class="error"><p>The uploaded file is not a CSV file.</p></div>';
            }
        }
    }

    if (isset($_GET['error']) && sanitize_text_field($_GET['error']) == 'no_api_key') {
        echo '<div class="error"><p>Please set your OpenAI API Key in the Turbo Blogger <a href="' . esc_url(admin_url("admin.php?page=turbo-blogger-settings")) . '">settings</a> first.</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1>Bulk Upload</h1>
        <p>Upload a CSV file containing multiple blog details for bulk blog generation. *NOTE: You can only upload as many blogs as your server allows. If the page gets stuck loading you will need to ask your server admin to reconfigure your server settings.*</p>
        <form method="post" enctype="multipart/form-data" id="bulk-upload-form">
            <?php wp_nonce_field('turbo-blogger-bulk-upload'); ?>
            <input type="file" name="upload-file" required />
            <p>CSV File Format: Blog Title, Category, SEO Keywords, Target Audience, Call to Action (Optional), Additional Requests (Optional). Download an example template <a href="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/Turb-Blogger-Bulk-Upload-Demo-Data.csv'); ?>">here</a>.</p>
            <?php submit_button('Generate Blogs', 'primary', 'upload-file-submit'); ?>
        </form>
    </div>
    <?php
}

// Render the resources page
function turbo_blogger_resources_page() {
    ?>
    <div class="wrap">
        <h1>TurboBlogger Resources</h1> 
        <p>Looking for information on how to use TurboBlogger and all of its features? Click the button below to get started.</p>
        <a href="https://turboblogger.io/resources/" target="_blank" class="button-primary">View Resources</a>
    </div>
    <?php
}

// Render the follow me page
function turbo_blogger_follow_me_page() {
    ?>
    <div class="wrap">
        <h1>Follow The Founder</h1>
        <p>My name is Ciaran Mcintyre, the 23 year old founder of TurboBlogger and many others. TurboBlogger was made to give WordPress Bloggers a fast lane to content creation, and I truly appreciate all of your support. If you find my free plugin useful, my only ask is that you follow me on social media!</p>
        <p>Click the button below to follow me on Twitter:</p>
        <a href="https://turboblogger.io/follow-me" target="_blank" class="button-primary">Follow Me</a>
    </div>
    <?php
}

// Links to dashboard and settings page from plugins tab
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'turbo_blogger_add_plugin_page_links');

function turbo_blogger_add_plugin_page_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=turbo-blogger') . '">Create a Blog</a>',
	'<a href="' . admin_url('admin.php?page=turbo-blogger-bulk-upload') . '">Bulk Upload</a>',
        '<a href="' . admin_url('admin.php?page=turbo-blogger-settings') . '">Settings</a>'
    );
    return array_merge($plugin_links, $links);
}

// Render the plugin dashboard page
function turbo_blogger_admin_page_display() {
    // Register and enqueue the CSS file
    wp_enqueue_style('chatgpt-styles', plugins_url('assets/css/styles.css', __FILE__));

    // Add an action to admin_notices to display the error message
    if (isset($_GET['error']) && sanitize_text_field($_GET['error']) == 'no_api_key') {
        echo '<div class="error"><p>Please set your OpenAI API Key in the Turbo Blogger <a href="' . esc_url(admin_url("admin.php?page=turbo-blogger-settings")) . '">settings</a> first.</p></div>';
    }

    require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
}

// Render the plugin settings page
function turbo_blogger_settings_page() {
    // Check if the form is submitted
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['turbo-blogger-api-key']) && current_user_can('manage_options')) {
        // Store the API key in the database
        update_option('turbo-blogger-api-key', sanitize_text_field($_POST['turbo-blogger-api-key']));

        // Store the redirect option in the database
        update_option('turbo-blogger-redirect-option', sanitize_text_field($_POST['turbo-blogger-redirect-option']));

        // Store the post status option in the database
        update_option('turbo-blogger-post-status-option', sanitize_text_field($_POST['turbo-blogger-post-status-option']));

        // Store the OpenAI Engine option in the database
        update_option('turbo-blogger-openai-engine', sanitize_text_field($_POST['turbo-blogger-openai-engine']));

        // Display an updated message
        echo '<div class="updated"><p>Settings saved!</p></div>';

        // Retrieve the updated options
        $api_key = get_option('turbo-blogger-api-key', '');
        $redirect_option = get_option('turbo-blogger-redirect-option', 'no_redirect');
        $post_status_option = get_option('turbo-blogger-post-status-option', 'draft');
        $openai_engine = get_option('turbo-blogger-openai-engine', 'gpt-3.5-turbo-16k');
    } else {
        // Retrieve the options from the database
        $api_key = get_option('turbo-blogger-api-key', '');
        $redirect_option = get_option('turbo-blogger-redirect-option', 'no_redirect');
        $post_status_option = get_option('turbo-blogger-post-status-option', 'draft');
        $openai_engine = get_option('turbo-blogger-openai-engine', 'gpt-3.5-turbo-16k');
    }

    ?>
    <div class="wrap">
        <h1>TurboBlogger Settings</h1>
        <form method="post">
            <?php wp_nonce_field('turbo-blogger-settings'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Post Status</th>
                    <td>
                        <select name="turbo-blogger-post-status-option">
                            <option value="draft" <?php selected($post_status_option, 'draft'); ?>>Draft</option>
                            <option value="pending_review" <?php selected($post_status_option, 'pending_review'); ?>>Pending Review</option>
                            <option value="publish" <?php selected($post_status_option, 'publish'); ?>>Published</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Redirect Option</th>
                    <td>
                        <select name="turbo-blogger-redirect-option">
                            <option value="no_redirect" <?php selected($redirect_option, 'no_redirect'); ?>>No Redirect</option>
                            <option value="redirect_new_blog" <?php selected($redirect_option, 'redirect_new_blog'); ?>>Redirect To New Blog</option>
                            <option value="redirect_posts" <?php selected($redirect_option, 'redirect_posts'); ?>>Redirect To Posts</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                <th scope="row">OpenAI Engine</th>
                <td>
                    <select name="turbo-blogger-openai-engine">
                        <option value="gpt-3.5-turbo-16k" <?php selected($openai_engine, 'gpt-3.5-turbo-16k'); ?>>GPT-3.5 Turbo 16k</option>
                        <option value="gpt-4" <?php selected($openai_engine, 'gpt-4'); ?>>GPT-4 8k</option>
                        <option value="gpt-4-32k" <?php selected($openai_engine, 'gpt-4-32k'); ?>>GPT-4 32k</option>
                    </select>
                    <a href="https://turboblogger.io/resources/what-openai-engine-is-best-for-blogging/" target="_blank">Learn More</a>
                </td>

                </tr>
                <tr valign="top">
                    <th scope="row">OpenAI API Key</th>
                    <td>
                        <input type="password" id="turbo-blogger-api-key" name="turbo-blogger-api-key" value="<?php echo esc_attr($api_key); ?>" />
                        <a href="https://turboblogger.io/resources/how-to-create-an-openai-api-key-with-turboblogger/" target="_blank">Learn More</a>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register and define the settings and options
add_action('admin_init', 'turbo_blogger_register_settings');

function turbo_blogger_register_settings() {
    // Register the settings section
    add_settings_section(
        'turbo-blogger-general-settings',
        'General Settings',
        'turbo_blogger_general_settings_callback',
        'turbo-blogger-settings'
    );

    // Register the redirect option field
    add_settings_field(
        'turbo-blogger-redirect-option',
        'Redirect Option',
        'turbo_blogger_redirect_option_callback',
        'turbo-blogger-settings',
        'turbo-blogger-general-settings'
    );

    // Register the post status option field
    add_settings_field(
        'turbo-blogger-post-status-option',
        'Post Status',
        'turbo_blogger_post_status_option_callback',
        'turbo-blogger-settings',
        'turbo-blogger-general-settings'
    );

    // Register the settings field
    register_setting(
        'turbo-blogger-settings',
        'turbo-blogger-redirect-option',
        array('sanitize_callback' => 'sanitize_text_field')
    );

    // Register the settings field
    register_setting(
        'turbo-blogger-settings',
        'turbo-blogger-post-status-option',
        array('sanitize_callback' => 'sanitize_text_field')
    );
}

// Render the general settings section
function turbo_blogger_general_settings_callback() {
    echo 'Configure general settings for Turbo Blogger';
}

// Render the redirect option field
function turbo_blogger_redirect_option_callback() {
    $option = get_option('turbo-blogger-redirect-option');
    ?>
    <select name="turbo-blogger-redirect-option">
        <option value="no_redirect" <?php selected($option, 'no_redirect'); ?>>No Redirect</option>
        <option value="redirect_new_blog" <?php selected($option, 'redirect_new_blog'); ?>>Redirect To New Blog</option>
        <option value="redirect_posts" <?php selected($option, 'redirect_posts'); ?>>Redirect To Posts</option>
    </select>
    <?php
}

// Render the post status option field
function turbo_blogger_post_status_option_callback() {
    $option = get_option('turbo-blogger-post-status-option');
    ?>
    <select name="turbo-blogger-post-status-option">
        <option value="draft" <?php selected($option, 'draft'); ?>>Draft</option>
        <option value="pending_review" <?php selected($option, 'pending_review'); ?>>Pending Review</option>
        <option value="publish" <?php selected($option, 'publish'); ?>>Published</option>
    </select>
    <?php
}

// Handle form submission and generate content
add_action('admin_post_generate_content', 'turbo_blogger_handle_form_submission');

function turbo_blogger_handle_form_submission() {
    $api_key = get_option('turbo-blogger-api-key');
    if (empty($api_key)) {
        esc_url(wp_redirect(admin_url('admin.php?page=turbo-blogger-bulk-upload&error=no_api_key')));
        exit;
    }

    // Capture the domain
    $domain = $_SERVER['HTTP_HOST'];

    // Handle the form data
    $title = sanitize_text_field($_POST['title']);
    $category = sanitize_text_field($_POST['category']);
    $writing_styles = sanitize_text_field($_POST['writing-style']);
    $keywords = sanitize_text_field($_POST['keywords']);
    $target_audience = sanitize_text_field($_POST['target-audience']);
    $call_to_action = sanitize_text_field($_POST['call-to-action']);
    $additional_requests = sanitize_textarea_field($_POST['additional-requests']);   

    // Prepare form data for saving to database
    $formData = array (
        'title' => $title,
        'writing_style' => $writing_styles,
        'keywords' => $keywords,
        'target_audience' => $target_audience,
        'call_to_action' => $call_to_action,
        'additional_requests' => $additional_requests,
        'domain' => $domain
    );

    // Save the data to the database
    turbo_blogger_save_form_and_csv_data_to_db($formData);

    if(isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST['_wpnonce'] ) ) , 'turbo-blogger-settings-page' ) )  {

        // Check if a file is uploaded
        if (isset($_FILES['upload-file']) && $_FILES['upload-file']['error'] === UPLOAD_ERR_OK) {
            $file = sanitize_text_field($_FILES['upload-file']['tmp_name']);
            $handle = fopen($file, 'r');

            // Skip the header row
            $header_row = fgetcsv($handle);

            // Process each row in the file
            while (($data = fgetcsv($handle)) !== false) {
                // Extract the data from the CSV row
                $title = isset($data[0]) ? sanitize_text_field($data[0]) : '';
                $category = isset($data[1]) ? sanitize_text_field($data[1]) : '';
                $writing_styles = isset($data[2]) ? sanitize_text_field($data[2]) : '';
                $keywords = isset($data[3]) ? sanitize_text_field($data[3]) : '';
                $target_audience = isset($data[4]) ? sanitize_text_field($data[4]) : '';
                $call_to_action = isset($data[5]) ? sanitize_text_field($data[5]) : '';
                $additional_requests = isset($data[6]) ? sanitize_textarea_field($data[6]) : '';

                try {
                    // Generate the blog content
                    $generated_content = turbo_blogger_generate_content($title, $keywords, $additional_requests, $target_audience, $call_to_action, $writing_styles);

                    // Get the selected post status from the settings
                    $post_status_option = get_option('turbo-blogger-post-status-option');

                    // Create the new post
                    $new_post_id = wp_insert_post(array(
                        'post_title' => $title,
                        'post_content' => $generated_content['content'],
                        'post_name' => sanitize_title_with_dashes($generated_content['title']),
                        'post_status' => $post_status_option,
                        'post_category' => array($category),
                    ));

                    if (is_wp_error($new_post_id)) {
                        throw new Exception($new_post_id->get_error_message());
                    }
                } catch (Exception $e) {
                    turbo_blogger_log_error(esc_html($e->getMessage()));
                    echo "There has been a system error: " . esc_html($e->getMessage());
                }
            }

            fclose($handle);
        } else {
            // No file uploaded, handle the form submission as before
            try {
                // Generate the blog content
                $generated_content = turbo_blogger_generate_content($title, $keywords, $additional_requests, $target_audience, $call_to_action, $writing_styles);

                // Get the selected post status from the settings
                $post_status_option = get_option('turbo-blogger-post-status-option');

                // Create the new post
                $new_post_id = wp_insert_post(array(
                    'post_title' => $title,
                    'post_content' => $generated_content['content'],
                    'post_name' => sanitize_title_with_dashes($generated_content['title']),
                    'post_status' => $post_status_option,
                    'post_category' => array($category),
                ));

                if (is_wp_error($new_post_id)) {
                    throw new Exception($new_post_id->get_error_message());
                }

                // Redirect the user based on the selected option
                $redirect_option = get_option('turbo-blogger-redirect-option');
                switch ($redirect_option) {
                    case 'no_redirect':
                        // Redirect to the Turbo Blogger "Create a Blog" page
                        wp_redirect(admin_url('admin.php?page=turbo-blogger'));
                        exit;
                    case 'redirect_new_blog':
                        // Redirect to the newly created blog post
                        wp_redirect(get_permalink($new_post_id));
                        exit;
                    case 'redirect_posts':
                        // Redirect to the posts listing page
                        wp_redirect(admin_url('edit.php'));
                        exit;
                    default:
                        // Redirect to the Turbo Blogger "Create a Blog" page
                        wp_redirect(admin_url('admin.php?page=turbo-blogger'));
                        exit;
                }
            } catch (Exception $e) {
                turbo_blogger_log_error(esc_html($e->getMessage()));
                echo "There has been a system error: " . esc_html($e->getMessage());
            }
        }
    } else {
        echo "Sorry, your nonce did not verify.";
        exit;
    }
}

function turbo_blogger_generate_content($title, $keywords, $additional_requests, $target_audience, $call_to_action, $writing_styles) {
    $api_key = get_option('turbo-blogger-api-key'); // retrieve API key from the settings

    $url = 'https://api.openai.com/v1/chat/completions';

    $openai_engine = get_option('turbo-blogger-openai-engine', 'gpt-3.5-turbo-16k'); // Retrieve the OpenAI engine option from the database

    $model = '';

    if ($openai_engine === 'gpt-4') {
        $model = 'gpt-4'; // Set the model as "gpt-4" for GPT-4
    } elseif ($openai_engine === 'gpt-4-32k') {
        $model = 'gpt-4-32k'; // Set the model as "gpt-4-32k" for GPT-4 32k
    } else {
        $model = 'gpt-3.5-turbo-16k'; // Set the model as "gpt-3.5-turbo-16k" for the default option
    }

    $body = array(
        'model' => $model,
        'temperature' => .65,
        'top_p' => 1.0,
        'frequency_penalty' => 0.0,
        'presence_penalty' => 0.0,
        'messages' => array(
            array(
                'role' => 'system',
                'content' => 'You are a TurboBlogger. A talented, experienced SEO-focused blog writer, skilled in crafting engaging and informative content.'
            ),
            array(
                'role' => 'user',
                'content' => 'I want you to write a WordPress blog for me. The post you write later will be directly posted to a WordPress blog, so its important that you write it perfectly formatted.'
            ),
            array(
                'role' => 'assistant',
                'content' => 'Okay, I can write that blog post for you. Please give me the requirements you are looking for in the blog post I write.'
            ),
            array(
                'role' => 'user',
                'content' => 'Its important that you correctly follow these requirements:

                1. Write the blog post in a "' . $writing_styles . '" writing style.
                2. Write the blog post with an introduction, the body content which is where you will focus on answering or providing the necessary information, and lastly a conclusion or summary.
                3. Write as much as you can about the topic without being repetitive.
                4. Use proper blog SEO formatting.'
            ),
            array(
                'role' => 'assistant',
                'content' => 'No problem! I will be sure to write the blog post in a "' . $writing_styles . '" writing style. I will be sure to write the blog post with an introduction, the body content which is where you will focus on answering or providing the necessary information, and lastly a conclusion or summary. I will be sure to write as much as I can about the topic without being repetitive. Lastly, I will write the blog post using proper blog post SEO formatting.'
            ),
            array(
                'role' => 'user',
                'content' => 'Perfect! Now I will give you examples of the SEO formatting we are looking for in the blog post that you write.'
            ),
            array(
                'role' => 'assistant',
                'content' => 'Okay, I understand. Please give me examples of the proper SEO formatting which you are looking for.'
            ),
            array(
                'role' => 'user',
                'content' => 'It is important that you write headings with proper H tags. For example, you would do something like <h2>Heading Example</h2> if its a main topic covered in the article, and its important that you cascade the H tags for subtopics, so you would do <h3>Subtopic Heading Example</h3> for example. In addition, its important that you also put P tags around paragraph content. For example, you would do something like <p>This is an example paragraph for formatting.</p>. Lastly, if the blog post requires you to write a list its important that you use <ul> OR <ol> tags around any lists, remember this is ONLY if you do choose to add a list.'
            ),
            array(
                'role' => 'assistant',
                'content' => 'That makes sense! It sounds like you want a simple HTML formatted blog post using <h2> tags for main topics and <h3> tags for subtopics, <p> tags for all paragraphs, and <ul> OR <ol> tags only if I include list in the blog post content. I will be sure to follow that carefully. Any other requirements?'
            ),
            array(
                'role' => 'user',
                'content' => 'There is 1 more requirement thats very important. Its important that you do not include the titles "Introduction" or "Body Content" in the blog post you write, those titles I provided to you are simply for structure and will NOT be included in the content. However, for the Conclusion/Summary paragraph, it is alright if you use the "Conclusion" OR "Summary" as a <h2> heading if necessary, otherwise, you can use a similar sentence to convey to the readers that its the final paragraph.'
            ),
            array(
                'role' => 'assistant',
                'content' => 'No problem! I understand that the reference of "Introduction" and  "Body Content" is for structuring purposes and these will NOT be included as headings. I am ready to write the blog now, please provide the details of the blog post you want me to write.'
            ),
            array(
                'role' => 'user',
                'content' => 'Perfect! The target audience will be "' . $target_audience . '". The title of the blog you will write the content for is "' . $title . '", and make sure you do not include the title in the blog post you write. The primary SEO keywords to focus on are "' . $keywords . '". ' . (!empty($call_to_action) ? 'The call to action that will be at the end of the blog will be "' . $call_to_action . '". ' : '') . (!empty($additional_requests) ? 'Also, the additional requests I have are as follows: ' . $additional_requests . '. ' : '') . 'Make sure you are using proper HTML formatting, as mentioned earlier in our conversation and start with the first introduction paragraph.'
            ),
            array(
                'role' => 'assistant',
                'content' => 'Okay, I understand the target audience is "' . $target_audience . '", the title of the blog I will write the content for is "' . $title . '" and I won\'t include the title in the blog I write, the primary SEO keywords to focus on are "' . $keywords . '". ' . (!empty($call_to_action) ? 'The call to action that will be at the end of the blog will be "' . $call_to_action . '". ' : '') . (!empty($additional_requests) ? 'The additional requests are "' . $additional_requests . '". ' : '') . 'Lastly, I will be sure to use the proper HTML tags such as h tags, p tags, and ul/ol tags as mentioned previously in our conversation.'
            ),
            array(
                'role' => 'user',
                'content' => 'Write the formatted blog post using the blog post details and proper formatting, starting with the first paragraph, no title. Make sure that your reply is the formatted post itself and NOTHING else.'
            )
        )
    );

    // Log the request
    error_log('OpenAI Request: ' . print_r($body, true));

    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-type' => 'application/json'
    );

    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
        'headers' => $headers,
        'body' => json_encode($body)
    );

    $response = wp_remote_post($url, $args);

    // Log the response
    error_log('OpenAI Response: ' . print_r($response, true));

    // Check for errors and throw an exception if found
    if ( is_wp_error( $response ) ) {
        throw new Exception('Http request error: ' . $response->get_error_message());
    }

    $response_data = json_decode( wp_remote_retrieve_body( $response ), true );
    $generated_content = $response_data['choices'][0]['message']['content'];

    // Remove values before the first <p> tag
    $generated_content = preg_replace('/^.*?(<p>)/s', '<p>', $generated_content);

    // Remove values after the last </p> tag
    $generated_content = preg_replace('/(?!<p>)<\/p>[^<]*$/s', '</p>', $generated_content);


    return array(
        'title' => $title,
        'content' => $generated_content,
    );
}

// Define the turbo_blogger_log_error() function
function turbo_blogger_log_error($message) {
    error_log('Turbo Blogger Error: ' . $message);
}

// AJAX Bulk Upload Request for Background Process
add_action('wp_ajax_turbo_blogger_bulk_upload', 'turbo_blogger_bulk_upload_callback');
add_action('wp_ajax_nopriv_turbo_blogger_bulk_upload', 'turbo_blogger_bulk_upload_callback');

function turbo_blogger_bulk_upload_callback() {
    include_once(plugin_dir_path(__FILE__) . 'background_process.php');
}

// Data Collection for TurboBlogger
register_activation_hook(__FILE__, 'turbo_blogger_track_installation');
register_deactivation_hook(__FILE__, 'turbo_blogger_track_deactivation');

function turbo_blogger_track_installation() {
    $domain = sanitize_text_field($_SERVER['HTTP_HOST']);
    $version = get_plugin_data(__FILE__)['Version'];
    $activation_date = current_time('mysql');

    // Prepare data
    $data = array(
        'domain' => $domain,
        'version' => $version,
        'activation_date' => $activation_date
    );

    // Send data to your API endpoint
    $api_url =  'https://turboblogger.io/tb-api.php';
    $response = wp_safe_remote_post($api_url, array(
        'body' => $data
    ));

    // Parse the response to check if the entry was updated
    if (!is_wp_error($response)) {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code == 200) {
            // Entry was updated successfully
            update_option('turbo_blogger_last_updated', current_time('mysql'));
        }
    }
}

function turbo_blogger_track_deactivation() {
    $domain = sanitize_text_field($_SERVER['HTTP_HOST']);

    // Prepare data
    $data = array(
        'domain' => $domain,
        'deactivation' => true // Adding a flag to indicate deactivation
    );

    // Send data to your API endpoint
    $api_url =  'https://turboblogger.io/tb-api.php';
    $response = wp_safe_remote_post($api_url, array(
        'body' => $data
    ));
}

function turbo_blogger_save_form_and_csv_data_to_db($formData) {
    // Send the data to the API endpoint
    $response = wp_remote_post('https://turboblogger.io/tb-api-submissions.php', [
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($formData)
    ]);

    // Handle the API response
    if (is_wp_error($response)) {
        error_log('API Error: ' . $response->get_error_message());
    } else {
        $api_response = json_decode(wp_remote_retrieve_body($response), true);
        if ($api_response['status'] === 'error') {
            error_log('API Error: ' . $api_response['message']);
        } else {
            error_log('Data successfully sent to API.');
        }
    }
}
?>