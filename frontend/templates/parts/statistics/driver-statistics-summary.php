<?php
/**
 * Driver statistics summary template part
 */
?>

<div class="statistics-summary">
    <div class="summary-loading">
        <p><?php _e( 'Loading statistics...', 'scrap-driver' ); ?></p>
    </div>
    
    <div class="summary-cards" style="display: none;">
        <div class="summary-row">
            <!-- Summary cards will be populated dynamically via JavaScript -->
        </div>
    </div>
    
    <div class="summary-error" style="display: none;">
        <p><?php _e( 'Error loading statistics. Please try again.', 'scrap-driver' ); ?></p>
    </div>
</div> 