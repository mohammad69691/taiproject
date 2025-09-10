<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Tarkistetaan autentikaatio ja oikeudet
requireAuth();

// Vain adminit voivat viedä tietokannan
if (!canEditAll()) {
    header('Location: access_denied.php');
    exit();
}

// Tarkistetaan tietokantayhteys
if (!testDbConnection()) {
    die('Tietokantayhteys puuttuu');
}

$pdo = getDbConnection();

// Get database configuration
$dbname = DB_NAME;

// Generate filename with timestamp
$filename = 'database_export_' . date('Y-m-d_H-i-s') . '.sql';

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Start output buffering
ob_start();

// SQL dump header
echo "-- Database Export\n";
echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
echo "-- Database: " . $dbname . "\n";
echo "-- \n";
echo "-- This file contains the complete database structure and data\n";
echo "-- Import this file to recreate the database on another server\n";
echo "-- \n\n";

// Set SQL mode and disable foreign key checks
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET AUTOCOMMIT = 0;\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+00:00\";\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// Get all tables
$tables = [];
try {
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
} catch (Exception $e) {
    die("Error getting tables: " . $e->getMessage());
}

// Define table order for proper creation (tables first, then views)
$tableOrder = [
    'kayttajat',
    'opettajat', 
    'opiskelijat',
    'tilat',
    'kurssit',
    'kurssikirjautumiset'
];

$viewTables = [];
$regularTables = [];

// Separate tables and views
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $createStatement = $row[1];
        
        if (stripos($createStatement, 'CREATE VIEW') !== false || stripos($createStatement, 'CREATE ALGORITHM') !== false) {
            $viewTables[] = $table;
        } else {
            $regularTables[] = $table;
        }
    } catch (Exception $e) {
        // If we can't determine, treat as regular table
        $regularTables[] = $table;
    }
}

// Sort regular tables according to our order
$sortedTables = [];
foreach ($tableOrder as $orderedTable) {
    if (in_array($orderedTable, $regularTables)) {
        $sortedTables[] = $orderedTable;
    }
}

// Add any remaining tables not in our order
foreach ($regularTables as $table) {
    if (!in_array($table, $sortedTables)) {
        $sortedTables[] = $table;
    }
}

// Export regular tables first
foreach ($sortedTables as $table) {
    echo "-- --------------------------------------------------------\n";
    echo "-- Table structure for table `$table`\n";
    echo "-- --------------------------------------------------------\n\n";
    
    // Drop table if exists
    echo "DROP TABLE IF EXISTS `$table`;\n";
    
    // Get table structure
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo $row[1] . ";\n\n";
    } catch (Exception $e) {
        echo "-- Error getting structure for table $table: " . $e->getMessage() . "\n\n";
        continue;
    }
    
    // Get table data
    try {
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            echo "-- Dumping data for table `$table`\n";
            
            // Get column names
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';
            
            // Insert data
            echo "INSERT INTO `$table` ($columnList) VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $rowValues = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $rowValues[] = 'NULL';
                    } else {
                        // Escape the value properly
                        $escapedValue = $pdo->quote($value);
                        $rowValues[] = $escapedValue;
                    }
                }
                $values[] = '(' . implode(', ', $rowValues) . ')';
            }
            
            echo implode(",\n", $values) . ";\n\n";
        } else {
            echo "-- No data in table `$table`\n\n";
        }
    } catch (Exception $e) {
        echo "-- Error getting data for table $table: " . $e->getMessage() . "\n\n";
    }
}

// Export views after all tables are created
foreach ($viewTables as $table) {
    echo "-- --------------------------------------------------------\n";
    echo "-- View structure for view `$table`\n";
    echo "-- --------------------------------------------------------\n\n";
    
    // Drop view if exists
    echo "DROP VIEW IF EXISTS `$table`;\n";
    
    // Get view structure
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo $row[1] . ";\n\n";
    } catch (Exception $e) {
        echo "-- Error getting structure for view $table: " . $e->getMessage() . "\n\n";
        continue;
    }
}

// Add foreign key checks and commit
echo "-- --------------------------------------------------------\n";
echo "-- End of database export\n";
echo "-- --------------------------------------------------------\n\n";
echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "COMMIT;\n";

// Get the output and send it
$output = ob_get_clean();
echo $output;

// Log the export action
try {
    $logStmt = $pdo->prepare("INSERT INTO kayttajat (kayttajanimi, salasana_hash, rooli, etunimi, sukunimi, email, aktiivinen) VALUES (?, ?, ?, ?, ?, ?, ?)");
    // This is just for logging - we'll use a simple approach
    error_log("Database exported by user: " . getCurrentUserName() . " at " . date('Y-m-d H:i:s'));
} catch (Exception $e) {
    // Ignore logging errors
}

exit();
?>
