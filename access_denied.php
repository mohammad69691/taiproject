<?php
require_once 'config/auth.php';
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pääsy estetty - Kurssienhallinta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .access-denied-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .access-denied-icon {
            font-size: 4rem;
            color: #ff6b6b;
            margin-bottom: 1rem;
        }
        .btn-back {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="access-denied-container">
        <div class="access-denied-icon">
            <i class="fas fa-ban"></i>
        </div>
        
        <h2 class="text-danger mb-3">Pääsy estetty</h2>
        
        <p class="text-muted mb-4">
            Sinulla ei ole oikeuksia tähän sivulle tai toimintoon. 
            Jos uskot tämän olevan virhe, ota yhteyttä järjestelmän ylläpitäjään.
        </p>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-user-shield me-2"></i>Nykyinen rooli</h6>
                        <p class="mb-0">
                            <?php if (isLoggedIn()): ?>
                                <strong><?php echo htmlspecialchars(getCurrentUserRole()); ?></strong>
                            <?php else: ?>
                                <em>Ei kirjautunut</em>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-user me-2"></i>Käyttäjä</h6>
                        <p class="mb-0">
                            <?php if (isLoggedIn()): ?>
                                <strong><?php echo htmlspecialchars(getCurrentUserName()); ?></strong>
                            <?php else: ?>
                                <em>Ei kirjautunut</em>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-grid gap-2">
            <a href="./" class="btn btn-primary btn-back">
                <i class="fas fa-home me-2"></i>Palaa etusivulle
            </a>
            
            <?php if (!isLoggedIn()): ?>
                <a href="login.php" class="btn btn-outline-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Kirjaudu sisään
                </a>
            <?php else: ?>
                <a href="logout.php" class="btn btn-outline-secondary">
                    <i class="fas fa-sign-out-alt me-2"></i>Kirjaudu ulos
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
