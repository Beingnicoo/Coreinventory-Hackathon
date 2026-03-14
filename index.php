<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CoreInventory - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light min-vh-100 d-flex align-items-center">
    <button class="btn btn-outline-secondary position-fixed top-0 end-0 m-3" id="darkModeToggle" type="button" style="z-index: 1050;">
        <i class="bi bi-moon-stars"></i>
    </button>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h1 class="display-4 fw-bold text-primary mb-3">
                    <i class="bi bi-box-seam"></i> CoreInventory
                </h1>
                <p class="lead text-muted mb-4">Streamline your inventory management</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="login.php" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </a>
                    <a href="register.php" class="btn btn-outline-primary btn-lg px-4">
                        <i class="bi bi-person-plus me-2"></i>Register
                    </a>
                </div>
                <p class="mt-4 text-muted small">
                    <a href="config/init_db.php">Initialize Database</a> (run once if first time)
                </p>
            </div>
        </div>
    </div>
    <script src="assets/js/app.js"></script>
</body>
</html>
