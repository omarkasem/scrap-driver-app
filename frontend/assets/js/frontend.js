/**
 * Driver Statistics JavaScript
 * 
 * Handles UI interactions, AJAX requests, and data visualization
 */

jQuery(document).ready(function($) {
    // Initialize the Driver Statistics module
    DriverStatistics.initialize($);

    if($('#driver-selector').length) {  
        $('#driver-selector').select2({
            placeholder: 'Select a driver',
            allowClear: true
        });
    }
    
    // Copy to clipboard functionality
    $('.copy-btn').on('click', function() {
        const text = $(this).parent('.copyable').data('copy');
        
        // Create temporary textarea element
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed'; // Avoid scrolling to bottom
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            // Try modern clipboard API first
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    $(this).addClass('copied');
                    setTimeout(() => $(this).removeClass('copied'), 2000);
                });
            } else {
                // Fallback to execCommand
                document.execCommand('copy');
                $(this).addClass('copied');
                setTimeout(() => $(this).removeClass('copied'), 2000);
            }
        } catch (err) {
            console.error('Failed to copy text: ', err);
        } finally {
            // Clean up
            document.body.removeChild(textarea);
        }
    });

    if ($('#collections-table').length) {
        $('#collections-table').DataTable({
            order: [[0, 'asc']], // Sort by Order column by default
            pageLength: 25,
            responsive: true,
            language: {
                search: sdaDataTableTranslations.search,
                lengthMenu: sdaDataTableTranslations.lengthMenu,
                info: sdaDataTableTranslations.info,
                infoEmpty: sdaDataTableTranslations.infoEmpty,
                infoFiltered: sdaDataTableTranslations.infoFiltered,
                emptyTable: sdaDataTableTranslations.emptyTable,
                paginate: {
                    first: sdaDataTableTranslations.first,
                    last: sdaDataTableTranslations.last,
                    next: sdaDataTableTranslations.next,
                    previous: sdaDataTableTranslations.previous
                }
            }
        });
    }

    // Initialize DataTables for driver dashboard
    if ($('#today-collections-table').length) {
        $('#today-collections-table').DataTable({
            language: sdaDataTableTranslations,
            responsive: true,
            order: [[0, 'asc']]
        });
    }
    
    if ($('#shifts-table').length) {
        $('#shifts-table').DataTable({
            language: sdaDataTableTranslations,
            responsive: true,
            order: [[0, 'desc']]
        });
    }
    
    if ($('#all-collections-table').length) {
        $('#all-collections-table').DataTable({
            language: sdaDataTableTranslations,
            responsive: true,
            order: [[3, 'desc'], [0, 'asc']]
        });
    }
    
    // Accordion functionality
    $('.sda-accordion-header').on('click', function() {
        $(this).parent().toggleClass('open');
        
        // If opening a section containing charts, redraw them
        if ($(this).parent().hasClass('open') && $(this).parent().find('.chart-container').length) {
            DriverStatistics.redrawCharts($);
        }
    });

    // Status form submission
    $('#sda-status-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        
        submitButton.prop('disabled', true);
        
        $.ajax({
            url: sdaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'sda_update_collection_status',
                ...Object.fromEntries(new FormData(this))
            },
            success: function(response) {
                if (response.success) {
                    form.find('textarea[name="notes"]').val('');
                    $('.sda-notes-display').html(response.data.notes);
                    alert(response.data.message);
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            },
            complete: function() {
                submitButton.prop('disabled', false);
            }
        });
    });

    // Photo upload
    $('#sda-photo-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        
        submitButton.prop('disabled', true);
        
        const formData = new FormData(this);
        formData.append('action', 'sda_upload_collection_photo');
        
        $.ajax({
            url: sdaAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const photoHtml = `
                        <div class="sda-photo-wrapper">
                            <img src="${response.data.image_url}" class="attachment-medium size-medium" alt="">
                            <button class="sda-remove-photo" data-id="${response.data.attachment_id}">×</button>
                        </div>
                    `;
                    $('#sda-photo-gallery').append(photoHtml);
                    form.find('input[type="file"]').val('');
                    alert(response.data.message);
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            },
            complete: function() {
                submitButton.prop('disabled', false);
            }
        });
    });

    // Photo removal
    $(document).on('click', '.sda-remove-photo', function(e) {
        e.preventDefault();
        const button = $(this);
        const photoWrapper = button.closest('.sda-photo-wrapper');
        const collectionId = $('#sda-photo-form input[name="collection_id"]').val();
        const nonce = $('#sda-photo-form input[name="nonce"]').val();
        
        if (confirm('Are you sure you want to remove this photo?')) {
            $.ajax({
                url: sdaAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sda_remove_collection_photo',
                    collection_id: collectionId,
                    attachment_id: button.data('id'),
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        photoWrapper.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
    });
});

// Main DriverStatistics module
var DriverStatistics = {
    // Charts instances
    charts: {},
    
    // DataTable instance
    dataTable: null,
    
    // Initialize the module
    initialize: function( $ ) {
        // Set up event listeners
        this.setupEventListeners( $ );
        
        // If admin, preselect the first driver
        if (sdaAjax.isAdmin === 'true') {
            var firstDriver = $( '#driver-selector option:first' );
            if (firstDriver.length) {
                firstDriver.prop( 'selected', true );
            }
        }
        
        // Initialize date pickers
        // this.initializeDatePickers( $ );
        
        // Load statistics with default values
        this.loadStatisticsData( $ );
    },
    
    // Initialize date pickers
    initializeDatePickers: function( $ ) {
        if ($.datepicker) {
            $( '#start-date, #end-date' ).datepicker({
                dateFormat: 'yy-mm-dd',
                maxDate: 0, // Can't select future dates
                changeMonth: true,
                changeYear: true
            });
        }
    },
    
    // Set up event listeners
    setupEventListeners: function( $ ) {
        var self = this;
        
        // Filter form submission
        $( '#statistics-filter-form' ).on( 'submit', function( e ) {
            e.preventDefault();
            self.loadStatisticsData( $ );
        });
        
        // Reset filters button
        $( '#reset-filters' ).on( 'click', function() {
            self.resetFilters( $ );
        });
        
        // Export buttons
        $( '#export-csv' ).on( 'click', function() {
            self.handleExport( 'csv', $ );
        });
        
        $( '#export-pdf' ).on( 'click', function() {
            self.handleExport( 'pdf', $ );
        });
    },
    
    // Reset filters to default values
    resetFilters: function( $ ) {
        // Reset date filters to last 30 days
        var today = new Date();
        
        $( '#end-date' ).val(this.formatDate(today));
        $( '#start-date' ).val(this.formatDate(today));
        
        // Reset interval to day
        $( '#interval' ).val('day');
        
        // If admin, reset to first driver
        if (sdaAjax.isAdmin === 'true') {
            $( '#driver-selector option' ).prop( 'selected', false );
            $( '#driver-selector option:first' ).prop( 'selected', true );
        }
        
        // Reload data
        this.loadStatisticsData( $ );
    },
    
    // Format date as YYYY-MM-DD
    formatDate: function(date) {
        var year = date.getFullYear();
        var month = (date.getMonth() + 1).toString().padStart(2, '0');
        var day = date.getDate().toString().padStart(2, '0');
        return year + '-' + month + '-' + day;
    },
    
    // Load statistics data via AJAX
    loadStatisticsData: function( $ ) {
        var self = this;
        
        // Show loading indicators
        $('.summary-loading, .charts-loading, .table-loading').show();
        $('.summary-cards, .charts-container, .table-container, .summary-error, .charts-error, .table-error').hide();
        
        // Get form data
        var driverIds = [];
        
        if (sdaAjax.isAdmin === 'true') {
            driverIds = $( '#driver-selector' ).val();
        } else {
            driverIds = [sdaAjax.currentUserId];
        }
        
        var startDate = $( '#start-date' ).val();
        var endDate = $( '#end-date' ).val();
        var interval = $( '#interval' ).val();
        
        // Make AJAX request
        jQuery.ajax({
            url: sdaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_driver_stats',
                nonce: sdaAjax.nonce,
                driver_ids: driverIds,
                start_date: startDate,
                end_date: endDate,
                interval: interval
            },
            success: function(response) {
                if (response.success) {
                    // Update UI with statistics data
                    self.updateSummary( response.data.stats, $ );
                    self.initializeCharts( response.data.stats, response.data.comparative, $ );
                    self.initializeTable( response.data.stats, $ );
                } else {
                    // Show error messages
                    $( '.summary-error, .charts-error, .table-error' ).show();
                }
            },
            error: function() {
                // Show error messages
                $( '.summary-error, .charts-error, .table-error' ).show();
            }
        });
    },
    
    // Update summary metrics
    updateSummary: function( stats, $ ) {
        var $summaryRow = $( '.summary-row' );
        $summaryRow.empty();
        
        // For each driver, add summary cards
        jQuery.each( stats, function( driverId, driverStats ) {
            var summary = driverStats.summary;

            // Get driver name
            var driverName = jQuery( '#driver-selector option[value="' + driverId + '"]' ).text();
            if (!driverName) {
                driverName = 'Driver';
            }
            
            // Add driver name header
            $summaryRow.append( '<h3 class="driver-name">' + driverName + '</h3>' );
            
            // Container for this driver's cards
            var $driverCards = jQuery( '<div class="driver-summary-cards"></div>' );
            
            // Total Collections card
            $driverCards.append(DriverStatistics.createStatCard({
                title: 'Total Collections',
                value: summary.total_collections,
                icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11V21H20V11"></path><path d="M4 11L1 8 12 2 23 8 20 11"></path></svg>'
            }));
            
            // Total Miles card
            $driverCards.append(DriverStatistics.createStatCard({
                title: 'Total Miles',
                value: summary.total_miles.toFixed(2),
                icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 2L12 22"></path><path d="M2 12L22 12"></path></svg>'
            }));
            
            // Collections per Hour card
            $driverCards.append(DriverStatistics.createStatCard({
                title: 'Collections per Hour',
                value: summary.collections_per_hour.toFixed(2),
                icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'
            }));
            
            // Time per Mile card
            $driverCards.append(DriverStatistics.createStatCard({
                title: 'Time per Mile (hrs)',
                value: summary.time_per_mile.toFixed(2),
                icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>'
            }));
            
            $summaryRow.append($driverCards);
        });
        
        // Show summary cards
        $('.summary-loading').hide();
        $('.summary-cards').show();
    },
    
    // Create a stat card
    createStatCard: function(data) {
        return '<div class="stat-card">' +
               '<div class="stat-card-header">' +
               '<h3>' + data.title + '</h3>' +
               (data.icon ? '<div class="stat-card-icon">' + data.icon + '</div>' : '') +
               '</div>' +
               '<div class="stat-card-value">' + data.value + '</div>' +
               (data.change ? '<div class="stat-card-change ' + (parseFloat(data.change) >= 0 ? 'positive' : 'negative') + '">' + data.change + '%</div>' : '') +
               '</div>';
    },
    
    // Initialize charts
    initializeCharts: function(stats, comparative, $ ) {
        console.log(comparative);
        var self = this;
        
        // Destroy any existing charts
        self.destroyCharts();
        
        // If we have comparative data (multiple drivers), use that for charts
        if (comparative) {
            self.initializeComparativeCharts(comparative, $ );
        } else {
            // Otherwise create interval-based charts for a single driver
            var driverId = Object.keys(stats)[0];
            if (driverId) {
                self.initializeSingleDriverCharts(stats[driverId], $ );
            }
        }
        
        // Show charts container
        $('.charts-loading').hide();
        $('.charts-container').show();
    },
    
    // Create charts for comparing multiple drivers
    initializeComparativeCharts: function(comparative, $ ) {
        var self = this;
        
        // Collections per Hour chart
        self.charts.collectionsPerHour = new Chart(
            document.getElementById('collections-per-hour-chart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: Object.keys(comparative.collections_per_hour),
                    datasets: [{
                        label: 'Collections per Hour',
                        data: Object.values(comparative.collections_per_hour),
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: self.getChartOptions('Collections per Hour', $ )
            }
        );
        
        // Miles Traveled chart
        self.charts.milesTraveled = new Chart(
            document.getElementById('miles-traveled-chart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: Object.keys(comparative.total_miles),
                    datasets: [{
                        label: 'Total Miles',
                        data: Object.values(comparative.total_miles),
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: self.getChartOptions('Total Miles', $ )
            }
        );
        
        // Time per Mile chart
        self.charts.timePerMile = new Chart(
            document.getElementById('time-per-mile-chart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: Object.keys(comparative.time_per_mile),
                    datasets: [{
                        label: 'Time per Mile (hrs)',
                        data: Object.values(comparative.time_per_mile),
                        backgroundColor: 'rgba(255, 159, 64, 0.6)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1
                    }]
                },
                options: self.getChartOptions('Time per Mile (hrs)', $ )
            }
        );
        
        // Total Collections chart
        self.charts.totalCollections = new Chart(
            document.getElementById('total-collections-chart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: Object.keys(comparative.total_collections),
                    datasets: [{
                        label: 'Total Collections',
                        data: Object.values(comparative.total_collections),
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: self.getChartOptions('Total Collections', $ )
            }
        );
    },
    
    // Create charts for a single driver showing interval data
    initializeSingleDriverCharts: function(driverData, $ ) {
        var self = this;
        var intervals = driverData.intervals;
        var labels = Object.keys(intervals);
        
        // Prepare datasets
        var collectionsData = [];
        var milesData = [];
        var collectionsPerHourData = [];
        var timePerMileData = [];
        
        // Process interval data
        jQuery.each(intervals, function(date, data) {
            collectionsData.push(data.collections);
            milesData.push(data.miles);
            
            // Calculate derived metrics
            var collectionsPerHour = data.hours > 0 ? data.collections / data.hours : 0;
            var timePerMile = data.miles > 0 ? data.hours / data.miles : 0;
            
            collectionsPerHourData.push(collectionsPerHour);
            timePerMileData.push(timePerMile);
        });
        
        // Collections per Hour chart
        self.charts.collectionsPerHour = new Chart(
            document.getElementById('collections-per-hour-chart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Collections per Hour',
                        data: collectionsPerHourData,
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: self.getChartOptions('Collections per Hour', $ )
            }
        );
        
        // Miles Traveled chart
        self.charts.milesTraveled = new Chart(
            document.getElementById('miles-traveled-chart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Miles',
                        data: milesData,
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: self.getChartOptions('Miles Traveled', $ )
            }
        );
        
        // Time per Mile chart
        self.charts.timePerMile = new Chart(
            document.getElementById('time-per-mile-chart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Time per Mile (hrs)',
                        data: timePerMileData,
                        backgroundColor: 'rgba(255, 159, 64, 0.1)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: self.getChartOptions('Time per Mile (hrs)', $ )
            }
        );
        
        // Total Collections chart
        self.charts.totalCollections = new Chart(
            document.getElementById('total-collections-chart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Collections',
                        data: collectionsData,
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: self.getChartOptions('Collections', $ )
            }
        );
    },
    
    // Get standard chart options
    getChartOptions: function(title, $ ) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 10,
                    titleFont: {
                        size: 14
                    },
                    bodyFont: {
                        size: 13
                    }
                },
                title: {
                    display: false,
                    text: title
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 1
                    }
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        };
    },
    
    // Destroy existing charts to prevent memory leaks
    destroyCharts: function() {
        if (this.charts.collectionsPerHour) {
            this.charts.collectionsPerHour.destroy();
        }
        if (this.charts.milesTraveled) {
            this.charts.milesTraveled.destroy();
        }
        if (this.charts.timePerMile) {
            this.charts.timePerMile.destroy();
        }
        if (this.charts.totalCollections) {
            this.charts.totalCollections.destroy();
        }
    },
    
    // Redraw charts (e.g., when accordion opens)
    redrawCharts: function( $ ) {
        if (this.charts.collectionsPerHour) {
            this.charts.collectionsPerHour.render();
        }
        if (this.charts.milesTraveled) {
            this.charts.milesTraveled.render();
        }
        if (this.charts.timePerMile) {
            this.charts.timePerMile.render();
        }
        if (this.charts.totalCollections) {
            this.charts.totalCollections.render();
        }
    },
    
    // Initialize DataTables
    initializeTable: function(stats, $ ) {
        var self = this;
        
        // Destroy existing DataTable if it exists
        if (self.dataTable) {
            self.dataTable.destroy();
        }
        
        // Prepare table data
        var tableData = [];
        
        jQuery.each(stats, function(driverId, driverStats) {
            var driverName = jQuery('#driver-selector option[value="' + driverId + '"]').text();
            if (!driverName) {
                driverName = 'Driver';
            }
            
            // Add row for each interval
            jQuery.each(driverStats.intervals, function(date, data) {
                var collectionsPerHour = data.hours > 0 ? data.collections / data.hours : 0;
                var timePerMile = data.miles > 0 ? data.hours / data.miles : 0;
                
                tableData.push([
                    driverName,
                    date,
                    data.collections,
                    data.miles.toFixed(2),
                    data.hours.toFixed(2),
                    collectionsPerHour.toFixed(2),
                    timePerMile.toFixed(2)
                ]);
            });
        });
        
        // Initialize DataTable
        self.dataTable = jQuery('#statistics-data-table').DataTable({
            data: tableData,
            order: [[1, 'desc']], // Sort by date descending
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            responsive: true
        });
        
        // Show table container
        $('.table-loading').hide();
        $('.table-container').show();
    },
    
    // Handle export functionality
    handleExport: function(format, $ ) {
        var self = this;
        
        // Get form data
        var driverIds = [];
        
        if (sdaAjax.isAdmin === 'true') {
            driverIds = $( '#driver-selector' ).val();
        } else {
            driverIds = [sdaAjax.currentUserId];
        }
        
        var startDate = $( '#start-date' ).val();
        var endDate = $( '#end-date' ).val();
        
        // Make AJAX request
        jQuery.ajax({
            url: sdaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'export_statistics',
                nonce: sdaAjax.nonce,
                driver_ids: driverIds,
                start_date: startDate,
                end_date: endDate,
                format: format
            },
            success: function(response) {
                if (response.success) {
                    // Download the exported file
                    self.downloadExport(response.data.data, response.data.filename, response.data.content_type);
                } else {
                    alert('Error exporting data. Please try again.');
                }
            },
            error: function() {
                alert('Error exporting data. Please try again.');
            }
        });
    },
    
    // Download the exported file
    downloadExport: function(data, filename, contentType) {
        var blob = new Blob([data], { type: contentType });
        var url = window.URL.createObjectURL(blob);
        
        var a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = filename;
        
        document.body.appendChild(a);
        a.click();
        
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }
};

