<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../php/utils.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: logout.php');
    exit;
}

$userId = $_SESSION['user_id'];
$sql = "SELECT role FROM users WHERE user_id = :userId";
$userData = executeQuery($sql, ['userId' => $userId]);
if (!$userData || !isset($userData[0]['role']) || $userData[0]['role'] !== 'admin') {
    // Redirect non-admins or if user data couldn't be fetched
    header('Location: tickets.php'); // Or an appropriate non-admin page
    exit;
}


$accountNavItems = [
    'Billetter' => 'tickets.php',
    'Rediger data' => 'edit-data.php',
    'Transaktioner' => 'transactions.php',
    'Mine abonnementer' => 'subscriptions.php',
    'Skift adgangskode' => 'change-password.php',
    'Admin' => 'admin.php',
];
$currentPage = basename($_SERVER['PHP_SELF']);

$recentScansQuery = "SELECT l.timestamp, l.message, t.ticket_id, t.match_id
                     FROM logs l
                     LEFT JOIN tickets t ON l.ticket_id = t.ticket_id
                     WHERE l.event_type = 'TICKET'
                     ORDER BY l.timestamp DESC
                     LIMIT 10";
$recentScans = executeQuery($recentScansQuery) ?: [];

$today = date('Y-m-d');
$statsQuery = "SELECT
                    COUNT(*) as total_scans,
                    SUM(CASE WHEN message LIKE '%successfully%' THEN 1 ELSE 0 END) as valid_scans,
                    SUM(CASE WHEN message LIKE '%failed%' THEN 1 ELSE 0 END) as invalid_scans
               FROM logs
               WHERE event_type = 'TICKET' AND DATE(timestamp) = :today";
$statsResult = executeQuery($statsQuery, ['today' => $today]);
$stats = ($statsResult && isset($statsResult[0])) ? $statsResult[0] : ['total_scans' => 0, 'valid_scans' => 0, 'invalid_scans' => 0];
?>

<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGF - Admin Panel</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        #language-menu { transition: opacity 0.2s ease-out, transform 0.2s ease-out; }
        #language-menu.hidden { opacity: 0; transform: translateY(-10px); pointer-events: none; }
        .account-nav-active { color: #2563eb; font-weight: 600; border-bottom: 2px solid #2563eb; }
        #canvas { display: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: middle;} /* Adjusted padding and border color */
        th { background-color: #f9fafb; font-weight: 600; color: #374151;} /* Adjusted header style */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); justify-content: center; align-items: center; z-index: 50; padding: 1rem;}
        .modal.show { display: flex; }
        .modal-content { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto;}
        .tooltip { position: relative; display: inline-block; }
        .tooltip .tooltiptext { visibility: hidden; width: 120px; background-color: black; color: #fff; text-align: center; border-radius: 6px; padding: 5px 0; position: absolute; z-index: 1; bottom: 125%; left: 50%; margin-left: -60px; opacity: 0; transition: opacity 0.3s; }
        .tooltip:hover .tooltiptext { visibility: visible; opacity: 1; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <?php include '../layout/navbar.php'; // Assuming navbar.php handles its own styling/logic ?>

    <main class="container mx-auto my-8 px-4">
        <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
            <nav class="mb-6 pb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
                    <?php foreach ($accountNavItems as $title => $file): ?>
                        <li class="mr-2">
                            <?php
                                $isActive = ($currentPage == $file);
                                $linkClasses = 'inline-block p-4 rounded-t-lg border-b-2 transition duration-150 ease-in-out ';
                                $linkClasses .= $isActive ? 'text-blue-600 border-blue-600 active font-semibold' : 'border-transparent hover:text-gray-600 hover:border-gray-300';
                            ?>
                            <a href="<?php echo htmlspecialchars($file); ?>" class="<?php echo $linkClasses; ?>" <?php if ($isActive) echo 'aria-current="page"'; ?>>
                                <?php echo htmlspecialchars($title); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <!-- Admin Content Area -->
            <div class="mt-6 space-y-8">

                <!-- Ticket Scanner Section -->
                <div>
                    <h2 class="text-2xl font-semibold mb-4">Billetscanning</h2>
                    <div class="flex flex-wrap gap-4 mb-4 items-center">
                        <button id="start-scanning" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-150 ease-in-out flex items-center gap-2">
                           <i class="fas fa-qrcode"></i> Start Desktop Scan
                        </button>
                        <!-- ADD THIS LINK -->
                        <a href="https://krisba.dk/ks/account/mobileScan.php" target="_blank" rel="noopener noreferrer" class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700 transition duration-150 ease-in-out flex items-center gap-2">
                            <i class="fas fa-mobile-alt"></i> Åbn Mobilscanner
                        </a>
                         <!-- END ADDED LINK -->
                        <label class="flex items-center cursor-pointer ml-auto"> <!-- Added ml-auto to push checkbox right -->
                            <input type="checkbox" id="continuous-scan" class="mr-2 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span>Kontinuerlig Scan (Desktop)</span>
                        </label>
                    </div>
                    <div id="scanner-container" class="hidden mt-4 p-4 border rounded-md bg-gray-50 max-w-sm">
                        <video id="video" width="300" height="200" class="border block mx-auto"></video>
                        <canvas id="canvas"></canvas> <!-- Still needed for jsQR -->
                        <button id="stop-scanning" class="mt-3 w-full px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition duration-150 ease-in-out">Stop Desktop Scan</button>
                    </div>
                    <div id="result" class="mt-4 p-3 border rounded-md bg-gray-50 min-h-[50px]"></div>

                    <div class="mt-6">
                        <h3 class="text-lg font-semibold mb-2">Manuel Indtastning</h3>
                        <form id="manual-form" class="flex gap-2">
                            <input type="text" id="manual-qr" class="border p-2 rounded w-full focus:ring-blue-500 focus:border-blue-500" placeholder="Indtast QR-kode data">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-150 ease-in-out">Valider</button>
                        </form>
                    </div>
                </div>
                <!-- End Ticket Scanner Section -->

                 <!-- Stats and Recent Scans Section -->
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Daily Stats -->
                    <div>
                        <h3 class="text-xl font-semibold mb-3">Dagens Statistik</h3>
                        <div class="stats-grid">
                            <div class="p-4 bg-gray-50 rounded-lg border">
                                <p class="text-sm text-gray-600">Total Scans</p>
                                <p class="text-2xl font-bold text-gray-800" id="stat-total"><?php echo $stats['total_scans']; ?></p>
                            </div>
                            <div class="p-4 bg-green-50 rounded-lg border border-green-200">
                                <p class="text-sm text-green-700">Gyldige</p>
                                <p class="text-2xl font-bold text-green-600" id="stat-valid"><?php echo $stats['valid_scans']; ?></p>
                            </div>
                            <div class="p-4 bg-red-50 rounded-lg border border-red-200">
                                <p class="text-sm text-red-700">Ugyldige</p>
                                <p class="text-2xl font-bold text-red-600" id="stat-invalid"><?php echo $stats['invalid_scans']; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Scans -->
                    <div>
                        <h3 class="text-xl font-semibold mb-3">Seneste Scans</h3>
                        <div class="overflow-x-auto max-h-80 border rounded-lg bg-white">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 bg-gray-100">
                                    <tr>
                                        <th class="py-2 px-3">Tidspunkt</th>
                                        <th class="py-2 px-3">Besked</th>
                                        <th class="py-2 px-3">Ticket ID</th>
                                        <th class="py-2 px-3">Match ID</th>
                                    </tr>
                                </thead>
                                <tbody id="recent-scans-tbody">
                                    <?php if (empty($recentScans)): ?>
                                        <tr><td colspan="4" class="text-center py-4 text-gray-500">Ingen scans endnu i dag.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentScans as $scan): ?>
                                            <tr>
                                                <td class="py-2 px-3 whitespace-nowrap"><?php echo htmlspecialchars(date('H:i:s', strtotime($scan['timestamp']))); ?></td>
                                                <td class="py-2 px-3"><?php echo htmlspecialchars($scan['message']); ?></td>
                                                <td class="py-2 px-3"><?php echo htmlspecialchars($scan['ticket_id'] ?: '-'); ?></td>
                                                <td class="py-2 px-3"><?php echo htmlspecialchars($scan['match_id'] ?: '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                 <!-- End Stats and Recent Scans Section -->


                <!-- Match Management Section -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="flex flex-wrap justify-between items-center mb-4 gap-4">
                         <h3 class="text-xl font-semibold">Administrer Kampe</h3>
                         <button id="add-match-btn" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition duration-150 ease-in-out flex items-center gap-2">
                             <i class="fas fa-plus"></i> Tilføj Ny Kamp
                         </button>
                    </div>
                    <div class="overflow-x-auto border rounded-lg bg-white">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="py-2 px-3">Dato & Tid</th>
                                    <th class="py-2 px-3">Modstander</th>
                                    <th class="py-2 px-3">Stadion</th>
                                    <th class="py-2 px-3">Kort URL</th>
                                    <th class="py-2 px-3 text-center">Handlinger</th>
                                </tr>
                            </thead>
                            <tbody id="matches-tbody">
                                <!-- Loading indicator -->
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-gray-500">Henter kampe...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="matches-feedback" class="mt-2 text-sm"></div>
                </div>
                <!-- End Match Management Section -->

            </div>
            <!-- End Admin Content Area -->

            <!-- Add Match Modal -->
            <div id="add-match-modal" class="modal">
                <div class="modal-content">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Tilføj Ny Kamp</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600 close-modal-btn" aria-label="Close modal">
                            <i class="fas fa-times fa-lg"></i>
                        </button>
                    </div>
                    <form id="add-match-form">
                        <div class="mb-4">
                            <label for="match-date" class="block text-sm font-medium text-gray-700 mb-1">Dato og Tid</label>
                            <input type="datetime-local" id="match-date" name="match_date" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="opponent" class="block text-sm font-medium text-gray-700 mb-1">Modstander</label>
                            <input type="text" id="opponent" name="opponent" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="stadium" class="block text-sm font-medium text-gray-700 mb-1">Stadion</label>
                            <input type="text" id="stadium" name="stadium" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="card-url" class="block text-sm font-medium text-gray-700 mb-1">Kort URL</label>
                            <input type="url" id="card-url" name="card_url" required placeholder="https://example.com/image.jpg" class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition duration-150 ease-in-out close-modal-btn">Annuller</button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition duration-150 ease-in-out">Tilføj Kamp</button>
                        </div>
                    </form>
                    <div id="modal-result" class="mt-3 text-sm"></div>
                </div>
            </div>

            <!-- Edit Match Modal -->
            <div id="edit-match-modal" class="modal">
                <div class="modal-content">
                     <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Rediger Kamp</h3>
                         <button type="button" class="text-gray-400 hover:text-gray-600 close-edit-modal-btn" aria-label="Close modal">
                             <i class="fas fa-times fa-lg"></i>
                         </button>
                    </div>
                    <form id="edit-match-form">
                        <input type="hidden" id="edit-match-id" name="edit_match_id">
                        <div class="mb-4">
                            <label for="edit-match-date" class="block text-sm font-medium text-gray-700 mb-1">Dato og Tid</label>
                            <input type="datetime-local" id="edit-match-date" name="edit_match_date" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="edit-opponent" class="block text-sm font-medium text-gray-700 mb-1">Modstander</label>
                            <input type="text" id="edit-opponent" name="edit_opponent" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="edit-stadium" class="block text-sm font-medium text-gray-700 mb-1">Stadion</label>
                            <input type="text" id="edit-stadium" name="edit_stadium" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="edit-card-url" class="block text-sm font-medium text-gray-700 mb-1">Kort URL</label>
                            <input type="url" id="edit-card-url" name="edit_card_url" required placeholder="https://example.com/image.jpg" class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition duration-150 ease-in-out close-edit-modal-btn">Annuller</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-150 ease-in-out">Gem Ændringer</button>
                        </div>
                    </form>
                    <div id="edit-modal-result" class="mt-3 text-sm"></div>
                </div>
            </div>

        </div>
    </main>

    <?php include '../layout/footer.php'; // Assuming footer.php handles its own styling/logic ?>

    <script>
        // --- DOM Elements ---
        const langButton = document.getElementById('language-button');
        const langMenu = document.getElementById('language-menu');
        const currentLangSpan = document.getElementById('current-language');
        const langOptions = document.querySelectorAll('.language-option');

        const startScanningButton = document.getElementById('start-scanning');
        const scannerContainer = document.getElementById('scanner-container');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas'); // Required by jsQR
        const resultDiv = document.getElementById('result');
        const stopScanningButton = document.getElementById('stop-scanning');
        const manualForm = document.getElementById('manual-form');
        const manualQrInput = document.getElementById('manual-qr');
        const continuousScanCheckbox = document.getElementById('continuous-scan');

        const addMatchBtn = document.getElementById('add-match-btn');
        const addMatchModal = document.getElementById('add-match-modal');
        const addMatchForm = document.getElementById('add-match-form');
        const modalResult = document.getElementById('modal-result');

        const matchesTableBody = document.getElementById('matches-tbody');
        const editMatchModal = document.getElementById('edit-match-modal');
        const editMatchForm = document.getElementById('edit-match-form');
        const editModalResult = document.getElementById('edit-modal-result');
        const matchesFeedback = document.getElementById('matches-feedback');

        const closeModalBtns = document.querySelectorAll('.close-modal-btn');
        const closeEditModalBtns = document.querySelectorAll('.close-edit-modal-btn');

        // --- Global Variables ---
        let stream = null; // To keep track of the camera stream
        let scanInterval = null; // For continuous scanning interval (may not be used if using requestAnimationFrame)
        let animationFrameId = null; // Added for requestAnimationFrame loop
        let isScanningActive = false; // Added scan lock
        let scanTimeout = null; // Added timer for continuous scan delay
        let lastProcessedQrData = null;

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function formatDateTimeForDisplay(dateTimeStr) {
            if (!dateTimeStr) return 'N/A';
            try {
                const date = new Date(dateTimeStr);
                return date.toLocaleString('da-DK', {
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit', hour12: false
                }).replace(',', '');
            } catch (e) {
                console.warn("Could not format date:", dateTimeStr, e);
                return dateTimeStr;
            }
        }

         function displayFeedback(element, message, type = 'info') {
            element.textContent = message;
            element.className = 'mt-2 text-sm '; // Reset classes
            switch (type) {
                case 'success': element.classList.add('text-green-600'); break;
                case 'error': element.classList.add('text-red-600'); break;
                case 'warning': element.classList.add('text-yellow-600'); break;
                case 'loading': element.classList.add('text-gray-600'); break;
                default: element.classList.add('text-gray-700'); // info
            }
        }

        // --- Language Menu Logic ---
        if (langButton && langMenu) {
            langButton.addEventListener('click', (event) => {
                event.stopPropagation();
                const isExpanded = langButton.getAttribute('aria-expanded') === 'true';
                langMenu.classList.toggle('hidden');
                langButton.setAttribute('aria-expanded', !isExpanded);
            });
            document.addEventListener('click', (event) => {
                if (!langButton.contains(event.target) && !langMenu.contains(event.target)) {
                    if (!langMenu.classList.contains('hidden')) {
                        langMenu.classList.add('hidden');
                        langButton.setAttribute('aria-expanded', 'false');
                    }
                }
            });
            langOptions.forEach(option => {
                option.addEventListener('click', (event) => {
                    event.preventDefault();
                    // ... (language change logic if implemented) ...
                    langMenu.classList.add('hidden');
                    langButton.setAttribute('aria-expanded', 'false');
                });
            });
        }

        // --- Scanner Logic ---
        function startScanning() {
            if (stream) return; // Already scanning

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(activeStream => {
                    stream = activeStream;
                    video.srcObject = stream;
                    video.setAttribute('playsinline', true); // Required for iOS
                    video.play();
                    scannerContainer.classList.remove('hidden');
                    startScanningButton.disabled = true;
                    displayFeedback(resultDiv, 'Kamera aktivt. Ret mod QR-kode.', 'info');
                    requestAnimationFrame(tick); // Start the scanning loop
                })
                .catch(err => {
                    console.error('Error accessing camera:', err);
                    displayFeedback(resultDiv, `Fejl: Kunne ikke få adgang til kameraet (${err.name}).`, 'error');
                    logEvent('ERROR', `Camera access failed: ${err.message}`);
                    stopScanning(); // Clean up
                });
        }

        function tick() {
            // Check if scanning should continue
            if (!stream || video.readyState !== video.HAVE_ENOUGH_DATA) {
                if (stream) animationFrameId = requestAnimationFrame(tick); // Keep trying if stream exists but video not ready
                return;
            }

            // Only process if not already handling a scan
            if (!isScanningActive) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                try {
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: 'dontInvert',
                    });

                    if (code && code.data) {
                        // --- Check for Duplicate Scan ---
                        if (code.data === lastProcessedQrData) {
                            // Same code as last processed one, ignore it.
                            // console.log("Duplicate scan ignored:", code.data); // Optional debug log
                        } else {
                            // --- New Code Detected ---
                            console.log("New QR Code detected:", code.data);
                            lastProcessedQrData = code.data; // Store this code as the last processed one
                            isScanningActive = true;         // Lock processing
                            verifyTicket(code.data);         // Call the API

                            // Handle continuous scan delay OR stop if not continuous
                            if (continuousScanCheckbox.checked) {
                                clearTimeout(scanTimeout); // Clear previous timer if any
                                scanTimeout = setTimeout(() => {
                                    isScanningActive = false; // Unlock after delay
                                     // Reset lastProcessedQrData *after delay* to allow re-scanning the same code later if needed
                                    // lastProcessedQrData = null; // Or keep it to prevent immediate re-scan? Decide based on desired UX. Let's keep it for now.
                                    if (stream) animationFrameId = requestAnimationFrame(tick); // Continue loop if still active
                                }, 1500); // 1.5 second delay (adjust as needed)
                                // Exit tick for now, wait for timeout
                                if (stream) animationFrameId = requestAnimationFrame(tick); // Continue animation frame loop immediately for next detection attempt
                                return; // But don't process another QR in *this* tick's logic
                            }
                            // If not continuous, verifyTicket's finally block will handle stopping etc.
                            // isScanningActive will also be reset there.
                        }
                    }
                } catch (e) {
                    console.error("Error during QR scan processing:", e);
                    // Don't stop the loop, just log and continue trying
                }
            }

            // Continue the loop only if the stream is active
            if (stream) animationFrameId = requestAnimationFrame(tick);
        }


        function stopScanning() {
            if (animationFrameId) { // Use animationFrameId check
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
            }
             clearTimeout(scanTimeout); // Clear any pending continuous scan delay

            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null; // Clear the stream variable
            }
             video.srcObject = null; // Detach stream from video element
             scannerContainer.classList.add('hidden');
             startScanningButton.disabled = false;
             isScanningActive = false; // Ensure lock is reset
             lastProcessedQrData = null; // <<<--- ADD THIS LINE: Reset on stop
             // Optional: Clear the result div or show a stopped message
             // displayFeedback(resultDiv, 'Scanning stoppet.', 'info');
        }

        async function verifyTicket(qrText) {
             displayFeedback(resultDiv, 'Verificerer billet...', 'loading');
             try {
                 const response = await fetch('../api/validate-ticket.php', {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                     body: `qrText=${encodeURIComponent(qrText)}`,
                 });
                 const data = await response.json();

                 let ticketId = null;
                 let matchId = null;
                 if (typeof qrText === 'string' && qrText.includes('|')) {
                      const parts = qrText.split('|');
                      matchId = parts[0] || null;
                      ticketId = parts[1] || null;
                 }

                 if (data.valid) {
                    displayFeedback(resultDiv, 'Henter billetdetaljer...', 'loading');
                    fetchTicketDetails(ticketId, matchId); // Fetch details on success
                    logEvent('TICKET', `Ticket validated successfully: ${qrText}`, null, ticketId, matchId); // Use TICKET type
                 } else {
                     displayFeedback(resultDiv, `Billet Ugyldig: ${data.reason || 'Ukendt fejl'}`, 'error');
                     logEvent('TICKET', `Ticket validation failed: ${qrText} - Reason: ${data.reason || 'Unknown'}`, null, ticketId, matchId); // Use TICKET type
                 }
             } catch (error) {
                 console.error('Error verifying ticket:', error);
                 displayFeedback(resultDiv, 'Fejl under billetverifikation.', 'error');
                 logEvent('ERROR', `Ticket verification network/fetch error: ${error.message}`);
             } finally {
                if (!continuousScanCheckbox.checked || !scanTimeout) {
                    isScanningActive = false;
                }
                if (!continuousScanCheckbox.checked && stream) {
                    stopScanning();
                }
                refreshRecentScans(); // Always refresh scans list
                refreshStats(); // Always refresh stats
             }
        }

         async function fetchTicketDetails(ticketId, matchId) {
            if (!ticketId || !matchId) {
                 displayFeedback(resultDiv, 'Billet gyldig, men ID mangler for detaljer.', 'warning');
                 return;
            }
             try {
                 const response = await fetch(`../api/get_ticket_details.php?ticket_id=${ticketId}&match_id=${matchId}`);
                 if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}`);
                 }
                 const data = await response.json();
                 if(data.error){
                     displayFeedback(resultDiv, `Billet gyldig, men detaljer kunne ikke hentes: ${data.error}`, 'warning');
                 } else {
                     resultDiv.innerHTML = `
                         <p class="text-green-600 font-semibold mb-1">Billet Gyldig</p>
                         <p><strong>Kamp:</strong> ${escapeHtml(data.match_name || 'N/A')}</p>
                         <p><strong>Afsnit:</strong> ${escapeHtml(data.section || 'N/A')}</p>
                         <p><strong>Række:</strong> ${escapeHtml(data.row || 'N/A')}</p>
                         <p><strong>Sæde:</strong> ${escapeHtml(data.seat_number || 'N/A')}</p>
                     `;
                      resultDiv.classList.remove('text-red-600', 'text-yellow-600', 'text-gray-600');
                      resultDiv.classList.add('text-green-600'); // Ensure correct color
                 }
             } catch (error) {
                 console.error('Error fetching ticket details:', error);
                  displayFeedback(resultDiv, 'Billet gyldig, men detaljer kunne ikke hentes (netværksfejl).', 'warning');
             }
         }


        // --- Match Management Logic ---
        async function loadMatches() {
            displayFeedback(matchesFeedback, '', 'info'); // Clear feedback
            matchesTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">Henter kampe...</td></tr>';

            try {
                const response = await fetch('../api/get_matches.php');
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({ error: 'Ukendt fejl ved hentning af kampe.' }));
                    throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
                }
                const matches = await response.json();

                matchesTableBody.innerHTML = ''; // Clear loading/previous data
                if (!Array.isArray(matches) || matches.length === 0) {
                    matchesTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">Ingen kampe fundet. Tilføj en ny kamp.</td></tr>';
                    return;
                }

                matches.forEach(match => {
                    const row = matchesTableBody.insertRow();
                    row.setAttribute('data-match-id', match.match_id);
                    // Store data securely for later use
                    row.dataset.matchData = JSON.stringify(match);

                    row.innerHTML = `
                        <td class="py-2 px-3 whitespace-nowrap">${formatDateTimeForDisplay(match.match_datetime_local)}</td>
                        <td class="py-2 px-3">${escapeHtml(match.opponent)}</td>
                        <td class="py-2 px-3">${escapeHtml(match.stadium)}</td>
                        <td class="py-2 px-3">
                            <a href="${escapeHtml(match.card_url)}" target="_blank" rel="noopener noreferrer"
                               class="text-blue-600 hover:underline truncate block max-w-[200px]"
                               title="${escapeHtml(match.card_url)}">
                               ${escapeHtml(match.card_url)}
                            </a>
                        </td>
                        <td class="py-2 px-3 text-center whitespace-nowrap">
                             <button class="edit-match-btn text-blue-600 hover:text-blue-800 mr-3 transition duration-150 ease-in-out tooltip" data-tooltip="Rediger">
                                 <i class="fas fa-edit fa-fw"></i>
                             </button>
                             <button class="delete-match-btn text-red-600 hover:text-red-800 transition duration-150 ease-in-out tooltip" data-tooltip="Slet">
                                 <i class="fas fa-trash fa-fw"></i>
                             </button>
                        </td>
                    `;
                });
            } catch (error) {
                console.error('Error loading matches:', error);
                matchesTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-red-600">Fejl: ${escapeHtml(error.message)}</td></tr>`;
                displayFeedback(matchesFeedback, 'Kunne ikke hente kamplisten.', 'error');
            }
        }

        function openEditModal(match) {
            displayFeedback(editModalResult, '', 'info'); // Clear previous results
            editMatchForm.reset();

            document.getElementById('edit-match-id').value = match.match_id;
            document.getElementById('edit-match-date').value = match.match_datetime_local || '';
            document.getElementById('edit-opponent').value = match.opponent || '';
            document.getElementById('edit-stadium').value = match.stadium || '';
            document.getElementById('edit-card-url').value = match.card_url || '';

            editMatchModal.classList.add('show');
        }

        function updateTableRow(updatedMatch) {
            const row = matchesTableBody.querySelector(`tr[data-match-id="${updatedMatch.match_id}"]`);
            if (row) {
                // Re-render the row content using the same structure as in loadMatches
                row.innerHTML = `
                    <td class="py-2 px-3 whitespace-nowrap">${formatDateTimeForDisplay(updatedMatch.match_datetime_local)}</td>
                    <td class="py-2 px-3">${escapeHtml(updatedMatch.opponent)}</td>
                    <td class="py-2 px-3">${escapeHtml(updatedMatch.stadium)}</td>
                    <td class="py-2 px-3">
                        <a href="${escapeHtml(updatedMatch.card_url)}" target="_blank" rel="noopener noreferrer"
                           class="text-blue-600 hover:underline truncate block max-w-[200px]"
                           title="${escapeHtml(updatedMatch.card_url)}">
                           ${escapeHtml(updatedMatch.card_url)}
                        </a>
                    </td>
                    <td class="py-2 px-3 text-center whitespace-nowrap">
                         <button class="edit-match-btn text-blue-600 hover:text-blue-800 mr-3 transition duration-150 ease-in-out tooltip" data-tooltip="Rediger">
                             <i class="fas fa-edit fa-fw"></i>
                         </button>
                         <button class="delete-match-btn text-red-600 hover:text-red-800 transition duration-150 ease-in-out tooltip" data-tooltip="Slet">
                             <i class="fas fa-trash fa-fw"></i>
                         </button>
                    </td>
                `;
                // Update the stored data
                row.dataset.matchData = JSON.stringify(updatedMatch);
            } else {
                console.warn("Could not find row to update, reloading list.");
                loadMatches(); // Fallback: reload the whole list
            }
        }

        async function deleteMatch(matchId, rowElement) {
            const matchData = JSON.parse(rowElement.dataset.matchData || '{}');
            const opponent = matchData.opponent || `Kamp ID ${matchId}`;

            if (!confirm(`Er du sikker på, du vil slette kampen mod "${escapeHtml(opponent)}"?\nHandlingen kan ikke fortrydes og vil fjerne kampen permanent.`)) {
                return;
            }

            displayFeedback(matchesFeedback, 'Sletter kamp...', 'loading');

            try {
                const response = await fetch('../api/delete_match.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `match_id=${encodeURIComponent(matchId)}`
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    displayFeedback(matchesFeedback, `Kamp mod "${escapeHtml(opponent)}" blev slettet.`, 'success');
                    rowElement.remove();
                    if (matchesTableBody.rows.length === 0) {
                        matchesTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">Ingen kampe fundet.</td></tr>';
                    }
                     logEvent('INFO', `Match ID ${matchId} deleted successfully.`);
                } else {
                     throw new Error(data.error || `Kunne ikke slette kamp (Status: ${response.status})`);
                }
            } catch (error) {
                console.error('Error deleting match:', error);
                displayFeedback(matchesFeedback, `Fejl ved sletning: ${escapeHtml(error.message)}`, 'error');
                logEvent('ERROR', `Failed to delete match ID ${matchId}: ${error.message}`);
            }
        }


        // --- Stats & Recent Scans Refresh ---
        async function refreshRecentScans() {
            try {
                const response = await fetch('../api/get_recent_scans.php');
                const scans = await response.json();
                const tbody = document.getElementById('recent-scans-tbody');
                tbody.innerHTML = ''; // Clear existing
                if (scans.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">Ingen scans endnu i dag.</td></tr>';
                } else {
                    scans.forEach(scan => {
                         const row = tbody.insertRow();
                         row.innerHTML = `
                             <td class="py-2 px-3 whitespace-nowrap">${htmlspecialchars(scan.timestamp ? new Date(scan.timestamp).toLocaleTimeString('da-DK') : '-')}</td>
                             <td class="py-2 px-3">${htmlspecialchars(scan.message)}</td>
                             <td class="py-2 px-3">${htmlspecialchars(scan.ticket_id || '-')}</td>
                             <td class="py-2 px-3">${htmlspecialchars(scan.match_id || '-')}</td>
                         `;
                    });
                }
            } catch (error) {
                console.error('Error refreshing recent scans:', error);
                 document.getElementById('recent-scans-tbody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-red-500">Fejl ved hentning af scans.</td></tr>';
            }
        }

        async function refreshStats() {
            try {
                const response = await fetch('../api/get_ticket_stats.php');
                const stats = await response.json();
                document.getElementById('stat-total').textContent = stats.total_scans || 0;
                document.getElementById('stat-valid').textContent = stats.valid_scans || 0;
                document.getElementById('stat-invalid').textContent = stats.invalid_scans || 0;
            } catch (error) {
                console.error('Error refreshing stats:', error);
                 // Optionally indicate error in the UI
            }
        }

        // --- Logging ---
        function logEvent(eventType, message, userId = null, ticketId = null, matchId = null) {
             // Use the PHP user ID if available, otherwise keep it null
             const currentUserId = <?php echo json_encode($userId); ?>; // Get PHP session user ID

             fetch('../php/log_event.php', { // Make sure path is correct
                 method: 'POST',
                 headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                 body: new URLSearchParams({
                     event_type: eventType,
                     message: message,
                     user_id: userId || currentUserId, // Prioritize passed ID, fallback to session ID
                     ticket_id: ticketId || '',
                     match_id: matchId || ''
                 }).toString()
             }).catch(error => console.error('Failed to log event:', error));
        }


        // --- Event Listeners ---
        if(startScanningButton) {
             startScanningButton.addEventListener('click', startScanning);
        }
        if(stopScanningButton) {
            stopScanningButton.addEventListener('click', stopScanning);
        }

        if(manualForm) {
            manualForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const qrText = manualQrInput.value.trim();
                if (qrText) {
                    verifyTicket(qrText);
                    manualQrInput.value = '';
                } else {
                    displayFeedback(resultDiv, 'Indtast venligst QR-kode data.', 'warning');
                }
            });
        }

        // Add Match Modal Listeners
        if(addMatchBtn) {
            addMatchBtn.addEventListener('click', () => {
                 displayFeedback(modalResult, '', 'info'); // Clear feedback
                 addMatchForm.reset();
                 addMatchModal.classList.add('show');
            });
        }

        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                addMatchModal.classList.remove('show');
            });
        });

         addMatchModal.addEventListener('click', (e) => {
             if (e.target === addMatchModal) { // Click on backdrop
                 addMatchModal.classList.remove('show');
             }
         });

         if(addMatchForm) {
            addMatchForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                displayFeedback(modalResult, 'Tilføjer kamp...', 'loading');
                const formData = new FormData(addMatchForm);

                try {
                    const response = await fetch('../api/add_match.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (response.ok && data.success) {
                        displayFeedback(modalResult, 'Kamp tilføjet succesfuldt!', 'success');
                        logEvent('INFO', `Match added via admin panel`);
                        loadMatches(); // Refresh the list
                        setTimeout(() => {
                            addMatchModal.classList.remove('show');
                        }, 1500);
                    } else {
                        throw new Error(data.error || `Kunne ikke tilføje kamp (Status: ${response.status})`);
                    }
                } catch (error) {
                    console.error('Error adding match:', error);
                    displayFeedback(modalResult, `Fejl: ${escapeHtml(error.message)}`, 'error');
                    logEvent('ERROR', `Failed to add match: ${error.message}`);
                }
            });
        }


        // Edit/Delete Match Listeners (Event Delegation)
        if(matchesTableBody) {
            matchesTableBody.addEventListener('click', (e) => {
                const editButton = e.target.closest('.edit-match-btn');
                const deleteButton = e.target.closest('.delete-match-btn');
                const row = e.target.closest('tr');

                if (!row || !row.dataset.matchId) return; // Ensure click is on a button within a valid row

                const matchId = row.dataset.matchId;
                const matchData = JSON.parse(row.dataset.matchData || '{}');

                if (editButton && matchData) {
                    openEditModal(matchData);
                } else if (deleteButton) {
                    deleteMatch(matchId, row);
                }
            });
        }

         // Edit Match Modal Listeners
        closeEditModalBtns.forEach(btn => {
             btn.addEventListener('click', () => {
                 editMatchModal.classList.remove('show');
             });
         });

         editMatchModal.addEventListener('click', (e) => {
             if (e.target === editMatchModal) { // Click on backdrop
                 editMatchModal.classList.remove('show');
             }
         });

         if(editMatchForm) {
             editMatchForm.addEventListener('submit', async (e) => {
                 e.preventDefault();
                 displayFeedback(editModalResult, 'Gemmer ændringer...', 'loading');
                 const formData = new FormData(editMatchForm);
                 const matchId = formData.get('edit_match_id');

                 try {
                     const response = await fetch('../api/edit_match.php', { method: 'POST', body: formData });
                     const data = await response.json();

                     if (response.ok && data.success) {
                         displayFeedback(editModalResult, 'Ændringer gemt!', 'success');
                         logEvent('INFO', `Match ID ${matchId} updated via admin panel.`);
                         updateTableRow(data.match); // Update table row
                         setTimeout(() => {
                             editMatchModal.classList.remove('show');
                         }, 1500);
                     } else {
                          throw new Error(data.error || `Kunne ikke gemme ændringer (Status: ${response.status})`);
                     }
                 } catch (error) {
                     console.error('Error updating match:', error);
                     displayFeedback(editModalResult, `Fejl: ${escapeHtml(error.message)}`, 'error');
                     logEvent('ERROR', `Failed to update match ID ${matchId}: ${error.message}`);
                 }
             });
         }


        // --- Initial Load ---
        document.addEventListener('DOMContentLoaded', () => {
            if (matchesTableBody) {
                loadMatches(); // Load matches on page load
            }
            // Initial refresh of stats and scans might be good too
            refreshStats();
            refreshRecentScans();

             // Set interval to refresh stats and scans periodically (e.g., every 30 seconds)
            // setInterval(refreshStats, 30000);
            // setInterval(refreshRecentScans, 30000);
        });

    </script>
</body>
</html>