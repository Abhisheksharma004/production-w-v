# Stages Management Implementation

## Overview
The stages management system creates **dynamic database tables** for each part number, where each stage becomes a column in that table.

## How It Works

### 1. Setup
First, run `setup-stages-metadata.php` in your browser to create the `stages_metadata` table:
```
http://localhost/production-management/setup-stages-metadata.php
```

### 2. Adding Stages for a Part
1. Select a part from the dropdown
2. Click "+ Add Stage" to add stage names (as many as needed)
3. Click "Save Stages"

### 3. What Happens Behind the Scenes

When you save stages for a part (e.g., Part Code: `ABC123`):

**A new table is created:**
```sql
CREATE TABLE [part_ABC123] (
    id INT IDENTITY(1,1) PRIMARY KEY,
    part_id INT NOT NULL,
    stage_1_cutting NVARCHAR(255) NULL,
    stage_2_welding NVARCHAR(255) NULL,
    stage_3_painting NVARCHAR(255) NULL,
    created_at DATETIME2 DEFAULT GETDATE(),
    updated_at DATETIME2 DEFAULT GETDATE(),
    FOREIGN KEY (part_id) REFERENCES parts(id)
)
```

**Metadata is stored:**
The `stages_metadata` table tracks:
- Part ID and Part Code
- Table name (e.g., `part_ABC123`)
- Stage names (JSON array)
- Creation timestamp

### 4. Benefits
- **Flexible structure**: Each part can have different stages
- **Easy querying**: All data for a part is in one table
- **Column-based tracking**: Each stage is a separate column for easy reporting

### 5. Important Notes
- Table names are sanitized (special characters replaced with underscores)
- Column names are limited to 128 characters
- Once a table is created for a part, you cannot create another one
- Deleting a stage configuration will **drop the entire table** and all its data

### 6. Example Usage

**Part: ABC123 with stages: Cutting, Welding, Painting**
- Creates table: `part_ABC123`
- Columns: `stage_1_cutting`, `stage_2_welding`, `stage_3_painting`

**Part: XYZ789 with stages: Assembly, Testing**
- Creates table: `part_XYZ789`
- Columns: `stage_1_assembly`, `stage_2_testing`

## Database Schema

### stages_metadata Table
```sql
id              INT IDENTITY(1,1) PRIMARY KEY
part_id         INT NOT NULL
part_code       NVARCHAR(50) NOT NULL
table_name      NVARCHAR(255) NOT NULL UNIQUE
stage_names     NVARCHAR(MAX) NOT NULL (JSON array)
created_at      DATETIME2
```

### Dynamic Part Tables (e.g., part_ABC123)
```sql
id              INT IDENTITY(1,1) PRIMARY KEY
part_id         INT NOT NULL
stage_1_*       NVARCHAR(255) NULL
stage_2_*       NVARCHAR(255) NULL
...
created_at      DATETIME2
updated_at      DATETIME2
```
