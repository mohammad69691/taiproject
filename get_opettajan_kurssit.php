<?php
require_once 'config/database.php';

if (!testDbConnection()) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantayhteys puuttuu']);
    exit;
}

if (!isset($_GET['opettaja_tunnus']) || !is_numeric($_GET['opettaja_tunnus'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Virheellinen opettajatunnus']);
    exit;
}

$opettaja_tunnus = (int)$_GET['opettaja_tunnus'];

try {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT k.nimi, k.alkupaiva, k.loppupaiva, t.nimi AS tila_nimi
        FROM kurssit k
        JOIN tilat t ON k.tila_tunnus = t.tunnus
        WHERE k.opettaja_tunnus = ?
        ORDER BY k.alkupaiva DESC
    ");
    $stmt->execute([$opettaja_tunnus]);
    $kurssit = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($kurssit);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Virhe kurssien haussa: ' . $e->getMessage()]);
}
?>
