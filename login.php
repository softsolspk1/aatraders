<?php
// AA TRADERS - Login & Demo User Switcher
require_once __DIR__ . '/includes/auth.php';

// Check if switching demo user directly
if (isset($_GET['switch_user'])) {
    $targetUser = trim($_GET['switch_user']);
    if (Auth::switchDemoUser($targetUser)) {
        header('Location: index.php');
        exit;
    }
}

// If already logged in, redirect to dashboard
if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            max-width: 480px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .login-header {
            background: #090d16;
            color: #fff;
            padding: 28px 24px;
            text-align: center;
            border-bottom: 2px solid #0284c7;
        }
        .login-brand-icon {
            width: 52px;
            height: 52px;
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
        .demo-roles-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 14px;
        }
        .demo-btn {
            background: var(--slate-100);
            border: 1px solid var(--border-color);
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            text-align: left;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            color: var(--slate-800);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .demo-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary-dark);
            font-weight: 600;
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

    <div style="padding: 28px;">
        <?php if ($error): ?>
            <div style="background: var(--danger-light); color: var(--danger); border: 1px solid #fecaca; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 18px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="e.g. admin" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; font-size: 14px; font-weight: 700; margin-top: 6px;">
                Secure ERP Sign In
            </button>
        </form>

        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--slate-500); margin-bottom: 8px;">
                ⚡ Fast 1-Click Role Switcher (Evaluation)
            </div>
            <div class="demo-roles-grid">
                <a href="login.php?switch_user=admin" class="demo-btn">
                    <span>🛡️</span>
                    <div>
                        <strong>Super Admin</strong>
                        <div style="font-size: 10px; color: #64748b;">Full ERP Control</div>
                    </div>
                </a>
                <a href="login.php?switch_user=sales_mgr" class="demo-btn">
                    <span>📈</span>
                    <div>
                        <strong>Sales Manager</strong>
                        <div style="font-size: 10px; color: #64748b;">Orders & Schemes</div>
                    </div>
                </a>
                <a href="login.php?switch_user=warehouse_mgr" class="demo-btn">
                    <span>🏭</span>
                    <div>
                        <strong>Warehouse Mgr</strong>
                        <div style="font-size: 10px; color: #64748b;">FEFO & Dispatch</div>
                    </div>
                </a>
                <a href="login.php?switch_user=accounts_mgr" class="demo-btn">
                    <span>💰</span>
                    <div>
                        <strong>Accounts Mgr</strong>
                        <div style="font-size: 10px; color: #64748b;">Ledgers & Cashbook</div>
                    </div>
                </a>
                <a href="login.php?switch_user=kamran_rep" class="demo-btn">
                    <span>💼</span>
                    <div>
                        <strong>Kamran (Rep)</strong>
                        <div style="font-size: 10px; color: #64748b;">Book Orders & Visits</div>
                    </div>
                </a>
                <a href="login.php?switch_user=auditor" class="demo-btn">
                    <span>🔍</span>
                    <div>
                        <strong>Auditor</strong>
                        <div style="font-size: 10px; color: #64748b;">Audit Logs & Recall</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
