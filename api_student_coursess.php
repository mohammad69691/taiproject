<?php
error_log("api_student_courses.php accessed with POST: " . json_encode($_POST) . ", GET: " . json_encode($_GET));
require_once 'config/database.php';
require_once 'config/auth.php';

if (!isLoggedIn()) {
    error_log("User not logged in");
    http_response_code(401);
    echo json_encode(['error' => 'Ei kirjautunut']);
    exit();
}

if (!testDbConnection()) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantayhteys puuttuu']);
    exit();
}

try {
    $pdo = getDbConnection();
    
    $tunnus = $_POST['tunnus'] ?? $_GET['tunnus'] ?? null;
    error_log("Processing request for tunnus: " . $tunnus);
    
    if (!$tunnus || $tunnus === '') {
        error_log("No tunnus provided");
        http_response_code(400);
        echo json_encode(['error' => 'Opiskelijan tunnus puuttuu', 'debug' => 'Received POST: ' . json_encode($_POST) . ', GET: ' . json_encode($_GET)]);
        exit();
    }
    
    $stmt = $pdo->prepare("
        SELECT k.nimi, k.alkupaiva, k.loppupaiva, kk.kirjautumispvm
        FROM kurssikirjautumiset kk
        JOIN kurssit k ON kk.kurssi_tunnus = k.tunnus
        WHERE kk.opiskelija_tunnus = ?
        ORDER BY k.alkupaiva DESC
    ");
    $stmt->execute([$tunnus]);
    $kurssit = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($kurssit);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantavirhe: ' . $e->getMessage()]);
}
?>
