class CollectionCalendar {
    constructor() {
        this.$calendar = jQuery('#calendar');
        this.calendar = null;
        this.today = new Date();
        this.today.setHours(0, 0, 0, 0);
        this.$driverField = jQuery('select[name="acf[field_67938b4d7f418]"]');
        this.driverId = this.$driverField.val();
        
        // Only check for calendar existence
        if (!this.$calendar.length) {
            return;
        }
        
        this.init();
    }

    init() {
        this.initCalendar();
        this.bindEvents();
        // Only load events if we have a driver ID
        if (this.driverId) {
            this.loadEvents();
        }
    }

    initCalendar() {
        const self = this;
        this.calendar = new FullCalendar.Calendar(this.$calendar[0], {
            initialView: 'multiMonthYear',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'multiMonthYear,dayGridMonth,timeGridDay'
            },
            editable: true,
            selectMirror: true,
            dayMaxEvents: true,
            slotDuration: '00:30:00',
            allDaySlot: false,
            multiMonthMaxColumns: 1,
            multiMonthMinWidth: 350,
            dragScroll: true,
            scrollTime: '08:00:00',
            eventDuration: '01:00',
            forceEventDuration: true,
            eventResizableFromStart: false,
            eventDurationEditable: false,
            
            eventContent: function(arg) {
                return {
                    html: `<div class="fc-event-title">${arg.event.title}</div>`
                };
            },
            
            views: {
                timeGridDay: {
                    type: 'timeGrid',
                    dayMaxEvents: false,
                    dayMaxEventRows: false
                }
            },
            
            eventDragStart: function(info) {
                self.$calendar.addClass('fc-dragging');
            },
            eventDragStop: function(info) {
                self.$calendar.removeClass('fc-dragging');
            },
            validRange: {
                start: self.today
            },
            eventDrop: function(info) {
                self.handleEventDrop(info);
            },
            eventDidMount: function(info) {
                info.el.title = info.event.title;
                info.el.classList.add('clickable-event');
            },
            eventClick: function(info) {
                self.handleEventClick(info);
            },
            // Prevent dragging to past dates
            eventConstraint: {
                start: self.today
            },
            dayCellContent: function(arg) {
                return {
                    html: `<div class="fc-daygrid-day-number clickable-day">${arg.dayNumberText}</div>`
                };
            },
            dateClick: function(info) {
                // Only handle clicks on day numbers
                if (info.jsEvent.target.classList.contains('fc-daygrid-day-number')) {
                    self.calendar.changeView('timeGridDay', info.date);
                }
            }
        });
        
        this.calendar.render();
    }

    bindEvents() {
        const self = this;
        this.$driverField.on('change', () => this.loadEvents());
    }

    loadEvents() {
        const self = this;
        this.driverId = this.$driverField.val();
        jQuery.ajax({
            url: sdaRoute.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_collections',
                nonce: sdaRoute.nonce,
                driver_id: this.driverId
            },
            success: function(response) {
                console.log('Raw response:', response);
                self.calendar.removeAllEvents();
                if (response.success && response.data) {
                    const events = response.data.map(event => ({
                        id: event.id,
                        title: event.title,
                        start: event.start,
                        end: event.end,
                        driverId: event.driverId,
                        routeOrder: event.routeOrder,
                        url: event.url
                    }));
                    self.calendar.addEventSource(events);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', {xhr, status, error});
                alert('Error loading collections');
            }
        });
    }

    // Helper methods
    formatDateOnly(date) {
        if (!date) {
            console.warn('Empty date provided to formatDateOnly');
            return null;
        }
        const d = new Date(date);
        if (isNaN(d.getTime())) {
            console.warn('Invalid date:', date);
            return null;
        }
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    formatTime(date) {
        if (!date) {
            console.warn('Empty date provided to formatTime');
            return null;
        }
        const d = new Date(date);
        if (isNaN(d.getTime())) {
            console.warn('Invalid date:', date);
            return null;
        }
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const seconds = String(d.getSeconds()).padStart(2, '0');
        return `${hours}:${minutes}:${seconds}`;
    }

    handleEventDrop(info) {
        const event = info.event;
        const newDate = this.formatDateOnly(event.start);
        const startTime = this.formatTime(event.start);
        const endTime = this.formatTime(event.end);
        
        if (event.start < this.today) {
            alert('Cannot move collections to past dates');
            info.revert();
            return;
        }

        const currentDriverId = event.extendedProps.driverId;
        const selectedDriverId = this.$driverField.val();
        const driverIdToUse = selectedDriverId || currentDriverId;
        const routeOrder = event.extendedProps.routeOrder || 1;

        // Debug logs
        console.log('Event drop details:', {
            newDate,
            startTime,
            endTime,
            event: event
        });

        jQuery.ajax({
            url: sdaRoute.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_collection_route',
                nonce: sdaRoute.nonce,
                collection_id: event.id,
                new_date: newDate,
                start_time: startTime,
                end_time: endTime,
                driver_id: driverIdToUse,
                route_order: routeOrder
            },
            success: function(response) {
                console.log('Update response:', response); // Debug log
                if (!response.success) {
                    info.revert();
                    alert('Error updating collection: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Update error:', {xhr, status, error}); // Debug log
                alert('Error updating collection');
                info.revert();
            }
        });
    }

    handleEventClick(info) {
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
    }
}

// Initialize when DOM is ready
jQuery(document).ready(function() {
    new CollectionCalendar();
});