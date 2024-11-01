<?php
/**
 * Turbo Blogger Bulk Upload Background Process
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Load WordPress
if (file_exists(ABSPATH . '/wp-load.php')) {
    require_once(ABSPATH . '/wp-load.php');
} else {
    require_once(ABSPATH. '/wp-config.php');
}

global $wpdb;

// Process the CSV file
if (isset($_FILES['upload-file'])) {
    if ( empty( $_FILES ) ) {
        return;
    }

    // Check if there was an error in file upload
    if ($_FILES['upload-file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json(array('success' => false, 'data' => 'Error uploading the file. Please try again.'));
        exit;
    }

    $file_extension = pathinfo(sanitize_file_name($_FILES['upload-file']['name']), PATHINFO_EXTENSION);

    // Check if the file extension is 'csv' (case-insensitive)
    if (strtolower($file_extension) !== 'csv') {
        $generated_content = (array('success' => false, 'data' => 'The uploaded file is not a CSV file.'));
        exit;
    }

    $file_path = sanitize_file_name($_FILES['upload-file']['tmp_name']);
    $handle = fopen($file_path, 'r');

    // Check if the file could be opened
    if ($handle) {
        $success_count = 0;
        $error_count = 0;

        // Skip the header row
        $header_row = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            // Extract the data from the CSV row
            $topic = isset($data[0]) ? sanitize_text_field($data[0]) : '';
            $category = isset($data[1]) ? sanitize_text_field($data[1]) : '';
            $keywords = isset($data[2]) ? sanitize_text_field($data[2]) : '';
            $target_audience = isset($data[3]) ? sanitize_text_field($data[3]) : '';
            $call_to_action = isset($data[4]) ? sanitize_text_field($data[4]) : '';
            $additional_requests = isset($data[5]) ? sanitize_textarea_field($data[5]) : '';

            try {
                // Generate the blog content
                $generated_content = turbo_blogger_generate_content($topic, $keywords, $additional_requests, $target_audience, $call_to_action);

                // Get the selected post status from the settings
                $post_status_option = get_option('turbo-blogger-post-status-option');

                // Create the new post
                $new_post_id = wp_insert_post(array(
                    'post_title' => $generated_content['title'],
                    'post_content' => $generated_content['content'],
                    'post_name' => sanitize_title_with_dashes($generated_content['title']),
                    'post_status' => $post_status_option,
                    'post_category' => array($category),
                ));

                if ($new_post_id) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } catch (Exception $e) {
                turbo_blogger_log_error($e->getMessage());
                $error_count++;
            }
        }

        fclose($handle);

        $response = array(
            'success' => true,
            'data' => array(
                'success_count' => $success_count,
                'error_count' => $error_count
            )
        );
        wp_send_json($response);
        exit;
    } else {
        $response = array(
            'success' => false,
            'data' => 'Error opening the uploaded file.'
        );
        wp_send_json($response);
        exit;
    }
}