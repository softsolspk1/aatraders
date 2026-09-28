<?php
// AA TRADERS - Secure Authentication
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (Auth::login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid username or password. Please verify your credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AA TRADERS Pharmaceutical ERP</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0c4a6e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .login-header {
            background: #090d16;
            color: #fff;
            padding: 32px 24px;
            text-align: center;
            border-bottom: 2px solid #0284c7;
        }
        .login-brand-icon {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, #0284c7, #0d9488);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 800;
            margin: 0 auto 12px;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="login-brand-icon">AA</div>
        <h1 style="font-size: 22px; font-weight: 800; letter-spacing: 0.5px;">AA TRADERS</h1>
        <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Pharmaceutical Distribution Management System</p>
    </div>

    <div style="padding: 32px 28px;">
        <?php if ($error): ?>
            <div style="background: var(--danger-light); color: var(--danger); border: 1px solid #fecaca; padding: 11px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
            </div>

            <div class="form-group" style="margin-top: 16px;">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 700; margin-top: 14px;">
                Secure ERP Sign In
            </button>
        </form>

        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); text-align: center; font-size: 11.5px; color: var(--slate-500);">
            Developed by <a href="https://softsols.pk" target="_blank" rel="noopener noreferrer" style="color: var(--primary); text-decoration: none; font-weight: 600;">Softsols Pakistan</a>
        </div>
    </div>
</div>

</body>
</html>
