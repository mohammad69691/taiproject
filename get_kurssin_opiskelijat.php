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
    
    // Haetaan kurssin tunnus
    $kurssi_tunnus = $_GET['kurssi_tunnus'] ?? null;
    
    if (!$kurssi_tunnus) {
        http_response_code(400);
        echo json_encode(['error' => 'Kurssin tunnus puuttuu']);
        exit();
    }
    
    // Jos opettaja, tarkistetaan että kurssi on hänen
    if (isTeacher()) {
        $teacherId = getCurrentTeacherId();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM kurssit 
            WHERE tunnus = ? AND opettaja_tunnus = ?
        ");
        $stmt->execute([$kurssi_tunnus, $teacherId]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Ei oikeuksia tähän kurssiin']);
            exit();
        }
    }
    
    // Haetaan kurssin opiskelijat
    $stmt = $pdo->prepare("
        SELECT o.etunimi, o.sukunimi, o.vuosikurssi, kk.kirjautumispvm
        FROM kurssikirjautumiset kk
        JOIN opiskelijat o ON kk.opiskelija_tunnus = o.tunnus
        WHERE kk.kurssi_tunnus = ?
        ORDER BY o.sukunimi, o.etunimi
    ");
    $stmt->execute([$kurssi_tunnus]);
    $opiskelijat = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Palautetaan JSON-muodossa
    header('Content-Type: application/json');
    echo json_encode($opiskelijat);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantavirhe: ' . $e->getMessage()]);
}
?>
