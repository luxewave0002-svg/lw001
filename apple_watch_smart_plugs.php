<?php
require_once 'db.php';

// Logout handling for watch
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: apple_watch_login.php");
    exit;
}

requireLogin($pdo, 'apple_watch_login.php');

$csrfToken = generateCsrfToken();
$userId = $_SESSION['user_id'];

// Fetch user devices
$stmt = $pdo->prepare("SELECT * FROM devices WHERE user_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$userId]);
$userDevices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Smart Plugs - Watch</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 10px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .header {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        h1 {
            font-size: 18px;
            font-weight: 300;
            letter-spacing: 1px;
            margin: 0;
        }
        .logout-link {
            color: #007aff;
            text-decoration: none;
            font-size: 12px;
        }
        .device-list {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .device-card {
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        .device-name {
            font-size: 16px;
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .device-id {
            font-size: 10px;
            color: #888;
        }
        .toggle-container {
            display: flex;
            align-items: center;
            justify-content: flex-end; /* Align to right */
            width: 100%;
            margin-top: 5px;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #555;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #007aff;
        }
        input:focus + .slider {
            box-shadow: 0 0 1px #007aff;
        }
        input:checked + .slider:before {
            -webkit-transform: translateX(16px);
            -ms-transform: translateX(16px);
            transform: translateX(16px);
        }
        .status-text {
            font-size: 10px;
            margin-top: 5px;
            text-align: right;
            width: 100%;
            color: #888;
        }
        .status-text.processing { color: #ffcc00; } /* Yellow */
        .status-text.success { color: #34c759; } /* Green */
        .status-text.error { color: #ff3b30; } /* Red */
        .no-devices {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-top: 30px;
        }
        .add-device-link {
            display: block;
            margin-top: 20px;
            color: #007aff;
            text-decoration: none;
            font-size: 12px;
        }
        .refresh-button {
            background-color: #333;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            font-size: 12px;
            cursor: pointer;
            margin-top: 20px;
            -webkit-appearance: none;
        }
        .refresh-button:hover {
            background-color: #555;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Smart Plugs</h1>
        <a href="?logout=1" class="logout-link">Logout</a>
    </div>

    <div class="device-list">
        <?php if (empty($userDevices)): ?>
            <p class="no-devices">No devices registered.</p>
            <a href="add_device.php" class="add-device-link">Add New Device (use phone/PC)</a>
        <?php else: ?>
            <?php foreach ($userDevices as $device): ?>
                <div class="device-card">
                    <div class="device-name">
                        <span style="color: <?php echo htmlspecialchars($device['color'] ?? '#fff'); ?>;"><?php echo htmlspecialchars($device['icon'] ?? '🔌'); ?></span>
                        <?php echo htmlspecialchars($device['device_name']); ?>
                    </div>
                    <p class="device-id">ID: <?php echo htmlspecialchars($device['device_id']); ?></p>
                    <div class="toggle-container">
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggleSwitchBot_<?php echo $device['device_id']; ?>"
                                onchange="toggleSwitchBotPlug('<?php echo $device['device_id']; ?>', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <p id="switchbot-status-<?php echo $device['device_id']; ?>" class="status-text">Ready</p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button class="refresh-button" onclick="checkDevicesStatus()">Refresh Status</button>

    <script>
        let plugIntendedStates = {}; // To keep track of the intended state from the watch UI

        function toggleSwitchBotPlug(deviceId, isTurnOn) {
            plugIntendedStates[deviceId] = isTurnOn;
            const statusText = document.getElementById('switchbot-status-' + deviceId);
            const command = isTurnOn ? 'turnOn' : 'turnOff';
            
            if (statusText) {
                statusText.textContent = 'Processing...';
                statusText.className = 'status-text processing';
            }

            fetch('user_switchbot_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                },
                body: JSON.stringify({ action: command, device_id: deviceId })
            })
            .then(response => response.json())
            .then(data => {
                if (statusText) {
                    if (data.success) {
                        statusText.textContent = isTurnOn ? 'Power is ON' : 'Power is OFF';
                        statusText.className = 'status-text success';
                    } else {
                        statusText.textContent = 'Error: ' + (data.error || 'Failed to communicate');
                        statusText.className = 'status-text error';
                        // Revert toggle if API call failed
                        const toggle = document.getElementById('toggleSwitchBot_' + deviceId);
                        if (toggle) toggle.checked = !isTurnOn;
                    }
                }
                // After a short delay, revert to 'Ready' or actual status
                setTimeout(() => {
                    if (statusText && !statusText.textContent.startsWith('Error')) {
                        statusText.textContent = 'Ready';
                        statusText.className = 'status-text';
                    }
                    checkDevicesStatus(deviceId); // Update status after action
                }, 2000);
            })
            .catch(error => {
                console.error('Error:', error);
                if (statusText) {
                    statusText.textContent = 'Network Error.';
                    statusText.className = 'status-text error';
                    const toggle = document.getElementById('toggleSwitchBot_' + deviceId);
                    if (toggle) toggle.checked = !isTurnOn;
                }
                setTimeout(() => {
                    if (statusText) {
                        statusText.textContent = 'Ready';
                        statusText.className = 'status-text';
                    }
                }, 3000);
            });
        }

        const deviceMap = {
            <?php foreach($userDevices as $d): ?>
            '<?php echo htmlspecialchars($d['device_id'], ENT_QUOTES); ?>': '<?php echo $d['id']; ?>',
            <?php endforeach; ?>
        };

        function checkDevicesStatus(singleDeviceId = null) {
            // Only fetch status if not currently processing a toggle
            const fetchUrl = singleDeviceId ? 'user_switchbot_status.php?device_id=' + singleDeviceId : 'user_switchbot_status.php';

            fetch(fetchUrl)
            .then(response => response.json())
            .then(data => {
                if (data.forced_logout) {
                    window.location.href = 'apple_watch_login.php?forced_logout=1';
                    return;
                }
                if (!data.success || !data.statuses) {
                    console.error('Failed to fetch statuses:', data.error);
                    return;
                }
                
                for (const [deviceId, currentPower] of Object.entries(data.statuses)) {
                    const statusText = document.getElementById('switchbot-status-' + deviceId);
                    const toggle = document.getElementById('toggleSwitchBot_' + deviceId);
                    
                    if (!statusText || !toggle) continue;

                    if (currentPower.startsWith('error: ')) {
                        statusText.textContent = currentPower.substring(7);
                        statusText.className = 'status-text error';
                        toggle.disabled = true;
                    } else {
                        statusText.textContent = 'Ready';
                        statusText.className = 'status-text';
                        toggle.disabled = false;
                        // Only update toggle if it's not currently being manipulated by the user
                        if (plugIntendedStates[deviceId] === undefined || plugIntendedStates[deviceId] !== (currentPower === 'on')) {
                            toggle.checked = (currentPower === 'on');
                        }
                    }
                }
            })
            .catch(error => console.error('Status Check Error:', error));
        }
        
        window.addEventListener('DOMContentLoaded', () => {
            checkDevicesStatus(); // Initial status check
            // Keep session alive
            setInterval(() => { fetch('keep_alive.php').catch(() => {}); }, 300000);
        });
    </script>
</body>
</html>