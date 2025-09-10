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
$message = '';
$error = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO opettajat (etunimi, sukunimi, aine) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['etunimi'], $_POST['sukunimi'], $_POST['aine']]);
        $message = 'Opettaja lisätty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe opettajan lisäämisessä: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM opettajat WHERE tunnus = ?");
        $stmt->execute([$_POST['tunnus']]);
        $message = 'Opettaja poistettu onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe opettajan poistamisessa: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update') {
    try {
        $stmt = $pdo->prepare("UPDATE opettajat SET etunimi = ?, sukunimi = ?, aine = ? WHERE tunnus = ?");
        $stmt->execute([$_POST['etunimi'], $_POST['sukunimi'], $_POST['aine'], $_POST['tunnus']]);
        $message = 'Opettaja päivitetty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe opettajan päivittämisessä: ' . $e->getMessage();
    }
}

$opettajat = [];
try {
    $stmt = $pdo->query("SELECT * FROM opettajat ORDER BY sukunimi, etunimi");
    $opettajat = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Virhe opettajien haussa: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opettajat - Kurssienhallintajärjestelmä</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
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
                            <a class="nav-link active" href="opettajat.php">
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
                                <li><a class="dropdown-item" href="./">
                                    <i class="fas fa-home me-2"></i>Etusivu
                                </a></li>
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
                    <h1><i class="fas fa-chalkboard-teacher me-2"></i>Opettajat</h1>
                    <p>Hallitse opettajien tietoja ja aineita</p>
                </div>
                <?php if (canEditAll()): ?>
                    <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="fas fa-plus me-2"></i>Lisää opettaja
                    </button>
                <?php endif; ?>
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

        <!-- Opettajien lista -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Tunnus</th>
                                <th>Nimi</th>
                                <th>Aine</th>
                                <th>Kurssit</th>
                                <th>Toiminnot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($opettajat as $opettaja): ?>
                                <tr>
                                    <td><?= $opettaja['tunnus'] ?></td>
                                    <td><?= htmlspecialchars($opettaja['etunimi'] . ' ' . $opettaja['sukunimi']) ?></td>
                                    <td>
                                        <span class="badge bg-success"><?= htmlspecialchars($opettaja['aine']) ?></span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#coursesModal" 
                                                data-opettaja="<?= $opettaja['tunnus'] ?>"
                                                data-nimi="<?= htmlspecialchars($opettaja['etunimi'] . ' ' . $opettaja['sukunimi']) ?>">
                                            <i class="fas fa-eye me-1"></i>Katso kurssit
                                        </button>
                                    </td>
                                    <td>
                                        <?php if (canEditAll()): ?>
                                            <button class="btn btn-sm btn-warning me-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editTeacherModal"
                                                    data-opettaja='<?= json_encode($opettaja) ?>'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Haluatko varmasti poistaa tämän opettajan?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="tunnus" value="<?= $opettaja['tunnus'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Ei oikeuksia</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        </div>
    </div>

    <!-- Lisää opettaja -modal -->
    <?php if (canEditAll()): ?>
    <div class="modal fade" id="addTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lisää uusi opettaja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="etunimi" class="form-label">Etunimi</label>
                            <input type="text" class="form-control" id="etunimi" name="etunimi" required>
                        </div>
                        <div class="mb-3">
                            <label for="sukunimi" class="form-label">Sukunimi</label>
                            <input type="text" class="form-control" id="sukunimi" name="sukunimi" required>
                        </div>
                        <div class="mb-3">
                            <label for="aine" class="form-label">Aine</label>
                            <input type="text" class="form-control" id="aine" name="aine" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-primary">Lisää opettaja</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Muokkaa opettaja -modal -->
    <?php if (canEditAll()): ?>
    <div class="modal fade" id="editTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Muokkaa opettajaa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="tunnus" id="edit_tunnus">
                        <div class="mb-3">
                            <label for="edit_etunimi" class="form-label">Etunimi</label>
                            <input type="text" class="form-control" id="edit_etunimi" name="etunimi" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_sukunimi" class="form-label">Sukunimi</label>
                            <input type="text" class="form-control" id="edit_sukunimi" name="sukunimi" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_aine" class="form-label">Aine</label>
                            <input type="text" class="form-control" id="edit_aine" name="aine" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-warning">Päivitä opettaja</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Kurssit -modal -->
    <div class="modal fade" id="coursesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Opettajan kurssit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 id="teacherName"></h6>
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
        document.getElementById('editTeacherModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const opettaja = JSON.parse(button.getAttribute('data-opettaja'));
            
            document.getElementById('edit_tunnus').value = opettaja.tunnus;
            document.getElementById('edit_etunimi').value = opettaja.etunimi;
            document.getElementById('edit_sukunimi').value = opettaja.sukunimi;
            document.getElementById('edit_aine').value = opettaja.aine;
        });

        document.getElementById('coursesModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const opettaja_tunnus = button.getAttribute('data-opettaja');
            const nimi = button.getAttribute('data-nimi');
            
            document.getElementById('teacherName').textContent = nimi;
            
            fetch(`get_opettajan_kurssit.php?opettaja_tunnus=${opettaja_tunnus}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const coursesList = document.getElementById('coursesList');
                    
                    if (data.error) {
                        coursesList.innerHTML = `<p class="text-danger text-center">Virhe: ${data.error}</p>`;
                    } else if (!Array.isArray(data)) {
                        coursesList.innerHTML = '<p class="text-danger text-center">Virhe: Odottamaton vastaus palvelimelta</p>';
                    } else if (data.length > 0) {
                        let html = '<div class="table-responsive"><table class="table table-sm">';
                        html += '<thead><tr><th>Kurssi</th><th>Aloituspäivä</th><th>Loppupäivä</th><th>Tila</th></tr></thead><tbody>';
                        data.forEach(kurssi => {
                            html += `<tr>
                                <td>${kurssi.nimi}</td>
                                <td>${new Date(kurssi.alkupaiva).toLocaleDateString('fi-FI')}</td>
                                <td>${new Date(kurssi.loppupaiva).toLocaleDateString('fi-FI')}</td>
                                <td>${kurssi.tila_nimi}</td>
                            </tr>`;
                        });
                        html += '</tbody></table></div>';
                        coursesList.innerHTML = html;
                    } else {
                        coursesList.innerHTML = '<p class="text-muted">Ei kursseja.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('coursesList').innerHTML = '<p class="text-danger">Virhe kurssien haussa: ' + error.message + '</p>';
                });
        });
    </script>
    <script src="assets/js/no-reload.js"></script>
</body>
</html>
