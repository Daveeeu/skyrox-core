<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hytale Authentication Demo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #00d4ff;
            --secondary: #ff6b35;
            --accent: #8b5cf6;
            --dark: #0a0a0f;
            --darker: #050507;
            --surface: #1a1a24;
            --surface-light: #252532;
            --text: #ffffff;
            --text-dim: #a0a0ab;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;

            --glow: 0 0 20px var(--primary);
            --glow-secondary: 0 0 20px var(--secondary);
            --shadow-deep: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Exo 2', sans-serif;
            background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 50%, var(--surface) 100%);
            color: var(--text);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background grid */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image:
                linear-gradient(rgba(0, 212, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 212, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: grid-flow 20s linear infinite;
            pointer-events: none;
            z-index: -1;
        }

        @keyframes grid-flow {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        /* Floating particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.6;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) translateX(0px);
                opacity: 0.3;
            }
            50% {
                transform: translateY(-20px) translateX(10px);
                opacity: 0.8;
            }
        }

        .container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .auth-panel {
            background: rgba(26, 26, 36, 0.9);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 16px;
            padding: 3rem;
            width: 100%;
            max-width: 480px;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-deep), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
            transition: all 0.3s ease;
        }

        .auth-panel::before {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: linear-gradient(45deg, var(--primary), var(--accent), var(--secondary));
            border-radius: 16px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .auth-panel:hover::before {
            opacity: 0.3;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            font-family: 'Orbitron', monospace;
            font-weight: 900;
            font-size: 2.5rem;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: var(--glow);
            margin-bottom: 0.5rem;
            animation: glow-pulse 3s ease-in-out infinite;
        }

        @keyframes glow-pulse {
            0%, 100% { filter: brightness(1); }
            50% { filter: brightness(1.2); }
        }

        .logo p {
            color: var(--text-dim);
            font-weight: 300;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .status {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            font-weight: 500;
            display: none;
            animation: slide-in 0.3s ease;
        }

        @keyframes slide-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status.info {
            background: rgba(0, 212, 255, 0.1);
            border-color: var(--primary);
            color: var(--primary);
        }

        .status.success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .status.error {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--error);
            color: var(--error);
        }

        .status.warning {
            background: rgba(245, 158, 11, 0.1);
            border-color: var(--warning);
            color: var(--warning);
        }

        .btn {
            width: 100%;
            padding: 1rem 2rem;
            border: 2px solid var(--primary);
            background: linear-gradient(45deg, rgba(0, 212, 255, 0.1), rgba(139, 92, 246, 0.1));
            color: var(--primary);
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover:before {
            left: 100%;
        }

        .btn:hover {
            background: linear-gradient(45deg, var(--primary), var(--accent));
            color: var(--dark);
            box-shadow: var(--glow);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .device-code-display {
            text-align: center;
            padding: 2rem;
            margin: 1.5rem 0;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            display: none;
        }

        .user-code {
            font-family: 'Orbitron', monospace;
            font-size: 2rem;
            font-weight: 900;
            color: var(--secondary);
            text-shadow: var(--glow-secondary);
            letter-spacing: 0.5rem;
            margin-bottom: 1rem;
            animation: code-pulse 2s ease-in-out infinite;
        }

        @keyframes code-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .verification-link {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--secondary);
            color: var(--dark);
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .verification-link:hover {
            background: var(--primary);
            transform: scale(1.05);
            box-shadow: var(--glow);
        }

        .polling-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1rem;
            color: var(--text-dim);
            font-size: 0.9rem;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(0, 212, 255, 0.3);
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .user-info {
            display: none;
            padding: 1.5rem;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            border-radius: 12px;
            margin-top: 1.5rem;
        }

        .user-info h3 {
            color: var(--success);
            margin-bottom: 1rem;
            font-family: 'Orbitron', monospace;
        }

        .user-info .user-details {
            display: grid;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .user-info .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info .detail-label {
            color: var(--text-dim);
        }

        .user-info .detail-value {
            color: var(--text);
            font-weight: 500;
        }

        .logout-btn {
            margin-top: 1rem;
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--error);
            color: var(--error);
        }

        .logout-btn:hover {
            background: var(--error);
            color: var(--text);
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .auth-panel {
                padding: 2rem;
                margin: 1rem;
            }

            .logo h1 {
                font-size: 2rem;
            }

            .user-code {
                font-size: 1.5rem;
                letter-spacing: 0.25rem;
            }
        }

        /* Debug panel */
        .debug-panel {
            position: fixed;
            bottom: 1rem;
            left: 1rem;
            background: rgba(0, 0, 0, 0.8);
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: var(--primary);
            max-width: 400px;
            display: none;
            border: 1px solid rgba(0, 212, 255, 0.3);
        }

        .debug-toggle {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            background: var(--surface);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 0.5rem;
            border-radius: 50%;
            cursor: pointer;
            font-family: 'Orbitron', monospace;
            font-size: 0.8rem;
        }
    </style>
</head>

<body>
<!-- Floating particles -->
<div class="particles"></div>

<div class="container">
    <div class="auth-panel">
        <div class="logo">
            <h1>HYTALE</h1>
            <p>OAuth2 Device Flow Authentication</p>
        </div>

        <div id="status" class="status"></div>

        <!-- Initial login button -->
        <div id="login-section">
            <button id="login-btn" class="btn">
                🎮 Start Authentication
            </button>
        </div>

        <!-- Device code display -->
        <div id="device-code-section" class="device-code-display">
            <p style="margin-bottom: 1rem; color: var(--text-dim);">
                Visit the link below and enter this code:
            </p>
            <div id="user-code" class="user-code">----</div>
            <a id="verification-link" href="#" class="verification-link" target="_blank">
                Open Hytale Authentication
            </a>
            <div class="polling-status">
                <div class="spinner"></div>
                <span>Waiting for authorization...</span>
            </div>
        </div>

        <!-- User info display -->
        <div id="user-info" class="user-info">
            <h3>🎉 Authentication Successful!</h3>
            <div id="user-details" class="user-details">
                <!-- User details will be populated here -->
            </div>
            <button id="logout-btn" class="btn logout-btn">
                Logout
            </button>
        </div>
    </div>
</div>

<!-- Debug panel -->
<div id="debug-panel" class="debug-panel">
    <strong>Debug Info:</strong><br>
    <div id="debug-content"></div>
</div>
<button class="debug-toggle" onclick="toggleDebug()">🔧</button>

<script>
    // Configuration
    const API_BASE = 'https://core.skyrox.hu/api/v1';

    // State management
    let authState = {
        deviceCode: null,
        userCode: null,
        polling: false,
        pollInterval: null,
        accessToken: localStorage.getItem('hytale_access_token'),
        refreshToken: localStorage.getItem('hytale_refresh_token')
    };

    // DOM elements
    const elements = {
        status: document.getElementById('status'),
        loginSection: document.getElementById('login-section'),
        loginBtn: document.getElementById('login-btn'),
        deviceCodeSection: document.getElementById('device-code-section'),
        userCodeDisplay: document.getElementById('user-code'),
        verificationLink: document.getElementById('verification-link'),
        userInfoSection: document.getElementById('user-info'),
        userDetails: document.getElementById('user-details'),
        logoutBtn: document.getElementById('logout-btn'),
        debugPanel: document.getElementById('debug-panel'),
        debugContent: document.getElementById('debug-content')
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        createParticles();

        // Check if user already has valid token
        if (authState.accessToken) {
            validateExistingToken();
        }

        // Event listeners
        elements.loginBtn.addEventListener('click', startDeviceFlow);
        elements.logoutBtn.addEventListener('click', logout);
    });

    // Create floating particles effect
    function createParticles() {
        const particlesContainer = document.querySelector('.particles');
        for (let i = 0; i < 20; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 6 + 's';
            particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
            particlesContainer.appendChild(particle);
        }
    }

    // Show status message
    function showStatus(message, type = 'info') {
        elements.status.textContent = message;
        elements.status.className = `status ${type}`;
        elements.status.style.display = 'block';

        debug(`Status [${type}]: ${message}`);

        // Auto-hide after 5 seconds for non-error messages
        if (type !== 'error') {
            setTimeout(() => {
                elements.status.style.display = 'none';
            }, 5000);
        }
    }

    // Debug logging
    function debug(message) {
        console.log(`[Hytale Auth] ${message}`);
        elements.debugContent.innerHTML = `${new Date().toLocaleTimeString()}: ${message}<br>` + elements.debugContent.innerHTML;
    }

    // Toggle debug panel
    function toggleDebug() {
        const panel = elements.debugPanel;
        panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
    }

    // Validate existing token
    async function validateExistingToken() {
        try {
            showStatus('Validating existing session...', 'info');

            const response = await fetch(`${API_BASE}/auth/me`, {
                headers: {
                    'Authorization': `Bearer ${authState.accessToken}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    showUserInfo(result.user);
                    return;
                }
            }

            // Token invalid, clear and show login
            clearTokens();
            showLoginSection();

        } catch (error) {
            debug(`Token validation error: ${error.message}`);
            clearTokens();
            showLoginSection();
        }
    }

    // Start Device Code Flow
    async function startDeviceFlow() {
        try {
            elements.loginBtn.disabled = true;
            elements.loginBtn.textContent = '🔄 Initiating...';

            showStatus('Starting Hytale authentication...', 'info');
            debug('Initiating Device Code Flow');

            const response = await fetch(`${API_BASE}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            debug(`Device Flow Response: ${JSON.stringify(result)}`);

            if (!result.success) {
                throw new Error(result.error || 'Authentication initiation failed');
            }

            // Store device code info
            authState.deviceCode = result.device_code;
            authState.userCode = result.user_code;

            // Show device code UI
            showDeviceCode(result);

            // Start polling
            startPolling();

        } catch (error) {
            showStatus(`Error: ${error.message}`, 'error');
            debug(`Device Flow Error: ${error.message}`);
            resetLoginButton();
        }
    }

    // Show device code display
    function showDeviceCode(deviceData) {
        elements.userCodeDisplay.textContent = deviceData.user_code;
        elements.verificationLink.href = deviceData.verification_uri_complete || deviceData.verification_uri;

        elements.loginSection.style.display = 'none';
        elements.deviceCodeSection.style.display = 'block';

        showStatus('Please complete authentication in the browser window', 'info');
        debug(`Device code displayed: ${deviceData.user_code}`);
    }

    // Start polling for token
    function startPolling() {
        authState.polling = true;
        debug('Started polling for authorization');

        authState.pollInterval = setInterval(async () => {
            try {
                const response = await fetch(`${API_BASE}/auth/poll`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        device_code: authState.deviceCode,
                        user_code: authState.userCode
                    })
                });

                const result = await response.json();
                debug(`Poll response [${response.status}]: ${JSON.stringify(result)}`);

                if (response.status === 200 && result.success) {
                    // Authentication successful
                    stopPolling();
                    handleAuthSuccess(result);

                } else if (response.status === 202) {
                    // Still waiting - continue polling
                    debug('Still waiting for user authorization...');

                } else if (response.status === 429) {
                    // Slow down
                    debug('Polling too fast, slowing down...');

                } else if (response.status === 410) {
                    // Expired
                    stopPolling();
                    showStatus('Authentication expired. Please try again.', 'error');
                    resetToLogin();

                } else if (response.status === 403) {
                    // Denied
                    stopPolling();
                    showStatus('Authentication was denied.', 'error');
                    resetToLogin();

                } else {
                    // Other error
                    stopPolling();
                    showStatus(`Authentication error: ${result.error}`, 'error');
                    resetToLogin();
                }

            } catch (error) {
                debug(`Poll error: ${error.message}`);
                stopPolling();
                showStatus('Network error during authentication', 'error');
                resetToLogin();
            }
        }, 5000); // Poll every 5 seconds
    }

    // Stop polling
    function stopPolling() {
        if (authState.pollInterval) {
            clearInterval(authState.pollInterval);
            authState.pollInterval = null;
        }
        authState.polling = false;
        debug('Stopped polling');
    }

    // Handle successful authentication
    function handleAuthSuccess(result) {
        debug('Authentication successful!');

        // Store tokens
        authState.accessToken = result.tokens.access_token;
        authState.refreshToken = result.tokens.refresh_token;
        localStorage.setItem('hytale_access_token', authState.accessToken);
        localStorage.setItem('hytale_refresh_token', authState.refreshToken);

        // Show user info
        showUserInfo(result.user, result.hytale);

        showStatus('Authentication successful! Welcome to Hytale!', 'success');
    }

    // Show user information
    function showUserInfo(user, hytaleData = null) {
        elements.deviceCodeSection.style.display = 'none';
        elements.loginSection.style.display = 'none';
        elements.userInfoSection.style.display = 'block';

        // Populate user details
        const details = [
            { label: 'Username', value: user.username || 'N/A' },
            { label: 'Hytale UUID', value: user.hytale_uuid || 'N/A' },
            { label: 'Player ID', value: user.hytale_player_id || user.id },
            { label: 'Display Name', value: user.display_name || user.username },
            { label: 'Verified', value: user.is_verified ? '✅ Yes' : '❌ No' },
            { label: 'Online', value: user.is_online ? '🟢 Online' : '⚪ Offline' },
            { label: 'Login Count', value: user.login_count || 0 }
        ];

        if (hytaleData) {
            details.push({ label: 'Owner UUID', value: hytaleData.owner_uuid });
            details.push({ label: 'Profiles', value: hytaleData.profiles?.length || 0 });
        }

        elements.userDetails.innerHTML = details.map(detail => `
                <div class="detail-row">
                    <span class="detail-label">${detail.label}:</span>
                    <span class="detail-value">${detail.value}</span>
                </div>
            `).join('');

        debug(`User info displayed for: ${user.username}`);
    }

    // Logout
    async function logout() {
        try {
            showStatus('Logging out...', 'info');
            debug('Starting logout process');

            // Call logout endpoint
            if (authState.accessToken) {
                await fetch(`${API_BASE}/auth/logout`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${authState.accessToken}`,
                        'Content-Type': 'application/json'
                    }
                });
            }

            clearTokens();
            resetToLogin();
            showStatus('Logged out successfully', 'success');
            debug('Logout completed');

        } catch (error) {
            debug(`Logout error: ${error.message}`);
            // Force logout even if API call fails
            clearTokens();
            resetToLogin();
            showStatus('Logged out (with errors)', 'warning');
        }
    }

    // Clear stored tokens
    function clearTokens() {
        authState.accessToken = null;
        authState.refreshToken = null;
        localStorage.removeItem('hytale_access_token');
        localStorage.removeItem('hytale_refresh_token');
    }

    // Reset to login state
    function resetToLogin() {
        stopPolling();
        showLoginSection();
        resetLoginButton();
    }

    // Show login section
    function showLoginSection() {
        elements.loginSection.style.display = 'block';
        elements.deviceCodeSection.style.display = 'none';
        elements.userInfoSection.style.display = 'none';
    }

    // Reset login button
    function resetLoginButton() {
        elements.loginBtn.disabled = false;
        elements.loginBtn.textContent = '🎮 Start Authentication';
    }

    // Health check on load
    fetch(`${API_BASE}/auth/health`)
        .then(response => response.json())
        .then(result => {
            if (result.status === 'healthy') {
                debug('Health check passed - API is ready');
            } else {
                debug('Health check warning - API may have issues');
            }
        })
        .catch(error => {
            debug(`Health check failed: ${error.message}`);
            showStatus('⚠️ API connection issue - check if Laravel server is running', 'warning');
        });

</script>
</body>
</html>
