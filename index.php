<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Tarkistetaan tietokantayhteys
$dbConnected = testDbConnection();

// Haetaan tilastot
$stats = [];
if ($dbConnected) {
    try {
        $pdo = getDbConnection();
        
        // Opiskelijoiden määrä
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM opiskelijat");
        $stats['opiskelijat'] = $stmt->fetch()['count'];
        
        // Opettajien määrä
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM opettajat");
        $stats['opettajat'] = $stmt->fetch()['count'];
        
        // Kurssien määrä
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM kurssit");
        $stats['kurssit'] = $stmt->fetch()['count'];
        
        // Aktiivisten kurssien määrä
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM kurssit WHERE alkupaiva <= CURDATE() AND loppupaiva >= CURDATE()");
        $stats['aktiiviset_kurssit'] = $stmt->fetch()['count'];
        
        // Jos käyttäjä on opettaja, haetaan vain hänen kurssinsa
        if (isTeacher()) {
            $teacherId = getCurrentTeacherId();
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM kurssit WHERE opettaja_tunnus = ?");
            $stmt->execute([$teacherId]);
            $stats['omakurssit'] = $stmt->fetch()['count'];
        }
        
        // Jos käyttäjä on opiskelija, haetaan vain hänen kurssinsa
        if (isStudent()) {
            header("Location: student_dashboard.php");
            $studentId = getCurrentStudentId();
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM kurssikirjautumiset WHERE opiskelija_tunnus = ?");
            $stmt->execute([$studentId]);
            $stats['omakirjautumiset'] = $stmt->fetch()['count'];
        }
        
    } catch (Exception $e) {
        $stats = ['error' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurssienhallintajärjestelmä</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.1)"><polygon points="0,0 1000,100 1000,0"/></svg>');
            background-size: cover;
            background-position: bottom;
        }
        
        .hero-section .container {
            position: relative;
            z-index: 1;
        }
        
        .hero-section h1 {
            font-weight: 800;
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .hero-section .lead {
            font-size: 1.25rem;
            font-weight: 400;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        
        .user-info {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .user-info h5 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .user-info p {
            margin: 0;
            opacity: 0.9;
        }
        
        .btn-hero {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 1rem 2rem;
            font-weight: 600;
            border-radius: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .btn-hero:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .section-title {
            font-weight: 700;
            font-size: 2.5rem;
            color: #1f2937;
            margin-bottom: 3rem;
            text-align: center;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -1rem;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        .stats-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        
        .features-section {
            padding: 5rem 0;
            background: white;
        }
        
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            overflow: hidden;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 15%;
            animation-delay: 2s;
        }
        
        .floating-element:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
    </style>
</head>
<body>
    <!-- Navigaatio -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="./">
                <i class="fas fa-graduation-cap me-2"></i>
                Kurssienhallinta
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (isLoggedIn()): ?>
                        <?php if (canEditAll() || canManageCourses()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="opiskelijat.php">
                                    <i class="fas fa-users me-1"></i>Opiskelijat
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (canEditAll()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="opettajat.php">
                                    <i class="fas fa-chalkboard-teacher me-1"></i>Opettajat
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (canManageCourses()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="kurssit.php">
                                    <i class="fas fa-book me-1"></i>Kurssit
                                </a>
                            </li>
                        <?php endif; ?>
                        
                                            <?php if (canEditAll()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="tilat.php">
                                <i class="fas fa-building me-1"></i>Tilat
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user_management.php">
                                <i class="fas fa-user-cog me-1"></i>Käyttäjät
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="database_tools.php">
                                <i class="fas fa-database me-1"></i>Tietokanta
                            </a>
                        </li>
                    <?php endif; ?>
                        
                        <?php if (canEnrollStudents()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="kirjautumiset.php">
                                    <i class="fas fa-sign-in-alt me-1"></i>Kirjautumiset
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Kirjaudu sisään
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <?php if (isLoggedIn()): ?>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars(getCurrentUserName()); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item-text">
                                    <i class="fas fa-user-shield me-2"></i><?php echo ucfirst(htmlspecialchars(getCurrentUserRole())); ?>
                                </span></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if (isTeacher()): ?>
                                    <li><a class="dropdown-item" href="teacher_dashboard.php">
                                        <i class="fas fa-chalkboard-teacher me-2"></i>Opettajan etusivu
                                    </a></li>
                                <?php elseif (isStudent()): ?>
                                    <li><a class="dropdown-item" href="student_dashboard.php">
                                        <i class="fas fa-user-graduate me-2"></i>Opiskelijan etusivu
                                    </a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Kirjaudu ulos
                                </a></li>
                            </ul>
                        </li>
                    </ul>
                <?php endif; ?>
                
                
            </div>
        </div>
    </nav>

    <!-- Hero-osio -->
    <section class="hero-section">
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>
        <div class="container text-center">
            <h1 class="display-4 mb-4">Kurssienhallintajärjestelmä</h1>
            <p class="lead mb-4">Oppilaitoksen kurssien, opiskelijoiden ja opettajien hallinta</p>
            
            <?php if (isLoggedIn()): ?>
                <div class="user-info">
                    <h5><i class="fas fa-user-check me-2"></i>Tervetuloa, <?php echo htmlspecialchars(getCurrentUserName()); ?>!</h5>
                    <p class="mb-0">Rooli: <strong><?php echo ucfirst(htmlspecialchars(getCurrentUserRole())); ?></strong></p>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <a href="login.php" class="btn btn-hero btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Kirjaudu sisään
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if (!$dbConnected): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Tietokantayhteys puuttuu. <a href="setup.php" class="alert-link">Aseta tietokanta</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Tilastot -->
    <?php if ($dbConnected && !isset($stats['error'])): ?>
    <section class="stats-section">
        <div class="container">
            <h2 class="section-title">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>Yleiskatsaus<?php endif; ?>
                    <?php if (isTeacher()): ?>Omat kurssit<?php endif; ?>
                    <?php if (isStudent()): ?>Omat kurssit<?php endif; ?>
                <?php else: ?>
                    Yleiskatsaus
                <?php endif; ?>
            </h2>
            <div class="row g-4">
                <?php if (isAdmin() || !isLoggedIn()): ?>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon text-primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="text-primary"><?= $stats['opiskelijat'] ?></h3>
                            <p class="text-muted mb-0">Opiskelijaa</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon text-success">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <h3 class="text-success"><?= $stats['opettajat'] ?></h3>
                            <p class="text-muted mb-0">Opettajaa</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon text-info">
                                <i class="fas fa-book"></i>
                            </div>
                            <h3 class="text-info"><?= $stats['kurssit'] ?></h3>
                            <p class="text-muted mb-0">Kurssia</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon text-warning">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3 class="text-warning"><?= $stats['aktiiviset_kurssit'] ?></h3>
                            <p class="text-muted mb-0">Aktiivista kurssia</p>
                        </div>
                    </div>
                <?php elseif (isTeacher()): ?>
                    <div class="col-md-6">
                        <div class="stat-card text-center">
                            <div class="stat-icon text-info">
                                <i class="fas fa-book"></i>
                            </div>
                            <h3 class="text-info"><?= $stats['omakurssit'] ?></h3>
                            <p class="text-muted mb-0">Omat kurssit</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card text-center">
                            <div class="stat-icon text-warning">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3 class="text-warning"><?= $stats['aktiiviset_kurssit'] ?></h3>
                            <p class="text-muted mb-0">Aktiivista kurssia</p>
                        </div>
                    </div>
                <?php elseif (isStudent()): ?>
                    <div class="col-md-6">
                        <div class="stat-card text-center">
                            <div class="stat-icon text-primary">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <h3 class="text-primary"><?= $stats['omakirjautumiset'] ?></h3>
                            <p class="text-muted mb-0">Kurssikirjautumisia</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card text-center">
                            <div class="stat-icon text-success">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3 class="text-success"><?= $stats['aktiiviset_kurssit'] ?></h3>
                            <p class="text-muted mb-0">Aktiivista kurssia</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Ominaisuudet -->
    <section class="features-section">
        <div class="container">
            <h2 class="section-title">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>Järjestelmän ominaisuudet<?php endif; ?>
                    <?php if (isTeacher()): ?>Opettajan työkalut<?php endif; ?>
                    <?php if (isStudent()): ?>Opiskelijan työkalut<?php endif; ?>
                <?php else: ?>
                    Järjestelmän ominaisuudet
                <?php endif; ?>
            </h2>
            <div class="row g-4">
                <?php if (isAdmin() || !isLoggedIn()): ?>
                    <div class="col-md-4">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Opiskelijoiden hallinta</h5>
                                <p class="card-text">Lisää, muokkaa ja poista opiskelijatietoja. Hallitse vuosikursseja ja kurssikirjautumisia.</p>
                                <?php if (canEditAll() || canManageCourses()): ?>
                                    <a href="opiskelijat.php" class="btn btn-primary">Hallitse opiskelijoita</a>
                                <?php else: ?>
                                    <span class="text-muted">Pääsy rajoitettu</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-chalkboard fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Kurssien hallinta</h5>
                                <p class="card-text">Hallitse kurssitietoja, aikatauluja ja opettajavastuuta. Seuraa osallistujamääriä.</p>
                                <?php if (canManageCourses()): ?>
                                    <a href="kurssit.php" class="btn btn-success">Hallitse kursseja</a>
                                <?php else: ?>
                                    <span class="text-muted">Pääsy rajoitettu</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-building fa-3x text-info mb-3"></i>
                                <h5 class="card-title">Tilojen hallinta</h5>
                                <p class="card-text">Hallitse luokkien kapasiteetteja ja varoita ylikapasiteetista.</p>
                                <?php if (canEditAll()): ?>
                                    <a href="tilat.php" class="btn btn-info">Hallitse tiloja</a>
                                <?php else: ?>
                                    <span class="text-muted">Pääsy rajoitettu</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif (isTeacher()): ?>
                    <div class="col-md-6">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-book fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Omat kurssit</h5>
                                <p class="card-text">Hallitse omia kurssejasi, katso osallistujia ja hallitse kurssikirjautumisia.</p>
                                <a href="kurssit.php" class="btn btn-success">Katso kurssit</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Opiskelijoiden hallinta</h5>
                                <p class="card-text">Lisää ja poista opiskelijoita kursseillesi. Hallitse kurssikirjautumisia.</p>
                                <a href="opiskelijat.php" class="btn btn-primary">Hallitse opiskelijoita</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (isStudent()): ?>
                    <div class="col-md-6">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-book fa-3x text-info mb-3"></i>
                                <h5 class="card-title">Omat kurssit</h5>
                                <p class="card-text">Katso kurssit, joille olet kirjautunut. Seuraa aikatauluja ja kurssitietoja.</p>
                                <a href="opiskelijat.php" class="btn btn-info">Katso kurssit</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Kurssiaikataulu</h5>
                                <p class="card-text">Seuraa kurssiesi aikatauluja, tiloja ja opettajia.</p>
                                <a href="kurssit.php" class="btn btn-success">Katso aikataulu</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 Kurssienhallintajärjestelmä. Kaikki oikeudet pidätetään.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
