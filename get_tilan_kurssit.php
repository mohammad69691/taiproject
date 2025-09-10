<?php
require_once 'config/database.php';

// Tarkistetaan tietokantayhteys
if (!testDbConnection()) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantayhteys puuttuu']);
    exit;
}

// Tarkistetaan pyyntö
if (!isset($_GET['tila_tunnus']) || !is_numeric($_GET['tila_tunnus'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Virheellinen tilatunnus']);
    exit;
}

$tila_tunnus = (int)$_GET['tila_tunnus'];

try {
    $pdo = getDbConnection();
    
    // Haetaan tilan kurssit
    $stmt = $pdo->prepare("
        SELECT k.nimi, 
               CONCAT(o.etunimi, ' ', o.sukunimi) AS opettaja_nimi,
               k.alkupaiva, 
               k.loppupaiva,
               t.kapasiteetti,
               COUNT(kk.opiskelija_tunnus) AS osallistujien_maara
        FROM kurssit k
        JOIN opettajat o ON k.opettaja_tunnus = o.tunnus
        JOIN tilat t ON k.tila_tunnus = t.tunnus
        LEFT JOIN kurssikirjautumiset kk ON k.tunnus = kk.kurssi_tunnus
        WHERE k.tila_tunnus = ?
        GROUP BY k.tunnus
        ORDER BY k.alkupaiva DESC
    ");
    $stmt->execute([$tila_tunnus]);
    $kurssit = $stmt->fetchAll();
    
    // Palautetaan JSON-muodossa
    header('Content-Type: application/json');
    echo json_encode($kurssit);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Virhe kurssien haussa: ' . $e->getMessage()]);
}
?>
