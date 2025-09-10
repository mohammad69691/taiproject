<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireAuth();

if (!canEditAll()) {
    header('Location: access_denied.php');
    exit();
}

if (!testDbConnection()) {
    die('Tietokantayhteys puuttuu');
}

$pdo = getDbConnection();

$dbname = DB_NAME;

$filename = 'database_export_' . date('Y-m-d_H-i-s') . '.sql';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

ob_start();

echo "-- Database Export\n";
echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
echo "-- Database: " . $dbname . "\n";
echo "-- \n";
echo "-- This file contains the complete database structure and data\n";
echo "-- Import this file to recreate the database on another server\n";
echo "-- \n\n";

echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET AUTOCOMMIT = 0;\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+00:00\";\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$tables = [];
try {
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
} catch (Exception $e) {
    die("Error getting tables: " . $e->getMessage());
}

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

$sortedTables = [];
foreach ($tableOrder as $orderedTable) {
    if (in_array($orderedTable, $regularTables)) {
        $sortedTables[] = $orderedTable;
    }
}

foreach ($regularTables as $table) {
    if (!in_array($table, $sortedTables)) {
        $sortedTables[] = $table;
    }
}

foreach ($sortedTables as $table) {
    echo "-- --------------------------------------------------------\n";
    echo "-- Table structure for table `$table`\n";
    echo "-- --------------------------------------------------------\n\n";
    
    echo "DROP TABLE IF EXISTS `$table`;\n";
    
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo $row[1] . ";\n\n";
    } catch (Exception $e) {
        echo "-- Error getting structure for table $table: " . $e->getMessage() . "\n\n";
        continue;
    }
    
    try {
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            echo "-- Dumping data for table `$table`\n";
            
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';
            
            echo "INSERT INTO `$table` ($columnList) VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $rowValues = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $rowValues[] = 'NULL';
                    } else {
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

foreach ($viewTables as $table) {
    echo "-- --------------------------------------------------------\n";
    echo "-- View structure for view `$table`\n";
    echo "-- --------------------------------------------------------\n\n";
    
    echo "DROP VIEW IF EXISTS `$table`;\n";
    
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo $row[1] . ";\n\n";
    } catch (Exception $e) {
        echo "-- Error getting structure for view $table: " . $e->getMessage() . "\n\n";
        continue;
    }
}

echo "-- --------------------------------------------------------\n";
echo "-- End of database export\n";
echo "-- --------------------------------------------------------\n\n";
echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "COMMIT;\n";

$output = ob_get_clean();
echo $output;

try {
    $logStmt = $pdo->prepare("INSERT INTO kayttajat (kayttajanimi, salasana_hash, rooli, etunimi, sukunimi, email, aktiivinen) VALUES (?, ?, ?, ?, ?, ?, ?)");
    error_log("Database exported by user: " . getCurrentUserName() . " at " . date('Y-m-d H:i:s'));
} catch (Exception $e) {
}

exit();
?>
