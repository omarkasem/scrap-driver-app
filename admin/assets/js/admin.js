document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('calendar')) {
        return;
    }

    const calendarEl = document.getElementById('calendar');
    const driverSelect = document.getElementById('driver-select');

    // Get today's date at midnight for validation
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Initialize FullCalendar
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek'
        },
        editable: true,
        validRange: {
            start: today // Disable all dates before today
        },
        eventDrop: function(info) {
            const event = info.event;
            // Format date as YYYY-MM-DD ensuring proper timezone
            const newDate = formatDate(event.start);
            
            // Validate if the new date is not in the past
            if (event.start < today) {
                alert('Cannot move collections to past dates');
                info.revert();
                return;
            }

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
                        console.log('Collection updated successfully');
                    } else {
                        info.revert();
                        alert('Error updating collection');
                    }
                },
                error: function() {
                    alert('Error updating collection');
                    info.revert();
                }
            });
        },
        eventDidMount: function(info) {
            info.el.title = info.event.title;
        },
        // Prevent dragging to past dates
        eventConstraint: {
            start: today
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
                console.log('Raw response:', response); // Debug log
                calendar.removeAllEvents();
                if (response.success && response.data) {
                    // Format dates properly before adding to calendar
                    const events = response.data.map(event => {
                        console.log('Processing event:', event); // Debug log
                        return {
                            ...event,
                            start: formatDate(event.start)
                        };
                    });
                    console.log('Formatted events:', events); // Debug log
                    calendar.addEventSource(events);
                } else {
                    console.error('Invalid response:', response); // Debug log
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', {xhr, status, error}); // Debug log
                alert('Error loading collections');
            }
        });
    }

    // Helper function to format dates consistently
    function formatDate(date) {
        if (!date) {
            console.warn('Empty date provided to formatDate'); // Debug log
            return null;
        }
        const d = new Date(date);
        if (isNaN(d.getTime())) {
            console.warn('Invalid date:', date); // Debug log
            return null;
        }
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
});
