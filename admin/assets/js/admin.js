document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('calendar')) {
        return;
    }

    const calendarEl = document.getElementById('calendar');
    const driverSelect = document.getElementById('driver-select');

    // Initialize FullCalendar
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek'
        },
        editable: true,
        eventDrop: function(info) {
            const event = info.event;
            const newDate = event.start.toISOString().split('T')[0];
            
            // Calculate new route order
            const routeOrder = event.extendedProps.routeOrder || 1;

            // Send AJAX request to update the collection
            jQuery.ajax({
                url: sdaRoute.ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_collection_route',
                    nonce: sdaRoute.nonce,
                    collection_id: event.id,
                    new_date: newDate,
                    driver_id: driverSelect.value || null,
                    route_order: routeOrder
                },
                success: function(response) {
                    if (response.success) {
                        // Optionally show success message
                        console.log('Collection updated successfully');
                    }
                },
                error: function() {
                    alert('Error updating collection');
                    info.revert();
                }
            });
        },
        eventDidMount: function(info) {
            // Add custom styling or tooltips if needed
            info.el.title = info.event.title;
        }
    });

    // Load initial events
    loadEvents();
    calendar.render();

    // Handle driver selection change
    driverSelect.addEventListener('change', function() {
        loadEvents();
    });

    function loadEvents() {
        const driverId = driverSelect.value;
        
        jQuery.ajax({
            url: sdaRoute.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_collections',
                nonce: sdaRoute.nonce,
                driver_id: driverId
            },
            success: function(response) {
                calendar.removeAllEvents();
                if (response.success && response.data) {
                    calendar.addEventSource(response.data);
                }
            },
            error: function() {
                alert('Error loading collections');
            }
        });
    }
});
