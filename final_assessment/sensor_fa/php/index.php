<?php
// Dashboard Interface: Renders live readings, status charts, settings panel, and device listing.
session_start();
// Initialize database connection and load registered device configurations.
include 'db_connect.php';

// Check if a POST request has been sent to perform an authentication action.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(["status" => 400, "message" => "Username and password are required."]);
        exit;
    }

    // If action is login, query user profile, verify password, and establish a session.
    if ($action === 'login') {
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                echo json_encode(["status" => 200, "message" => "Login successful."]);
                exit;
            }
        }
        echo json_encode(["status" => 401, "message" => "Invalid username or password."]);
        exit;
    }

    // If action is signup, validate parameters, verify username availability, hash password, and create account.
    if ($action === 'signup') {
        if (strlen($username) < 3) {
            echo json_encode(["status" => 400, "message" => "Username must be at least 3 characters."]);
            exit;
        }
        if (strlen($password) < 6) {
            echo json_encode(["status" => 400, "message" => "Password must be at least 6 characters."]);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(["status" => 400, "message" => "Username is already taken."]);
            exit;
        }
        $stmt->close();

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed);
        if ($stmt->execute()) {
            echo json_encode(["status" => 201, "message" => "Account created successfully! Please sign in."]);
        } else {
            echo json_encode(["status" => 500, "message" => "Failed to create user account."]);
        }
        $stmt->close();
        exit;
    }

    // If action is update_profile, change username/password for the active session.
    if ($action === 'update_profile') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["status" => 401, "message" => "Unauthorized"]);
            exit;
        }

        $new_username = trim($input['username'] ?? '');
        $new_password = $input['password'] ?? '';

        if (empty($new_username)) {
            echo json_encode(["status" => 400, "message" => "Username cannot be empty."]);
            exit;
        }
        if (strlen($new_username) < 3) {
            echo json_encode(["status" => 400, "message" => "Username must be at least 3 characters."]);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $new_username, $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(["status" => 400, "message" => "Username is already taken by another user."]);
            exit;
        }
        $stmt->close();

        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                echo json_encode(["status" => 400, "message" => "Password must be at least 6 characters."]);
                exit;
            }
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_username, $hashed, $_SESSION['user_id']);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->bind_param("si", $new_username, $_SESSION['user_id']);
        }

        if ($stmt->execute()) {
            $_SESSION['username'] = $new_username;
            echo json_encode(["status" => 200, "message" => "Profile updated successfully."]);
        } else {
            echo json_encode(["status" => 500, "message" => "Failed to update profile."]);
        }
        $stmt->close();
        exit;
    }
}

// Clear user session and redirect to the login screen on logout.
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Render the login/signup screen if no authenticated user session is detected.
if (!isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>IoT Safety Portal - Access Control</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                font-family: 'Outfit', sans-serif;
                background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                color: #f1f5f9;
                overflow: hidden;
                position: relative;
            }

            body::before {
                content: '';
                position: absolute;
                width: 300px;
                height: 300px;
                background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
                top: 10%;
                left: 15%;
                border-radius: 50%;
            }

            body::after {
                content: '';
                position: absolute;
                width: 400px;
                height: 400px;
                background: radial-gradient(circle, rgba(239, 68, 68, 0.1) 0%, transparent 70%);
                bottom: 10%;
                right: 15%;
                border-radius: 50%;
            }

            .auth-container {
                background: rgba(30, 41, 59, 0.7);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 24px;
                width: 100%;
                max-width: 420px;
                padding: 40px 32px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                z-index: 10;
                position: relative;
                transform: translateY(0);
                transition: transform 0.3s ease;
            }

            .logo-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
                border-radius: 16px;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 28px;
                box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
            }

            h1 {
                text-align: center;
                font-size: 24px;
                font-weight: 700;
                color: #fff;
                margin-bottom: 8px;
            }

            p.subtitle {
                text-align: center;
                font-size: 14px;
                color: #94a3b8;
                margin-bottom: 32px;
            }

            .tabs {
                display: flex;
                background: rgba(15, 23, 42, 0.6);
                border-radius: 12px;
                padding: 4px;
                margin-bottom: 24px;
            }

            .tab-btn {
                flex: 1;
                border: none;
                background: transparent;
                color: #94a3b8;
                padding: 10px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                border-radius: 8px;
                transition: all 0.2s ease;
            }

            .tab-btn.active {
                background: #6366f1;
                color: #fff;
                box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
            }

            .form-group {
                margin-bottom: 20px;
            }

            label {
                display: block;
                font-size: 13px;
                font-weight: 500;
                color: #cbd5e1;
                margin-bottom: 6px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            input {
                width: 100%;
                background: rgba(15, 23, 42, 0.5);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                padding: 12px 16px;
                font-family: inherit;
                font-size: 14px;
                color: #fff;
                outline: none;
                transition: border-color 0.2s ease;
            }

            input:focus {
                border-color: #6366f1;
            }

            .btn-submit {
                width: 100%;
                background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
                color: #fff;
                border: none;
                border-radius: 10px;
                padding: 14px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 8px 16px rgba(99, 102, 241, 0.2);
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .btn-submit:hover {
                transform: translateY(-1px);
                box-shadow: 0 12px 20px rgba(99, 102, 241, 0.3);
            }

            .btn-submit:active {
                transform: translateY(0);
            }

            .error-message {
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid rgba(239, 68, 68, 0.2);
                color: #f87171;
                padding: 12px;
                border-radius: 10px;
                font-size: 13px;
                margin-bottom: 20px;
                display: none;
            }

            .spinner {
                width: 18px;
                height: 18px;
                border: 2px solid rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                border-top-color: #fff;
                animation: spin 0.8s linear infinite;
                display: none;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }
        </style>
    </head>

    <body>
        <div class="auth-container">
            <div class="logo-icon">🛡️</div>
            <h1 id="auth-title">Welcome Back</h1>
            <p class="subtitle" id="auth-subtitle">Access your smart safety operations command dashboard.</p>

            <div class="tabs">
                <button class="tab-btn active" id="tab-login" onclick="setMode('login')">Sign In</button>
                <button class="tab-btn" id="tab-signup" onclick="setMode('signup')">Sign Up</button>
            </div>

            <div class="error-message" id="error-box"></div>

            <form onsubmit="handleSubmit(event)">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="username" placeholder="Enter username" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="password" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn-submit" id="btn-text">
                    <span class="spinner" id="spinner"></span>
                    <span id="submit-label">Sign In</span>
                </button>
            </form>
        </div>

        <script>
            let currentMode = 'login';

            function setMode(mode) {
                currentMode = mode;
                document.getElementById('error-box').style.display = 'none';

                const tabLogin = document.getElementById('tab-login');
                const tabSignup = document.getElementById('tab-signup');
                const title = document.getElementById('auth-title');
                const subtitle = document.getElementById('auth-subtitle');
                const submitLabel = document.getElementById('submit-label');

                if (mode === 'login') {
                    tabLogin.classList.add('active');
                    tabSignup.classList.remove('active');
                    title.textContent = 'Welcome Back';
                    subtitle.textContent = 'Access your smart safety operations command dashboard.';
                    submitLabel.textContent = 'Sign In';
                } else {
                    tabLogin.classList.remove('active');
                    tabSignup.classList.add('active');
                    title.textContent = 'Create Account';
                    subtitle.textContent = 'Register a new profile to manage and scan your IoT safety networks.';
                    submitLabel.textContent = 'Create Profile';
                }
            }

            async function handleSubmit(e) {
                e.preventDefault();
                const errorBox = document.getElementById('error-box');
                const spinner = document.getElementById('spinner');
                const submitLabel = document.getElementById('submit-label');
                const usernameVal = document.getElementById('username').value.trim();
                const passwordVal = document.getElementById('password').value;

                errorBox.style.display = 'none';
                spinner.style.display = 'inline-block';
                submitLabel.textContent = currentMode === 'login' ? 'Signing In...' : 'Registering...';

                try {
                    const response = await fetch(`index.php?action=${currentMode}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ username: usernameVal, password: passwordVal })
                    });
                    const result = await response.json();

                    if (result.status === 200) {
                        window.location.reload();
                    } else if (result.status === 201) {
                        errorBox.textContent = result.message;
                        errorBox.style.color = '#137333';
                        errorBox.style.background = '#e6f4ea';
                        errorBox.style.borderColor = '#137333';
                        errorBox.style.display = 'block';
                        spinner.style.display = 'none';
                        submitLabel.textContent = 'Create Profile';
                        setTimeout(() => {
                            setMode('login');
                            document.getElementById('password').value = '';
                        }, 2000);
                    } else {
                        errorBox.textContent = result.message;
                        errorBox.style.color = '#f87171';
                        errorBox.style.background = 'rgba(239, 68, 68, 0.1)';
                        errorBox.style.borderColor = 'rgba(239, 68, 68, 0.2)';
                        errorBox.style.display = 'block';
                        spinner.style.display = 'none';
                        submitLabel.textContent = currentMode === 'login' ? 'Sign In' : 'Create Profile';
                    }
                } catch (error) {
                    errorBox.textContent = 'Network error occurred. Please try again.';
                    errorBox.style.display = 'block';
                    spinner.style.display = 'none';
                    submitLabel.textContent = currentMode === 'login' ? 'Sign In' : 'Create Profile';
                }
            }
        </script>
    </body>

    </html>
    <?php
    exit;
}

$devices = [];
$res = $conn->query("SELECT device_id, device_ip, device_name FROM device_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $devices[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Safety Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Theme, CSS Grid Layouts, and responsive styling parameters. -->
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 14px;
            border-bottom: 2px solid #ddd;
        }

        header h1 {
            margin: 0;
            font-size: 22px;
        }

        .device-tag {
            font-size: 14px;
            color: #666;
        }

        .device-tag strong {
            color: #111;
        }

        .section-title {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #555;
            margin: 28px 0 12px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 8px;
        }

        .card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .07);
            text-align: center;
        }

        .card h3 {
            margin: 0 0 6px;
            color: #777;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .value {
            font-size: 30px;
            font-weight: 700;
            color: #111;
            margin: 6px 0;
        }

        .threshold-info {
            font-size: 11px;
            color: #777;
            margin-top: 4px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 18px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
        }

        .status-safe {
            background: #e6f4ea;
            color: #137333;
        }

        .status-warning {
            background: #fef7e0;
            color: #b06000;
        }

        .status-danger {
            background: #fce8e6;
            color: #c5221f;
        }

        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-button {
            padding: 12px 18px;
            border: none;
            border-radius: 999px;
            background: #e9eef6;
            color: #2f4f74;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s ease, color .2s ease;
        }

        .tab-button.active {
            background: #1a73e8;
            color: #fff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 8px;
        }

        @media (max-width: 720px) {
            .two-col {
                grid-template-columns: 1fr;
            }
        }

        .panel {
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .07);
        }

        .panel h2 {
            margin: 0 0 16px;
            font-size: 15px;
            font-weight: 700;
            color: #222;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .ctrl-group {
            margin-bottom: 16px;
        }

        .ctrl-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #888;
            margin-bottom: 8px;
        }

        .btn-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 9px 18px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .15s, transform .1s;
        }

        .btn:active {
            transform: scale(.97);
        }

        .btn-red {
            background: #fce8e6;
            color: #c5221f;
        }

        .btn-green {
            background: #e6f4ea;
            color: #137333;
        }

        .btn-blue {
            background: #e8f0fe;
            color: #1a73e8;
        }

        .btn-grey {
            background: #f1f3f4;
            color: #444;
        }

        .btn:hover {
            opacity: .85;
        }

        .btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        #ctrl-feedback {
            margin-top: 12px;
            font-size: 13px;
            color: #137333;
            min-height: 18px;
        }

        .field {
            margin-bottom: 14px;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .field input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #111;
            background: #fafafa;
        }

        #settings-feedback {
            font-size: 13px;
            min-height: 18px;
            margin-top: 4px;
        }

        .insight-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .insight-row:last-child {
            border-bottom: none;
        }

        .insight-row .ilabel {
            color: #666;
        }

        .insight-row .ivalue {
            font-weight: 700;
            color: #111;
        }

        .insight-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .insight-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background: #ffffff;
            border-color: #dee2e6;
        }

        .insight-card .ilabel {
            font-size: 11px;
            color: #777;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .insight-card .ivalue {
            font-size: 20px;
            font-weight: 700;
            color: #222;
        }

        .ivalue.warn {
            color: #b06000 !important;
        }

        .ivalue.danger {
            color: #c5221f !important;
        }

        .ivalue.safe {
            color: #137333 !important;
        }

        .chart-container {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .07);
            margin-bottom: 8px;
        }

        .chart-container h2 {
            margin: 0 0 14px;
            font-size: 15px;
            font-weight: 700;
            color: #222;
        }

        .table-wrap {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .07);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 11px 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        th {
            background: #343a40;
            color: #fff;
            font-size: 12px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        tr:hover td {
            background: #f9fafb;
        }


        .device-item-card {
            background: #fff;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            border-left: 5px solid #ccc;
        }

        .device-item-card.active-connected {
            background: #f8fbf9;
        }

        .device-item-details h4 {
            margin: 0 0 4px;
            font-size: 15px;
            color: #111;
        }

        .device-item-details p {
            margin: 0;
            font-size: 13px;
            color: #666;
        }

        .device-status-lbl {
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 2px;
        }


        #wifi-config-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-card {
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .modal-card h2 {
            margin: 0 0 6px;
            font-size: 18px;
        }

        .modal-subtitle {
            margin: 0 0 20px;
            font-size: 13px;
            color: #666;
        }

        .steps-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #444;
            line-height: 2.1;
        }

        .warn-box {
            background: #fef7e0;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #b06000;
        }

        .error-box {
            background: #fce8e6;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #c5221f;
        }

        .open-link {
            display: block;
            text-align: center;
            padding: 13px;
            background: #1a73e8;
            color: #fff;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            margin-bottom: 10px;
        }

        .open-link:hover {
            opacity: .9;
        }

        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 3px solid #ddd;
            border-top-color: #1a73e8;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spin-anim {
            display: inline-block;
            animation: spin 2s linear infinite;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>🛡️ Smart Safety &amp; Gas Leakage System</h1>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="device-tag" style="margin:0;">
                    Device:
                    <select id="device-select" onchange="changeSelectedDevice(this)"
                        style="padding: 4px 8px; border-radius: 6px; border: 1px solid #ccc; font-weight: 700; color: #111; background: #fff; cursor: pointer; outline: none; vertical-align: middle;">
                        <option value="" data-ip="---" data-name="">No Primary Device</option>
                        <?php foreach ($devices as $index => $d): ?>
                            <option value="<?php echo htmlspecialchars($d['device_id']); ?>"
                                data-ip="<?php echo htmlspecialchars($d['device_ip'] ?? '---'); ?>"
                                data-name="<?php echo htmlspecialchars($d['device_name'] ?? ''); ?>" <?php echo $index === 0 ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(!empty($d['device_name']) ? $d['device_name'] : $d['device_id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    | Device IP: <strong
                        id="device-ip-val"><?php echo htmlspecialchars($devices[0]['device_ip'] ?? '---'); ?></strong>
                </div>
                <div class="user-profile"
                    style="display: flex; align-items: center; gap: 8px; background: #f1f3f4; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; color: #444;">
                    <span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="index.php?action=logout"
                        style="color: #c5221f; text-decoration: none; border-left: 1px solid #ccc; padding-left: 8px; margin-left: 4px; font-weight: 700;"
                        title="Log Out">Log Out</a>
                </div>
            </div>
        </header>

        <div class="tabs">
            <button class="tab-button active" data-tab="tab-main" onclick="openTab('tab-main')">Main</button>
            <button class="tab-button" data-tab="tab-events" onclick="openTab('tab-events')">Event Log</button>
            <button class="tab-button" data-tab="tab-devices" onclick="openTab('tab-devices')">Scan Devices</button>
            <button class="tab-button" data-tab="tab-profile" onclick="openTab('tab-profile')">👤 Profile</button>
        </div>

        <!-- TAB content 1: Renders parameter cards (gas, temp, flame, motion) and dashboard status. -->
        <div id="tab-main" class="tab-content active">
            <div class="section-title">📡 Live Sensor Readings</div>
            <div class="grid">
                <div class="card">
                    <h3>Gas Level (MQ Sensor)</h3>
                    <div class="value" id="gas-val">— ppm</div>
                    <div class="threshold-info" id="gas-thresholds-info">Warn: — ppm | Danger: — ppm</div>
                </div>
                <div class="card">
                    <h3>Temperature (DHT22)</h3>
                    <div class="value" id="temp-val">— °C</div>
                    <div class="threshold-info" id="temp-threshold-info">Danger: — °C</div>
                </div>
                <div class="card">
                    <h3>Motion Detector (PIR)</h3>
                    <div class="value" id="motion-val">—</div>
                </div>
                <div class="card">
                    <h3>Flame Detector</h3>
                    <div class="value" id="flame-val">—</div>
                </div>
                <div class="card">
                    <h3>System Condition</h3>
                    <div id="status-badge-container">
                        <span class="status-badge status-safe" id="status-val">Safe</span>
                    </div>
                </div>
            </div>

            <div class="section-title">🎛️ Device Control &amp; Settings</div>
            <div class="two-col">
                <div class="panel">
                    <h2>🔊 Alarm &amp; Fan Control</h2>
                    <div class="ctrl-group">
                        <label>Alarm (Buzzer)</label>
                        <div class="btn-row">
                            <button class="btn btn-red" onclick="sendControl('alarm_on')">🔴 Sound Alarm</button>
                            <button class="btn btn-green" onclick="sendControl('alarm_off')">🟢 Stop Alarm</button>
                            <button class="btn btn-grey" onclick="sendControl('alarm_mute')">🔕 Mute Alarm</button>
                        </div>
                    </div>
                    <div class="ctrl-group">
                        <label>Ventilation Fan</label>
                        <div class="btn-row">
                            <button class="btn btn-green" onclick="sendControl('fan_on')">🌀 Fan ON</button>
                            <button class="btn btn-grey" onclick="sendControl('fan_off')">⬜ Fan OFF</button>
                        </div>
                    </div>
                    <div id="ctrl-feedback"></div>
                    <div class="ctrl-status-panel"
                        style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <h3
                            style="font-size: 15px; color: #444; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; border: none; padding: 0;">
                            <span>📊 Active Actuator States</span>
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">

                            <div class="status-indicator-card"
                                style="background: #f8f9fa; border-radius: 10px; padding: 15px; border: 1px solid #e9ecef; text-align: center;">
                                <div style="font-size: 28px; margin-bottom: 6px; height: 36px; display: flex; align-items: center; justify-content: center;"
                                    id="speaker-status-icon">🔇</div>
                                <div
                                    style="font-size: 12px; color: #777; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">
                                    Speaker (Buzzer)</div>
                                <div style="font-size: 15px; font-weight: 700; color: #333; margin-top: 4px;"
                                    id="speaker-status-lbl">Stop</div>
                            </div>


                            <div class="status-indicator-card"
                                style="background: #f8f9fa; border-radius: 10px; padding: 15px; border: 1px solid #e9ecef; text-align: center;">
                                <div style="font-size: 28px; margin-bottom: 6px; height: 36px; display: flex; align-items: center; justify-content: center;"
                                    id="fan-status-icon">⬜</div>
                                <div
                                    style="font-size: 12px; color: #777; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">
                                    Ventilation Fan</div>
                                <div style="font-size: 15px; font-weight: 700; color: #333; margin-top: 4px;"
                                    id="fan-status-lbl">OFF</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>⚙️ Device Settings &amp; Thresholds</h2>
                    <div class="field">
                        <label>Preferred Device Name</label>
                        <input type="text" id="set-device-name" placeholder="e.g. Kitchen Sensor">
                    </div>
                    <div class="field">
                        <label>Gas Warning Threshold (ppm)</label>
                        <input type="number" id="set-threshold1" placeholder="e.g. 250">
                    </div>
                    <div class="field">
                        <label>Gas Danger Threshold (ppm)</label>
                        <input type="number" id="set-threshold2-gas" placeholder="e.g. 400">
                    </div>
                    <div class="field">
                        <label>Temperature Danger Threshold (°C)</label>
                        <input type="number" id="set-threshold2-temp" placeholder="e.g. 45">
                    </div>
                    <button class="btn btn-green" style="width:100%; margin-top:4px;" onclick="saveSettings()">💾 Save
                        Settings</button>
                    <div id="settings-feedback"></div>
                </div>
            </div>

            <div class="section-title">📊 Event Analysis &amp; Insights</div>
            <div class="panel" style="margin-bottom:8px;">
                <h2>Summary of Last 20 Records</h2>
                <div
                    style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 15px;">
                    <div class="insight-card">
                        <span class="ilabel">🟢 Safe Events</span>
                        <span class="ivalue safe" id="ins-safe">—</span>
                    </div>
                    <div class="insight-card">
                        <span class="ilabel">🟡 Warning Events</span>
                        <span class="ivalue warn" id="ins-warn">—</span>
                    </div>
                    <div class="insight-card">
                        <span class="ilabel">🔴 Danger Events</span>
                        <span class="ivalue danger" id="ins-danger">—</span>
                    </div>
                    <div class="insight-card">
                        <span class="ilabel">📈 Avg Gas Level</span>
                        <span class="ivalue" id="ins-avg-gas">—</span>
                    </div>
                    <div class="insight-card">
                        <span class="ilabel">🔥 Peak Gas Reading</span>
                        <span class="ivalue" id="ins-peak-gas">—</span>
                    </div>
                    <div class="insight-card">
                        <span class="ilabel">⏱️ Peak Gas Time</span>
                        <span class="ivalue" id="ins-peak-time"
                            style="font-size: 15px; font-weight: 700; line-height: 1.4; word-break: break-all;">—</span>
                    </div>
                    <div class="insight-card">
                        <span class="ilabel">🌡️ Max Temperature</span>
                        <span class="ivalue" id="ins-peak-temp">—</span>
                    </div>
                    <div class="insight-card">
                        <span class="ilabel">🏃 Motion Events</span>
                        <span class="ivalue" id="ins-motion">—</span>
                    </div>
                    <div class="insight-card">
                        <span class="ilabel">🔥 Flame Events</span>
                        <span class="ivalue danger" id="ins-flame">—</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB content 2: Lists past event entries and shows live line charts. -->
        <div id="tab-events" class="tab-content">

            <div class="section-title">📈 Live Parameter Trends</div>
            <div class="chart-container">
                <canvas id="trendChart" height="100"></canvas>
            </div>

            <div class="section-title">📋 Historical Event Log (Last 20 Records)</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Gas (ppm)</th>
                            <th>Temp (°C)</th>
                            <th>Flame</th>
                            <th>Motion</th>
                            <th>Safety Status</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <tr>
                            <td colspan="6" style="text-align:center;color:#aaa;">Loading data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB content 3: Scan network page showing online operational devices. -->
        <div id="tab-devices" class="tab-content">
            <div class="panel"
                style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="margin:0; border:none; padding:0;">Network Scan Management</h2>
                    <p style="margin: 4px 0 0; font-size:13px; color:#666;">Queries live IoT endpoints registered across
                        your operational subnet channel.</p>
                </div>
                <button class="btn btn-blue" onclick="performRealNetworkScan()">🔄 Scan Network Now</button>
            </div>

            <div class="section-title">🟢 Active Primary Node (Your ESP32)</div>
            <div id="active-primary-node-container">
                <div class="device-item-card" style="border-left-color: #aaa;">
                    <div class="device-item-details">
                        <p>Checking network availability...</p>
                    </div>
                </div>
            </div>

            <div class="section-title">🔍 Other Registered Network Extensions</div>
            <div id="other-devices-container"></div>
        </div>

        <!-- TAB content 4: Profile management panel to update username and password. -->
        <div id="tab-profile" class="tab-content">
            <div class="panel" style="max-width: 500px; margin: 20px auto;">
                <h2>👤 Manage Profile</h2>
                <p style="font-size:13px; color:#666; margin-bottom: 20px;">View your account details and update your
                    login credentials.</p>

                <div id="profile-feedback"
                    style="font-weight:700; font-size:14px; margin-bottom:15px; display:none; padding:10px; border-radius:6px;">
                </div>

                <div class="field" style="margin-bottom:15px;">
                    <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Username</label>
                    <input type="text" id="profile-username"
                        value="<?php echo htmlspecialchars($_SESSION['username']); ?>"
                        style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc; font-weight: 500;">
                </div>

                <div class="field" style="margin-bottom:15px;">
                    <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Stored Hashed
                        Password (Encrypted)</label>
                    <?php
                    $user_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $user_stmt->bind_param("i", $_SESSION['user_id']);
                    $user_stmt->execute();
                    $user_res = $user_stmt->get_result();
                    $hash = "";
                    if ($user_res->num_rows > 0) {
                        $hash = $user_res->fetch_assoc()['password'];
                    }
                    $user_stmt->close();
                    ?>
                    <input type="text" readonly value="<?php echo htmlspecialchars($hash); ?>"
                        style="width:100%; padding:10px; border-radius:6px; border:1px solid #ddd; background:#f4f4f4; color:#777; font-family: monospace; font-size: 11px;">
                </div>

                <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">

                <div class="field" style="margin-bottom:15px;">
                    <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">New Password
                        (Optional)</label>
                    <input type="password" id="profile-new-password" placeholder="Leave blank to keep current password"
                        style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
                </div>

                <button class="btn btn-green" style="width:100%; margin-top:10px;" onclick="saveUserProfile()">💾 Save
                    Profile Changes</button>
            </div>
        </div>
    </div>

    <!-- Modal layout for configuring the remote Wi-Fi of an ESP32. -->
    <div id="wifi-config-modal">
        <div class="modal-card">

            <div id="modal-step-sending">
                <h2>📡 Configure WiFi</h2>
                <p class="modal-subtitle">Contacting <strong id="modal-device-name"></strong>...</p>
                <div style="text-align:center; padding:28px 0; color:#888; font-size:14px;">
                    <span class="spinner"></span> Sending reboot command to device...
                </div>
            </div>

            <div id="modal-step-ready" style="display:none;">
                <h2>✅ ESP32 is ready</h2>
                <p class="modal-subtitle">The device has restarted in setup mode and is broadcasting its hotspot.</p>
                <div class="steps-box">
                    <div>① On your phone/laptop, open <strong>WiFi Settings</strong></div>
                    <div>② Connect to: <strong id="modal-ap-ssid"
                            style="color:#1a73e8; font-size:14px;">ESP32-Safety-Setup</strong></div>
                    <div>③ Tap the button below to open the setup page</div>
                </div>
                <div class="warn-box">
                    ⚠️ Your internet will disconnect briefly while connected to the ESP32 hotspot. Reconnect to your
                    normal WiFi after saving the new credentials.
                </div>
                <a href="http://192.168.4.1" target="_blank" class="open-link">🌐 Open WiFi Setup Page →</a>
                <button class="btn btn-grey" style="width:100%;" onclick="closeWifiModal()">Close</button>
            </div>

            <div id="modal-step-error" style="display:none;">
                <h2>❌ Could not reach device</h2>
                <p class="modal-subtitle">The ESP32 did not respond. It may be offline or unreachable.</p>
                <div class="error-box">
                    Make sure the ESP32 is powered on and connected to the same network, then try again. You can also
                    manually reboot the ESP32 — it will enter setup mode automatically if it cannot connect to WiFi.
                </div>
                <button class="btn btn-grey" style="width:100%;" onclick="closeWifiModal()">Close</button>
            </div>

        </div>
    </div>

    <script>
        let DEVICE_ID = document.getElementById('device-select') ? document.getElementById('device-select').value : 'ESP32_SAFETY_01';
        let lastScanDevices = [];
        let trendChart;
        let latestLog = null;
        let currentSettings = null;

        function changeSelectedDevice(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            DEVICE_ID = selectElement.value;
            const deviceIp = selectedOption ? (selectedOption.getAttribute('data-ip') || '---') : '---';

            document.getElementById('device-ip-val').innerText = deviceIp;

            renderDevicesList();

            document.getElementById('settings-feedback').textContent = '';
            document.getElementById('set-device-name').value = '';
            document.getElementById('set-threshold1').value = '';
            document.getElementById('set-threshold2-gas').value = '';
            document.getElementById('set-threshold2-temp').value = '';

            fetchSettingsData();

            fetchDashboardData();
        }

        function updateDeviceDropdown(devices) {
            const select = document.getElementById('device-select');
            if (!select) return;
            const currentSelected = DEVICE_ID;

            const realOptions = Array.from(select.options).filter(opt => opt.value !== "");
            const existingValues = realOptions.map(opt => opt.value);
            const newValues = devices.map(d => d.device_id);
            const isSame = existingValues.length === newValues.length && existingValues.every((val, index) => val === newValues[index]);

            const existingNames = realOptions.map(opt => opt.textContent);
            const newNames = devices.map(d => d.device_name ? (d.device_name + " (" + d.device_id + ")") : d.device_id);
            const namesSame = existingNames.length === newNames.length && existingNames.every((val, index) => val === newNames[index]);

            if (!isSame || !namesSame) {
                select.innerHTML = '';

                const defaultOpt = document.createElement('option');
                defaultOpt.value = "";
                defaultOpt.textContent = "No Primary Device";
                defaultOpt.setAttribute('data-ip', '---');
                defaultOpt.setAttribute('data-name', '');
                if (currentSelected === "") {
                    defaultOpt.selected = true;
                }
                select.appendChild(defaultOpt);

                devices.forEach(device => {
                    const opt = document.createElement('option');
                    opt.value = device.device_id;
                    opt.textContent = device.device_name ? (device.device_name + " (" + device.device_id + ")") : device.device_id;
                    opt.setAttribute('data-ip', device.device_ip || '---');
                    opt.setAttribute('data-name', device.device_name || '');
                    if (device.device_id === currentSelected) {
                        opt.selected = true;
                    }
                    select.appendChild(opt);
                });
            } else {
                select.value = currentSelected;
            }

            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption) {
                document.getElementById('device-ip-val').innerText = selectedOption.getAttribute('data-ip') || '---';
            }
        }

        // JS: Renders network device cards into online/offline categories.
        function renderDevicesList() {
            const primaryContainer = document.getElementById('active-primary-node-container');
            const secondaryContainer = document.getElementById('other-devices-container');

            if (!primaryContainer || !secondaryContainer || lastScanDevices.length === 0) return;

            primaryContainer.innerHTML = '';
            secondaryContainer.innerHTML = '';
            let primaryCount = 0, secondaryCount = 0;

            lastScanDevices.forEach(device => {
                const isConnected = device.status === "Connected";

                const configBtn = isConnected
                    ? `<button class="btn btn-blue" onclick="triggerWifiConfig('${device.device_id}', '${device.device_ip}')">📡 Configure WiFi</button>`
                    : `<button class="btn btn-grey" disabled title="Device must be online to configure WiFi">📡 Configure WiFi</button>`;

                const renameBtn = `<button class="btn btn-green" onclick="triggerRenameDevice('${device.device_id}', '${device.device_name}')">✏️ Rename</button>`;

                const displayName = device.device_name ? `${device.device_name} (${device.device_id})` : device.device_id;

                const removeBtn = (device.device_id === DEVICE_ID)
                    ? `<button class="btn btn-red" onclick="clearPrimaryDevice()" title="Unset as Primary Node">❌ Remove</button>`
                    : '';

                const cardHtml = `
                    <div class="device-item-card ${isConnected ? 'active-connected' : ''}" style="border-left: 5px solid ${isConnected ? '#137333' : '#c5221f'}">
                        <div class="device-item-details">
                            <span class="device-status-lbl" style="color: ${isConnected ? '#137333' : '#c5221f'};">
                                ${isConnected ? '● Online (Answering)' : '○ Offline (Unreachable)'}
                            </span>
                            <h4>${displayName}</h4>
                            <p>IP Address: ${device.device_ip} ${device.wifi_ssid ? `| Connected WiFi: <strong>${device.wifi_ssid}</strong>` : ''}</p>
                        </div>
                        <div class="btn-row" style="gap: 8px;">
                            ${removeBtn}
                            ${renameBtn}
                            ${configBtn}
                        </div>
                    </div>`;

                if (device.device_id === DEVICE_ID) {
                    primaryContainer.innerHTML += cardHtml;
                    primaryCount++;
                } else {
                    secondaryContainer.innerHTML += cardHtml;
                    secondaryCount++;
                }
            });

            if (primaryCount === 0) {
                primaryContainer.innerHTML = `<p style="color:#777; font-size:13px; padding-left:10px;">Primary node target configuration profile not logged in dataset.</p>`;
            }
            if (secondaryCount === 0) {
                secondaryContainer.innerHTML = `<p style="color:#777; font-size:13px; padding-left:10px;">No external secondary hardware units configured on this instance layout yet.</p>`;
            }
        }

        function openTab(tabId) {
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.toggle('active', button.dataset.tab === tabId);
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.toggle('active', content.id === tabId);
            });
            if (tabId === 'tab-events' && trendChart) {
                trendChart.resize();
                trendChart.update();
            }
        }

        // JS: Sends override states (alarm toggle/mute, fan) to server.
        async function sendControl(action) {
            const feedback = document.getElementById('ctrl-feedback');
            feedback.style.color = '#888';
            feedback.textContent = 'Sending command...';
            const labels = {
                alarm_on: '🔴 Alarm ON', alarm_off: '🟢 Alarm OFF',
                alarm_mute: '🔕 Alarm MUTED', fan_on: '🌀 Fan ON', fan_off: '⬜ Fan OFF'
            };
            try {
                const body = new URLSearchParams({ device_id: DEVICE_ID, action: action });
                const res = await fetch('control.php', { method: 'POST', body });
                const data = await res.json();
                if (data.status === 200) {
                    feedback.style.color = '#137333';
                    feedback.textContent = labels[action] || 'Command success';
                    fetchSettingsData();
                } else {
                    feedback.style.color = '#c5221f';
                    feedback.textContent = '❌ ' + data.message;
                }
            } catch (err) {
                feedback.style.color = '#c5221f';
                feedback.textContent = '❌ Network error';
            }
            setTimeout(() => { feedback.textContent = ''; }, 4000);
        }

        // JS: Submits new settings parameters back to database.
        async function saveSettings() {
            const fb = document.getElementById('settings-feedback');
            const devName = document.getElementById('set-device-name').value.trim();
            const t1 = document.getElementById('set-threshold1').value.trim();
            const t2g = document.getElementById('set-threshold2-gas').value.trim();
            const t2t = document.getElementById('set-threshold2-temp').value.trim();
            if (!t1 && !t2g && !t2t && !devName) { fb.style.color = '#b06000'; fb.textContent = '⚠️ Enter a value.'; return; }
            fb.style.color = '#888'; fb.textContent = 'Saving…';
            try {
                const body = new URLSearchParams({ device_id: DEVICE_ID });
                if (devName !== '') body.set('device_name', devName);
                if (t1) body.set('threshold_1', t1);
                if (t2g) body.set('threshold_2', t2g);
                if (t2t) body.set('threshold_2_temp', t2t);
                const res = await fetch('update_settings.php', { method: 'POST', body });
                const data = await res.json();
                if (data.status === 200) {
                    fb.style.color = '#137333';
                    fb.textContent = '✅ Settings saved.';
                    fetchSettingsData();
                    performRealNetworkScan();
                }
            } catch (err) { fb.style.color = '#c5221f'; fb.textContent = '❌ Error saving settings.'; }
            setTimeout(() => { fb.textContent = ''; }, 5000);
        }

        function updateInsights(data) {
            if (!data || data.length === 0) return;
            let safe = 0, warn = 0, danger = 0, motion = 0, flame = 0, totalGas = 0,
                peakGas = -Infinity, peakGasTime = '—', peakTemp = -Infinity;
            data.forEach(row => {
                const s = row.status ? row.status.toLowerCase() : '';
                if (s.includes('danger')) danger++;
                else if (s.includes('warning')) warn++;
                else safe++;
                const gas = parseFloat(row.sensor_1);
                const temp = parseFloat(row.sensor_2);
                if (gas > peakGas) { peakGas = gas; peakGasTime = row.created_at; }
                if (temp > peakTemp) peakTemp = temp;
                if (parseInt(row.motion) === 1) motion++;
                if (parseInt(row.sensor_3) === 1) flame++;
                totalGas += gas;
            });
            document.getElementById('ins-safe').textContent = safe + ' / ' + data.length;
            document.getElementById('ins-warn').textContent = warn + ' / ' + data.length;
            document.getElementById('ins-danger').textContent = danger + ' / ' + data.length;
            document.getElementById('ins-peak-gas').textContent = peakGas.toFixed(1) + ' ppm';
            document.getElementById('ins-peak-time').textContent = peakGasTime;
            document.getElementById('ins-peak-temp').textContent = peakTemp.toFixed(1) + ' °C';
            document.getElementById('ins-motion').textContent = motion + ' event' + (motion !== 1 ? 's' : '');
            document.getElementById('ins-flame').textContent = flame + ' event' + (flame !== 1 ? 's' : '');
            document.getElementById('ins-avg-gas').textContent = (totalGas / data.length).toFixed(1) + ' ppm';
        }

        async function fetchSettingsData() {
            if (!DEVICE_ID) {
                const warnInfo = document.getElementById('gas-thresholds-info');
                const dangerInfo = document.getElementById('temp-threshold-info');
                const ipInfo = document.getElementById('device-ip-val');
                if (warnInfo) warnInfo.innerText = 'Warn: — ppm | Danger: — ppm';
                if (dangerInfo) dangerInfo.innerText = 'Danger: — °C';
                if (ipInfo) ipInfo.innerText = '---';

                const nameInput = document.getElementById('set-device-name');
                const t1Input = document.getElementById('set-threshold1');
                const t2GasInput = document.getElementById('set-threshold2-gas');
                const t2TempInput = document.getElementById('set-threshold2-temp');
                if (nameInput) nameInput.value = '';
                if (t1Input) t1Input.value = '';
                if (t2GasInput) t2GasInput.value = '';
                if (t2TempInput) t2TempInput.value = '';

                currentSettings = null;
                updateActuatorStatesUI();
                return;
            }
            try {
                const response = await fetch('get_settings.php?device_id=' + DEVICE_ID);
                const result = await response.json();
                if (result.status === 200) {
                    const settings = result.data;
                    document.getElementById('gas-thresholds-info').innerText =
                        `Warn: ${parseFloat(settings.threshold_1).toFixed(0)} ppm | Danger: ${parseFloat(settings.threshold_2).toFixed(0)} ppm`;
                    document.getElementById('temp-threshold-info').innerText =
                        `Danger: ${parseFloat(settings.threshold_2_temp).toFixed(1)} °C`;
                    document.getElementById('device-ip-val').innerText = settings.device_ip || '---';
                    const nameInput = document.getElementById('set-device-name');
                    const t1Input = document.getElementById('set-threshold1');
                    const t2GasInput = document.getElementById('set-threshold2-gas');
                    const t2TempInput = document.getElementById('set-threshold2-temp');
                    if (document.activeElement !== nameInput && !nameInput.value) nameInput.value = settings.device_name || '';
                    if (document.activeElement !== t1Input && !t1Input.value) t1Input.value = settings.threshold_1;
                    if (document.activeElement !== t2GasInput && !t2GasInput.value) t2GasInput.value = settings.threshold_2;
                    if (document.activeElement !== t2TempInput && !t2TempInput.value) t2TempInput.value = settings.threshold_2_temp;

                    currentSettings = settings;
                    updateActuatorStatesUI();
                }
            } catch (error) { console.error('Settings fetch error:', error); }
        }

        // JS: Evaluates threshold levels client-side to dynamically update dashboard indicators.
        function updateActuatorStatesUI() {
            if (!latestLog || !currentSettings) return;

            const gasValue = parseFloat(latestLog.sensor_1);
            const temp = parseFloat(latestLog.sensor_2);
            const flameStatus = parseInt(latestLog.sensor_3);

            const threshold_1 = parseFloat(currentSettings.threshold_1);
            const threshold_2 = parseFloat(currentSettings.threshold_2);
            const threshold_2_temp = parseFloat(currentSettings.threshold_2_temp);

            const isDanger = (gasValue > threshold_2 || temp > threshold_2_temp || flameStatus === 1);

            const isWarning = (gasValue > threshold_1 && gasValue <= threshold_2);
            const hasHazard = (isDanger || isWarning);

            const actStatus = currentSettings.actuator_status || "0|0";
            const parts = actStatus.split('|');
            const webAlarmState = parseInt(parts[0] ?? 0);
            const webFanState = parseInt(parts[1] ?? 0);

            const manualTrigger = (webAlarmState === 1);

            let buzzerOutput = false;
            if (webAlarmState === 1) {
                buzzerOutput = true;
            } else if (webAlarmState === 2) {
                buzzerOutput = false;
            } else {
                buzzerOutput = hasHazard;
            }

            let fanOutput = (isDanger || webFanState === 1 || webAlarmState === 1 || webAlarmState === 2);

            const speakerLbl = document.getElementById('speaker-status-lbl');
            const speakerIcon = document.getElementById('speaker-status-icon');
            if (speakerLbl && speakerIcon) {
                if (webAlarmState === 2) {
                    speakerLbl.textContent = "Mute";
                    speakerLbl.style.color = "#f2994a";
                    speakerIcon.textContent = "🔕";
                } else if (buzzerOutput) {
                    speakerLbl.textContent = "Sound";
                    speakerLbl.style.color = "#c5221f";
                    speakerIcon.textContent = "🔊";
                } else {
                    speakerLbl.textContent = "Stop";
                    speakerLbl.style.color = "#137333";
                    speakerIcon.textContent = "🔇";
                }
            }

            const fanLbl = document.getElementById('fan-status-lbl');
            const fanIcon = document.getElementById('fan-status-icon');
            if (fanLbl && fanIcon) {
                if (fanOutput) {
                    fanLbl.textContent = "ON";
                    fanLbl.style.color = "#137333";
                    fanIcon.textContent = "🌀";
                    fanIcon.className = "spin-anim";
                } else {
                    fanLbl.textContent = "OFF";
                    fanLbl.style.color = "#777";
                    fanIcon.textContent = "⬜";
                    fanIcon.className = "";
                }
            }
        }

        // JS: Downloads log data from get_logs.php and populates table/charts.
        async function fetchDashboardData() {
            if (!DEVICE_ID) {
                document.getElementById('gas-val').innerText = '— ppm';
                document.getElementById('temp-val').innerText = '— °C';
                document.getElementById('motion-val').innerText = '—';
                const flameEl = document.getElementById('flame-val');
                if (flameEl) flameEl.innerText = '—';
                const badge = document.getElementById('status-val');
                if (badge) {
                    badge.innerText = 'No Device';
                    badge.className = 'status-badge status-safe';
                }
                const tbody = document.getElementById('table-body');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#aaa;">No primary device selected.</td></tr>';
                }

                if (trendChart) {
                    trendChart.data.labels = [];
                    trendChart.data.datasets[0].data = [];
                    trendChart.data.datasets[1].data = [];
                    trendChart.update();
                }

                const ids = ['ins-safe', 'ins-warn', 'ins-danger', 'ins-peak-gas', 'ins-peak-time', 'ins-peak-temp', 'ins-motion', 'ins-flame', 'ins-avg-gas'];
                ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = '—';
                });

                latestLog = null;
                updateActuatorStatesUI();
                return;
            }
            try {
                const response = await fetch('get_logs.php?device_id=' + DEVICE_ID);
                const result = await response.json();
                if (result.status === 200) {
                    if (result.data.length > 0) {
                        const latest = result.data[0];
                        latestLog = latest;
                        updateActuatorStatesUI();
                        const select = document.getElementById('device-select');
                        if (select) select.value = latest.device_id;

                        document.getElementById('gas-val').innerText = parseFloat(latest.sensor_1).toFixed(1) + ' ppm';
                        document.getElementById('temp-val').innerText = parseFloat(latest.sensor_2).toFixed(1) + ' °C';
                        document.getElementById('motion-val').innerText = parseInt(latest.motion) === 1 ? '⚠️ Motion' : '🟢 Clear';

                        const flameVal = parseInt(latest.sensor_3);
                        const flameEl = document.getElementById('flame-val');
                        if (flameEl) {
                            if (flameVal === 1) {
                                flameEl.innerHTML = '<span style="color:#c5221f; font-weight:700;">🔥 Flame Detected</span>';
                            } else {
                                flameEl.innerHTML = '<span style="color:#137333; font-weight:700;">🟢 Clear</span>';
                            }
                        }

                        const badge = document.getElementById('status-val');
                        badge.innerText = latest.status;
                        badge.className = 'status-badge status-' + (
                            latest.status.toLowerCase().includes('danger') ? 'danger' :
                                latest.status.toLowerCase().includes('warning') ? 'warning' : 'safe'
                        );
                        const tbody = document.getElementById('table-body');
                        tbody.innerHTML = '';
                        result.data.forEach(row => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${row.created_at}</td>
                                <td>${parseFloat(row.sensor_1).toFixed(1)} ppm</td>
                                <td>${parseFloat(row.sensor_2).toFixed(1)} °C</td>
                                <td>${parseInt(row.sensor_3) === 1 ? '<span style="color:#c5221f; font-weight:bold;">🔥 Yes</span>' : '—'}</td>
                                <td>${parseInt(row.motion) === 1 ? '⚠️ Motion' : '—'}</td>
                                <td><span class="status-badge status-${row.status.toLowerCase().includes('danger') ? 'danger' :
                                    row.status.toLowerCase().includes('warning') ? 'warning' : 'safe'
                                }">${row.status}</span></td>`;
                            tbody.appendChild(tr);
                        });
                        const reversed = [...result.data].reverse();
                        const timestamps = reversed.map(d => d.created_at.split(' ')[1]);
                        const gasPoints = reversed.map(d => parseFloat(d.sensor_1));
                        const tempPoints = reversed.map(d => parseFloat(d.sensor_2));
                        if (trendChart) {
                            trendChart.data.labels = timestamps;
                            trendChart.data.datasets[0].data = gasPoints;
                            trendChart.data.datasets[1].data = tempPoints;
                            trendChart.update();
                        } else {
                            const ctx = document.getElementById('trendChart').getContext('2d');
                            trendChart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: timestamps,
                                    datasets: [
                                        { label: 'Gas (ppm)', data: gasPoints, borderColor: '#ff9f43', backgroundColor: 'rgba(255,159,67,.08)', tension: 0.3, fill: true, yAxisID: 'y' },
                                        { label: 'Temp (°C)', data: tempPoints, borderColor: '#ee5253', backgroundColor: 'rgba(238,82,83,.08)', tension: 0.3, fill: true, yAxisID: 'y1' }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    interaction: { mode: 'index', intersect: false },
                                    scales: {
                                        y: { type: 'linear', position: 'left', title: { display: true, text: 'Gas Level (ppm)' } },
                                        y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Temperature (°C)' } }
                                    }
                                }
                            });
                        }
                        updateInsights(result.data);
                    } else {
                        latestLog = null;
                        updateActuatorStatesUI();

                        document.getElementById('gas-val').innerText = '— ppm';
                        document.getElementById('temp-val').innerText = '— °C';
                        document.getElementById('motion-val').innerText = '—';
                        const flameEl = document.getElementById('flame-val');
                        if (flameEl) flameEl.innerText = '—';
                        const badge = document.getElementById('status-val');
                        badge.innerText = 'No Data';
                        badge.className = 'status-badge status-safe';
                        const tbody = document.getElementById('table-body');
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#aaa;">No event logs recorded for this device.</td></tr>';

                        if (trendChart) {
                            trendChart.data.labels = [];
                            trendChart.data.datasets[0].data = [];
                            trendChart.data.datasets[1].data = [];
                            trendChart.update();
                        }

                        document.getElementById('ins-safe').textContent = '—';
                        document.getElementById('ins-warn').textContent = '—';
                        document.getElementById('ins-danger').textContent = '—';
                        document.getElementById('ins-peak-gas').textContent = '—';
                        document.getElementById('ins-peak-time').textContent = '—';
                        document.getElementById('ins-peak-temp').textContent = '—';
                        document.getElementById('ins-motion').textContent = '—';
                        document.getElementById('ins-flame').textContent = '—';
                        document.getElementById('ins-avg-gas').textContent = '—';
                    }
                }
            } catch (error) { console.error('Fetch error:', error); }
        }

        // JS: Connects to local subnet to discover operating extensions.
        async function performRealNetworkScan() {
            const primaryContainer = document.getElementById('active-primary-node-container');
            const secondaryContainer = document.getElementById('other-devices-container');
            primaryContainer.innerHTML = `<div class="device-item-card"><div class="device-item-details"><p>Scanning active channel sockets...</p></div></div>`;
            secondaryContainer.innerHTML = `<div class="device-item-card"><div class="device-item-details"><p>Probing extended node frequencies...</p></div></div>`;

            try {
                const response = await fetch('scan_network.php');
                const result = await response.json();

                if (result.status === 200) {
                    lastScanDevices = result.devices;
                    updateDeviceDropdown(result.devices);
                    renderDevicesList();
                }
            } catch (error) {
                console.error("Scanning request execution failure:", error);
                primaryContainer.innerHTML = `<p style="color:#c5221f; font-size:13px;">Error communicating with scanning endpoint.</p>`;
            }
        }

        // JS: Sends remote command to reboot selected ESP32 into config mode.
        async function triggerWifiConfig(deviceId, deviceIp) {

            document.getElementById('modal-device-name').textContent = deviceId;

            let apSSID = "ESP32-Safety-Setup";
            if (deviceId) {
                let match = deviceId.match(/\d+$/);
                if (match) {
                    apSSID = "ESP32-Safety-Setup-" + match[0];
                } else {
                    apSSID = "ESP32-Safety-Setup-" + deviceId;
                }
            }
            document.getElementById('modal-ap-ssid').textContent = apSSID;

            document.getElementById('modal-step-sending').style.display = 'block';
            document.getElementById('modal-step-ready').style.display = 'none';
            document.getElementById('modal-step-error').style.display = 'none';
            document.getElementById('wifi-config-modal').style.display = 'flex';

            try {
                const body = new URLSearchParams({ device_id: deviceId, action: 'reboot_config' });
                const res = await fetch('control.php', { method: 'POST', body });
                const data = res.ok ? await res.json() : null;
                if (data && data.status === 200) {
                    showModalReady();
                } else {
                    showModalError();
                }
            } catch (e) {
                showModalError();
            }
        }

        // JS: Prompts popup dialog to rename preferred node tag.
        async function triggerRenameDevice(deviceId, currentName) {
            const displayCurrent = (currentName && currentName !== 'undefined' && currentName !== 'null') ? currentName : '';
            const newName = prompt(`Enter new preferred name for device "${deviceId}":`, displayCurrent);
            if (newName === null) return;

            try {
                const body = new URLSearchParams({ device_id: deviceId, device_name: newName.trim() });
                const res = await fetch('update_settings.php', { method: 'POST', body });
                const data = await res.json();
                if (data.status === 200) {
                    alert('✅ Device renamed successfully!');
                    performRealNetworkScan();
                    if (deviceId === DEVICE_ID) {
                        fetchSettingsData();
                    }
                } else {
                    alert('❌ Failed to rename device: ' + data.message);
                }
            } catch (err) {
                alert('❌ Network error while renaming device.');
            }
        }

        function clearPrimaryDevice() {
            const select = document.getElementById('device-select');
            if (select) {
                select.value = "";
                changeSelectedDevice(select);
            } else {
                DEVICE_ID = "";
                renderDevicesList();
            }
        }

        function showModalReady() {

            setTimeout(() => {
                document.getElementById('modal-step-sending').style.display = 'none';
                document.getElementById('modal-step-ready').style.display = 'block';
            }, 1500);
        }

        function showModalError() {
            document.getElementById('modal-step-sending').style.display = 'none';
            document.getElementById('modal-step-error').style.display = 'block';
        }

        function closeWifiModal() {
            document.getElementById('wifi-config-modal').style.display = 'none';
        }


        document.getElementById('wifi-config-modal').addEventListener('click', function (e) {
            if (e.target === this) closeWifiModal();
        });

        // JS: Sends new username and password profile details to the server.
        async function saveUserProfile() {
            const fb = document.getElementById('profile-feedback');
            if (!fb) return;

            const username = document.getElementById('profile-username').value.trim();
            const password = document.getElementById('profile-new-password').value;

            fb.style.display = 'none';

            try {
                const res = await fetch('index.php?action=update_profile', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const data = await res.json();

                if (data.status === 200) {
                    fb.style.display = 'block';
                    fb.style.background = '#e6f4ea';
                    fb.style.color = '#137333';
                    fb.textContent = '✅ ' + data.message;
                    const usernameSpans = document.querySelectorAll('.user-profile span');
                    usernameSpans.forEach(span => {
                        span.textContent = '👤 ' + username;
                    });
                    document.getElementById('profile-new-password').value = '';
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    fb.style.display = 'block';
                    fb.style.background = '#fce8e6';
                    fb.style.color = '#c5221f';
                    fb.textContent = '❌ ' + data.message;
                }
            } catch (err) {
                fb.style.display = 'block';
                fb.style.background = '#fce8e6';
                fb.style.color = '#c5221f';
                fb.textContent = '❌ Connection error.';
            }
        }

        fetchDashboardData();
        fetchSettingsData();
        // JS: Auto-polls database every 3 seconds to keep UI state updated.
        setInterval(fetchDashboardData, 3000);
        setInterval(fetchSettingsData, 5000);
        setTimeout(performRealNetworkScan, 1200);
    </script>
</body>

</html>