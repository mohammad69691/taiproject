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

// Generate filename with timestamp
$filename = 'migration_' . date('Y-m-d_H-i-s') . '.sql';

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Start output buffering
ob_start();

// SQL dump header
echo "-- Database Migration Export\n";
echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
echo "-- Database: " . DB_NAME . "\n";
echo "-- \n";
echo "-- This file is designed for migrating to a new server\n";
echo "-- It creates a fresh database without foreign key conflicts\n";
echo "-- \n\n";

// Set SQL mode
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET AUTOCOMMIT = 0;\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+00:00\";\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// Create database
echo "-- Create database\n";
echo "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
echo "USE `" . DB_NAME . "`;\n\n";

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

// Define table order for proper creation
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

// Export regular tables first (CREATE ONLY, no DROP)
foreach ($sortedTables as $table) {
    echo "-- --------------------------------------------------------\n";
    echo "-- Table structure for table `$table`\n";
    echo "-- --------------------------------------------------------\n\n";
    
    // Get table structure
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $createStatement = $row[1];
        
        // Remove AUTO_INCREMENT values for clean migration
        $createStatement = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $createStatement);
        
        echo $createStatement . ";\n\n";
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
    
    // Get view structure
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $createStatement = $row[1];
        
        // Remove DEFINER for cross-server compatibility
        $createStatement = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', '', $createStatement);
        
        echo $createStatement . ";\n\n";
    } catch (Exception $e) {
        echo "-- Error getting structure for view $table: " . $e->getMessage() . "\n\n";
        continue;
    }
}

// Add foreign key checks and commit
echo "-- --------------------------------------------------------\n";
echo "-- End of migration export\n";
echo "-- --------------------------------------------------------\n\n";
echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "COMMIT;\n";

// Get the output and send it
$output = ob_get_clean();
echo $output;

// Log the export action
error_log("Database migration export by user: " . getCurrentUserName() . " at " . date('Y-m-d H:i:s'));

exit();
?>
