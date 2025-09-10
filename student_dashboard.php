<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireAuth();

if (!isStudent()) {
    header('Location: access_denied.php');
    exit();
}

$pdo = getDbConnection();
$studentId = getCurrentStudentId();
$error = '';

$opiskelija = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM opiskelijat WHERE tunnus = ?");
    $stmt->execute([$studentId]);
    $opiskelija = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Virhe opiskelijatietojen haussa: ' . $e->getMessage();
}

$kurssit = [];
try {
    $stmt = $pdo->prepare("
        SELECT k.*, t.nimi AS tila_nimi, t.kapasiteetti,
               op.etunimi AS opettaja_etunimi, op.sukunimi AS opettaja_sukunimi,
               kk.kirjautumispvm
        FROM kurssit k
        JOIN tilat t ON k.tila_tunnus = t.tunnus
        JOIN opettajat op ON k.opettaja_tunnus = op.tunnus
        JOIN kurssikirjautumiset kk ON k.tunnus = kk.kurssi_tunnus
        WHERE kk.opiskelija_tunnus = ?
        ORDER BY k.alkupaiva ASC
    ");
    $stmt->execute([$studentId]);
    $kurssit = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Virhe kurssien haussa: ' . $e->getMessage();
}

$tulevat_kurssit = array_filter($kurssit, function($k) {
    return $k['alkupaiva'] >= date('Y-m-d');
});

$menneet_kurssit = array_filter($kurssit, function($k) {
    return $k['loppupaiva'] < date('Y-m-d');
});

$aktiiviset_kurssit = array_filter($kurssit, function($k) {
    return $k['alkupaiva'] <= date('Y-m-d') && $k['loppupaiva'] >= date('Y-m-d');
});
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opiskelijan etusivu - Kurssienhallinta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        html {
            scroll-behavior: smooth;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 4rem 0;
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
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .hero-section .lead {
            font-size: 1.25rem;
            font-weight: 400;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        
        .student-info {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .student-info .alert {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 1rem;
        }
        
        .stats-section {
            padding: 4rem 0;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        
        .schedule-section {
            padding: 4rem 0;
            background: white;
        }
        
        .courses-section {
            padding: 4rem 0;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
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
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 2px;
        }
        
        .schedule-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .schedule-timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 2px;
        }
        
        .schedule-item {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .schedule-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 1rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: #3b82f6;
            border: 4px solid white;
            box-shadow: 0 0 0 2px #3b82f6;
            z-index: 1;
        }
        
        .upcoming-course::before {
            background: #10b981;
            box-shadow: 0 0 0 2px #10b981;
        }
        
        .active-course::before {
            background: #f59e0b;
            box-shadow: 0 0 0 2px #f59e0b;
        }
        
        .completed-course::before {
            background: #6b7280;
            box-shadow: 0 0 0 2px #6b7280;
        }
        
        .course-card {
            border: none;
            border-radius: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }
        
        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .upcoming-course::before {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .active-course::before {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .completed-course::before {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        }
        
        .course-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .course-card .card-body {
            padding: 2rem;
        }
        
        .course-card .card-title {
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        
        .course-card .card-text {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-weight: 600;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%) !important;
        }
        
        .badge.bg-info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
        }
    </style>
</head>
<body>
    <!-- Navigaatio -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="student_dashboard.php">
                <i class="fas fa-user-graduate me-2"></i>
                Opiskelijan etusivu
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#schedule">
                            <i class="fas fa-calendar me-1"></i>Oma aikataulu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#courses">
                            <i class="fas fa-book me-1"></i>Kurssit
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars(getCurrentUserName()); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">
                                <i class="fas fa-user-graduate me-2"></i>Opiskelija
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="./">
                                <i class="fas fa-home me-2"></i>Pääsivu
                            </a></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Kirjaudu ulos
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero-osio -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">Tervetuloa, <?php echo htmlspecialchars(getCurrentUserName()); ?>!</h1>
            <p class="lead mb-4">Opiskelijan henkilökohtainen etusivu ja aikataulu</p>
            <?php if ($opiskelija): ?>
                <div class="student-info">
                    <div class="row justify-content-center">
                        <div class="col-md-4">
                            <div class="alert">
                                <i class="fas fa-id-card me-2"></i>
                                <strong>Opiskelijanumero:</strong> <?php echo htmlspecialchars($opiskelija['opiskelijanumero']); ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert">
                                <i class="fas fa-graduation-cap me-2"></i>
                                <strong>Vuosikurssi:</strong> <?php echo htmlspecialchars($opiskelija['vuosikurssi']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Tilastot -->
    <section class="stats-section">
        <div class="container">
            <h2 class="section-title">Yleiskatsaus</h2>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3 class="text-primary"><?php echo count($kurssit); ?></h3>
                        <p class="text-muted mb-0">Kaikki kurssit</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <h3 class="text-success"><?php echo count($aktiiviset_kurssit); ?></h3>
                        <p class="text-muted mb-0">Aktiivista kurssia</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="text-warning"><?php echo count($tulevat_kurssit); ?></h3>
                        <p class="text-muted mb-0">Tulevaa kurssia</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="text-secondary"><?php echo count($menneet_kurssit); ?></h3>
                        <p class="text-muted mb-0">Suoritettu kurssia</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Aikataulu -->
    <section id="schedule" class="schedule-section">
        <div class="container">
            <h2 class="section-title">
                <i class="fas fa-calendar me-2"></i>Oma aikataulu
            </h2>
            
            <?php if (empty($kurssit)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Et ole vielä kirjautunut kursseille. Ota yhteyttä opettajaasi kurssikirjautumisesta.
                </div>
            <?php else: ?>
                <div class="schedule-timeline">
                    <?php foreach ($kurssit as $kurssi): ?>
                        <?php 
                        $courseClass = '';
                        if ($kurssi['alkupaiva'] > date('Y-m-d')) {
                            $courseClass = 'upcoming-course';
                        } elseif ($kurssi['alkupaiva'] <= date('Y-m-d') && $kurssi['loppupaiva'] >= date('Y-m-d')) {
                            $courseClass = 'active-course';
                        } else {
                            $courseClass = 'completed-course';
                        }
                        ?>
                        <div class="schedule-item">
                            <div class="card course-card <?php echo $courseClass; ?>">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h5 class="card-title mb-2">
                                                <?php echo htmlspecialchars($kurssi['nimi']); ?>
                                                <?php if ($courseClass === 'active-course'): ?>
                                                    <span class="badge bg-warning text-dark ms-2">
                                                        <i class="fas fa-play me-1"></i>Aktiivinen
                                                    </span>
                                                <?php elseif ($courseClass === 'upcoming-course'): ?>
                                                    <span class="badge bg-success ms-2">
                                                        <i class="fas fa-clock me-1"></i>Tuleva
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary ms-2">
                                                        <i class="fas fa-check me-1"></i>Suoritettu
                                                    </span>
                                                <?php endif; ?>
                                            </h5>
                                            
                                            <p class="card-text text-muted mb-2">
                                                <?php echo htmlspecialchars($kurssi['kuvaus']); ?>
                                            </p>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <strong>Alkaa:</strong> <?php echo date('d.m.Y', strtotime($kurssi['alkupaiva'])); ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-6">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar-check me-1"></i>
                                                        <strong>Päättyy:</strong> <?php echo date('d.m.Y', strtotime($kurssi['loppupaiva'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 text-md-end">
                                            <div class="mb-2">
                                                <span class="badge bg-info">
                                                    <i class="fas fa-chalkboard-teacher me-1"></i>
                                                    <?php echo htmlspecialchars($kurssi['opettaja_etunimi'] . ' ' . $kurssi['opettaja_sukunimi']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-building me-1"></i>
                                                    <?php echo htmlspecialchars($kurssi['tila_nimi']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-sign-in-alt me-1"></i>
                                                    Kirjautunut: <?php echo date('d.m.Y', strtotime($kurssi['kirjautumispvm'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Kurssit listana -->
    <section id="courses" class="courses-section">
        <div class="container">
            <h2 class="section-title">
                <i class="fas fa-book me-2"></i>Kurssit listana
            </h2>
            
            <?php if (empty($kurssit)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Ei kursseja näytettäväksi.
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Kurssi</th>
                                        <th>Opettaja</th>
                                        <th>Tila</th>
                                        <th>Alkaa</th>
                                        <th>Päättyy</th>
                                        <th>Tila</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kurssit as $kurssi): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($kurssi['nimi']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($kurssi['kuvaus']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($kurssi['opettaja_etunimi'] . ' ' . $kurssi['opettaja_sukunimi']); ?></td>
                                            <td><?php echo htmlspecialchars($kurssi['tila_nimi']); ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($kurssi['alkupaiva'])); ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($kurssi['loppupaiva'])); ?></td>
                                            <td>
                                                <?php if ($kurssi['alkupaiva'] > date('Y-m-d')): ?>
                                                    <span class="badge bg-success">Tuleva</span>
                                                <?php elseif ($kurssi['alkupaiva'] <= date('Y-m-d') && $kurssi['loppupaiva'] >= date('Y-m-d')): ?>
                                                    <span class="badge bg-warning text-dark">Aktiivinen</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Suoritettu</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 Kurssienhallintajärjestelmä - Opiskelijan etusivu</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('a[href^="#"]');
            
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    
                    if (href.startsWith('#') && href.length > 1) {
                        e.preventDefault();
                        
                        const targetId = href.substring(1);
                        const targetElement = document.getElementById(targetId);
                        
                        if (targetElement) {
                            targetElement.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
