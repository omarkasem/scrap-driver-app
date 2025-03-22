<?php
/**
 * Driver statistics charts template part
 */
?>

<div class="statistics-charts">
    <div class="charts-loading">
        <p><?php _e( 'Loading charts...', 'scrap-driver' ); ?></p>
    </div>
    
    <div class="charts-container" style="display: none;">
        <div class="chart-row">
            <div class="chart-container">
                <h3><?php _e( 'Collections per Hour', 'scrap-driver' ); ?></h3>
                <canvas id="collections-per-hour-chart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3><?php _e( 'Miles Traveled', 'scrap-driver' ); ?></h3>
                <canvas id="miles-traveled-chart"></canvas>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-container">
                <h3><?php _e( 'Time per Mile', 'scrap-driver' ); ?></h3>
                <canvas id="time-per-mile-chart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3><?php _e( 'Total Collections', 'scrap-driver' ); ?></h3>
                <canvas id="total-collections-chart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="charts-error" style="display: none;">
        <p><?php _e( 'Error loading charts. Please try again.', 'scrap-driver' ); ?></p>
    </div>
</div> 