<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireAuth();

if (!canManageCourses()) {
    header('Lo  cation: access_denied.php');
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
        $stmt = $pdo->prepare("INSERT INTO kurssit (nimi, kuvaus, alkupaiva, loppupaiva, opettaja_tunnus, tila_tunnus) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['nimi'], $_POST['kuvaus'], $_POST['alkupaiva'], $_POST['loppupaiva'], $_POST['opettaja_tunnus'], $_POST['tila_tunnus']]);
        $message = 'Kurssi lisätty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe kurssin lisäämisessä: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM kurssit WHERE tunnus = ?");
        $stmt->execute([$_POST['tunnus']]);
        $message = 'Kurssi poistettu onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe kurssin poistamisessa: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update') {
    try {
        $stmt = $pdo->prepare("UPDATE kurssit SET nimi = ?, kuvaus = ?, alkupaiva = ?, loppupaiva = ?, opettaja_tunnus = ?, tila_tunnus = ? WHERE tunnus = ?");
        $stmt->execute([$_POST['nimi'], $_POST['kuvaus'], $_POST['alkupaiva'], $_POST['loppupaiva'], $_POST['opettaja_tunnus'], $_POST['tila_tunnus'], $_POST['tunnus']]);
        $message = 'Kurssi päivitetty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe kurssin päivittämisessä: ' . $e->getMessage();
    }
}

// Handle schedule actions
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add_schedule') {
    try {
        $stmt = $pdo->prepare("INSERT INTO kurssi_sessiot (kurssi_tunnus, viikonpaiva, alkuaika, loppuaika) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['kurssi_tunnus'], $_POST['viikonpaiva'], $_POST['alkuaika'], $_POST['loppuaika']]);
        $message = 'Aikataulu lisätty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe aikataulun lisäämisessä: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete_schedule') {
    try {
        $stmt = $pdo->prepare("DELETE FROM kurssi_sessiot WHERE tunnus = ?");
        $stmt->execute([$_POST['schedule_id']]);
        $message = 'Aikataulu poistettu onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe aikataulun poistamisessa: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update_schedule') {
    try {
        $stmt = $pdo->prepare("UPDATE kurssi_sessiot SET viikonpaiva = ?, alkuaika = ?, loppuaika = ? WHERE tunnus = ?");
        $stmt->execute([$_POST['viikonpaiva'], $_POST['alkuaika'], $_POST['loppuaika'], $_POST['schedule_id']]);
        $message = 'Aikataulu päivitetty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe aikataulun päivittämisessä: ' . $e->getMessage();
    }
}

$kurssit = [];
$schedules = [];
try {
    if (isAdmin()) {
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
        
        // Get all schedules
        $stmt = $pdo->query("
            SELECT ks.*, k.nimi AS kurssi_nimi, 
                   CONCAT(o.etunimi, ' ', o.sukunimi) AS opettaja_nimi,
                   t.nimi AS tila_nimi
            FROM kurssi_sessiot ks
            JOIN kurssit k ON ks.kurssi_tunnus = k.tunnus
            JOIN opettajat o ON k.opettaja_tunnus = o.tunnus
            JOIN tilat t ON k.tila_tunnus = t.tunnus
            ORDER BY ks.viikonpaiva, ks.alkuaika
        ");
        $schedules = $stmt->fetchAll();
    } elseif (isTeacher()) {
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
        
        // Get schedules for teacher's courses
        $stmt = $pdo->prepare("
            SELECT ks.*, k.nimi AS kurssi_nimi, 
                   CONCAT(o.etunimi, ' ', o.sukunimi) AS opettaja_nimi,
                   t.nimi AS tila_nimi
            FROM kurssi_sessiot ks
            JOIN kurssit k ON ks.kurssi_tunnus = k.tunnus
            JOIN opettajat o ON k.opettaja_tunnus = o.tunnus
            JOIN tilat t ON k.tila_tunnus = t.tunnus
            WHERE k.opettaja_tunnus = ?
            ORDER BY ks.viikonpaiva, ks.alkuaika
        ");
        $stmt->execute([$teacherId]);
        $schedules = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = 'Virhe kurssien haussa: ' . $e->getMessage();
}

$opettajat = [];
$tilat = [];
try {
    if (isAdmin()) {
        $stmt = $pdo->query("SELECT tunnus, etunimi, sukunimi, aine FROM opettajat ORDER BY sukunimi, etunimi");
        $opettajat = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT tunnus, nimi, kapasiteetti FROM tilat ORDER BY nimi");
        $tilat = $stmt->fetchAll();
    } elseif (isTeacher()) {
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
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
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
        
        /* Calendar Styles */
        .calendar-container {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .calendar-table th {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            color: white;
            font-weight: 600;
            padding: 1rem;
            text-align: center;
            border: none;
            font-size: 0.9rem;
        }
        
        .calendar-table th:first-child {
            width: 100px;
        }
        
        .calendar-table td {
            padding: 0;
            border: 1px solid #e5e7eb;
            vertical-align: top;
            position: relative;
            min-height: 70px;
            height: 70px;
        }
        
        .calendar-table td:first-child {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            text-align: right;
            padding-right: 1rem;
            border-right: 2px solid #d1d5db;
            width: 100px;
        }
        
        .calendar-slot {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            transition: all 0.3s ease;
        }
        
        .calendar-slot:hover {
            background: rgba(59, 130, 246, 0.1);
        }
        
        .calendar-slot.has-schedule {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border-radius: 0.5rem;
            margin: 2px;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        .schedule-item {
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            padding: 0.25rem;
            line-height: 1.2;
        }
        
        .schedule-item .course-name {
            font-size: 0.7rem;
            margin-bottom: 0.1rem;
        }
        
        .schedule-item .time-info {
            font-size: 0.65rem;
            opacity: 0.9;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 0.75rem 0.75rem 0 0;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            color: #374151;
            background: #f3f4f6;
        }
        
        .nav-tabs .nav-link.active {
            background: white;
            color: #10b981;
            border-bottom: 3px solid #10b981;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 1.5rem 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            border-top: none;
            padding: 2rem;
        }
        
        /* Schedule Tab Improvements */
        .schedule-table {
            margin-top: 1rem;
        }
        
        .schedule-table .table {
            margin-bottom: 0;
        }
        
        .schedule-table .table th {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 0.75rem;
            border: none;
        }
        
        .schedule-table .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-color: #f3f4f6;
        }
        
        .schedule-table .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .schedule-table .table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: scale(1.01);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .day-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-block;
        }
        
        .time-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-block;
        }
        
        .course-info {
            font-weight: 600;
            color: #374151;
        }
        
        .teacher-info {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .room-info {
            color: #6b7280;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
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
                    <?php if (canEditAll()): ?>
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

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-4" id="courseTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button" role="tab">
                    <i class="fas fa-book me-2"></i>Kurssit
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
                    <i class="fas fa-calendar-week me-2"></i>Viikkoaikataulu
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar" type="button" role="tab">
                    <i class="fas fa-calendar-alt me-2"></i>Viikkokalenteri
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="courseTabContent">
            <!-- Courses Tab -->
            <div class="tab-pane fade show active" id="courses" role="tabpanel">
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

            <!-- Schedule Tab -->
            <div class="tab-pane fade" id="schedule" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-calendar-week me-2"></i>Kurssien viikkoaikataulu</h4>
                    <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                        <i class="fas fa-plus me-2"></i>Lisää aikataulu
                    </button>
                </div>
                
                <div class="table-container schedule-table">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-book me-2"></i>Kurssi</th>
                                    <th><i class="fas fa-calendar-day me-2"></i>Viikonpäivä</th>
                                    <th><i class="fas fa-clock me-2"></i>Aika</th>
                                    <th><i class="fas fa-user me-2"></i>Opettaja</th>
                                    <th><i class="fas fa-building me-2"></i>Tila</th>
                                    <th><i class="fas fa-cogs me-2"></i>Toiminnot</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($schedules)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>
                                            <h5>Ei aikatauluja</h5>
                                            <p class="mb-0">Lisää ensimmäinen aikataulu painamalla "Lisää aikataulu" -painiketta</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <tr>
                                            <td>
                                                <div class="course-info"><?php echo htmlspecialchars($schedule['kurssi_nimi']); ?></div>
                                            </td>
                                            <td>
                                                <span class="day-badge">
                                                    <?php 
                                                    $days = ['ma' => 'Maanantai', 'ti' => 'Tiistai', 'ke' => 'Keskiviikko', 'to' => 'Torstai', 'pe' => 'Perjantai'];
                                                    echo $days[$schedule['viikonpaiva']];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="time-badge">
                                                    <?php echo date('H:i', strtotime($schedule['alkuaika'])); ?> - <?php echo date('H:i', strtotime($schedule['loppuaika'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="teacher-info"><?php echo htmlspecialchars($schedule['opettaja_nimi']); ?></div>
                                            </td>
                                            <td>
                                                <div class="room-info"><?php echo htmlspecialchars($schedule['tila_nimi']); ?></div>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-warning me-1" 
                                                            onclick="editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)"
                                                            title="Muokkaa aikataulua">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Haluatko varmasti poistaa tämän aikataulun?')">
                                                        <input type="hidden" name="action" value="delete_schedule">
                                                        <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($schedule['tunnus']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Poista aikataulu">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
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

            <!-- Calendar Tab -->
            <div class="tab-pane fade" id="calendar" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4><i class="fas fa-calendar-alt me-2"></i>Viikkokalenteri</h4>
                        <p class="text-muted mb-0">Selaa viikkoja ja tarkastele aikatauluja visuaalisessa muodossa</p>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="previousWeek()" title="Edellinen viikko">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="btn btn-primary" id="currentWeekBtn">
                            <i class="fas fa-calendar-week me-2"></i>Viikko <span id="weekNumber"></span>
                        </button>
                        <button class="btn btn-outline-primary" onclick="nextWeek()" title="Seuraava viikko">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                
                <div class="calendar-container">
                    <table class="calendar-table">
                        <thead>
                            <tr>
                                <th>Aika</th>
                                <th>Ma</th>
                                <th>Ti</th>
                                <th>Ke</th>
                                <th>To</th>
                                <th>Pe</th>
                            </tr>
                        </thead>
                        <tbody id="calendarContent">
                            <!-- Calendar content will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lisää aikataulu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_schedule">
                        <div class="mb-3">
                            <label for="schedule_kurssi_tunnus" class="form-label">Kurssi</label>
                            <select class="form-select" id="schedule_kurssi_tunnus" name="kurssi_tunnus" required>
                                <option value="">Valitse kurssi</option>
                                <?php foreach ($kurssit as $kurssi): ?>
                                    <option value="<?php echo htmlspecialchars($kurssi['tunnus']); ?>">
                                        <?php echo htmlspecialchars($kurssi['nimi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="schedule_viikonpaiva" class="form-label">Viikonpäivä</label>
                            <select class="form-select" id="schedule_viikonpaiva" name="viikonpaiva" required>
                                <option value="">Valitse päivä</option>
                                <option value="ma">Maanantai</option>
                                <option value="ti">Tiistai</option>
                                <option value="ke">Keskiviikko</option>
                                <option value="to">Torstai</option>
                                <option value="pe">Perjantai</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="schedule_alkuaika" class="form-label">Alkuaika</label>
                                    <input type="time" class="form-control" id="schedule_alkuaika" name="alkuaika" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="schedule_loppuaika" class="form-label">Loppuaika</label>
                                    <input type="time" class="form-control" id="schedule_loppuaika" name="loppuaika" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-primary">Lisää aikataulu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Muokkaa aikataulua</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_schedule">
                        <input type="hidden" name="schedule_id" id="edit_schedule_id">
                        <div class="mb-3">
                            <label class="form-label">Kurssi</label>
                            <input type="text" class="form-control" id="edit_schedule_kurssi" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_schedule_viikonpaiva" class="form-label">Viikonpäivä</label>
                            <select class="form-select" id="edit_schedule_viikonpaiva" name="viikonpaiva" required>
                                <option value="ma">Maanantai</option>
                                <option value="ti">Tiistai</option>
                                <option value="ke">Keskiviikko</option>
                                <option value="to">Torstai</option>
                                <option value="pe">Perjantai</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_schedule_alkuaika" class="form-label">Alkuaika</label>
                                    <input type="time" class="form-control" id="edit_schedule_alkuaika" name="alkuaika" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_schedule_loppuaika" class="form-label">Loppuaika</label>
                                    <input type="time" class="form-control" id="edit_schedule_loppuaika" name="loppuaika" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-warning">Päivitä aikataulu</button>
                    </div>
                </form>
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

        function editSchedule(schedule) {
            document.getElementById('edit_schedule_id').value = schedule.tunnus;
            document.getElementById('edit_schedule_kurssi').value = schedule.kurssi_nimi;
            document.getElementById('edit_schedule_viikonpaiva').value = schedule.viikonpaiva;
            document.getElementById('edit_schedule_alkuaika').value = schedule.alkuaika;
            document.getElementById('edit_schedule_loppuaika').value = schedule.loppuaika;
            
            new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
        }

        // Calendar functionality
        let currentWeek = new Date();
        
        function getWeekNumber(date) {
            const firstDayOfYear = new Date(date.getFullYear(), 0, 1);
            const pastDaysOfYear = (date - firstDayOfYear) / 86400000;
            return Math.ceil((pastDaysOfYear + firstDayOfYear.getDay() + 1) / 7);
        }
        
        function getWeekDates(date) {
            const week = [];
            const start = new Date(date);
            start.setDate(date.getDate() - date.getDay() + 1); // Monday
            
            for (let i = 0; i < 5; i++) {
                const day = new Date(start);
                day.setDate(start.getDate() + i);
                week.push(day);
            }
            return week;
        }
        
        function updateCalendar() {
            const weekDates = getWeekDates(currentWeek);
            const weekNumber = getWeekNumber(currentWeek);
            
            document.getElementById('weekNumber').textContent = weekNumber;
            
            const calendarContent = document.getElementById('calendarContent');
            calendarContent.innerHTML = '';
            
            // Generate time slots from 8:00 to 16:00
            for (let hour = 8; hour < 16; hour++) {
                const timeRow = document.createElement('tr');
                
                // Time cell
                const timeCell = document.createElement('td');
                timeCell.textContent = hour + ':00';
                timeRow.appendChild(timeCell);
                
                // Day cells
                for (let day = 0; day < 5; day++) {
                    const dayCell = document.createElement('td');
                    const dayCode = ['ma', 'ti', 'ke', 'to', 'pe'][day];
                    
                    const slot = document.createElement('div');
                    slot.className = 'calendar-slot';
                    slot.dataset.day = dayCode;
                    slot.dataset.hour = hour;
                    dayCell.appendChild(slot);
                    
                    timeRow.appendChild(dayCell);
                }
                
                calendarContent.appendChild(timeRow);
            }
            
            // Load schedule data
            loadScheduleData();
        }
        
        function loadScheduleData() {
            // This would typically fetch data from the server
            // For now, we'll use the existing schedule data from PHP
            const schedules = <?php echo json_encode($schedules); ?>;
            
            schedules.forEach(schedule => {
                const startHour = schedule.alkuaika.split(':')[0];
                const endHour = schedule.loppuaika.split(':')[0];
                const startMin = schedule.alkuaika.split(':')[1];
                const endMin = schedule.loppuaika.split(':')[1];
                
                const slot = document.querySelector(`[data-day="${schedule.viikonpaiva}"][data-hour="${startHour}"]`);
                if (slot) {
                    slot.innerHTML = `
                        <div class="schedule-item">
                            <div class="course-name">${schedule.kurssi_nimi}</div>
                            <div class="time-info">${schedule.alkuaika} - ${schedule.loppuaika}</div>
                        </div>
                    `;
                    slot.classList.add('has-schedule');
                    slot.title = `${schedule.kurssi_nimi} - ${schedule.opettaja_nimi} - ${schedule.tila_nimi}`;
                }
            });
        }
        
        function previousWeek() {
            currentWeek.setDate(currentWeek.getDate() - 7);
            updateCalendar();
        }
        
        function nextWeek() {
            currentWeek.setDate(currentWeek.getDate() + 7);
            updateCalendar();
        }
        
        // Initialize calendar when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateCalendar();
        });

        function showCourseStudents(courseId, courseName) {
            document.getElementById('courseName').textContent = courseName;
            
            const url = `./get_kurssin_opiskelijat?kurssi_tunnus=${courseId}`;
            console.log('Fetching course students URL:', url);
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response URL:', response.url);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const studentsList = document.getElementById('studentsList');
                    
                    if (data.error) {
                        studentsList.innerHTML = `<p class="text-danger text-center">Virhe: ${data.error}</p>`;
                    } else if (!Array.isArray(data)) {
                        studentsList.innerHTML = '<p class="text-danger text-center">Virhe: Odottamaton vastaus palvelimelta</p>';
                    } else if (data.length > 0) {
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
