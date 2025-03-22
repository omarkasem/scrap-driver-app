<?php
/**
 * Template Name: Driver Statistics
 * Template Post Type: page
 *
 * The main template for displaying driver statistics.
 */

get_header();
?>

<div class="sda-dashboard driver-statistics-dashboard">
    <h1><?php _e( 'Driver Statistics', 'scrap-driver' ); ?></h1>
    
    <?php 
    // Access control check
    $controller = new FrontendStatisticsController();
    if ( !$controller->can_view_statistics() ) {
        include( plugin_dir_path( __FILE__ ) . 'parts/access-denied.php' );
    } else {
        // Include filters
        include( plugin_dir_path( __FILE__ ) . 'parts/driver-statistics-filters.php' );
        
        // Include summary section
        echo '<div id="statistics-summary-container">';
        include( plugin_dir_path( __FILE__ ) . 'parts/driver-statistics-summary.php' );
        echo '</div>';
        
        // Include charts section
        echo '<div class="sda-accordion-section">';
        echo '<div class="sda-accordion-header"><h2>Performance Charts</h2><div class="sda-accordion-icon"></div></div>';
        echo '<div class="sda-accordion-content"><div class="accordion-content-inner">';
        include( plugin_dir_path( __FILE__ ) . 'parts/driver-statistics-charts.php' );
        echo '</div></div></div>';
        
        // Include table section
        echo '<div class="sda-accordion-section">';
        echo '<div class="sda-accordion-header"><h2>Detailed Statistics</h2><div class="sda-accordion-icon"></div></div>';
        echo '<div class="sda-accordion-content"><div class="accordion-content-inner">';
        include( plugin_dir_path( __FILE__ ) . 'parts/driver-statistics-table.php' );
        echo '</div></div></div>';
        
        // Include export options
        echo '<div class="export-container">';
        include( plugin_dir_path( __FILE__ ) . 'parts/export-options.php' );
        echo '</div>';
    }
    ?>
</div>

<?php get_footer(); ?> 