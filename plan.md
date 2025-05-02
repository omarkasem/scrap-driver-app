frontend/templates/
├── parts/
│   ├── statistics/          (already exists)
│   ├── driver-schedule/     (to be created)
│   ├── collection/          (to be created)
│   ├── shifts/              (to be created)
│   └── dashboard/           (to be created)

# Template Refactoring Implementation Plan

## 1. Driver Schedule Implementation

### Files to Create in `parts/driver-schedule/`:
- `weekly-schedule.php` - Weekly working schedule section
- `leave-allowance.php` - Annual leave information section
- `holiday-requests.php` - Holiday requests section
- `request-form.php` - Request annual leave form
- `previous-requests.php` - Previous holiday requests
- `calendar-scripts.php` - Calendar scripts for date selection

### Main Files Modifications:
- Modify `single-driver_schedule.php` to include these parts
- Keep `page-driver-schedule.php` as is (it already includes the single template)

## 2. Collection Implementation

### Files to Create in `parts/collection/`:
- `access-check.php` - Permission validation
- `start-collection.php` - Start collection button and logic
- `vehicle-info.php` - Vehicle information section
- `customer-details.php` - Customer details section
- `collection-address.php` - Address information with map link
- `edit-form.php` - Edit collection form (ACF form)
- `complete-collection.php` - Complete collection button
- `collections-table.php` - Table for displaying collections
- `todays-collections-table.php` - Today's collections table

### Main Files Modifications:
- Modify `single-collection.php`, `view-collections.php` and `view-todays-collections.php` to include these parts

## 3. Shifts Implementation

### Files to Create in `parts/shifts/`:
- `shift-info.php` - Shift information section
- `completed-collections.php` - Collections completed during shift
- `adjustment-request.php` - Request shift adjustment form
- `previous-adjustments.php` - Previous adjustment requests
- `shifts-table.php` - Table for displaying shifts
- `shift-control.php` - Start/end shift control buttons

### Main Files Modifications:
- Modify `single-sda-shift.php` and `view-shifts.php` to include these parts

## 4. Dashboard Implementation 

### Files to Create in `parts/dashboard/`:
- `today-shift-section.php` - Today's shift section
- `today-collections-section.php` - Today's collections section
- `all-shifts-section.php` - All shifts section
- `all-collections-section.php` - All collections section
- `dashboard-scripts.php` - Dashboard-specific scripts

### Main Files Modifications:
- Modify `view-driver-dashboard.php` to include these parts

## 5. Implementation Steps

1. Create all folder structures first
2. For each template section:
   - Create the part files with extracted code
   - Update the original template files to include the parts
   - Test each section after implementation
3. Apply CSS/styling fixes as needed 
4. Ensure any JavaScript functionality continues to work correctly

## 6. Testing Approach

For each template:
1. Test original functionality before changes
2. Test after refactoring to ensure identical behavior
3. Verify all conditional logic works correctly
4. Test on mobile and desktop views

## 7. Common Components

Consider creating common components for:
- Access control checks
- Data tables 
- Form elements
- Notification messages

## 8. Benefits of Refactoring

- Improved code organization
- Easier maintenance
- Reusable components
- Better separation of concerns
- Simpler updates to specific functionality
