<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../php/utils.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: logout.php');
    exit;
}

$secretKey = defined('TICKET_SECRET_KEY');

$userId = $_SESSION['user_id'];

$accountNavItems = [
    'Billetter' => 'tickets.php',
    'Rediger data' => 'edit-data.php',
    'Transaktioner' => 'transactions.php',
    'Mine abonnementer' => 'subscriptions.php',
    'Skift adgangskode' => 'change-password.php',
    'Admin' => 'admin.php',
];

$currentPage = basename($_SERVER['PHP_SELF']);

// --- Fetch Tickets with MORE Details ---
$userTickets = [];
$fetchError = null;

if ($userId) {
    try {
        $sql = "SELECT
                t.ticket_id, t.ticket_type, t.price, t.section, t.row, t.seat_number,
                m.match_id, m.opponent, m.match_datetime, m.stadium,
                o.order_id, o.order_date,
                u.first_name, u.last_name
                FROM tickets t
                JOIN orders o ON t.order_id = o.order_id
                JOIN matches m ON t.match_id = m.match_id
                JOIN users u ON o.user_id = u.user_id
                WHERE o.user_id = :user_id AND t.is_used = 0
                ORDER BY m.match_datetime DESC, o.order_id DESC, t.ticket_id ASC";

        $userTickets = executeQuery($sql, ['user_id' => $userId]);

        if ($userTickets === false) {
            $fetchError = "Database query failed.";
            $userTickets = [];
        }

    } catch (Exception $e) {
        $fetchError = "An exception occurred while fetching tickets.";
        error_log("Error fetching tickets for user ID {$userId}: " . $e->getMessage());
        $userTickets = [];
    }
} else {
    $fetchError = "User not logged in.";
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-lang-key="pageTitleTickets">AGF - Min Konto - Billetter</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        #language-menu {
            transition: opacity 0.2s ease-out, transform 0.2s ease-out;
        }
        #language-menu.hidden {
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
        }
        .account-nav-active {
          color: #2563eb;
          font-weight: 600;
          border-bottom: 2px solid #2563eb;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <?php include '../layout/navbar.php'; ?>

    <main class="container mx-auto my-8 px-4">
        <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
            <nav class="mb-6 pb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
                    <?php foreach ($accountNavItems as $title => $file): ?>
                        <li class="mr-2">
                            <?php
                                $isActive = ($currentPage == $file);
                                $linkClasses = 'inline-block p-4 rounded-t-lg border-b-2 ';
                                if ($isActive) {
                                    $linkClasses .= 'text-blue-600 border-blue-600 active';
                                } else {
                                    $linkClasses .= 'border-transparent hover:text-gray-600 hover:border-gray-300';
                                }
                            ?>
                            <a href="<?php echo htmlspecialchars($file); ?>" class="<?php echo $linkClasses; ?>" <?php if($isActive) echo 'aria-current="page"'; ?>>
                                <?php echo htmlspecialchars($title); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="mt-6">
                <h2 class="text-2xl font-semibold mb-4" data-lang-key="ticketsPageHeading">Mine Billetter</h2>

                <?php
                if ($fetchError) {
                    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">';
                    echo '<span class="block sm:inline" data-lang-key="ticketFetchError">Kunne ikke hente dine billetter. Prøv venligst igen senere.</span>';
                    echo '</div>';
                } elseif (empty($userTickets)) {
                    echo '<p class="text-gray-600" data-lang-key="noTicketsFound">Du har ingen købte billetter endnu.</p>';
                } else {
                    foreach ($userTickets as $index => $ticket) {
                        // --- Extract and Sanitize ALL data needed for PDF ---
                        $ticketId = sanitizeOutput($ticket['ticket_id']);
                        $matchId = sanitizeOutput($ticket['match_id']);
                        $opponent = sanitizeOutput($ticket['opponent']);
                        $ticketType = sanitizeOutput($ticket['ticket_type']); // BILLETTYPE
                        $section = sanitizeOutput($ticket['section'] ?? '');   // Part of PLADS
                        $row = sanitizeOutput($ticket['row'] ?? '');           // Part of PLADS
                        $seatNumber = sanitizeOutput($ticket['seat_number'] ?? ''); // Part of PLADS
                        $price = number_format((float)($ticket['price'] ?? 0), 2, ',', '.'); // PRIS (We don't have Gebyr)
                        $stadium = sanitizeOutput($ticket['stadium'] ?? 'Ukendt Stadion'); // STED
                        $orderId = sanitizeOutput($ticket['order_id'] ?? ''); // ORDRENUMMER
                        $customerName = sanitizeOutput(($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? '')); // KUNDE

                        // Generate QR text with HMAC
                        $message = "$matchId|$ticketId";
                        $hmac = hash_hmac('sha256', $message, $secretKey);
                        $qrText = "$message|$hmac";

                        $formattedDateTime = 'Ukendt dato';
                        $formattedDate = 'N/A';
                        $formattedTime = 'N/A';
                        $isoDateTime = '';
                        if (!empty($ticket['match_datetime'])) {
                            try {
                                $dateTime = new DateTime($ticket['match_datetime']);
                                $formattedDateTime = $dateTime->format('d/m/Y H:i'); // For display on page
                                $formattedDate = $dateTime->format('l d. F Y'); // Format like "Lørdag d. 21. maj 2022" - Requires locale setup for Danish names
                                $formattedTime = $dateTime->format('H:i');       // Format like "17:00"
                                $isoDateTime = $dateTime->format(DateTime::ISO8601);
                            } catch (Exception $e) {
                            }
                        }

                        // Construct Plads string (example assumes Section C-nedre + D)
                        $pladsString = $stadium; // Start with stadium? Or Tribunenavn?
                        $tribune = "Ukendt Tribune"; // Placeholder - This needs logic or DB data
                        if (stripos($section, 'C') !== false) $tribune = "Ceres Tribunen";
                        if (stripos($section, 'A') !== false) $tribune = "Hørkram Tribunen";
                        if (stripos($section, 'B') !== false) $tribune = "Bravida Tribunen";
                        if (stripos($section, 'D') !== false) $tribune = "Super1Rent Tribunen";

                        $pladsString = $tribune;
                        if ($section) $pladsString .= ", Sektion " . $section;
                        if ($row) $pladsString .= ", Række " . $row;
                        if ($seatNumber) $pladsString .= ", Sæde " . $seatNumber;
                        elseif ($section) $pladsString .= " (unum.)"; // Add unum. if section exists but no seat

                        // Construct Indgang string (Placeholder - Needs data)
                        $indgangString = "Ukendt"; // Placeholder
                        if (stripos($section, 'C') !== false) $indgangString = "Indgang C+D";
                         if (stripos($section, 'A') !== false) $indgangString = "Indgang A";
                        // Add more logic based on section/row

                        $matchTitle = "AGF - " . $opponent; // Used for display/filename

                        ?>
                        <!-- Add all necessary data-* attributes -->
                            <div id="ticket-<?php echo $ticketId; ?>" class="ticket-container mt-4 p-4 border border-gray-200 rounded-lg bg-gray-50 shadow-sm hover:shadow-md transition-shadow duration-200"
                                data-ticket-id="<?php echo $ticketId; ?>"
                                data-match-title="<?php echo htmlspecialchars($matchTitle); ?>"
                                data-opponent="<?php echo htmlspecialchars($opponent); ?>"
                                data-datetime-iso="<?php echo $isoDateTime; ?>"
                                data-date-formatted="<?php echo htmlspecialchars($formattedDate); ?>"
                                data-time-formatted="<?php echo htmlspecialchars($formattedTime); ?>"
                                data-stadium="<?php echo htmlspecialchars($stadium); ?>"
                                data-ticket-type="<?php echo htmlspecialchars($ticketType); ?>"
                                data-plads="<?php echo htmlspecialchars($pladsString); ?>"
                                data-indgang="<?php echo htmlspecialchars($indgangString); ?>"
                                data-price="<?php echo htmlspecialchars($price); ?>"
                                data-gebyr="0,00"
                                data-order-id="<?php echo htmlspecialchars($orderId); ?>"
                                data-customer-name="<?php echo htmlspecialchars($customerName); ?>"
                                data-qr-text="<?php echo htmlspecialchars($qrText); ?>"
                            >

                            <div id="qr-code" style="display: none;"></div>

                            <h3 class="ticket-match-title text-lg font-semibold text-gray-800 mb-1"><?php echo $matchTitle; ?></h3>
                            <p class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-calendar-alt fa-fw mr-1 opacity-75"></i><span class="ticket-datetime"><?php echo $formattedDateTime; ?></span>
                            </p>
                             <p class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-ticket-alt fa-fw mr-1 opacity-75"></i><span class="ticket-type"><?php echo $ticketType; ?></span> (<span class="ticket-price"><?php echo $price; ?></span> kr.)
                            </p>
                             <p class="text-sm text-gray-600 mb-3">
                                <i class="fas fa-map-marker-alt fa-fw mr-1 opacity-75"></i><span class="ticket-seat-info"><?php echo $pladsString; ?></span>
                            </p>
                            <div class="flex space-x-2">
                                <button class="print-ticket-btn px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
                                        data-ticket-id="<?php echo $ticketId; ?>"
                                        data-lang-key="printTicket">
                                    <i class="fas fa-print mr-1"></i>Print billet
                                </button>
                            </div>
                        </div>
                        <?php
                    } // End foreach
                } // End else
                ?>
            </div>
        </div>
    </main>

    <?php include '../layout/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qr-creator/dist/qr-creator.min.js"></script>

    <script>
    const langButton = document.getElementById('language-button');
    const langMenu = document.getElementById('language-menu');
    const currentLangSpan = document.getElementById('current-language');
    const langOptions = document.querySelectorAll('.language-option');

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
            const selectedLangCode = option.getAttribute('data-lang');
            const selectedLangName = option.textContent;
            currentLangSpan.textContent = selectedLangName;
            // updateTranslations(selectedLangCode); // Assuming this function exists and handles lang keys
            document.documentElement.lang = selectedLangCode; // Update HTML lang attribute
            langMenu.classList.add('hidden');
            langButton.setAttribute('aria-expanded', 'false');
            langOptions.forEach(opt => opt.classList.remove('bg-gray-100', 'font-semibold'));
            option.classList.add('bg-gray-100', 'font-semibold');
             // You would ideally call a function here to load/apply translations based on data-lang-key attributes
        });
    });

    // --- PDF Generation Script ---
    document.addEventListener('DOMContentLoaded', () => {
        const ticketArea = document.querySelector('.container'); // Or a more specific parent

        
        async function computeHMAC(message, secretKey) {
            const encoder = new TextEncoder();
            const keyData = encoder.encode(secretKey);
            const messageData = encoder.encode(message);
            const cryptoKey = await window.crypto.subtle.importKey(
                'raw', keyData, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
            );
            const signature = await window.crypto.subtle.sign('HMAC', cryptoKey, messageData);
            return Array.from(new Uint8Array(signature)).map(b => b.toString(16).padStart(2, '0')).join('');
        }

        if (ticketArea) {
            ticketArea.addEventListener('click', function(event) {
                const printButton = event.target.closest('.print-ticket-btn');
                if (!printButton) return;

                const ticketContainer = printButton.closest('.ticket-container');
                if (!ticketContainer) { console.error("Could not find ticket container."); return; }

                const data = ticketContainer.dataset; // Get all data-* attributes

                // Basic data check
                if (!data.ticketId || !data.matchTitle || !data.stadium) {
                     console.error("Missing essential data attributes on ticket container.");
                     alert("Fejl: Kunne ikke læse billetdata korrekt.");
                     return;
                }

                // --- Generate PDF ---
                try {
                    QrCreator.render({
                        text: data.qrText,
                        radius: 0.0,
                        ecLevel: 'H',
                        fill: '#000000',
                        background: null,
                        size: 512
                    }, document.querySelector('#qr-code'));

                    const qrCanvas = document.querySelector('#qr-code canvas');
                    const qrImageDataUrl = qrCanvas.toDataURL('image/png')

                    const { jsPDF } = window.jspdf;
                    const ticketWidthMM = 105;
                    const ticketHeightMM = 220;
                    const doc = new jsPDF({
                        orientation: 'p', unit: 'mm', format: [ticketWidthMM, ticketHeightMM]
                    });

                    const pageHeight = ticketHeightMM;
                    const pageWidth = ticketWidthMM;
                    const margin = 8;
                    let yPos = 0;

                    // --- Constants ---
                    const agfRed = '#d81f25';
                    const darkBg = '#1f2237';
                    const lightGrey = '#f3f4f6';
                    const midGrey = '#d1d5db';
                    const textGrey = '#6b7280';
                    const textBlack = '#111827';

                    doc.setFillColor(agfRed);
                    doc.rect(0, yPos, pageWidth, 8, 'F');
                    doc.setFontSize(10); doc.setFont('helvetica', 'bold'); doc.setTextColor(255);
                    doc.text((data.ticketType || 'STANDARD BILLET').toUpperCase(), pageWidth / 2, yPos + 5.5, { align: 'center'});
                    yPos += 8 + 5;

                    const mapAreaY = yPos;
                    const mapAreaHeight = 60;
                    const mapWidth = pageWidth - (margin * 2);
                    const mapImageUrl = 'https://krisba.dk/ks/assets/printTicket/skovensarenaplantegning.jpg';

                    try {
                        // Add the stadium map image
                        doc.addImage(mapImageUrl, 'JPEG', margin, mapAreaY, mapWidth, mapAreaHeight);
                    } catch (imgError) {
                        console.error("Error adding stadium map image:", imgError);
                        // Fallback placeholder if image fails
                        doc.setDrawColor(midGrey); 
                        doc.setLineWidth(0.2);
                        doc.rect(margin, mapAreaY, mapWidth, mapAreaHeight); // Map area rect
                        doc.setFontSize(8); 
                        doc.setTextColor(textGrey);
                        doc.text('[Stadion Oversigt]', margin + mapWidth / 2, mapAreaY + mapAreaHeight / 2, { align: 'center'});
                    }

                    yPos = mapAreaY + mapAreaHeight + 5;


                    // --- 4. Information & Regler Area --- (Remains the same)
                    const infoAreaY = yPos;
                    const infoAreaHeight = 28;
                    doc.setFillColor(darkBg);
                    doc.rect(0, infoAreaY, pageWidth, infoAreaHeight, 'F');
                    yPos += 6;
                    doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(255);
                    doc.text('INFORMATION & REGLER', margin, yPos);
                    yPos += 5;
                    doc.setFontSize(6); doc.setFont('helvetica', 'normal');
                    const rules = [
                           '- Billetten refunderes ikke. Billetten må ikke videresælges til en pris højere end den påtrykte.',
                           '- Kontrol ved indgangen.',
                           '- The following items are not permitted: bottles and cans, food and beverages, fireworks, weapons etc.',
                           '- Ticket and luggage inspection at the entrance.'
                       ];
                    rules.forEach(rule => {
                        if (yPos < infoAreaY + infoAreaHeight - 2) {
                            const ruleLines = doc.splitTextToSize(rule, pageWidth - (margin * 2) - 2);
                            doc.text(ruleLines, margin + 2, yPos);
                            yPos += (ruleLines.length * 2.5);
                        }
                    });
                    yPos = infoAreaY + infoAreaHeight + 5;


                    // --- 5. Sponsor Logo Placeholder ---
                    const logoAreaY = yPos;
                    const logoAreaHeight = 8;
                    const logoWidth = pageWidth - (margin * 2); // Full width minus margins
                    const sponsorImageUrl = 'https://krisba.dk/ks/assets/printTicket/spons.png';

                    try {
                        // Add the sponsor image across the full width
                        doc.addImage(sponsorImageUrl, 'PNG', margin, logoAreaY, logoWidth, logoAreaHeight);
                    } catch (imgError) {
                        console.error("Error adding sponsor image:", imgError);
                        // Fallback placeholder if image fails
                        doc.setDrawColor(midGrey);
                        doc.rect(margin, logoAreaY, logoWidth, logoAreaHeight); // Single full-width rect
                        doc.setFontSize(6); 
                        doc.setTextColor(textGrey);
                        doc.text('[Sponsor Logo]', margin + logoWidth / 2, logoAreaY + logoAreaHeight / 2 + 1, { align: 'center'});
                    }

                    yPos = logoAreaY + logoAreaHeight + 5;


                    // --- 6. Main Ticket Details & QR Code Placeholder --- (Remains the same)
                    const detailAreaY = yPos;
                    const detailCol1X = margin;
                    const detailCol2X = margin + 25;
                    const qrCodeSize = 24;
                    const qrCodeX = pageWidth - margin - qrCodeSize;
                    const qrCodeY = detailAreaY + 2;
                    const valueMaxWidth = qrCodeX - detailCol2X - 3;

                    doc.setFontSize(7); doc.setTextColor(textBlack);
                    function addDetailRowPdf(label, value) {
                        if (yPos < pageHeight - 20) {
                          doc.setFont('helvetica', 'bold');
                          doc.text(label.toUpperCase() + ':', detailCol1X, yPos, { FONT_NAME_BOLD: true });
                          doc.setFont('helvetica', 'normal');
                          const splitValue = doc.splitTextToSize(value || 'N/A', valueMaxWidth);
                          doc.text(splitValue, detailCol2X, yPos);
                          yPos += (splitValue.length * 3) + 1;
                        }
                    }
                    addDetailRowPdf('Sted', data.stadium);
                    const tidspunkt = `${data.dateFormatted || ''} kl. ${data.timeFormatted || ''}`.trim();
                    addDetailRowPdf('Tidspunkt', tidspunkt);
                    addDetailRowPdf('Indgang', data.indgang || 'N/A');
                    addDetailRowPdf('Plads', data.plads);
                    addDetailRowPdf('Pris', `${data.price || '0,00'} DKK`);
                    addDetailRowPdf('Gebyr', `${data.gebyr || '0,00'} DKK`);
                    addDetailRowPdf('Ordrenummer', data.orderId || 'N/A');
                    addDetailRowPdf('Billetnummer', data.ticketId);
                    addDetailRowPdf('Billettype', data.ticketType);

                    // QR Code Placeholder - Simple Box (Remains the same)
                    doc.addImage(qrImageDataUrl, 'PNG', qrCodeX, qrCodeY, qrCodeSize, qrCodeSize);

                    yPos = Math.max(yPos, qrCodeY + qrCodeSize + 5);


                    // --- 7. Footer Image ---
                    const footerImageHeight = 50;
                    const footerImageY = pageHeight - footerImageHeight; // Position from bottom: bar height + image height + spacing
                    const footerImageUrl = 'https://krisba.dk/ks/assets/printTicket/bundstadionticket.jpg';
                    try {
                        doc.addImage(footerImageUrl, 'JPG', 0, footerImageY, pageWidth, footerImageHeight);
                    } catch (imgError) {
                        console.error("Error adding footer image:", imgError);
                        // Fallback placeholder if image fails
                        doc.setFillColor(midGrey);
                        doc.rect(0, footerImageY, pageWidth, footerImageHeight, 'F');
                        doc.setFontSize(8); doc.setTextColor(textGrey);
                        doc.text('[Fejl ved bundbillede]', pageWidth / 2, footerImageY + 10, { align: 'center'});
                    }

                    // --- Save --- (Remains the same)
                    const safeMatchTitle = data.matchTitle.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                    const filename = `AGF_Billet_${safeMatchTitle}_${data.ticketId}.pdf`;
                    doc.save(filename);

                } catch (error) {
                    console.error("Error generating PDF:", error);
                    alert("Der opstod en fejl under generering af PDF-billetten.");
                }
            }); // End button click listener
        } // End if(ticketArea)
    });
    </script>
</body>
</html>