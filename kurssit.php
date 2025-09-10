<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Tarkistetaan autentikaatio ja oikeudet
requireAuth();

// Vain adminit ja opettajat voivat hallita kursseja
if (!canManageCourses()) {
    header('Location: access_denied.php');
    exit();
}

// Tarkistetaan tietokantayhteys
if (!testDbConnection()) {
    header('Location: setup.php');
    exit;
}

$pdo = getDbConnection();
$message = '';
$error = '';

// Kurssin lisäys
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO kurssit (nimi, kuvaus, alkupaiva, loppupaiva, opettaja_tunnus, tila_tunnus) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['nimi'], $_POST['kuvaus'], $_POST['alkupaiva'], $_POST['loppupaiva'], $_POST['opettaja_tunnus'], $_POST['tila_tunnus']]);
        $message = 'Kurssi lisätty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe kurssin lisäämisessä: ' . $e->getMessage();
    }
}

// Kurssin poisto
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM kurssit WHERE tunnus = ?");
        $stmt->execute([$_POST['tunnus']]);
        $message = 'Kurssi poistettu onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe kurssin poistamisessa: ' . $e->getMessage();
    }
}

// Kurssin päivitys
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update') {
    try {
        $stmt = $pdo->prepare("UPDATE kurssit SET nimi = ?, kuvaus = ?, alkupaiva = ?, loppupaiva = ?, opettaja_tunnus = ?, tila_tunnus = ? WHERE tunnus = ?");
        $stmt->execute([$_POST['nimi'], $_POST['kuvaus'], $_POST['alkupaiva'], $_POST['loppupaiva'], $_POST['opettaja_tunnus'], $_POST['tila_tunnus'], $_POST['tunnus']]);
        $message = 'Kurssi päivitetty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe kurssin päivittämisessä: ' . $e->getMessage();
    }
}

// Haetaan kurssit
$kurssit = [];
try {
    if (isAdmin()) {
        // Admin näkee kaikki kurssit
        $stmt = $pdo->query("
            SELECT k.*, 
                   CONCAT(o.etunimi, ' ', o.sukunimi) AS opettaja_nimi,
                   t.nimi AS tila_nimi,
                   t.kapasiteetti,
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
        // Opettaja näkee vain omat kurssinsa
        $teacherId = getCurrentTeacherId();
        $stmt = $pdo->prepare("
            SELECT k.*, 
                   CONCAT(o.etunimi, ' ', o.sukunimi) AS opettaja_nimi,
                   t.nimi AS tila_nimi,
                   t.kapasiteetti,
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
    $error = 'Virhe kurssien haussa: ' . $e->getMessage();
}

// Haetaan opettajat ja tilat lomakkeita varten
$opettajat = [];
$tilat = [];
try {
    if (isAdmin()) {
        $stmt = $pdo->query("SELECT tunnus, etunimi, sukunimi, aine FROM opettajat ORDER BY sukunimi, etunimi");
        $opettajat = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT tunnus, nimi, kapasiteetti FROM tilat ORDER BY nimi");
        $tilat = $stmt->fetchAll();
    } elseif (isTeacher()) {
        // Opettaja voi luoda kursseja vain itselleen
        $teacherId = getCurrentTeacherId();
        $stmt = $pdo->prepare("SELECT tunnus, etunimi, sukunimi, aine FROM opettajat WHERE tunnus = ?");
        $stmt->execute([$teacherId]);
        $opettajat = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT tunnus, nimi, kapasiteetti FROM tilat ORDER BY nimi");
        $tilat = $stmt->fetchAll();
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
    <title>Kurssit - Kurssienhallintajärjestelmä</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
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
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
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
                    
                    <li class="nav-item">
                        <a class="nav-link active" href="kurssit.php">
                            <i class="fas fa-book me-1"></i>Kurssit
                        </a>
                    </li>
                    
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
                    <?php if (canEnrollStudents()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="kirjautumiset.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Kirjautumiset
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
                    <h1><i class="fas fa-book me-2"></i>Kurssit</h1>
                    <p>Hallitse kurssitietoja, aikatauluja ja osallistujia</p>
                </div>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="fas fa-plus me-2"></i>Lisää kurssi
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
                Näet vain omat kurssisi.
            </div>
        <?php endif; ?>

        <!-- Kurssien lista -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Tunnus</th>
                                <th>Nimi</th>
                                <th>Kuvaus</th>
                                <th>Aikataulu</th>
                                <th>Opettaja</th>
                                <th>Tila</th>
                                <th>Osallistujat</th>
                                <th>Toiminnot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kurssit)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>Ei kursseja
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kurssit as $kurssi): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kurssi['tunnus']); ?></td>
                                        <td><?php echo htmlspecialchars($kurssi['nimi']); ?></td>
                                        <td>
                                            <?php if (strlen($kurssi['kuvaus']) > 50): ?>
                                                <?php echo htmlspecialchars(substr($kurssi['kuvaus'], 0, 50)); ?>...
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($kurssi['kuvaus']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d.m.Y', strtotime($kurssi['alkupaiva'])); ?> - 
                                                <?php echo date('d.m.Y', strtotime($kurssi['loppupaiva'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($kurssi['opettaja_nimi']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($kurssi['tila_nimi']); ?></span>
                                        </td>
                                        <td>
                                            <span class="<?php echo $kurssi['osallistujien_maara'] > $kurssi['kapasiteetti'] ? 'capacity-warning' : ''; ?>">
                                                <?php echo $kurssi['osallistujien_maara']; ?> / <?php echo $kurssi['kapasiteetti']; ?>
                                                <?php if ($kurssi['osallistujien_maara'] > $kurssi['kapasiteetti']): ?>
                                                    <i class="fas fa-exclamation-triangle text-danger"></i>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-info me-1" 
                                                        onclick="showCourseStudents(<?php echo $kurssi['tunnus']; ?>, '<?php echo htmlspecialchars($kurssi['nimi']); ?>')">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                                
                                                <?php if (canEditAll() || canManageOwnCourses($kurssi['opettaja_tunnus'])): ?>
                                                    <button class="btn btn-sm btn-warning me-1" 
                                                            onclick="editCourse(<?php echo htmlspecialchars(json_encode($kurssi)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <?php if (canEditAll()): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Haluatko varmasti poistaa tämän kurssin?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="tunnus" value="<?php echo htmlspecialchars($kurssi['tunnus']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
        </div>
    </div>

    <!-- Lisää kurssi -modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lisää uusi kurssi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nimi" class="form-label">Kurssin nimi</label>
                                    <input type="text" class="form-control" id="nimi" name="nimi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="opettaja_tunnus" class="form-label">Opettaja</label>
                                    <select class="form-select" id="opettaja_tunnus" name="opettaja_tunnus" required>
                                        <option value="">Valitse opettaja</option>
                                        <?php foreach ($opettajat as $opettaja): ?>
                                            <option value="<?php echo htmlspecialchars($opettaja['tunnus']); ?>">
                                                <?php echo htmlspecialchars($opettaja['etunimi'] . ' ' . $opettaja['sukunimi']); ?> (<?php echo htmlspecialchars($opettaja['aine']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="kuvaus" class="form-label">Kuvaus</label>
                            <textarea class="form-control" id="kuvaus" name="kuvaus" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="alkupaiva" class="form-label">Aloituspäivä</label>
                                    <input type="date" class="form-control" id="alkupaiva" name="alkupaiva" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="loppupaiva" class="form-label">Loppupäivä</label>
                                    <input type="date" class="form-control" id="loppupaiva" name="loppupaiva" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="tila_tunnus" class="form-label">Tila</label>
                            <select class="form-select" id="tila_tunnus" name="tila_tunnus" required>
                                <option value="">Valitse tila</option>
                                <?php foreach ($tilat as $tila): ?>
                                    <option value="<?php echo htmlspecialchars($tila['tunnus']); ?>">
                                        <?php echo htmlspecialchars($tila['nimi']); ?> (kapasiteetti: <?php echo $tila['kapasiteetti']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-primary">Lisää kurssi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Muokkaa kurssi -modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Muokkaa kurssia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="tunnus" id="edit_tunnus">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nimi" class="form-label">Kurssin nimi</label>
                                    <input type="text" class="form-control" id="edit_nimi" name="nimi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_opettaja_tunnus" class="form-label">Opettaja</label>
                                    <select class="form-select" id="edit_opettaja_tunnus" name="opettaja_tunnus" required>
                                        <?php foreach ($opettajat as $opettaja): ?>
                                            <option value="<?php echo htmlspecialchars($opettaja['tunnus']); ?>">
                                                <?php echo htmlspecialchars($opettaja['etunimi'] . ' ' . $opettaja['sukunimi']); ?> (<?php echo htmlspecialchars($opettaja['aine']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_kuvaus" class="form-label">Kuvaus</label>
                            <textarea class="form-control" id="edit_kuvaus" name="kuvaus" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_alkupaiva" class="form-label">Aloituspäivä</label>
                                    <input type="date" class="form-control" id="edit_alkupaiva" name="alkupaiva" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_loppupaiva" class="form-label">Loppupäivä</label>
                                    <input type="date" class="form-control" id="edit_loppupaiva" name="loppupaiva" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_tila_tunnus" class="form-label">Tila</label>
                            <select class="form-select" id="edit_tila_tunnus" name="tila_tunnus" required>
                                <?php foreach ($tilat as $tila): ?>
                                    <option value="<?php echo htmlspecialchars($tila['tunnus']); ?>">
                                        <?php echo htmlspecialchars($tila['nimi']); ?> (kapasiteetti: <?php echo $tila['kapasiteetti']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-warning">Päivitä kurssi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Opiskelijat -modal -->
    <div class="modal fade" id="studentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kurssin opiskelijat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 id="courseName"></h6>
                    <div id="studentsList">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Ladataan...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Sulje</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCourse(course) {
            document.getElementById('edit_tunnus').value = course.tunnus;
            document.getElementById('edit_nimi').value = course.nimi;
            document.getElementById('edit_kuvaus').value = course.kuvaus;
            document.getElementById('edit_alkupaiva').value = course.alkupaiva;
            document.getElementById('edit_loppupaiva').value = course.loppupaiva;
            document.getElementById('edit_opettaja_tunnus').value = course.opettaja_tunnus;
            document.getElementById('edit_tila_tunnus').value = course.tila_tunnus;
            
            new bootstrap.Modal(document.getElementById('editCourseModal')).show();
        }

        function showCourseStudents(courseId, courseName) {
            document.getElementById('courseName').textContent = courseName;
            
            // Haetaan opiskelijat AJAX:lla
            fetch(`get_kurssin_opiskelijat.php?kurssi_tunnus=${courseId}`)
                .then(response => response.json())
                .then(data => {
                    const studentsList = document.getElementById('studentsList');
                    if (data.length > 0) {
                        let html = '<div class="table-responsive"><table class="table table-sm">';
                        html += '<thead><tr><th>Nimi</th><th>Vuosikurssi</th><th>Kirjautumispäivä</th></tr></thead><tbody>';
                        data.forEach(opiskelija => {
                            html += `<tr>
                                <td>${opiskelija.etunimi} ${opiskelija.sukunimi}</td>
                                <td><span class="badge bg-primary">${opiskelija.vuosikurssi}</span></td>
                                <td>${opiskelija.kirjautumispvm}</td>
                            </tr>`;
                        });
                        html += '</tbody></table></div>';
                        studentsList.innerHTML = html;
                    } else {
                        studentsList.innerHTML = '<p class="text-muted">Ei osallistujia.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('studentsList').innerHTML = 
                        '<p class="text-danger">Virhe opiskelijoiden haussa: ' + error.message + '</p>';
                });
            
            new bootstrap.Modal(document.getElementById('studentsModal')).show();
        }
    </script>
    <script src="assets/js/no-reload.js"></script>
</body>
</html>
