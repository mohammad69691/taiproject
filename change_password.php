<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Check if user is in temp session (password change required)
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDbConnection();
$error = '';
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Uusi salasana ja vahvistus vaaditaan.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Salasanan tulee olla vähintään 8 merkkiä pitkä.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Salasanat eivät täsmää.';
    } else {
        try {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE kayttajat SET salasana_hash = ?, salasana_vaihdettu = 1 WHERE tunnus = ?");
            $stmt->execute([$password_hash, $_SESSION['temp_user_id']]);
            
            // Set proper session variables
            $_SESSION['user_id'] = $_SESSION['temp_user_id'];
            $_SESSION['username'] = $_SESSION['temp_username'];
            $_SESSION['user_role'] = $_SESSION['temp_user_role'];
            $_SESSION['user_name'] = $_SESSION['temp_user_name'] ?? 'Käyttäjä';
            $_SESSION['last_activity'] = time();
            
            // Set role-specific IDs
            if ($_SESSION['temp_user_role'] === 'opettaja') {
                $_SESSION['teacher_id'] = $_SESSION['temp_teacher_id'];
            } elseif ($_SESSION['temp_user_role'] === 'opiskelija') {
                $_SESSION['student_id'] = $_SESSION['temp_student_id'];
            }
            
            // Update last login time
            $updateStmt = $pdo->prepare("UPDATE kayttajat SET viimeisin_kirjautuminen = NOW() WHERE tunnus = ?");
            $updateStmt->execute([$_SESSION['temp_user_id']]);
            
            // Clear temp session
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_username']);
            unset($_SESSION['temp_user_role']);
            unset($_SESSION['temp_user_name']);
            unset($_SESSION['temp_teacher_id']);
            unset($_SESSION['temp_student_id']);
            
            $success = 'Salasana vaihdettu onnistuneesti! Ohjataan etusivulle...';
            header('Refresh: 2; URL=./');
            
        } catch (Exception $e) {
            $error = 'Virhe salasanan vaihtamisessa: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaihda salasana - Kurssienhallinta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 500px;
        }
        .password-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .password-header i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-change {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-change:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .password-requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        .password-requirements h6 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        .password-requirements ul {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="password-container">
        <div class="password-header">
            <i class="fas fa-key"></i>
            <h2>Vaihda salasana</h2>
            <p class="text-muted">Tervetuloa, <?php echo htmlspecialchars($_SESSION['temp_user_name'] ?? 'Käyttäjä'); ?>!</p>
            <p class="text-muted">Sinun täytyy vaihtaa salasanasi ennen jatkamista.</p>
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
            <div class="mb-3">
                <label for="new_password" class="form-label">
                    <i class="fas fa-lock me-2"></i>Uusi salasana
                </label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
            
            <div class="mb-3">
                <label for="confirm_password" class="form-label">
                    <i class="fas fa-lock me-2"></i>Vahvista uusi salasana
                </label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-change">
                    <i class="fas fa-save me-2"></i>Vaihda salasana
                </button>
            </div>
        </form>
        
        <div class="password-requirements">
            <h6><i class="fas fa-info-circle me-2"></i>Salasanan vaatimukset:</h6>
            <ul>
                <li>Vähintään 8 merkkiä pitkä</li>
                <li>Käytä vahvaa salasanaa</li>
                <li>Vältä henkilökohtaisia tietoja</li>
            </ul>
        </div>
        
        <div class="text-center mt-3">
            <a href="logout.php" class="text-decoration-none">
                <i class="fas fa-sign-out-alt me-2"></i>Kirjaudu ulos
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Salasanat eivät täsmää');
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('new_password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>
