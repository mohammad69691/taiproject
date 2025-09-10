<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Tarkistetaan autentikaatio
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Ei kirjautunut']);
    exit();
}

// Tarkistetaan tietokantayhteys
if (!testDbConnection()) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantayhteys puuttuu']);
    exit();
}

try {
    $pdo = getDbConnection();
    
    // Haetaan opiskelijan tunnus
    $tunnus = $_GET['tunnus'] ?? null;
    
    if (!$tunnus) {
        http_response_code(400);
        echo json_encode(['error' => 'Opiskelijan tunnus puuttuu']);
        exit();
    }
    
    // Jos opettaja, tarkistetaan että opiskelija on hänen kursseissaan
    if (isTeacher()) {
        $teacherId = getCurrentTeacherId();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM kurssikirjautumiset kk
            JOIN kurssit k ON kk.kurssi_tunnus = k.tunnus
            WHERE kk.opiskelija_tunnus = ? AND k.opettaja_tunnus = ?
        ");
        $stmt->execute([$tunnus, $teacherId]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Ei oikeuksia tähän opiskelijaan']);
            exit();
        }
    }
    
    // Haetaan opiskelijan kurssit
    $stmt = $pdo->prepare("
        SELECT k.nimi, k.alkupaiva, k.loppupaiva, kk.kirjautumispvm
        FROM kurssikirjautumiset kk
        JOIN kurssit k ON kk.kurssi_tunnus = k.tunnus
        WHERE kk.opiskelija_tunnus = ?
        ORDER BY k.alkupaiva DESC
    ");
    $stmt->execute([$tunnus]);
    $kurssit = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Palautetaan JSON-muodossa
    header('Content-Type: application/json');
    echo json_encode($kurssit);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantavirhe: ' . $e->getMessage()]);
}
?>
