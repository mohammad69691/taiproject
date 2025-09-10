<?php
require_once 'config/database.php';
require_once 'config/auth.php';

requireAuth();

if (!canEditAll()) {
    header('Location: access_denied.php');
    exit();
}

$pdo = getDbConnection();
$message = '';
$error = '';
$generated_password = '';

function generateRandomPassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

function generateUsername($etunimi, $sukunimi) {
    $etunimi = strtolower(trim($etunimi));
    $sukunimi = strtolower(trim($sukunimi));
    
    $etunimi = preg_replace('/[^a-z]/', '', $etunimi);
    $sukunimi = preg_replace('/[^a-z]/', '', $sukunimi);
    
    return $etunimi . '.' . $sukunimi;
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    try {
        $pdo->beginTransaction();
        
        $username = generateUsername($_POST['etunimi'], $_POST['sukunimi']);
        $password = generateRandomPassword();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM kayttajat WHERE kayttajanimi = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetchColumn() > 0) {
            $counter = 1;
            do {
                $newUsername = $username . $counter;
                $checkStmt->execute([$newUsername]);
                $counter++;
            } while ($checkStmt->fetchColumn() > 0);
            $username = $newUsername;
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM kayttajat LIKE 'salasana_vaihdettu'");
        $has_password_fields = $stmt->rowCount() > 0;
        
        if ($has_password_fields) {
            $stmt = $pdo->prepare("
                INSERT INTO kayttajat (kayttajanimi, salasana_hash, rooli, etunimi, sukunimi, email, aktiivinen, salasana_vaihdettu, salasana_luotu) 
                VALUES (?, ?, ?, ?, ?, ?, 1, 0, NOW())
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO kayttajat (kayttajanimi, salasana_hash, rooli, etunimi, sukunimi, email, aktiivinen) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
        }
        $stmt->execute([$username, $password_hash, $_POST['rooli'], $_POST['etunimi'], $_POST['sukunimi'], $_POST['email']]);
        $user_id = $pdo->lastInsertId();
        
        if ($_POST['rooli'] === 'opettaja') {
            $stmt = $pdo->prepare("INSERT INTO opettajat (kayttaja_tunnus, etunimi, sukunimi, aine) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $_POST['etunimi'], $_POST['sukunimi'], $_POST['aine']]);
        } elseif ($_POST['rooli'] === 'opiskelija') {
            $opiskelijanumero = date('Y') . sprintf('%04d', rand(1000, 9999));
            $stmt = $pdo->prepare("INSERT INTO opiskelijat (kayttaja_tunnus, opiskelijanumero, etunimi, sukunimi, syntymapaiva, vuosikurssi) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $opiskelijanumero, $_POST['etunimi'], $_POST['sukunimi'], $_POST['syntymapaiva'], $_POST['vuosikurssi']]);
        }
        
        $pdo->commit();
        $message = 'Käyttäjä luotu onnistuneesti!';
        $generated_password = $password;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Virhe käyttäjän luomisessa: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM opettajat WHERE kayttaja_tunnus = ?");
        $stmt->execute([$_POST['user_id']]);
        
        $stmt = $pdo->prepare("DELETE FROM opiskelijat WHERE kayttaja_tunnus = ?");
        $stmt->execute([$_POST['user_id']]);
        
        $stmt = $pdo->prepare("DELETE FROM kayttajat WHERE tunnus = ?");
        $stmt->execute([$_POST['user_id']]);
        
        $pdo->commit();
        $message = 'Käyttäjä poistettu onnistuneesti!';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Virhe käyttäjän poistamisessa: ' . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'reset_password') {
    try {
        $password = generateRandomPassword();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->query("SHOW COLUMNS FROM kayttajat LIKE 'salasana_vaihdettu'");
        $has_password_fields = $stmt->rowCount() > 0;
        
        if ($has_password_fields) {
            $stmt = $pdo->prepare("UPDATE kayttajat SET salasana_hash = ?, salasana_vaihdettu = 0, salasana_luotu = NOW() WHERE tunnus = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE kayttajat SET salasana_hash = ? WHERE tunnus = ?");
        }
        $stmt->execute([$password_hash, $_POST['user_id']]);
        
        $message = 'Salasana nollattu onnistuneesti!';
        $generated_password = $password;
        
    } catch (Exception $e) {
        $error = 'Virhe salasanan nollaamisessa: ' . $e->getMessage();
    }
}

$users = [];
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM kayttajat LIKE 'salasana_vaihdettu'");
    $has_password_fields = $stmt->rowCount() > 0;
    
    if ($has_password_fields) {
        $stmt = $pdo->query("
            SELECT k.*, 
                   o.aine as opettaja_aine,
                   op.opiskelijanumero, op.vuosikurssi
            FROM kayttajat k
            LEFT JOIN opettajat o ON k.tunnus = o.kayttaja_tunnus
            LEFT JOIN opiskelijat op ON k.tunnus = op.kayttaja_tunnus
            ORDER BY k.rooli, k.sukunimi, k.etunimi
        ");
    } else {
        $stmt = $pdo->query("
            SELECT k.*, 
                   o.aine as opettaja_aine,
                   op.opiskelijanumero, op.vuosikurssi,
                   1 as salasana_vaihdettu
            FROM kayttajat k
            LEFT JOIN opettajat o ON k.tunnus = o.kayttaja_tunnus
            LEFT JOIN opiskelijat op ON k.tunnus = op.kayttaja_tunnus
            ORDER BY k.rooli, k.sukunimi, k.etunimi
        ");
    }
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Virhe käyttäjien haussa: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Käyttäjien hallinta - Kurssienhallintajärjestelmä</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
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
        
        .badge.bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        }
        .password-display {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid #e5e7eb;
            border-radius: 1rem;
            padding: 1rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.1rem;
            font-weight: bold;
            color: #ef4444;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
                    <?php if (canEditAll() || canEnrollStudents()): ?>
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
                            <a class="nav-link active" href="user_management.php">
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

    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-user-cog me-2"></i>Käyttäjien hallinta</h1>
                    <p>Hallitse käyttäjätilejä ja oikeuksia</p>
                </div>
                <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>Lisää käyttäjä
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

        <?php if ($generated_password): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-key me-2"></i><strong>Uusi salasana:</strong>
                <div class="password-display mt-2"><?php echo htmlspecialchars($generated_password); ?></div>
                <small class="text-muted">Tallenna tämä salasana turvalliseen paikkaan. Käyttäjä joutuu vaihtamaan sen ensimmäisellä kirjautumisella.</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Käyttäjänimi</th>
                                <th>Nimi</th>
                                <th>Rooli</th>
                                <th>Lisätiedot</th>
                                <th>Salasana</th>
                                <th>Viimeksi kirjautunut</th>
                                <th>Toiminnot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['kayttajanimi']); ?></td>
                                    <td><?php echo htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['rooli'] === 'admin' ? 'danger' : ($user['rooli'] === 'opettaja' ? 'success' : 'primary'); ?>">
                                            <?php echo ucfirst($user['rooli']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['rooli'] === 'opettaja' && $user['opettaja_aine']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($user['opettaja_aine']); ?></small>
                                        <?php elseif ($user['rooli'] === 'opiskelija' && $user['opiskelijanumero']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($user['opiskelijanumero']); ?> (<?php echo $user['vuosikurssi']; ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($user['salasana_vaihdettu']) && $user['salasana_vaihdettu']): ?>
                                            <span class="badge bg-success">Vaihdettu</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Vaihdettava</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['viimeisin_kirjautuminen']): ?>
                                            <small><?php echo date('d.m.Y H:i', strtotime($user['viimeisin_kirjautuminen'])); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Ei koskaan</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-warning btn-sm" 
                                                    onclick="resetPassword(<?php echo $user['tunnus']; ?>, '<?php echo htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']); ?>')">
                                                <i class="fas fa-key me-1"></i>Nollaa salasana
                                            </button>
                                            
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                    onclick="deleteUser(<?php echo $user['tunnus']; ?>, '<?php echo htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']); ?>')">
                                                <i class="fas fa-trash me-1"></i>Poista
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lisää uusi käyttäjä</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="etunimi" class="form-label">Etunimi *</label>
                                    <input type="text" class="form-control" id="etunimi" name="etunimi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sukunimi" class="form-label">Sukunimi *</label>
                                    <input type="text" class="form-control" id="sukunimi" name="sukunimi" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Sähköposti</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="rooli" class="form-label">Rooli *</label>
                            <select class="form-select" id="rooli" name="rooli" required onchange="toggleRoleFields()">
                                <option value="">Valitse rooli</option>
                                <option value="admin">Admin</option>
                                <option value="opettaja">Opettaja</option>
                                <option value="opiskelija">Opiskelija</option>
                            </select>
                        </div>
                        
                        <div id="teacherFields" style="display: none;">
                            <div class="mb-3">
                                <label for="aine" class="form-label">Aine *</label>
                                <input type="text" class="form-control" id="aine" name="aine">
                            </div>
                        </div>
                        
                        <div id="studentFields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="syntymapaiva" class="form-label">Syntymäpäivä *</label>
                                        <input type="date" class="form-control" id="syntymapaiva" name="syntymapaiva">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="vuosikurssi" class="form-label">Vuosikurssi *</label>
                                        <select class="form-select" id="vuosikurssi" name="vuosikurssi">
                                            <option value="">Valitse vuosikurssi</option>
                                            <option value="1">1. vuosikurssi</option>
                                            <option value="2">2. vuosikurssi</option>
                                            <option value="3">3. vuosikurssi</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Käyttäjälle luodaan automaattisesti käyttäjänimi ja satunnainen salasana. 
                            Käyttäjä joutuu vaihtamaan salasanan ensimmäisellä kirjautumisella.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-primary">Luo käyttäjä</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vahvista poisto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Oletko varma, että haluat poistaa käyttäjän <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Tämä toiminto ei ole peruttavissa!</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-danger">Poista käyttäjä</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nollaa salasana</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Haluatko nollata käyttäjän <strong id="resetUserName"></strong> salasanan?</p>
                    <p class="text-warning"><i class="fas fa-key me-2"></i>Käyttäjä joutuu vaihtamaan uuden salasanan seuraavalla kirjautumisella.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peruuta</button>
                        <button type="submit" class="btn btn-warning">Nollaa salasana</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleRoleFields() {
            const role = document.getElementById('rooli').value;
            const teacherFields = document.getElementById('teacherFields');
            const studentFields = document.getElementById('studentFields');
            
            teacherFields.style.display = role === 'opettaja' ? 'block' : 'none';
            studentFields.style.display = role === 'opiskelija' ? 'block' : 'none';
            
            document.getElementById('aine').required = role === 'opettaja';
            document.getElementById('syntymapaiva').required = role === 'opiskelija';
            document.getElementById('vuosikurssi').required = role === 'opiskelija';
        }

        function deleteUser(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }

        function resetPassword(userId, userName) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('resetUserName').textContent = userName;
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }
    </script>
    <script src="assets/js/no-reload.js"></script>
</body>
</html>
