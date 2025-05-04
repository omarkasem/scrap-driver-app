class RoutePlanning {
    constructor() {
        this.$calendar = jQuery('#calendar');
        this.calendar = null;
        this.today = new Date();
        this.today.setHours(0, 0, 0, 0);
        this.$driverField = jQuery('select[name="acf[field_67938b4d7f418]"]');
        this.driverId = this.$driverField.val();
        this.$shiftDate = jQuery('input[name="acf[field_67938e50736a3]"]');
        // Only check for calendar existence
        if (!this.$calendar.length) {
            return;
        }

        this.init();
        var that = this;
        jQuery('.acf-tab-button[data-key="field_67938ed0736a8"]').on('click', function() {
            setTimeout(function() {
                // Force the calendar container to have a specific minimum height
                that.$calendar.css('min-height', '600px');
                // Force a resize after initialization
                if (that.calendar) {
                    that.calendar.updateSize();
                }
            }, 100); // Reduced timeout since we're handling sizing explicitly
        });
    }

    init() {
        console.log(this);
        this.initCalendar();
        this.bindEvents();
        // Only load events if we have a driver ID
        if (this.driverId) {
            this.loadEvents();
        }
    }

    initCalendar() {
        const self = this;
        
        // Get the shift date value to use as initial date
        let initialDate = null;
        if (this.$shiftDate.length && this.$shiftDate.val()) {
            initialDate = this.$shiftDate.val();
        }
        
        this.calendar = new FullCalendar.Calendar(this.$calendar[0], {
            initialView: 'timeGridDay',
            initialDate: initialDate, // Set the initial date from shift date
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridDay'
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
            // Prevent dragging to past dates but still allow viewing past dates
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

class Distance {
    constructor() {
        this.$calendar = jQuery('#calendar');
        this.$shiftDate = jQuery('input[name="acf[field_67938e50736a3]"]');
        this.$driverField = jQuery('select[name="acf[field_67938b4d7f418]"]');
        this.$startingPoint = jQuery('input[name="acf[field_67938b627f419]"]');
        this.$endingPoint = jQuery('input[name="acf[field_67938b777f41b]"]');
        
        // Only initialize if calendar exists
        if (!this.$calendar.length) {
            return;
        }
        
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        const self = this;
        
        // Listen to FullCalendar's eventChange events
        if (window.routePlanning && window.routePlanning.calendar) {
            window.routePlanning.calendar.on('eventChange', (info) => {
                setTimeout(() => {
                    self.processRoute();
                }, 500);
            });
        }
        
        // AI Route Optimization button
        jQuery('#ai-reorder-route').on('click', function() {
            self.processRoute(true, jQuery(this));
        });
    }

    // Unified function to handle both manual moves and AI optimization
    processRoute(optimize = false, button = null) {
        const shiftDate = this.$shiftDate.val();
        const driverId = this.$driverField.val();
        const startingPoint = this.$startingPoint.val();
        const endingPoint = this.$endingPoint.val();

        if (!shiftDate || !driverId || !startingPoint || !endingPoint) {
            console.warn('Missing required fields for route processing');
            return;
        }

        // If this is an optimization request, show loading state on button
        if (optimize && button) {
            const originalText = button.html();
            button.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin-right:5px"></span> Optimizing route...');
        }

        // Show loading indicator in the distance total area
        jQuery('.distance-total').html(`<img src="${sdaRoute.loader}" alt="Loading..." />`);

        jQuery.ajax({
            url: sdaRoute.ajaxurl,
            type: 'POST',
            data: {
                action: optimize ? 'optimize_route' : 'calculate_route_distance',
                nonce: sdaRoute.nonce,
                shift_date: shiftDate,
                driver_id: driverId,
                starting_point: startingPoint,
                ending_point: endingPoint,
                post_id: sdaRoute.postId
            },
            success: (response) => {
                if (response.success) {
                    // Update the distance/time display
                    const distanceElement = document.querySelector('.distance-total');
                    if (distanceElement) {
                        let html = `<div>Total Distance: ${response.data.distance} miles</div>`;
                        if (response.data.time_formatted) {
                            html += `<div>Total Time: ${response.data.time_formatted}</div>`;
                        } else if (response.data.time) {
                            const hours = Math.floor(response.data.time / 3600);
                            const minutes = Math.round((response.data.time % 3600) / 60);
                            html += `<div>Total Time: ${hours} hour${hours !== 1 ? 's' : ''} ${minutes} minute${minutes !== 1 ? 's' : ''}</div>`;
                        }
                        distanceElement.innerHTML = html;
                    }

                    // If this was an optimization, show success message and reload
                    if (optimize) {
                        window.location.href = window.location.pathname + ( window.location.search ? window.location.search + '&' : '?' ) + 'optimize=1';
                    }
                } else {
                    console.error('Error processing route:', response.data);
                    if (optimize) {
                        alert('Error optimizing route: ' + (response.data || 'Unknown error'));
                    }
                    const distanceElement = document.querySelector('.distance-total');
                    if (distanceElement) {
                        distanceElement.innerHTML = `Total Distance: 0 miles`;
                    }
                }
            },
            error: (xhr, status, error) => {
                console.error('Ajax error:', {xhr, status, error});
                if (optimize) {
                    alert('Error optimizing route');
                }
            },
            complete: () => {
                // If this is an optimization request, restore button state
                if (optimize && button) {
                    const originalText = button.html().replace(/<span class="spinner.*?<\/span>\s*/, '');
                    button.prop('disabled', false).html(originalText);
                }
            }
        });
    }
}

class DriverSchedule {
    constructor() {
        this.$calendar = jQuery('#schedule-calendar');
        if (!this.$calendar.length) return;
        
        this.postId = sdaRoute.postId;
        this.calendar = null;
        this.selectedDates = [];
        this.isDialogOpen = false;
        
        this.init();
    }
    
    init() {
        this.initCalendar();
        this.loadScheduleDates();
    }
    
    initCalendar() {
        this.calendar = new FullCalendar.Calendar(this.$calendar[0], {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth'
            },
            selectable: true,
            select: (info) => this.handleDateSelect(info),
            unselect: (info) => this.handleUnselect(info),
            eventDidMount: (info) => {
                info.el.title = info.event.title;
            }
        });
        
        this.calendar.render();
    }
    
    handleDateSelect(info) {
        // Store dates in a temporary variable
        const datesToSave = [];
        
        let current = new Date(info.start);
        const end = new Date(info.end);
        
        // Adjust for timezone to prevent date shifting
        current.setMinutes(current.getMinutes() - current.getTimezoneOffset());
        end.setMinutes(end.getMinutes() - end.getTimezoneOffset());
        
        // Subtract one day from end date since FullCalendar's end date is exclusive
        end.setDate(end.getDate() - 1);
        
        // Collect all dates in the range
        while (current <= end) {
            datesToSave.push(this.formatDate(new Date(current)));
            current.setDate(current.getDate() + 1);
        }
        
        // Only update selectedDates after collecting all dates
        this.selectedDates = datesToSave;
        
        console.log('Selected dates before dialog:', this.selectedDates);
        
        // Set dialog open flag
        this.isDialogOpen = true;
        
        // Show SweetAlert2 dialog
        Swal.fire({
            title: 'Set Status for Selected Dates',
            html: `
                <select id="date-status" class="swal2-select">
                    <option value="full_day">Full Day</option>
                    <option value="half_day">Half Day</option>
                    <option value="not_working">Not Working</option>
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Save',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'button button-primary',
                cancelButton: 'button'
            },
            didOpen: () => {
                console.log('Dialog opened, dates:', this.selectedDates);
            },
            willClose: () => {
                this.isDialogOpen = false;
            }
        }).then((result) => {
            console.log('Dialog result:', result, 'Selected dates:', this.selectedDates);
            if (result.isConfirmed && this.selectedDates.length > 0) {
                const status = document.getElementById('date-status').value;
                this.saveSelectedDates(status);
            } else {
                this.handleUnselect();
            }
        });
    }
    
    handleUnselect(info) {
        // Only clear dates if dialog is not open
        if (!this.isDialogOpen) {
            console.log('Unselecting dates, dialog closed');
            this.selectedDates = [];
            this.calendar.unselect();
        } else {
            console.log('Preventing unselect while dialog is open');
        }
    }
    
    saveSelectedDates(status) {
        console.log('saveSelectedDates called with dates:', this.selectedDates); // Debug log
        
        if (!this.selectedDates || !this.selectedDates.length) {
            Swal.fire({
                icon: 'warning',
                title: 'No Dates Selected',
                text: 'Please select at least one date.',
                timer: 2000
            });
            return;
        }

        // Create a copy of selected dates to prevent them from being cleared too early
        const datesToSave = [...this.selectedDates];
        
        console.log('Saving dates:', datesToSave, 'with status:', status); // Debug log
        
        // Show loading state
        Swal.fire({
            title: 'Saving...',
            didOpen: () => {
                Swal.showLoading();
            },
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false
        });
        
        jQuery.ajax({
            url: sdaRoute.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_schedule_dates',
                nonce: sdaRoute.schedule_nonce,
                post_id: this.postId,
                dates: datesToSave.join(','),
                status: status
            },
            success: (response) => {
                console.log('Save response:', response); // Debug log
                if (response.success) {
                    this.loadScheduleDates();
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Schedule dates have been updated.',
                        timer: 1500
                    });
                    // Only clear dates after successful save
                    this.selectedDates = [];
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.data || 'Failed to save schedule dates'
                    });
                }
            },
            error: (xhr, status, error) => {
                console.error('Ajax error:', {xhr, status, error});
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save schedule dates'
                });
            }
        });
    }
    
    loadScheduleDates() {
        jQuery.ajax({
            url: sdaRoute.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_schedule_dates',
                post_id: this.postId,
                nonce: sdaRoute.schedule_nonce
            },
            success: (response) => {
                if (response.success) {
                    this.calendar.removeAllEvents();
                    this.renderScheduleDates(response.data);
                }
            }
        });
    }
    
    renderScheduleDates(dates) {
        const events = [];
        const colors = {
            full_day: '#2196F3',
            half_day: '#FFC107',
            not_working: '#F44336'
        };
        
        for (const [date, status] of Object.entries(dates)) {
            events.push({
                title: this.getStatusLabel(status),
                start: date,
                backgroundColor: colors[status],
                allDay: true
            });
        }
        
        this.calendar.addEventSource(events);
    }
    
    getStatusLabel(status) {
        const labels = {
            full_day: 'Full Day',
            half_day: 'Half Day',
            not_working: 'Not Working'
        };
        return labels[status] || status;
    }
    
    formatDate(date) {
        return date.toISOString().split('T')[0];
    }
}

class LiveMap {
    constructor() {
        this.map = null;
        this.defaultLocation = { lat: 51.509865, lng: -0.118092 }; // London as default

        // Initialize only if we're on the live location page
        if ( document.getElementById( 'driver-live-map' ) ) {
            // Map will be initialized by the Google Maps callback
        }
    }

    initMap() {
        // Create basic map instance
        this.map = new google.maps.Map( document.getElementById( 'driver-live-map' ), {
            zoom: 10,
            center: this.defaultLocation
        });
    }
}

// Initialize when DOM is ready
jQuery(document).ready(function() {
    // Store routePlanning instance globally
    window.routePlanning = new RoutePlanning();
    new Distance();
    new DriverSchedule();
});

window.liveMap = new LiveMap();

// Google Maps callback function
function initDriverMap() {
    if (window.liveMap) {
        window.liveMap.initMap();
    }
}