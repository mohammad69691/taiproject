<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Tarkistetaan autentikaatio ja oikeudet
requireAuth();

// Vain adminit voivat käyttää tietokantatyökaluja
if (!canEditAll()) {
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

// Get database info
$dbInfo = [];
$tables = [];
try {
    $dbInfo['name'] = DB_NAME;
    $dbInfo['host'] = DB_HOST;
    
    // Get tables first
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $dbInfo['table_count'] = count($tables);
    
    // Get total records
    $totalRecords = 0;
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $totalRecords += $stmt->fetchColumn();
    }
    $dbInfo['total_records'] = $totalRecords;
    
} catch (Exception $e) {
    $error = 'Virhe tietokantatietojen haussa: ' . $e->getMessage();
    $tables = []; // Ensure tables is defined even if there's an error
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tietokantatyökalut - Kurssienhallinta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
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
        
        .btn-tool {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        
        .btn-tool:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
            color: white;
        }
        
        .info-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e5e7eb;
        }
        
        .info-card h5 {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #374151;
        }
        
        .info-value {
            color: #6b7280;
            font-family: 'JetBrains Mono', monospace;
        }
        .tool-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 1.5rem;
        }
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .db-info-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="./">
                <i class="fas fa-graduation-cap me-2"></i>Kurssienhallinta
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
                            <a class="nav-link active" href="database_tools.php">
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
            <div class="text-center">
                <h1><i class="fas fa-database me-2"></i>Tietokantatyökalut</h1>
                <p>Hallinnoi tietokantaa ja viedä tiedot</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container content-section">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Database Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card db-info-card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h3><i class="fas fa-database me-2"></i><?php echo htmlspecialchars($dbInfo['name']); ?></h3>
                                <p class="mb-0">Tietokanta</p>
                            </div>
                            <div class="col-md-4">
                                <h3><i class="fas fa-table me-2"></i><?php echo $dbInfo['table_count']; ?></h3>
                                <p class="mb-0">Taulua</p>
                            </div>
                            <div class="col-md-4">
                                <h3><i class="fas fa-list me-2"></i><?php echo number_format($dbInfo['total_records']); ?></h3>
                                <p class="mb-0">Tietuetta</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tools -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <!-- Database Export Card -->
                <div class="card tool-card mb-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-download fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Vie Tietokanta</h4>
                        <p class="card-text">
                            Lataa koko tietokanta SQL-tiedostona. Sisältää kaikki taulut, tiedot ja rakenteet.
                        </p>
                        <a href="export_database.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-download me-2"></i>Lataa SQL-tiedosto
                        </a>
                    </div>
                </div>
                
                <!-- Migration Export Card -->
                <div class="card tool-card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-server fa-3x text-success"></i>
                        </div>
                        <h4 class="card-title">Siirrä Palvelimelle</h4>
                        <p class="card-text">
                            Lataa tietokanta siirtoa varten. Optimoitu uudelle palvelimelle ilman konflikteja.
                        </p>
                        <a href="export_for_migration.php" class="btn btn-success btn-lg">
                            <i class="fas fa-server me-2"></i>Siirto-tiedosto
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <!-- Instructions Card -->
                <div class="card tool-card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-info-circle fa-3x text-info"></i>
                        </div>
                        <h4 class="card-title">Ohjeet</h4>
                        <p class="card-text">
                            <strong>Siirto-tiedoston käyttö:</strong><br>
                            1. Lataa "Siirto-tiedosto" nopeampaa palvelinta varten<br>
                            2. Luo uusi tietokanta uudella palvelimella<br>
                            3. Tuo tiedosto phpMyAdmin:lla<br>
                            4. Päivitä config/database.php<br>
                            5. Lataa kaikki PHP-tiedostot
                        </p>
                        <div class="alert alert-success">
                            <small>
                                <i class="fas fa-check-circle me-1"></i>
                                Siirto-tiedosto on optimoitu uudelle palvelimelle ilman konflikteja.
                            </small>
                        </div>
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Käytä "Lataa SQL-tiedosto" vain varmuuskopiointiin.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Tables Info -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Tietokantataulut
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Taulu</th>
                                        <th>Tietueita</th>
                                        <th>Kuvaus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $tableDescriptions = [
                                        'kayttajat' => 'Käyttäjätiedot ja autentikaatio',
                                        'opettajat' => 'Opettajien tiedot',
                                        'opiskelijat' => 'Opiskelijoiden tiedot',
                                        'tilat' => 'Kurssitilat',
                                        'kurssit' => 'Kurssitiedot',
                                        'kurssikirjautumiset' => 'Kurssikirjautumiset'
                                    ];
                                    
                                    if (!empty($tables)) {
                                        foreach ($tables as $table) {
                                            try {
                                                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                                                $count = $stmt->fetchColumn();
                                                $description = $tableDescriptions[$table] ?? 'Tietokantataulu';
                                                
                                                echo "<tr>";
                                                echo "<td><strong>" . htmlspecialchars($table) . "</strong></td>";
                                                echo "<td><span class='badge bg-primary'>" . number_format($count) . "</span></td>";
                                                echo "<td>" . htmlspecialchars($description) . "</td>";
                                                echo "</tr>";
                                            } catch (Exception $e) {
                                                echo "<tr><td colspan='3'>Virhe taulun $table haussa</td></tr>";
                                            }
                                        }
                                    } else {
                                        echo "<tr><td colspan='3' class='text-center text-muted'>Ei tauluja tai virhe tietokantayhteydessä</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 Kurssienhallintajärjestelmä - Tietokantatyökalut</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent navbar from collapsing when clicking on nav links
        document.addEventListener('DOMContentLoaded', function() {
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            
            // Close navbar when clicking on a nav link (on mobile)
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                            toggle: false
                        });
                        bsCollapse.hide();
                    }
                });
            });
            
            // Prevent navbar from auto-collapsing on window resize
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth >= 992) {
                        navbarCollapse.classList.remove('show');
                    }
                }, 250);
            });
        });
    </script>
    <script src="assets/js/no-reload.js"></script>
</body>
</html>
