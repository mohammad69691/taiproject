<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireAuth();

if (!isTeacher()) {
    header('Location: access_denied.php');
    exit();
}

$pdo = getDbConnection();
$teacherId = getCurrentTeacherId();
$error = '';

$opettaja = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM opettajat WHERE tunnus = ?");
    $stmt->execute([$teacherId]);
    $opettaja = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Virhe opettajatietojen haussa: ' . $e->getMessage();
}

$kurssit = [];
try {
    $stmt = $pdo->prepare("
        SELECT k.*, t.nimi AS tila_nimi, t.kapasiteetti,
               COUNT(kk.opiskelija_tunnus) AS osallistujien_maara
        FROM kurssit k
        JOIN tilat t ON k.tila_tunnus = t.tunnus
        LEFT JOIN kurssikirjautumiset kk ON k.tunnus = kk.kurssi_tunnus
        WHERE k.opettaja_tunnus = ?
        GROUP BY k.tunnus
        ORDER BY k.alkupaiva DESC
    ");
    $stmt->execute([$teacherId]);
    $kurssit = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Virhe kurssien haussa: ' . $e->getMessage();
}

$opiskelijat = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT o.*, COUNT(kk.kurssi_tunnus) AS kurssien_maara
        FROM opiskelijat o
        JOIN kurssikirjautumiset kk ON o.tunnus = kk.opiskelija_tunnus
        JOIN kurssit k ON kk.kurssi_tunnus = k.tunnus
        WHERE k.opettaja_tunnus = ?
        GROUP BY o.tunnus
        ORDER BY o.sukunimi, o.etunimi
    ");
    $stmt->execute([$teacherId]);
    $opiskelijat = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Virhe opiskelijoiden haussa: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opettajan etusivu - Kurssienhallinta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        
        .teacher-info {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .teacher-info .alert {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 1rem;
        }
        
        .stats-section {
            padding: 4rem 0;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        
        .courses-section {
            padding: 4rem 0;
            background: white;
        }
        
        .students-section {
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 2px;
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        
        .capacity-warning {
            color: #ef4444;
            font-weight: 600;
        }
        
        .capacity-ok {
            color: #10b981;
            font-weight: 600;
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
        
        .badge.bg-info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
        }
        
        .badge.bg-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
        }
    </style>
</head>
<body>
    <!-- Navigaatio -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="teacher_dashboard.php">
                <i class="fas fa-chalkboard-teacher me-2"></i>
                Opettajan etusivu
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="opiskelijat.php">
                            <i class="fas fa-users me-1"></i>Opiskelijat
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kurssit.php">
                            <i class="fas fa-book me-1"></i>Kurssit
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kirjautumiset.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Kirjautumiset
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
                                <i class="fas fa-user-shield me-2"></i>Opettaja
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
            <p class="lead mb-4">Opettajan työkalut kurssienhallintaan</p>
            <?php if ($opettaja): ?>
                <div class="teacher-info">
                    <div class="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Aine:</strong> <?php echo htmlspecialchars($opettaja['aine']); ?>
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
                <div class="col-md-4">
                    <div class="stat-card text-center">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3 class="text-success"><?php echo count($kurssit); ?></h3>
                        <p class="text-muted mb-0">Omat kurssit</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card text-center">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="text-success"><?php echo count($opiskelijat); ?></h3>
                        <p class="text-muted mb-0">Opiskelijat</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card text-center">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="text-success">
                            <?php 
                            $aktiiviset = array_filter($kurssit, function($k) {
                                return $k['alkupaiva'] <= date('Y-m-d') && $k['loppupaiva'] >= date('Y-m-d');
                            });
                            echo count($aktiiviset);
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Aktiivista kurssia</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Kurssit -->
    <section class="courses-section">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title mb-0"><i class="fas fa-book me-2"></i>Omat kurssit</h2>
                <a href="kurssit.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Hallitse kursseja
                </a>
            </div>
            
            <?php if (empty($kurssit)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Sinulla ei ole vielä kursseja. Ota yhteyttä ylläpitäjään kurssin luomiseksi.
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($kurssit as $kurssi): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card course-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($kurssi['nimi']); ?></h5>
                                    <p class="card-text">
                                        <?php 
                                        if (strlen($kurssi['kuvaus']) > 100) {
                                            echo htmlspecialchars(substr($kurssi['kuvaus'], 0, 100)) . '...';
                                        } else {
                                            echo htmlspecialchars($kurssi['kuvaus']);
                                        }
                                        ?>
                                    </p>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('d.m.Y', strtotime($kurssi['alkupaiva'])); ?> - 
                                            <?php echo date('d.m.Y', strtotime($kurssi['loppupaiva'])); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <span class="badge bg-info">
                                            <i class="fas fa-building me-1"></i>
                                            <?php echo htmlspecialchars($kurssi['tila_nimi']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <span class="<?php echo $kurssi['osallistujien_maara'] > $kurssi['kapasiteetti'] ? 'capacity-warning' : 'text-success'; ?>">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $kurssi['osallistujien_maara']; ?> / <?php echo $kurssi['kapasiteetti']; ?> osallistujaa
                                            <?php if ($kurssi['osallistujien_maara'] > $kurssi['kapasiteetti']): ?>
                                                <i class="fas fa-exclamation-triangle text-danger"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="kurssit.php" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-edit me-1"></i>Muokkaa kurssia
                                        </a>
                                        <a href="opiskelijat.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-users me-1"></i>Katso opiskelijat
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Opiskelijat -->
    <section class="students-section">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title mb-0"><i class="fas fa-users me-2"></i>Opiskelijat</h2>
                <a href="opiskelijat.php" class="btn btn-primary">
                    <i class="fas fa-cog me-2"></i>Hallitse opiskelijoita
                </a>
            </div>
            
            <?php if (empty($opiskelijat)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Sinulla ei ole vielä opiskelijoita kursseilla.
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Nimi</th>
                                        <th>Opiskelijanumero</th>
                                        <th>Vuosikurssi</th>
                                        <th>Kurssien määrä</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($opiskelijat, 0, 10) as $opiskelija): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($opiskelija['etunimi'] . ' ' . $opiskelija['sukunimi']); ?></td>
                                            <td><?php echo htmlspecialchars($opiskelija['opiskelijanumero']); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($opiskelija['vuosikurssi']); ?></span>
                                            </td>
                                            <td><?php echo $opiskelija['kurssien_maara']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($opiskelijat) > 10): ?>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Näytetään 10 ensimmäistä opiskelijaa. 
                                    <a href="opiskelijat.php">Katso kaikki</a>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 Kurssienhallintajärjestelmä - Opettajan etusivu</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
