<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireAuth();

if (!canViewStudents()) {
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
        $opiskelijanumero = $_POST['opiskelijanumero'] ?? '';
        if (empty($opiskelijanumero)) {
            $opiskelijanumero = date('Y') . sprintf('%04d', rand(1000, 9999));
        }
        
        $stmt = $pdo->prepare("INSERT INTO opiskelijat (opiskelijanumero, etunimi, sukunimi, syntymapaiva, vuosikurssi) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$opiskelijanumero, $_POST['etunimi'], $_POST['sukunimi'], $_POST['syntymapaiva'], $_POST['vuosikurssi']]);
        $message = 'Opiskelija lisätty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe opiskelijan lisäämisessä: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM opiskelijat WHERE tunnus = ?");
        $stmt->execute([$_POST['tunnus']]);
        $message = 'Opiskelija poistettu onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe opiskelijan poistamisessa: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update') {
    try {
        $stmt = $pdo->prepare("UPDATE opiskelijat SET opiskelijanumero = ?, etunimi = ?, sukunimi = ?, syntymapaiva = ?, vuosikurssi = ? WHERE tunnus = ?");
        $stmt->execute([$_POST['opiskelijanumero'], $_POST['etunimi'], $_POST['sukunimi'], $_POST['syntymapaiva'], $_POST['vuosikurssi'], $_POST['tunnus']]);
        $message = 'Opiskelija päivitetty onnistuneesti!';
    } catch (Exception $e) {
        $error = 'Virhe opiskelijan päivittämisessä: ' . $e->getMessage();
    }
}

$opiskelijat = [];
try {
    if (isAdmin()) {
        $stmt = $pdo->query("SELECT * FROM opiskelijat ORDER BY sukunimi, etunimi");
        $opiskelijat = $stmt->fetchAll();
    } elseif (isTeacher()) {
        $teacherId = getCurrentTeacherId();
        $stmt = $pdo->prepare("
            SELECT DISTINCT o.* 
            FROM opiskelijat o
            JOIN kurssikirjautumiset kk ON o.tunnus = kk.opiskelija_tunnus
            JOIN kurssit k ON kk.kurssi_tunnus = k.tunnus
            WHERE k.opettaja_tunnus = ?
            ORDER BY o.sukunimi, o.etunimi
        ");
        $stmt->execute([$teacherId]);
        $opiskelijat = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = 'Virhe opiskelijoiden haussa: ' . $e->getMessage();
}

function getOpiskelijanKurssit($pdo, $opiskelijanumero) {
    $stmt = $pdo->prepare("
        SELECT k.nimi, k.alkupaiva, kk.kirjautumispvm
        FROM kurssikirjautumiset kk
        JOIN kurssit k ON kk.kurssi_tunnus = k.tunnus
        WHERE kk.opiskelija_tunnus = ?
        ORDER BY k.alkupaiva DESC
    ");
    $stmt->execute([$opiskelijanumero]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opiskelijat - Kurssienhallintajärjestelmä</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
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
        
        .badge.bg-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
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
                            <a class="nav-link active" href="opiskelijat.php">
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
                        <?php if (canEditAll()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="database_tools.php">
                                <i class="fas fa-database me-1"></i>Tietokanta
                            </a>
                        </li>
                    <?php endif; ?>
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
                    <h1><i class="fas fa-users me-2"></i>Opiskelijat</h1>
                    <p>Hallitse opiskelijoiden tietoja ja kurssikirjautumisia</p>
                </div>
                <?php if (canEnrollStudents()): ?>
                    <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus me-2"></i>Lisää opiskelija
                    </button>
                <?php endif; ?>
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
                Näet vain omissa kursseissasi olevat opiskelijat.
            </div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Opiskelijanumero</th>
                                <th>Nimi</th>
                                <th>Syntymäpäivä</th>
                                <th>Vuosikurssi</th>
                                <th>Toiminnot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($opiskelijat)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>Ei opiskelijoita
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($opiskelijat as $opiskelija): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($opiskelija['opiskelijanumero']); ?></td>
                                        <td><?php echo htmlspecialchars($opiskelija['etunimi'] . ' ' . $opiskelija['sukunimi']); ?></td>
                                        <td><?php echo htmlspecialchars($opiskelija['syntymapaiva']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($opiskelija['vuosikurssi']); ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-info btn-sm" 
                                                        onclick="showStudentCourses(<?php echo $opiskelija['tunnus']; ?>, '<?php echo htmlspecialchars($opiskelija['etunimi'] . ' ' . $opiskelija['sukunimi']); ?>')">
                                                    <i class="fas fa-book me-1"></i>Katso kurssit
                                                </button>
                                                
                                                <?php if (canEditAll()): ?>
                                                    <button type="button" class="btn btn-warning btn-sm" 
                                                            onclick="editStudent(<?php echo htmlspecialchars(json_encode($opiskelija)); ?>)">
                                                        <i class="fas fa-edit me-1"></i>Muokkaa
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            onclick="deleteStudent(<?php echo $opiskelija['tunnus']; ?>, '<?php echo htmlspecialchars($opiskelija['etunimi'] . ' ' . $opiskelija['sukunimi']); ?>')">
                                                        <i class="fas fa-trash me-1"></i>Poista
                                                    </button>
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

    <!-- Opiskelijan lisäys - Modal -->
    <?php if (canEnrollStudents()): ?>
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lisää uusi opiskelija</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="opiskelijanumero" class="form-label">Opiskelijanumero</label>
                            <input type="text" class="form-control" id="opiskelijanumero" name="opiskelijanumero" 
                                   placeholder="Jätä tyhjäksi automaattiselle generoinnille">
                        </div>
                        
                        <div class="mb-3">
                            <label for="etunimi" class="form-label">Etunimi</label>
                            <input type="text" class="form-control" id="etunimi" name="etunimi" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sukunimi" class="form-label">Sukunimi</label>
                            <input type="text" class="form-control" id="sukunimi" name="sukunimi" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="syntymapaiva" class="form-label">Syntymäpäivä</label>
                            <input type="date" class="form-control" id="syntymapaiva" name="syntymapaiva" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="vuosikurssi" class="form-label">Vuosikurssi</label>
                            <select class="form-select" id="vuosikurssi" name="vuosikurssi" required>
                                <option value="">Valitse vuosikurssi</option>
                                <option value="1">1. vuosikurssi</option>
                                <option value="2">2. vuosikurssi</option>
                                <option value="3">3. vuosikurssi</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-primary">Lisää opiskelija</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Opiskelijan muokkaus - Modal -->
    <?php if (canEditAll()): ?>
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Muokkaa opiskelijaa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="tunnus" id="edit_tunnus">
                        
                        <div class="mb-3">
                            <label for="edit_opiskelijanumero" class="form-label">Opiskelijanumero</label>
                            <input type="text" class="form-control" id="edit_opiskelijanumero" name="opiskelijanumero" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_etunimi" class="form-label">Etunimi</label>
                            <input type="text" class="form-control" id="edit_etunimi" name="etunimi" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_sukunimi" class="form-label">Sukunimi</label>
                            <input type="text" class="form-control" id="edit_sukunimi" name="sukunimi" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_syntymapaiva" class="form-label">Syntymäpäivä</label>
                            <input type="date" class="form-control" id="edit_syntymapaiva" name="syntymapaiva" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_vuosikurssi" class="form-label">Vuosikurssi</label>
                            <select class="form-select" id="edit_vuosikurssi" name="vuosikurssi" required>
                                <option value="1">1. vuosikurssi</option>
                                <option value="2">2. vuosikurssi</option>
                                <option value="3">3. vuosikurssi</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-primary">Tallenna muutokset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Opiskelijan kurssit - Modal -->
    <div class="modal fade" id="studentCoursesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Opiskelijan kurssit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="studentCoursesContent">
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

    <!-- Opiskelijan poisto - Modal -->
    <?php if (canEditAll()): ?>
    <div class="modal fade" id="deleteStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vahvista poisto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Oletko varma, että haluat poistaa opiskelijan <strong id="deleteStudentName"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Tämä toiminto ei ole peruttavissa!</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="tunnus" id="delete_tunnus">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-danger">Poista opiskelija</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editStudent(student) {
            document.getElementById('edit_tunnus').value = student.tunnus;
            document.getElementById('edit_opiskelijanumero').value = student.opiskelijanumero;
            document.getElementById('edit_etunimi').value = student.etunimi;
            document.getElementById('edit_sukunimi').value = student.sukunimi;
            document.getElementById('edit_syntymapaiva').value = student.syntymapaiva;
            document.getElementById('edit_vuosikurssi').value = student.vuosikurssi;
            
            new bootstrap.Modal(document.getElementById('editStudentModal')).show();
        }

        function deleteStudent(tunnus, name) {
            document.getElementById('delete_tunnus').value = tunnus;
            document.getElementById('deleteStudentName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteStudentModal')).show();
        }

        function showStudentCourses(tunnus, name) {
            console.log('showStudentCourses called with:', { tunnus, name });
            document.getElementById('studentCoursesModal').querySelector('.modal-title').textContent = name + ' - Kurssit';
            
            if (!tunnus || tunnus === 'undefined' || tunnus === '') {
                console.log('Invalid tunnus:', tunnus);
                document.getElementById('studentCoursesContent').innerHTML = 
                    '<p class="text-danger text-center">Virhe: Opiskelijan tunnus puuttuu</p>';
                new bootstrap.Modal(document.getElementById('studentCoursesModal')).show();
                return;
            }
            
            const url = `./get_opiskelijan_kurssit?tunnus=${tunnus}`;
            console.log('Fetching URL:', url);
            
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
                    let content = '';
                    
                    if (data.error) {
                        content = `<p class="text-danger text-center">Virhe: ${data.error}</p>`;
                    } else if (!Array.isArray(data)) {
                        content = '<p class="text-danger text-center">Virhe: Odottamaton vastaus palvelimelta</p>';
                    } else if (data.length === 0) {
                        content = '<p class="text-muted text-center">Opiskelijalla ei ole kurssikirjautumisia.</p>';
                    } else {
                        content = '<div class="table-responsive"><table class="table table-striped">';
                        content += '<thead><tr><th>Kurssi</th><th>Aloituspäivä</th><th>Kirjautumispäivä</th></tr></thead><tbody>';
                        data.forEach(kurssi => {
                            content += `<tr><td>${kurssi.nimi}</td><td>${kurssi.alkupaiva}</td><td>${kurssi.kirjautumispvm}</td></tr>`;
                        });
                        content += '</tbody></table></div>';
                    }
                    document.getElementById('studentCoursesContent').innerHTML = content;
                })
                .catch(error => {
                    document.getElementById('studentCoursesContent').innerHTML = 
                        '<p class="text-danger text-center">Virhe kurssien haussa: ' + error.message + '</p>';
                });
            
            new bootstrap.Modal(document.getElementById('studentCoursesModal')).show();
        }
    </script>
    <script src="assets/js/no-reload.js"></script>
</body>
</html>
