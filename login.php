<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ./');
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Käyttäjänimi ja salasana vaaditaan.';
    } else {
        try {
            $pdo = getDbConnection();
            
            // Check if new columns exist
            $stmt = $pdo->query("SHOW COLUMNS FROM kayttajat LIKE 'salasana_vaihdettu'");
            $has_password_fields = $stmt->rowCount() > 0;
            
            // Get user with role information
            if ($has_password_fields) {
                $stmt = $pdo->prepare("
                    SELECT k.tunnus, k.kayttajanimi, k.salasana_hash, k.rooli, k.etunimi, k.sukunimi, k.salasana_vaihdettu,
                           o.tunnus as opettaja_tunnus, op.tunnus as opiskelija_tunnus
                    FROM kayttajat k
                    LEFT JOIN opettajat o ON k.tunnus = o.kayttaja_tunnus
                    LEFT JOIN opiskelijat op ON k.tunnus = op.kayttaja_tunnus
                    WHERE k.kayttajanimi = ? AND k.aktiivinen = 1
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT k.tunnus, k.kayttajanimi, k.salasana_hash, k.rooli, k.etunimi, k.sukunimi, 1 as salasana_vaihdettu,
                           o.tunnus as opettaja_tunnus, op.tunnus as opiskelija_tunnus
                    FROM kayttajat k
                    LEFT JOIN opettajat o ON k.tunnus = o.kayttaja_tunnus
                    LEFT JOIN opiskelijat op ON k.tunnus = op.kayttaja_tunnus
                    WHERE k.kayttajanimi = ? AND k.aktiivinen = 1
                ");
            }
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['salasana_hash'])) {
                // Check if password needs to be changed
                if (!$user['salasana_vaihdettu']) {
                    // Store user info in session for password change
                    $_SESSION['temp_user_id'] = $user['tunnus'];
                    $_SESSION['temp_username'] = $user['kayttajanimi'];
                    $_SESSION['temp_user_role'] = $user['rooli'];
                    $_SESSION['temp_user_name'] = $user['etunimi'] . ' ' . $user['sukunimi'];
                    $_SESSION['temp_teacher_id'] = $user['opettaja_tunnus'];
                    $_SESSION['temp_student_id'] = $user['opiskelija_tunnus'];
                    
                    // Redirect to password change page
                    header('Location: change_password.php');
                    exit();
                }
                
                // Login successful - set session variables
                $_SESSION['user_id'] = $user['tunnus'];
                $_SESSION['username'] = $user['kayttajanimi'];
                $_SESSION['user_role'] = $user['rooli'];
                $_SESSION['user_name'] = $user['etunimi'] . ' ' . $user['sukunimi'];
                $_SESSION['last_activity'] = time();
                
                // Set role-specific IDs
                if ($user['rooli'] === 'opettaja') {
                    $_SESSION['teacher_id'] = $user['opettaja_tunnus'];
                } elseif ($user['rooli'] === 'opiskelija') {
                    $_SESSION['student_id'] = $user['opiskelija_tunnus'];
                }
                
                // Update last login time
                $updateStmt = $pdo->prepare("UPDATE kayttajat SET viimeisin_kirjautuminen = NOW() WHERE tunnus = ?");
                $updateStmt->execute([$user['tunnus']]);
                
                $success = 'Kirjautuminen onnistui! Ohjataan etusivulle...';
                
                // Redirect after short delay
                header('Refresh: 2; URL=./');
            } else {
                $error = 'Virheellinen käyttäjänimi tai salasana.';
            }
        } catch (PDOException $e) {
            $error = 'Tietokantavirhe: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjautuminen - Kurssienhallinta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" fill="rgba(255,255,255,0.1)"><circle cx="200" cy="200" r="100"/><circle cx="800" cy="300" r="150"/><circle cx="400" cy="700" r="120"/><circle cx="900" cy="800" r="80"/></svg>');
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 2rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
            animation: slideUp 0.8s ease-out;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .login-header i {
            font-size: 4rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .login-header h2 {
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #6b7280;
            font-weight: 500;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-floating .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 1rem;
            padding: 1rem 1rem 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-floating .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
            transform: translateY(-2px);
        }
        
        .form-floating label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 1rem;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .demo-credentials {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 2rem;
            font-size: 0.9rem;
            border: 1px solid #e2e8f0;
        }
        
        .demo-credentials h6 {
            color: #667eea;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .demo-credentials .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .demo-credentials .credential-item:last-child {
            border-bottom: none;
        }
        
        .demo-credentials .credential-label {
            font-weight: 500;
            color: #374151;
        }
        
        .demo-credentials .credential-value {
            font-family: 'JetBrains Mono', monospace;
            background: #667eea;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
        }
        
        .back-link {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-link:hover {
            color: white;
            transform: translateX(-5px);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-graduation-cap"></i>
            <h2>Kurssienhallinta</h2>
            <p class="text-muted">Kirjaudu sisään omalla nimelläsi</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-floating">
                <input type="text" class="form-control" id="username" name="username" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       placeholder="Käyttäjänimi" required>
                <label for="username">
                    <i class="fas fa-user me-2"></i>Käyttäjänimi
                </label>
            </div>
            
            <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Salasana" required>
                <label for="password">
                    <i class="fas fa-lock me-2"></i>Salasana
                </label>
            </div>
            
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Kirjaudu sisään
                </button>
            </div>
        </form>
        

        
  
        
        <div class="text-center mt-4">
            <a href="./" class="back-link">
                <i class="fas fa-home me-2"></i>Palaa etusivulle
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
