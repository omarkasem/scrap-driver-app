# Driver Statistics Reporting System Implementation Plan

This document outlines a structured, professional plan for implementing the **Driver Statistics Reporting System** in the Scrap Driver application.

## **1. Architecture Overview**

- **Classes:** All PHP classes will be placed in `frontend/includes/`.
- **Templates:** All frontend templates will be placed in `frontend/templates/` and must contain `Template Name` comments for WordPress selection.
- **JavaScript & CSS:**
  - JavaScript functionality will be integrated into `frontend.js`.
  - Styles will be applied via `frontend.css`.
- **Data Source:**
  - **No new database tables** will be created.
  - All statistics will be gathered from existing WordPress post types and meta data.
- **Access Control:**
  - Admins can view statistics for all drivers.
  - Drivers can only access their own data.
  - Unauthorized users will be denied access.

---

## **2. Required Classes & Methods**

### **Class: `DriverStatistics` (`frontend/includes/class-driver-statistics.php`)**

Handles calculations and retrieval of driver performance data.

#### **Public Methods:**

- `get_driver_stats( $driver_ids, $start_date, $end_date, $interval = 'day' )`
  - Retrieves miles traveled, collections per hour, time per mile, etc.
  - Supports interval grouping (daily, weekly, monthly).
  - Returns structured statistical data.

- `get_driver_shifts( $driver_id, $start_date, $end_date )`
  - Fetches all shifts within a given date range.
  - Returns array of shift details.

- `calculate_shift_metrics( $shift_id )`
  - Processes shift data and calculates hours worked, collections per hour, distance traveled, and time per mile.

- `get_comparative_statistics( $driver_ids, $start_date, $end_date )`
  - Provides normalized data for driver performance comparisons.

---

### **Class: `FrontendStatisticsController` (`frontend/includes/class-frontend-statistics-controller.php`)**

Handles frontend routing, AJAX requests, and permissions.

#### **Public Methods:**

- `register_routes()`
  - Registers AJAX endpoints for fetching statistics data.

- `register_pages()`
  - Links templates to WordPress frontend pages.

- `can_view_statistics( $driver_id = null )`
  - Checks if the current user has permission to access statistics.

- `render_statistics_page()`
  - Loads the **driver statistics template** and initializes UI.

- `ajax_get_driver_stats()`
  - Processes AJAX requests to fetch driver statistics.

- `ajax_export_statistics()`
  - Handles CSV/PDF export functionality.

---

## **3. Template Requirements**

### **Templates (Stored in `frontend/templates/`)**

Each template will have a `Template Name` comment for easy selection in WordPress.

#### **`driver-statistics.php`**  
- **Main page** displaying driver statistics.
- Includes filters, summary, charts, and tables.

#### **`driver-statistics-summary.php`**  
- Displays key performance indicators.

#### **`driver-statistics-filters.php`**  
- Allows selection of drivers (admin-only) and date filtering.

#### **`driver-statistics-charts.php`**  
- Renders performance graphs using **Chart.js**.

#### **`driver-statistics-table.php`**  
- Displays a **DataTables-powered** table of statistics.

---

## **4. JavaScript Implementation (`frontend.js`)**

### **DriverStatistics Module**
- `initialize()` – Sets up event listeners and initializes UI.
- `loadStatisticsData()` – Fetches statistics via AJAX.
- `initializeCharts()` – Renders graphs using **Chart.js**.
- `initializeTable()` – Configures DataTables for tabular display.
- `handleExport( type )` – Handles CSV/PDF export functionality.

---

## **5. CSS Requirements (`frontend.css`)**

- **Statistics Filters:** Styling for form controls and date pickers.
- **Summary Metrics:** Responsive card-based layout.
- **Charts:** Custom styling for **Chart.js** elements.
- **Tables:** Improved **DataTables** styling for readability.
- **Access Control Styling:** Custom messages for unauthorized access.

---

## **6. Data Security & Performance Considerations**

- **Nonce validation** for all AJAX requests.
- **Capability checks** for admin-specific actions.
- **Optimized queries** to prevent performance bottlenecks.
- **Lazy loading** for large datasets.
- **Secure data export** ensuring only authorized users can generate reports.

---

## **7. File Structure**

```
frontend/
├── includes/
│   ├── class-driver-statistics.php
│   ├── class-frontend-statistics-controller.php
├── templates/
│   ├── driver-statistics.php               # Main statistics page template
│   ├── parts/
│       ├── driver-statistics-summary.php   # Summary section
│       ├── driver-statistics-filters.php   # Filters section
│       ├── driver-statistics-charts.php    # Chart section
│       ├── driver-statistics-table.php     # DataTables section
│       ├── access-denied.php               # Unauthorized access message
│       ├── driver-selector.php             # Driver selection UI
│       ├── driver-stat-card.php            # Individual statistic card
│       ├── chart-tooltip.php               # Chart tooltips
│       ├── export-options.php              # Export buttons
├── assets/
│   ├── css/frontend.css
│   ├── js/frontend.js
```

---

## **8. Implementation Strategy**

### **Step 1: Core Data Retrieval & Security**
- Implement **DriverStatistics** class for fetching driver data.
- Implement **FrontendStatisticsController** class with access control.
- Register AJAX routes for **secure** data retrieval.

### **Step 2: UI & Frontend Integration**
- Build **driver-statistics.php** and include filters, charts, tables.
- Load **DataTables & Chart.js** for visualization.
- Implement **frontend.js** for dynamic UI updates.

### **Step 3: Export & Performance Optimization**
- Implement **CSV/PDF export** functionality.
- Optimize **AJAX calls** to reduce load time.
- Final **CSS/UI refinements** for a polished user experience.

---

## **Final Notes**
- **Avoid unnecessary phases that slow AI execution.**
- **Keep implementation structured & incremental.**
- **Ensure security & optimization at every step.**
- **Match coding style to existing plugin conventions.**
