<?php
/**
 * Export options template part
 */
?>

<div class="export-options">
    <h3><?php _e( 'Export Data', 'scrap-driver' ); ?></h3>
    <div class="export-buttons">
        <button type="button" id="export-csv" class="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <?php _e( 'Export CSV', 'scrap-driver' ); ?>
        </button>
        <button type="button" id="export-pdf" class="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M9 15L12 12 15 15"></path><path d="M12 12L12 18"></path></svg>
            <?php _e( 'Export PDF', 'scrap-driver' ); ?>
        </button>
    </div>
</div> 