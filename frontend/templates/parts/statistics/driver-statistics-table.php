<?php
/**
 * Driver statistics table template part
 */
?>

<div class="statistics-table">
    <div class="table-loading">
        <p><?php _e( 'Loading data table...', 'scrap-driver' ); ?></p>
    </div>
    
    <div class="table-container" style="display: none;">
        <table id="statistics-data-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th><?php _e( 'Driver', 'scrap-driver' ); ?></th>
                    <th><?php _e( 'Date', 'scrap-driver' ); ?></th>
                    <th><?php _e( 'Collections', 'scrap-driver' ); ?></th>
                    <th><?php _e( 'Miles', 'scrap-driver' ); ?></th>
                    <th><?php _e( 'Hours', 'scrap-driver' ); ?></th>
                    <th><?php _e( 'Collections/Hour', 'scrap-driver' ); ?></th>
                    <th><?php _e( 'Time/Mile (Hrs)', 'scrap-driver' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Table data will be populated via JavaScript -->
            </tbody>
        </table>
    </div>
    
    <div class="table-error" style="display: none;">
        <p><?php _e( 'Error loading table data. Please try again.', 'scrap-driver' ); ?></p>
    </div>
</div> 