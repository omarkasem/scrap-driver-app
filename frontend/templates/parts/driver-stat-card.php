<?php
/**
 * Template for individual statistic card
 * 
 * @param string $title The statistic title
 * @param string $value The statistic value
 * @param string $icon Icon HTML (optional)
 * @param string $change Percentage change (optional)
 */

$title = isset( $args['title'] ) ? $args['title'] : '';
$value = isset( $args['value'] ) ? $args['value'] : '';
$icon = isset( $args['icon'] ) ? $args['icon'] : '';
$change = isset( $args['change'] ) ? $args['change'] : '';
$change_class = '';

if ( !empty( $change ) ) {
    if ( strpos( $change, '+' ) === 0 ) {
        $change_class = 'positive';
    } elseif ( strpos( $change, '-' ) === 0 ) {
        $change_class = 'negative';
    }
}
?>

<div class="stat-card">
    <div class="stat-card-header">
        <h3><?php echo esc_html( $title ); ?></h3>
        <?php if ( !empty( $icon ) ) : ?>
            <div class="stat-card-icon"><?php echo $icon; ?></div>
        <?php endif; ?>
    </div>
    <div class="stat-card-value">
        <?php echo esc_html( $value ); ?>
    </div>
    <?php if ( !empty( $change ) ) : ?>
        <div class="stat-card-change <?php echo esc_attr( $change_class ); ?>">
            <?php echo esc_html( $change ); ?>
        </div>
    <?php endif; ?>
</div> 