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
        initialView: 'multiMonthYear',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'multiMonthYear,dayGridMonth,dayGridWeek'
        },
        editable: true,
        selectMirror: true,
        dayMaxEvents: true,
        multiMonthMaxColumns: 1,
        multiMonthMinWidth: 350,
        dragScroll: true,
        scrollTime: 1000,
        dragScrolling: true,
        eventDragStart: function(info) {
            calendarEl.classList.add('fc-dragging');
        },
        eventDragStop: function(info) {
            calendarEl.classList.remove('fc-dragging');
        },
        validRange: {
            start: today
        },
        eventDrop: function(info) {
            const event = info.event;
            const newDate = formatDate(event.start);
            
            if (event.start < today) {
                alert('Cannot move collections to past dates');
                info.revert();
                return;
            }

            // Get the current driver ID from the event if no driver is selected
            const currentDriverId = event.extendedProps.driverId;
            const selectedDriverId = driverSelect.value;
            
            // Use the current driver ID if none is selected
            const driverIdToUse = selectedDriverId || currentDriverId;

            const routeOrder = event.extendedProps.routeOrder || 1;

            jQuery.ajax({
                url: sdaRoute.ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_collection_route',
                    nonce: sdaRoute.nonce,
                    collection_id: event.id,
                    new_date: newDate,
                    driver_id: driverIdToUse,
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
            info.el.classList.add('clickable-event');
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            
            const popoverContent = document.createElement('div');
            popoverContent.innerHTML = `
                <div class="fc-event-popover">
                    <h4>${info.event.title}</h4>
                    <a href="${info.event.url}" target="_blank" class="view-collection-link">View Collection</a>
                </div>
            `;
            
            // Remove any existing popovers
            document.querySelectorAll('.fc-event-popover').forEach(el => el.remove());
            
            // Get the clicked element's position
            const rect = info.el.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            // Position the popover just above the clicked event
            popoverContent.style.position = 'absolute';
            popoverContent.style.top = (rect.top + scrollTop - 10) + 'px'; // Position above the event with 10px offset
            popoverContent.style.left = rect.left + 'px';
            popoverContent.style.zIndex = 1000;
            document.body.appendChild(popoverContent);
            
            // Adjust if popover goes above viewport
            const popoverRect = popoverContent.getBoundingClientRect();
            if (popoverRect.top < 0) {
                // If popover would go above viewport, position it below the event instead
                popoverContent.style.top = (rect.bottom + scrollTop + 10) + 'px';
            }
            
            const closePopover = function(e) {
                if (!popoverContent.contains(e.target) && !info.el.contains(e.target)) {
                    popoverContent.remove();
                    document.removeEventListener('click', closePopover);
                }
            };
            
            setTimeout(() => {
                document.addEventListener('click', closePopover);
            }, 100);
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
