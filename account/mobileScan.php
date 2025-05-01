<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../php/utils.php';

if (!isset($_SESSION['user_id'])) {
    die("Authentication required. Please log in.");
    exit;
}

$currentUserId = $_SESSION['user_id'];

?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AGF Billetscanner</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overscroll-behavior-y: contain;
        }
        #scanner-container video {
            width: 100%;
            max-width: 400px;
            height: auto;
            border-radius: 8px;
            background-color: #333;
        }
        #canvas {
            display: none;
        }
        #scan-result {
            min-height: 100px;
            word-wrap: break-word;
        }
        .feedback-success { background-color: #d1fae5; border-color: #6ee7b7; color: #065f46; }
        .feedback-error { background-color: #fee2e2; border-color: #fca5a5; color: #991b1b; }
        .feedback-warning { background-color: #fef3c7; border-color: #fcd34d; color: #92400e; }
        .feedback-info { background-color: #e0e7ff; border-color: #a5b4fc; color: #3730a3; }
        .feedback-loading { background-color: #f3f4f6; border-color: #d1d5db; color: #4b5563; }
        button, label {
             touch-action: manipulation;
        }
    </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <header class="bg-blue-800 text-white p-4 text-center shadow-md">
        <h1 class="text-xl font-bold">Billetscanner</h1>
    </header>

    <main class="flex-grow container mx-auto p-4 max-w-lg">

        <div class="bg-white p-5 rounded-lg shadow-md space-y-4">
            <div id="scanner-container" class="relative">
                 <video id="video" playsinline muted class="border border-gray-300 block mx-auto"></video>
                 <div id="loading-indicator" class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 text-white hidden">
                     <span>Starter kamera...</span>
                 </div>
            </div>
            <canvas id="canvas"></canvas>

            <div class="space-y-3">
                <button id="start-scanning" class="w-full px-4 py-3 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center justify-center gap-2">
                    <i class="fas fa-qrcode"></i> Start Scanning
                </button>
                <button id="stop-scanning" class="w-full px-4 py-3 bg-red-600 text-white rounded-md font-semibold hover:bg-red-700 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 hidden flex items-center justify-center gap-2">
                    <i class="fas fa-stop-circle"></i> Stop Scanning
                </button>
                 <label class="flex items-center justify-center bg-gray-50 p-3 rounded-md border cursor-pointer">
                    <input type="checkbox" id="continuous-scan" class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 mr-3">
                    <span class="text-gray-700">Kontinuerlig Scan</span>
                </label>
            </div>

            <div id="scan-result" class="mt-4 p-4 border rounded-md text-center feedback-info">
                Tryk 'Start Scanning' for at begynde.
            </div>
        </div>

    </main>

    <footer class="text-center p-3 text-gray-500 text-xs">
        AGF Scanner Tool © <?php echo date("Y"); ?>
    </footer>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const startScanningButton = document.getElementById('start-scanning');
        const stopScanningButton = document.getElementById('stop-scanning');
        const continuousScanCheckbox = document.getElementById('continuous-scan');
        const scanResultDiv = document.getElementById('scan-result');
        const loadingIndicator = document.getElementById('loading-indicator');

        let stream = null;
        let animationFrameId = null;
        let isScanningActive = false;
        let scanTimeout = null;
        let lastProcessedQrData = null;

        const successSound = new Audio('../assets/sounds/success_scan.mp3');
        successSound.preload = 'auto';

        const currentUserId = <?php echo json_encode($currentUserId); ?>;

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

         function displayFeedback(message, type = 'info') {
            scanResultDiv.innerHTML = escapeHtml(message);
            scanResultDiv.className = 'mt-4 p-4 border rounded-md text-center ';
            switch (type) {
                case 'success': scanResultDiv.classList.add('feedback-success'); break;
                case 'error': scanResultDiv.classList.add('feedback-error'); break;
                case 'warning': scanResultDiv.classList.add('feedback-warning'); break;
                case 'loading': scanResultDiv.classList.add('feedback-loading'); break;
                default: scanResultDiv.classList.add('feedback-info');
            }
        }

        async function startScanning() {
            if (stream) return;

            displayFeedback('Starter kamera...', 'loading');
            loadingIndicator.classList.remove('hidden');
            startScanningButton.disabled = true;

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'environment',
                    }
                });

                video.srcObject = stream;
                await video.play();

                loadingIndicator.classList.add('hidden');
                startScanningButton.classList.add('hidden');
                stopScanningButton.classList.remove('hidden');
                displayFeedback('Klar til at scanne QR-koder.', 'info');

                isScanningActive = false;
                lastProcessedQrData = null; // Reset last scan on start
                tick();

            } catch (err) {
                console.error('Camera Access Error:', err);
                let message = 'Fejl: Kunne ikke få adgang til kameraet.';
                if (err.name === 'NotAllowedError') {
                    message = 'Kameratilladelse nægtet. Tillad venligst kameraadgang i browserindstillingerne.';
                } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    message = 'Intet kamera fundet.';
                } else if (err.name === 'NotReadableError') {
                     message = 'Kameraet er i brug af en anden applikation.';
                }
                displayFeedback(message, 'error');
                logEvent('ERROR', `Camera access failed: ${err.name} - ${err.message}`);
                stopScanning();
            }
        }

        function stopScanning() {
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
            }
             clearTimeout(scanTimeout);

            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            video.srcObject = null;
            startScanningButton.disabled = false;
            startScanningButton.classList.remove('hidden');
            stopScanningButton.classList.add('hidden');
            loadingIndicator.classList.add('hidden');
            isScanningActive = false;
            lastProcessedQrData = null;
        }

        function tick() {
            if (!stream || video.readyState !== video.HAVE_ENOUGH_DATA) {
                if (stream) animationFrameId = requestAnimationFrame(tick);
                return;
            }

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
                        if (code.data === lastProcessedQrData) {
                            // Duplicate scan, do nothing this frame
                        } else {
                            console.log("New QR Code detected:", code.data);
                            lastProcessedQrData = code.data;
                            isScanningActive = true;
                            verifyTicket(code.data);

                            if (continuousScanCheckbox.checked) {
                                clearTimeout(scanTimeout);
                                scanTimeout = setTimeout(() => {
                                    isScanningActive = false;
                                    if (stream) animationFrameId = requestAnimationFrame(tick);
                                }, 1500);
                                if (stream) animationFrameId = requestAnimationFrame(tick);
                                return;
                            }
                        }
                    }
                } catch(e) {
                     console.error("Error during QR processing:", e);
                }
            }

            if (stream) animationFrameId = requestAnimationFrame(tick);
        }

        async function verifyTicket(qrText) {
            displayFeedback('Verificerer billet...', 'loading');
            if (navigator.vibrate) { navigator.vibrate(50); }

            let ticketId = null;
            let matchId = null;

            try {
                 if (typeof qrText !== 'string' || !qrText.includes('|')) {
                     throw new Error('Ugyldigt QR-kode format.');
                 }
                 const parts = qrText.split('|', 3);
                 if (parts.length !== 3) {
                      throw new Error('Ugyldigt QR-kode format (forkert antal dele).');
                 }
                 matchId = parts[0];
                 ticketId = parts[1];

                 const response = await fetch('../api/validate-ticket.php', {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                     body: `qrText=${encodeURIComponent(qrText)}`,
                 });

                 const data = await response.json();

                 if (!response.ok || !data) {
                     throw new Error(data?.reason || `Serverfejl (${response.status}) ved validering.`);
                 }

                 if (data.valid) {
                     successSound.play().catch(error => {
                         console.warn("Success sound playback failed:", error);
                     });
                     if (navigator.vibrate) { navigator.vibrate([100, 50, 100]); }
                     displayFeedback('Billet gyldig! Henter detaljer...', 'success');
                     fetchTicketDetails(ticketId, matchId);
                 } else {
                     throw new Error(data.reason || 'Ukendt valideringsfejl.');
                 }

            } catch (error) {
                console.error('Verification Error:', error);
                displayFeedback(`Fejl: ${escapeHtml(error.message)}`, 'error');
                 if (navigator.vibrate) { navigator.vibrate([50, 100, 50]); }
                 logEvent('TICKET', `Client: Validation failed: ${qrText} - Reason: ${error.message}`, currentUserId, ticketId, matchId);
            } finally {
                if (!continuousScanCheckbox.checked || !scanTimeout) {
                     isScanningActive = false;
                 }
                if (!continuousScanCheckbox.checked && stream) {
                    stopScanning();
                }
            }
        }

        async function fetchTicketDetails(ticketId, matchId) {
             try {
                 const response = await fetch(`../api/get_ticket_details.php?ticket_id=${ticketId}&match_id=${matchId}`);
                 if (!response.ok) throw new Error(`HTTP error ${response.status}`);
                 const data = await response.json();

                 if(data.error){
                      scanResultDiv.innerHTML = `
                         <p class="font-semibold mb-1">Billet Gyldig</p>
                         <p class="text-sm">(Kunne ikke hente detaljer: ${escapeHtml(data.error)})</p>
                     `;
                     scanResultDiv.className = 'mt-4 p-4 border rounded-md text-center feedback-success';
                 } else {
                     scanResultDiv.innerHTML = `
                         <p class="font-semibold mb-1 text-lg">Billet Gyldig</p>
                         <p><strong>Kamp:</strong> ${escapeHtml(data.match_name || 'N/A')}</p>
                         <p><strong>Afsnit:</strong> ${escapeHtml(data.section || 'N/A')}</p>
                         <p><strong>Række:</strong> ${escapeHtml(data.row || 'N/A')}</p>
                         <p><strong>Sæde:</strong> ${escapeHtml(data.seat_number || 'N/A')}</p>
                     `;
                      scanResultDiv.className = 'mt-4 p-4 border rounded-md text-center feedback-success';
                 }
             } catch (error) {
                 console.error('Error fetching ticket details:', error);
                  scanResultDiv.innerHTML = `
                     <p class="font-semibold mb-1">Billet Gyldig</p>
                     <p class="text-sm">(Detaljer kunne ikke hentes - netværksfejl)</p>
                 `;
                 scanResultDiv.className = 'mt-4 p-4 border rounded-md text-center feedback-success';
             }
        }

        function logEvent(eventType, message, userId = null, ticketId = null, matchId = null) {
             fetch('php/log_event.php', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                 body: new URLSearchParams({
                     event_type: eventType,
                     message: message,
                     user_id: userId || '',
                     ticket_id: ticketId || '',
                     match_id: matchId || ''
                 }).toString()
             }).catch(error => console.error('Failed to log client event:', error));
        }

        startScanningButton.addEventListener('click', startScanning);
        stopScanningButton.addEventListener('click', stopScanning);

         document.addEventListener('visibilitychange', () => {
             if (document.hidden && stream) {
                 console.log("Page hidden, stopping scanner.");
                 stopScanning();
             }
         });

    </script>

</body>
</html>