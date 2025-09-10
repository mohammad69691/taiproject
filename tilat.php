<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireAuth();

if (!canEditAll()) {
    header('Location: access_denied.php');
    exit();
}

if (!testDbConnection()) {
    header('Location: setup.php');
    exit;
}

$pdo = getDbConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';

unset($_SESSION['success_message'], $_SESSION['error_message']);

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO tilat (nimi, kapasiteetti) VALUES (?, ?)");
        $stmt->execute([$_POST['nimi'], $_POST['kapasiteetti']]);
        
        $_SESSION['success_message'] = 'Tila lisätty onnistuneesti!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error = 'Virhe tilan lisäämisessä: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM tilat WHERE tunnus = ?");
        $stmt->execute([$_POST['tunnus']]);
        
        $_SESSION['success_message'] = 'Tila poistettu onnistuneesti!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error = 'Virhe tilan poistamisessa: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update') {
    try {
        $stmt = $pdo->prepare("UPDATE tilat SET nimi = ?, kapasiteetti = ? WHERE tunnus = ?");
        $stmt->execute([$_POST['nimi'], $_POST['kapasiteetti'], $_POST['tunnus']]);
        
        $_SESSION['success_message'] = 'Tila päivitetty onnistuneesti!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error = 'Virhe tilan päivittämisessä: ' . $e->getMessage();
    }
}

$tilat = [];
try {
    $stmt = $pdo->query("
        SELECT t.*, 
               COUNT(DISTINCT k.tunnus) AS kurssien_maara,
               SUM(CASE WHEN k.tunnus IS NOT NULL THEN 
                   (SELECT COUNT(*) FROM kurssikirjautumiset kk WHERE kk.kurssi_tunnus = k.tunnus)
               ELSE 0 END) AS yhteensa_osallistujia
        FROM tilat t
        LEFT JOIN kurssit k ON t.tunnus = k.tila_tunnus
        GROUP BY t.tunnus
        ORDER BY t.nimi
    ");
    $tilat = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Virhe tilojen haussa: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tilat - Kurssienhallintajärjestelmä</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
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
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
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
        
        .badge.bg-info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%) !important;
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
                    <li class="nav-item">
                        <a class="nav-link" href="opiskelijat.php">
                            <i class="fas fa-users me-1"></i>Opiskelijat
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="opettajat.php">
                            <i class="fas fa-chalkboard-teacher me-1"></i>Opettajat
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kurssit.php">
                            <i class="fas fa-book me-1"></i>Kurssit
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="tilat.php">
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
                    <li class="nav-item">
                        <a class="nav-link" href="kirjautumiset.php">
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
                    <h1><i class="fas fa-building me-2"></i>Tilat</h1>
                    <p>Hallitse tilojen kapasiteetteja ja käyttöä</p>
                    <?php if (isLoggedIn()): ?>
                        <div class="mt-2">
                            <small class="opacity-75">
                                <i class="fas fa-user me-1"></i>Tervetuloa, <?php echo htmlspecialchars(getCurrentUserName()); ?>!
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                    <i class="fas fa-plus me-2"></i>Lisää tila
                </button>
            </div>
        </div>
    </div>

    <div class="container content-section">

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tilojen lista -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Tunnus</th>
                                <th>Nimi</th>
                                <th>Kapasiteetti</th>
                                <th>Kurssit</th>
                                <th>Käyttö</th>
                                <th>Toiminnot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tilat as $tila): ?>
                                <tr>
                                    <td><?= $tila['tunnus'] ?></td>
                                    <td><?= htmlspecialchars($tila['nimi']) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= $tila['kapasiteetti'] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $tila['kurssien_maara'] ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $osallistujia = $tila['yhteensa_osallistujia'] ?? 0;
                                        $kapasiteetti = $tila['kapasiteetti'];
                                        $kaytto_prosentti = $kapasiteetti > 0 ? round(($osallistujia / $kapasiteetti) * 100) : 0;
                                        
                                        if ($osallistujia > $kapasiteetti): ?>
                                            <span class="capacity-warning">
                                                <?= $osallistujia ?> / <?= $kapasiteetti ?>
                                                <i class="fas fa-exclamation-triangle text-danger"></i>
                                            </span>
                                        <?php elseif ($kaytto_prosentti > 80): ?>
                                            <span class="text-warning">
                                                <?= $osallistujia ?> / <?= $kapasiteetti ?> (<?= $kaytto_prosentti ?>%)
                                            </span>
                                        <?php else: ?>
                                            <span class="capacity-ok">
                                                <?= $osallistujia ?> / <?= $kapasiteetti ?> (<?= $kaytto_prosentti ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#coursesModal" 
                                                data-tila="<?= $tila['tunnus'] ?>"
                                                data-nimi="<?= htmlspecialchars($tila['nimi']) ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editRoomModal"
                                                data-tila='<?= json_encode($tila) ?>'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Haluatko varmasti poistaa tämän tilan?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tunnus" value="<?= $tila['tunnus'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        </div>
    </div>

    <!-- Lisää tila -modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lisää uusi tila</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="nimi" class="form-label">Tilan nimi</label>
                            <input type="text" class="form-control" id="nimi" name="nimi" required>
                        </div>
                        <div class="mb-3">
                            <label for="kapasiteetti" class="form-label">Kapasiteetti</label>
                            <input type="number" class="form-control" id="kapasiteetti" name="kapasiteetti" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-primary">Lisää tila</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Muokkaa tila -modal -->
    <div class="modal fade" id="editRoomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Muokkaa tilaa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="tunnus" id="edit_tunnus">
                        <div class="mb-3">
                            <label for="edit_nimi" class="form-label">Tilan nimi</label>
                            <input type="text" class="form-control" id="edit_nimi" name="nimi" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_kapasiteetti" class="form-label">Kapasiteetti</label>
                            <input type="number" class="form-control" id="edit_kapasiteetti" name="kapasiteetti" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-warning">Päivitä tila</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kurssit -modal -->
    <div class="modal fade" id="coursesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tilan kurssit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 id="roomName"></h6>
                    <div id="coursesList"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Sulje</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('editRoomModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const tila = JSON.parse(button.getAttribute('data-tila'));
            
            document.getElementById('edit_tunnus').value = tila.tunnus;
            document.getElementById('edit_nimi').value = tila.nimi;
            document.getElementById('edit_kapasiteetti').value = tila.kapasiteetti;
        });

        document.getElementById('coursesModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const tila_tunnus = button.getAttribute('data-tila');
            const nimi = button.getAttribute('data-nimi');
            
            document.getElementById('roomName').textContent = nimi;
            
            fetch(`get_tilan_kurssit.php?tila_tunnus=${tila_tunnus}`)
                .then(response => response.json())
                .then(data => {
                    const coursesList = document.getElementById('coursesList');
                    if (data.length > 0) {
                        let html = '<div class="table-responsive"><table class="table table-sm">';
                        html += '<thead><tr><th>Kurssi</th><th>Opettaja</th><th>Aloituspäivä</th><th>Loppupäivä</th><th>Osallistujat</th><th>Kapasiteetti</th></tr></thead><tbody>';
                        data.forEach(kurssi => {
                            const osallistujat = kurssi.osallistujien_maara;
                            const kapasiteetti = kurssi.kapasiteetti;
                            const warningClass = osallistujat > kapasiteetti ? 'table-danger' : '';
                            const warningIcon = osallistujat > kapasiteetti ? '<i class="fas fa-exclamation-triangle text-danger"></i>' : '';
                            
                            html += `<tr class="${warningClass}">
                                <td>${kurssi.nimi}</td>
                                <td>${kurssi.opettaja_nimi}</td>
                                <td>${new Date(kurssi.alkupaiva).toLocaleDateString('fi-FI')}</td>
                                <td>${new Date(kurssi.loppupaiva).toLocaleDateString('fi-FI')}</td>
                                <td>${osallistujat} / ${kapasiteetti} ${warningIcon}</td>
                                <td>${kapasiteetti}</td>
                            </tr>`;
                        });
                        html += '</tbody></table></div>';
                        coursesList.innerHTML = html;
                    } else {
                        document.getElementById('coursesList').innerHTML = '<p class="text-muted">Ei kursseja.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('coursesList').innerHTML = '<p class="text-danger">Virhe kurssien haussa.</p>';
                });
        });
    </script>
    <script src="assets/js/no-reload.js"></script>
</body>
</html>
