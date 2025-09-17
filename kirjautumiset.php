<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireAuth();

if (!canEnrollStudents()) {
    header('Location: access_denied.php');
    exit();
}

if (!testDbConnection()) {
    header('Location: setup.php');
    exit;
}

$pdo = getDbConnection();
$message = '';
$error = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        // Validate input data
        if (empty($_POST['opiskelija_tunnus']) || empty($_POST['kurssi_tunnus'])) {
            $error = 'Opiskelija ja kurssi on valittava!';
        } else {
            // Check if student exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM opiskelijat WHERE tunnus = ?");
            $stmt->execute([$_POST['opiskelija_tunnus']]);
            if ($stmt->fetchColumn() == 0) {
                $error = 'Valittua opiskelijaa ei löydy!';
            } else {
                // Check if course exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM kurssit WHERE tunnus = ?");
                $stmt->execute([$_POST['kurssi_tunnus']]);
                if ($stmt->fetchColumn() == 0) {
                    $error = 'Valittua kurssia ei löydy! Tarkista kurssin ID: ' . htmlspecialchars($_POST['kurssi_tunnus']);
                } else {
                    // Check if already registered
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kurssikirjautumiset WHERE opiskelija_tunnus = ? AND kurssi_tunnus = ?");
                    $stmt->execute([$_POST['opiskelija_tunnus'], $_POST['kurssi_tunnus']]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Opiskelija on jo kirjautunut tälle kurssille!';
                    } else {
                        // Insert registration
                        $stmt = $pdo->prepare("INSERT INTO kurssikirjautumiset (opiskelija_tunnus, kurssi_tunnus, kirjautumispvm) VALUES (?, ?, NOW())");
                        $stmt->execute([$_POST['opiskelija_tunnus'], $_POST['kurssi_tunnus']]);
                        $message = 'Kurssikirjautuminen lisätty onnistuneesti!';
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Virhe kirjautumisen lisäämisessä: ' . $e->getMessage();
        // Log the error for debugging
        error_log("Registration error: " . $e->getMessage() . " - Student ID: " . ($_POST['opiskelija_tunnus'] ?? 'empty') . " - Course ID: " . ($_POST['kurssi_tunnus'] ?? 'empty'));
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM kurssikirjautumiset WHERE tunnus = ?");
        $stmt->execute([$_POST['tunnus']]);
        $message = 'Kurssikirjautuminen poistettu onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe kirjautumisen poistamisessa: ' . $e->getMessage();
    }
}

$kirjautumiset = [];
try {
    if (isAdmin()) {
        $stmt = $pdo->query("
            SELECT kk.tunnus, kk.opiskelija_tunnus, kk.kurssi_tunnus, kk.kirjautumispvm,
                   CONCAT(o.etunimi, ' ', o.sukunimi) AS opiskelija_nimi,
                   o.vuosikurssi,
                   k.nimi AS kurssi_nimi,
                   k.alkupaiva,
                   k.loppupaiva,
                   CONCAT(op.etunimi, ' ', op.sukunimi) AS opettaja_nimi,
                   t.nimi AS tila_nimi, t.kapasiteetti
            FROM kurssikirjautumiset kk
            JOIN opiskelijat o ON kk.opiskelija_tunnus = o.tunnus
            JOIN kurssit k ON kk.kurssi_tunnus = k.tunnus
            JOIN opettajat op ON k.opettaja_tunnus = op.tunnus
            JOIN tilat t ON k.tila_tunnus = t.tunnus
            ORDER BY kk.kirjautumispvm DESC
        ");
        $kirjautumiset = $stmt->fetchAll();
    } elseif (isTeacher()) {
        $teacherId = getCurrentTeacherId();
        $stmt = $pdo->prepare("
            SELECT kk.tunnus, kk.opiskelija_tunnus, kk.kurssi_tunnus, kk.kirjautumispvm,
                   CONCAT(o.etunimi, ' ', o.sukunimi) AS opiskelija_nimi,
                   o.vuosikurssi,
                   k.nimi AS kurssi_nimi,
                   k.alkupaiva,
                   k.loppupaiva,
                   CONCAT(op.etunimi, ' ', op.sukunimi) AS opettaja_nimi,
                   t.nimi AS tila_nimi, t.kapasiteetti
            FROM kurssikirjautumiset kk
            JOIN opiskelijat o ON kk.opiskelija_tunnus = o.tunnus
            JOIN kurssit k ON kk.kurssi_tunnus = k.tunnus
            JOIN opettajat op ON k.opettaja_tunnus = op.tunnus
            JOIN tilat t ON k.tila_tunnus = t.tunnus
            WHERE k.opettaja_tunnus = ?
            ORDER BY kk.kirjautumispvm DESC
        ");
        $stmt->execute([$teacherId]);
        $kirjautumiset = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = 'Virhe kurssikirjautumisten haussa: ' . $e->getMessage();
}

$opiskelijat = [];
$kurssit = [];
try {
    if (isAdmin()) {
        $stmt = $pdo->query("SELECT tunnus, opiskelijanumero, etunimi, sukunimi, vuosikurssi FROM opiskelijat ORDER BY sukunimi, etunimi");
        $opiskelijat = $stmt->fetchAll();
        
        $stmt = $pdo->query("
            SELECT k.tunnus, k.nimi, k.alkupaiva, k.loppupaiva, 
                   CONCAT(o.etunimi, ' ', o.sukunimi) AS opettaja_nimi,
                   t.nimi AS tila_nimi, t.kapasiteetti,
                   COUNT(kk.opiskelija_tunnus) AS osallistujien_maara
            FROM kurssit k
            JOIN opettajat o ON k.opettaja_tunnus = o.tunnus
            JOIN tilat t ON k.tila_tunnus = t.tunnus
            LEFT JOIN kurssikirjautumiset kk ON k.tunnus = kk.kurssi_tunnus
            GROUP BY k.tunnus
            ORDER BY k.alkupaiva DESC
        ");
        $kurssit = $stmt->fetchAll();
    } elseif (isTeacher()) {
        $teacherId = getCurrentTeacherId();
        $stmt = $pdo->query("SELECT tunnus, opiskelijanumero, etunimi, sukunimi, vuosikurssi FROM opiskelijat ORDER BY sukunimi, etunimi");
        $opiskelijat = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("
            SELECT k.tunnus, k.nimi, k.alkupaiva, k.loppupaiva, 
                   CONCAT(o.etunimi, ' ', o.sukunimi) AS opettaja_nimi,
                   t.nimi AS tila_nimi, t.kapasiteetti,
                   COUNT(kk.opiskelija_tunnus) AS osallistujien_maara
            FROM kurssit k
            JOIN opettajat o ON k.opettaja_tunnus = o.tunnus
            JOIN tilat t ON k.tila_tunnus = t.tunnus
            LEFT JOIN kurssikirjautumiset kk ON k.tunnus = kk.kurssi_tunnus
            WHERE k.opettaja_tunnus = ?
            GROUP BY k.tunnus
            ORDER BY k.alkupaiva DESC
        ");
        $stmt->execute([$teacherId]);
        $kurssit = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = 'Virhe tietojen haussa: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurssikirjautumiset - Kurssienhallintajärjestelmä</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 3rem;
        }
        
        .page-header h1 {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .content-section {
            padding: 2rem 0;
        }
        
        .btn-add {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(6, 182, 212, 0.4);
            color: white;
        }
        
        .table-container {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .table-container .table {
            margin: 0;
        }
        
        .table-container .table thead th {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1.5rem 1rem;
            border: none;
        }
        
        .table-container .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .table-container .table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: scale(1.01);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .table-container .table tbody td {
            padding: 1.5rem 1rem;
            vertical-align: middle;
            border: none;
        }
        
        .btn-group .btn {
            border-radius: 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-group .btn:hover {
            transform: translateY(-2px);
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
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%) !important;
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        }
        
        .capacity-warning {
            color: #ef4444;
            font-weight: 600;
        }
        
        .capacity-ok {
            color: #10b981;
            font-weight: 600;
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
                    <?php if (canViewStudents()): ?>
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
                    <?php endif; ?>
                    
                    <?php if (canEditAll()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user_management.php">
                                <i class="fas fa-user-cog me-1"></i>Käyttäjät
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (canEditAll()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="database_tools.php">
                                <i class="fas fa-database me-1"></i>Tietokanta
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="kirjautumiset.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Kirjautumiset
                        </a>
                    </li>
                    
                    
                    
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

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-sign-in-alt me-2"></i>Kurssikirjautumiset</h1>
                    <p>Hallitse opiskelijoiden kurssikirjautumisia</p>
                </div>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addRegistrationModal">
                    <i class="fas fa-plus me-2"></i>Lisää kirjautuminen
                </button>
            </div>
        </div>
    </div>

    <div class="container content-section">

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isTeacher()): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Näet vain omissa kursseissasi olevat kurssikirjautumiset.
            </div>
        <?php endif; ?>

        <!-- Kirjautumisten lista -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Tunnus</th>
                                <th>Opiskelija</th>
                                <th>Kurssi</th>
                                <th>Opettaja</th>
                                <th>Tila</th>
                                <th>Kapasiteetti</th>
                                <th>Kirjautumispäivä</th>
                                <th>Toiminnot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kirjautumiset)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>Ei kurssikirjautumisia
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kirjautumiset as $kirjautuminen): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kirjautuminen['tunnus']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($kirjautuminen['opiskelija_nimi']); ?>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($kirjautuminen['vuosikurssi']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($kirjautuminen['kurssi_nimi']); ?>
                                            <br><small class="text-muted">
                                                <?php echo date('d.m.Y', strtotime($kirjautuminen['alkupaiva'])); ?> - 
                                                <?php echo date('d.m.Y', strtotime($kirjautuminen['loppupaiva'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($kirjautuminen['opettaja_nimi']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($kirjautuminen['tila_nimi']); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kurssikirjautumiset WHERE kurssi_tunnus = ?");
                                            $stmt->execute([$kirjautuminen['kurssi_tunnus']]);
                                            $osallistujia = $stmt->fetchColumn();
                                            $kapasiteetti = $kirjautuminen['kapasiteetti'];
                                            
                                            if ($osallistujia > $kapasiteetti): ?>
                                                <span class="capacity-warning">
                                                    <?php echo $osallistujia; ?> / <?php echo $kapasiteetti; ?>
                                                    <i class="fas fa-exclamation-triangle text-danger"></i>
                                                </span>
                                            <?php else: ?>
                                                <?php echo $osallistujia; ?> / <?php echo $kapasiteetti; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('d.m.Y H:i', strtotime($kirjautuminen['kirjautumispvm'])); ?></small>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Haluatko varmasti poistaa tämän kirjautumisen?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="tunnus" value="<?php echo htmlspecialchars($kirjautuminen['tunnus']); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
        </div>
    </div>

    <!-- Lisää kirjautuminen -modal -->
    <div class="modal fade" id="addRegistrationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lisää uusi kurssikirjautuminen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="opiskelija_tunnus" class="form-label">Opiskelija</label>
                                    <select class="form-select" id="opiskelija_tunnus" name="opiskelija_tunnus" required>
                                        <option value="">Valitse opiskelija</option>
                                        <?php foreach ($opiskelijat as $opiskelija): ?>
                                            <option value="<?php echo htmlspecialchars($opiskelija['tunnus']); ?>">
                                                <?php echo htmlspecialchars($opiskelija['etunimi'] . ' ' . $opiskelija['sukunimi']); ?> (<?php echo htmlspecialchars($opiskelija['vuosikurssi']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kurssi_tunnus" class="form-label">Kurssi</label>
                                    <select class="form-select" id="kurssi_tunnus" name="kurssi_tunnus" required>
                                        <option value="">Valitse kurssi</option>
                                        <?php foreach ($kurssit as $kurssi): ?>
                                            <option value="<?php echo htmlspecialchars($kurssi['tunnus']); ?>">
                                                <?php echo htmlspecialchars($kurssi['nimi']); ?> 
                                                (<?php echo htmlspecialchars($kurssi['opettaja_nimi']); ?>, 
                                                <?php echo htmlspecialchars($kurssi['tila_nimi']); ?>, 
                                                <?php echo $kurssi['osallistujien_maara']; ?>/<?php echo $kurssi['kapasiteetti']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Kirjautumispäivä asetetaan automaattisesti nykyhetkeen.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-primary">Lisää kirjautuminen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
