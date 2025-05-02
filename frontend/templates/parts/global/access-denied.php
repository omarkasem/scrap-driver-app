<?php
/**
 * Access denied template part
 */
?>

<div class="access-denied-message">
    <div class="access-denied-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
        </svg>
    </div>
    <h2><?php _e( 'Access Denied', 'scrap-driver' ); ?></h2>
    <p><?php _e( 'You do not have permission to view driver statistics.', 'scrap-driver' ); ?></p>
    <p><?php _e( 'Please contact your administrator if you believe this is an error.', 'scrap-driver' ); ?></p>
</div> 