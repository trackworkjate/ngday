# System Specification: Monday-Style Project Management System
**Target Environment:** PHP 8.x + MySQL 8.x on Shared Hosting (`nigiwaigroup.com`)  
**Optimized for:** AI-Assisted Development (Antigravity / Cursor / Project IDX)  
**Author:** Technical Architecture & Full-Stack Advisory  
**Date:** August 2026  

---

## 1. Executive Summary & Constraints

### 1.1 Objective
Build a lightweight, highly responsive, Monday-style Data Grid Project Management application running seamlessly on a standard shared cPanel/DirectAdmin hosting environment without requiring dedicated VPS, Node.js daemons, or long-running WebSocket servers.

### 1.2 Architectural Constraints on Shared Hosting
| Constraint | Impact | Architectural Solution |
| :--- | :--- | :--- |
| **No Persistent Node.js/Daemon** | Cannot run Socket.io or custom WebSocket server | Use **Smart Short-Polling** (`last_sync_time`) when browser tab is active. |
| **Shared CPU / I/O Limits** | Heavy database re-indexing will cause slow queries | Use **Fractional Indexing** (`DOUBLE position`) to achieve $O(1)$ row reordering. |
| **Dynamic Column Customization** | Traditional relational schema requires `ALTER TABLE` | Use **Hybrid JSON Column** (`column_values JSON`) mapped against column definitions. |
| **Server Workload Minimization** | Heavy server-side template rendering consumes CPU | Use **Client-Side SPA Architecture** (Alpine.js / Tailwind CSS) consuming a stateless PHP JSON REST API. |

---

## 2. System Architecture

```
+-------------------------------------------------------------------------+
|                              Browser (SPA)                              |
|  - Alpine.js (Reactive Store & Virtual State)                           |
|  - Tailwind CSS (Monday.com High-Density UI Theme)                      |
|  - SortableJS (Drag-and-Drop for Items & Groups)                         |
|  - Optimistic UI Mutator & Smart Poller                                 |
+------------------------------------+------------------------------------+
                                     |
                       HTTP / HTTPS (JSON Payloads)
                                     |
+------------------------------------v------------------------------------+
|                         Shared Host: PHP 8.x Backend                     |
|  - Single Entry Point REST Router (`api/index.php`)                     |
|  - PDO Prepared Statements (SQL Injection Immunity)                     |
|  - JSON API Response Helper & Error Handler                             |
|  - Fractional Positioning Math Engine                                   |
+------------------------------------+------------------------------------+
                                     |
                               PDO MySQL
                                     |
+------------------------------------v------------------------------------+
|                            MySQL 8.x Database                           |
|  - `boards`, `board_groups`, `board_columns` (Definitions)              |
|  - `items` (Data rows with Native JSON values)                          |
|  - Composite Indexing on `(board_id, position)` & `(group_id, position)`|
+-------------------------------------------------------------------------+
```

---

## 3. Database Schema (DDL)

```sql
-- Database: nigiwai_pm
-- Character Set: utf8mb4_unicode_ci

CREATE DATABASE IF NOT EXISTS `nigiwai_pm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nigiwai_pm`;

-- 1. Boards Table
CREATE TABLE IF NOT EXISTS `boards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Groups Table (Board Sections)
CREATE TABLE IF NOT EXISTS `board_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `board_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `color` VARCHAR(32) DEFAULT '#579BFC',
    `position` DOUBLE NOT NULL DEFAULT 1.0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_groups_board` FOREIGN KEY (`board_id`) REFERENCES `boards`(`id`) ON DELETE CASCADE,
    INDEX `idx_board_pos` (`board_id`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Dynamic Columns Metadata Table
CREATE TABLE IF NOT EXISTS `board_columns` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY, -- Unique string key (e.g., 'col_status_1', 'col_date_2')
    `board_id` INT NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `type` ENUM('text', 'status', 'date', 'number', 'people') NOT NULL DEFAULT 'text',
    `settings` JSON NULL,                 -- Dropdown options, color badges, currency symbols
    `position` DOUBLE NOT NULL DEFAULT 1.0,
    CONSTRAINT `fk_columns_board` FOREIGN KEY (`board_id`) REFERENCES `boards`(`id`) ON DELETE CASCADE,
    INDEX `idx_board_col_pos` (`board_id`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tasks / Items Table (Hybrid Dynamic Storage)
CREATE TABLE IF NOT EXISTS `items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `column_values` JSON NOT NULL,        -- Key-value mapping: {"col_status_1": "Done", "col_date_2": "2026-09-01"}
    `position` DOUBLE NOT NULL DEFAULT 1.0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_items_group` FOREIGN KEY (`group_id`) REFERENCES `board_groups`(`id`) ON DELETE CASCADE,
    INDEX `idx_group_pos` (`group_id`, `position`),
    INDEX `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Engineering Implementation Details

### 4.1 Fractional Indexing Reordering Formula (Lexorank Concept)
When dragging an item between `Item A (position = pos_prev)` and `Item B (position = pos_next)`:
$$	ext{new\_pos} = rac{	ext{pos\_prev} + 	ext{pos\_next}}{2}$$
* **Moving to First Position:** `new_pos = pos_first / 2`
* **Moving to Last Position:** `new_pos = pos_last + 1.0`
* **Advantage:** Requires only a single `UPDATE items SET position = ? WHERE id = ?` execution without recalculating other rows.

### 4.2 Dynamic Cell Update via MySQL Native JSON
To update a single column cell inside the `column_values` JSON payload without overwriting other values:
```sql
UPDATE items 
SET column_values = JSON_SET(COALESCE(column_values, '{}'), '$.col_status_1', 'Done')
WHERE id = :item_id;
```

### 4.3 Database Connection Wrapper (`api/config/database.php`)
```php
<?php
declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = 'localhost';
            $db   = 'nigiwai_pm';
            $user = 'db_user';
            $pass = 'db_password';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            self::$instance = new PDO($dsn, $user, $pass, $options);
        }
        return self::$instance;
    }
}
```

---

## 5. API Specification & Endpoints

| HTTP Method | Endpoint | Description | Payload Example |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/boards/{id}/full` | Fetch full board hierarchy (Groups, Columns, Items) | `None` |
| `POST` | `/api/items` | Create new item row in group | `{"group_id": 1, "name": "New Task"}` |
| `PATCH` | `/api/items/{id}/cell` | Update specific custom column cell | `{"column_id": "col_status_1", "value": "Working on it"}` |
| `PATCH` | `/api/items/{id}/reorder`| Move item between/within groups | `{"target_group_id": 1, "new_position": 1.5}` |
| `GET` | `/api/boards/{id}/sync` | Polling sync endpoint | Query param `?since=2026-08-27%2015:00:00` |

---

## 6. Directory Structure

```
public_html/
├── api/
│   ├── config/
│   │   └── database.php           # PDO Singleton
│   ├── controllers/
│   │   ├── BoardController.php    # Board metadata & Sync endpoints
│   │   └── ItemController.php     # Item CRUD & Dynamic Cell Mutation
│   └── index.php                  # Lightweight Micro Router & CORS
├── assets/
│   ├── css/
│   │   └── custom.css             # Monday.com dense table styling
│   └── js/
│       └── board-app.js           # Alpine.js Data Store & Optimistic Mutator
└── index.php                      # Single Page Application Main View
```

---

## 7. Step-by-Step Prompt Roadmap for Antigravity / AI IDEs

### Prompt 1: Database Setup & PDO Core
> "Create `api/config/database.php` implementing a thread-safe PDO singleton pattern connecting to MySQL with `utf8mb4`. Also create `api/index.php` with a lightweight, clean micro-router supporting GET, POST, PATCH, DELETE, CORS headers, and standardized JSON responses."

### Prompt 2: API Controllers
> "Implement `ItemController.php` with two critical methods:
> 1. `updateCell($itemId, $columnId, $value)` using MySQL `JSON_SET`.
> 2. `reorderItem($itemId, $targetGroupId, $newPosition)` supporting fractional indexing position updates."

### Prompt 3: Frontend Data Grid (Alpine.js + Tailwind)
> "Build `index.php` and `assets/js/board-app.js` using Alpine.js and Tailwind CSS that replicates Monday.com's Data Grid:
> - Collapsible Board Groups with color indicators.
> - Dynamic columns rendered based on metadata (Status dropdowns, Date pickers, Inline text edit).
> - Optimistic UI updates with instant local feedback and background fetch syncing.
> - Integration with SortableJS for row reordering."