# Driver Statistics Reporting System Implementation Plan

This document outlines the plan for implementing a comprehensive driver statistics reporting system for the Scrap Driver application, including daily, weekly, and monthly collection reports with performance metrics and visualization capabilities.

## 1. Architecture Overview

The implementation will be frontend-based, with:
- Core classes in `frontend/includes/`
- Frontend templates in `templates/`
- All JavaScript in `frontend.js` (existing file)
- All CSS in `frontend.css` (existing file)
- Access control to restrict data visibility based on user role

No custom database tables will be created. Statistics will be calculated on-demand from existing WordPress post types and meta data.

## 2. Required Classes

### Class: DriverStatistics (frontend/includes/class-driver-statistics.php)

#### get_driver_stats( $driver_ids, $start_date, $end_date, $interval = 'day' )
- Retrieves comprehensive statistics for specified drivers within a date range
- Supports interval parameters (day, week, month) for data aggregation
- Calculates miles traveled, collections completed, hours worked, collections per hour, and average time per mile
- Returns array of driver statistics including totals and averages

#### get_driver_shifts( $driver_id, $start_date, $end_date )
- Retrieves all shifts for a driver within specified date range
- Filters shifts by status (completed only)
- Returns array of shift objects with their metadata

#### calculate_shift_metrics( $shift_id )
- Processes individual shift data from shift post type
- Calculates hours worked from clock in/out times (from start_time and end_time fields)
- Gets completed collections count for the shift using existing Collection::get_collection_number_completed_by_driver()
- Retrieves total distance traveled during shift from stored shift meta
- Computes collections per hour and minutes per mile
- Returns array of shift-level metrics

#### calculate_average( $total, $divisor )
- Utility method for safe average calculation
- Handles zero division cases
- Returns formatted average value

#### get_comparative_statistics( $driver_ids, $start_date, $end_date )
- Generates comparative data for multiple drivers
- Creates normalized data for fair comparison
- Returns structured data for comparison charts

### Class: FrontendStatisticsController (frontend/includes/class-frontend-statistics-controller.php)

#### register_routes()
- Registers endpoints for the statistics data
- Sets up proper permission callbacks for each endpoint

#### register_pages()
- Registers custom endpoints for statistics pages
- Maps URLs to template rendering functions
- Sets up necessary rewrite rules

#### can_view_statistics( $driver_id = null )
- Checks if current user has permission to view statistics
- For admins, returns true for all driver IDs
- For drivers, returns true only if driver_id matches their user ID
- Returns false for all other users

#### get_current_user_driver_id()
- Retrieves the driver ID associated with the current logged-in user
- Returns null if user is not a driver

#### render_statistics_page()
- Loads driver statistics template
- For admins, prepares driver list for selection
- For drivers, preselects their driver ID and disables selection
- Initializes date picker and form controls

#### ajax_get_driver_stats()
- Handles AJAX request for driver statistics
- Validates and sanitizes input parameters
- Checks permissions for requested driver data
- Returns JSON formatted statistics data for authorized requests only
- Handles different interval requests (daily/weekly/monthly)

#### ajax_export_statistics()
- Processes export request for driver statistics
- Validates user permissions before processing export
- Generates CSV or PDF file based on request type
- Formats data appropriately for the export format
- Returns file download response

## 3. Template Requirements

### Core Template Files (frontend/templates/)

#### driver-statistics.php
- Main template file for the driver statistics page
- Includes all sections (filters, summary, charts, table)
- Handles conditional display based on user role
- Entry point for the statistics dashboard

#### driver-statistics-summary.php
- Template for displaying summary metrics
- Shows key performance indicators
- Adapts based on selected date range and drivers
- Responsive card-based layout

#### driver-statistics-filters.php
- Contains all filter controls
- Implements driver selection (admin only)
- Date range picker implementation
- Interval selector and export options

#### driver-statistics-charts.php
- Contains all chart visualization components
- Implements trend charts for performance metrics
- Contains comparison charts (admin only)
- Responsive chart containers

#### driver-statistics-table.php
- DataTables implementation for detailed statistics
- Column definitions and data formatting
- Responsive table configuration
- Conditional columns based on user role

### Access Control Template Parts (frontend/templates/parts/)

#### access-denied.php
- Error message for unauthorized access attempts
- Login form or redirect options
- Clear explanation of access requirements

#### driver-selector.php
- Conditional template part that shows driver selection for admins
- Shows driver name (non-editable) for driver users
- Hidden completely for non-authorized users

### AJAX-Loaded Template Parts (frontend/templates/parts/)

#### driver-stat-card.php
- Individual statistic card template
- Used for dynamically loading statistics
- Consistent styling for metric display
- Supports different metrics types

#### chart-tooltip.php
- Custom tooltip template for chart visualizations
- Enhanced data display for hover states
- Consistent styling across all charts

#### export-options.php
- Template for export option buttons
- Configurable based on available export formats
- Includes necessary attributes for export handling

### All templates will support:
- Conditional rendering based on user permissions
- Responsive design for all screen sizes
- Proper sanitization of output data
- Translation readiness for internationalization

## 4. JavaScript Implementation (frontend.js)

The following JavaScript functionality will be added to the existing frontend.js file:

### DriverStatistics Module

#### initialize()
- Sets up event listeners for form controls
- Initializes date picker and driver selection (if user is admin)
- Sets up export button handlers
- Handles filter changes and reloads data
- Checks for user permissions and adjusts UI accordingly

#### loadStatisticsData()
- Makes AJAX request to fetch statistics data
- Passes filter parameters from form
- Includes proper nonce for security
- Handles response and updates UI components
- Manages error states including access denied responses

#### initializeCharts()
- Creates Chart.js instances for visualizations
- Sets up chart configurations and options
- Prepares color schemes and responsiveness
- Conditionally renders comparison charts based on user role

#### updateCharts( data )
- Updates chart data based on AJAX response
- Handles transition animations
- Manages chart legend and tooltips
- Adjusts for single vs. multiple driver views

#### initializeTable()
- Configures DataTables instance with responsive options
- Sets up columns and data formatting (conditional based on user role)
- Configures sorting and filtering capabilities
- Handles visibility of driver column based on user role

#### handleExport( type )
- Initiates export process for selected format
- Collects current filter parameters
- Sends export request to server with proper authentication
- Handles access denied responses

// Implementation will be integrated into the existing frontend.js file
// All functions will be properly namespaced to avoid conflicts

## 5. CSS Requirements (frontend.css)

The following CSS styles will be added to the existing frontend.css file:

1. Statistics filter container styling:
   - Form control layouts
   - Multi-select styling
   - Date picker customization
   - Responsive design for both admin and driver views

2. Statistics summary section styling:
   - Key metrics display
   - Card-based layout for metrics
   - Responsive cards for different screen sizes

3. Chart container styling:
   - Responsive chart containers
   - Chart legend positioning
   - Tooltip customization
   - Print-friendly chart layouts

4. DataTable styling:
   - Table header and cell formatting
   - Alternating row colors
   - Responsive table behavior
   - Sorting indicator styling
   - Mobile-friendly table collapse behavior

5. Export button styling:
   - Button appearance for export options
   - Icon integration for export types

6. Access control styling:
   - Error message styling
   - Permission indicators
   - Login prompt styling

// All CSS will be added to the existing frontend.css file
// Class names will be prefixed appropriately to avoid conflicts

## 6. Data Integration and Security

### Existing Code Integration
The implementation will leverage existing methods:
1. Collection::get_collection_number_completed_by_driver() - For counting completed collections
2. Distance from shift meta data - For calculating miles traveled
3. shift_date, start_time, end_time from shift meta fields - For calculating hours worked

### Security Implementation
1. Nonce verification for all AJAX requests
2. Capability checking for admin functions
3. User ID validation for driver access
4. Data sanitization for all inputs
5. Secure data export with permission checks
6. Prevention of direct access to template files

## 7. Implementation Phases

### Phase 1: Access Control & Frontend Foundation
This phase establishes security and core architecture.

**Implementation Steps:**
- Create FrontendStatisticsController in frontend/includes/ with access control methods
- Implement URL routing for frontend statistics pages
- Create basic templates with role-based content display
- Set up the essential permission checking systems
- Add user role detection and driver ID mapping

**Expected Output:**
- Working frontend pages with proper access control
- Admin users can access all driver statistics
- Drivers can only access their own statistics
- Non-authorized users are prevented from accessing statistics

### Phase 2: Data Processing & Calculation Logic
This phase focuses on calculation logic with security integration.

**Implementation Steps:**
- Complete all calculation methods in DriverStatistics class in frontend/includes/
- Implement date filtering functionality
- Add interval-based aggregation (daily/weekly/monthly)
- Create comparison calculation methods
- Add secure AJAX endpoints for retrieving statistics data
- Integrate permission checks with data retrieval

**Expected Output:**
- Accurate statistics calculations for all metrics
- Secure AJAX endpoints that validate user permissions
- Ability to filter by date range and aggregate by intervals
- Data responses filtered based on user role

### Phase 3: UI Implementation & DataTables Integration
This phase adds the visual representation layer with role-specific views.

**Implementation Steps:**
- Enhance frontend templates with role-appropriate controls
- Add JavaScript functionality to frontend.js for statistics features
- Add CSS styling to frontend.css for all UI components
- Implement DataTables for detailed statistics display
- Add statistics summary section with key metrics
- Create responsive layout and styling
- Implement conditional UI elements based on user role
- Add basic print functionality

**Expected Output:**
- Complete user interface with role-appropriate controls
- Admin view with multi-driver selection capability
- Driver view with fixed driver selection
- Responsive DataTables showing authorized statistics
- Summary cards displaying key performance metrics

### Phase 4: Data Visualization & Export Functionality
This phase adds advanced features with proper access control.

**Implementation Steps:**
- Integrate Chart.js visualization functionality into frontend.js
- Implement driver comparison charts (admin-only)
- Add trend analysis visualizations
- Create secure CSV export functionality
- Implement PDF report generation with permission checks
- Add final polish and performance optimizations

**Expected Output:**
- Interactive charts showing performance trends
- Comparative visualizations for admins
- Single-driver visualizations for drivers
- Secure export functionality for CSV and PDF formats
- Optimized performance for large data sets

## 8. Notes for Implementation

1. All time calculations should account for timezone settings
2. Performance optimization will be crucial for large datasets
3. The system should handle edge cases like incomplete shift data
4. All user inputs must be properly validated and sanitized
5. The implementation should follow WordPress coding standards
6. Maintain consistent spacing inside function parentheses as per custom instructions (ensure spaces inside function parentheses and before closing parenthesis)
7. Implement proper security checks at every data access point
8. Create a seamless UX that adapts based on user role
9. Ensure all error messages are user-friendly and non-technical
10. Make the interface responsive for desktop and mobile access
11. All PHP classes will be placed in the frontend/includes/ directory
12. All JavaScript code will be added to the existing frontend.js file with proper namespacing
13. All CSS styles will be added to the existing frontend.css file with appropriate prefixing
14. No custom JS or CSS files will be created

## 9. Template Integration and Loading

### Template Integration with WordPress

The statistics functionality will be implemented using standard WordPress page templates. Each template file will include the "Template Name" header comment so it appears in the WordPress page template selector when creating pages.

#### Template Registration

The templates will be registered using the existing WordPress template system:

1. Add filter to include the custom templates in the WordPress template selector
2. Create template files with proper header comments to identify them
3. Follow the existing pattern used in other frontend templates

#### Template Access Control

Each template will include access control at the beginning:

1. Check if the current user has proper permissions
2. Show appropriate content based on user role (admin vs. driver)
3. Display an access denied message for unauthorized users

## 10. File Structure and Template Files

The implementation will follow this file structure:

```
frontend/
├── includes/
│   ├── class-driver-statistics.php
│   └── class-frontend-statistics-controller.php
├── templates/
│   ├── driver-statistics.php               # Main page template
│   ├── parts/
│       ├── driver-statistics-summary.php   # Statistics summary section
│       ├── driver-statistics-filters.php   # Filter controls section
│       ├── driver-statistics-charts.php    # Chart visualization section
│       ├── driver-statistics-table.php     # DataTable section
│       ├── access-denied.php               
│       └── parts/
│           ├── driver-selector.php
│           ├── driver-stat-card.php
│           └── chart-tooltip.php
│           └── export-options.php
```

## 11. Implementation Notes for Templates

### Template Headers

Each template file should include the proper header comment to identify it as a WordPress template:

1. Main template should include "Template Name: Driver Statistics"
2. Follow the existing pattern seen in view-shifts.php and other templates

### Template Structure Guidelines

1. Start with access control checks
2. Include get_header() and get_footer() calls
3. Use consistent styling with the rest of the application
4. Implement responsive design patterns
5. Follow WordPress coding standards
6. Maintain proper security checks
7. Use translation functions for all text
8. Follow existing UI patterns in the application

### Access Control Implementation

Access control should be implemented at the beginning of each template:

1. Check user role (admin vs. driver)
2. For drivers, restrict data to only their own statistics
3. Deny access to unauthorized users with a clear message
4. Follow existing permission models in the application

### AJAX Integration

The templates will include JavaScript initialization that:

1. Registers event handlers
2. Makes AJAX calls to fetch statistics data
3. Updates the UI with the retrieved data
4. Implements interactive features like filters and sorting 