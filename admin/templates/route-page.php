<?php
$drivers = $this->get_drivers();
$collections = $this->get_collections();
?>

<div class="wrap">
    <h1>Route Planning</h1>

    <div class="sda-route-container">
        <div class="sda-route-header">
            <select id="driver-select">
                <option value="">All Drivers</option>
                <?php foreach ($drivers as $driver): ?>
                    <option value="<?php echo esc_attr($driver['id']); ?>">
                        <?php echo esc_html($driver['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="calendar"></div>
    </div>
</div> 