<?php
/**
 * Template Name: Driver Schedule Template
 */

namespace ScrapDriver;

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

// Get current user
$current_user_id = get_current_user_id();
$schedule_class = new Schedule();

// Check if user is a driver
if (!in_array('driver', wp_get_current_user()->roles)) {
    wp_redirect(home_url());
    exit;
}

// Get driver's schedule
$schedule = $schedule_class->get_driver_schedule($current_user_id);
if ($schedule) {
    // Set up the post data for the schedule
    global $post;
    $post = get_post($schedule->ID);
    setup_postdata($post);
    
    // Include the schedule template
    include(plugin_dir_path(__FILE__) . 'single-driver_schedule.php');
    wp_reset_postdata();
} else {
    wp_redirect(home_url());
    exit;
} 