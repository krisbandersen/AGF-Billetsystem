<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../php/utils.php';

if (!isset($_SESSION['user_id'])) {
    setFlashMessage('error', 'Du skal være logget ind for at se denne side.'); // Added login check
    redirect('../login.php'); // Redirect to login if not logged in
    exit;
}

// Retrieve match id from query parameter and fetch match data.
$matchId = filter_input(INPUT_GET, 'matchid', FILTER_VALIDATE_INT);
if (!$matchId) {
    setFlashMessage('error', 'Ingen kamp valgt.');
    redirect('tickets.php');
    exit;
}

$match = null;
$fetchError = null;
try {
    $sql = "SELECT match_id, match_datetime, opponent, stadium FROM matches WHERE match_id = :matchid LIMIT 1";
    $matchData = executeQuery($sql, ['matchid' => $matchId]);
    if (empty($matchData)) {
        $fetchError = 'Den valgte kamp blev ikke fundet.';
    } else {
        $match = $matchData[0];
    }
} catch (Exception $e) {
    logEvent('ERROR', "Failed fetching match data for stadium page (ID: $matchId): " . $e->getMessage());
    $fetchError = 'Fejl under hentning af kampdata.';
}

// Redirect if match not found or error occurred
if ($fetchError) {
     setFlashMessage('error', $fetchError);
     redirect('tickets.php');
     exit;
}


// --- Determine Home Match & Format Title ---
// Define your home stadium name(s) accurately
define('HOME_STADIUM_NAME', 'Ceres Park'); // Or an array if multiple names ['Ceres Park', 'Ceres Park Vejlby']

$isHomeMatch = (stripos($match['stadium'], HOME_STADIUM_NAME) !== false);

$matchDatetime = new DateTime($match['match_datetime']);
$dateFormatter = new IntlDateFormatter('da_DK', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'eeee');
$dayName = ucfirst($dateFormatter->format($matchDatetime)); // Get Danish day name

// Dynamic Title Formatting
if ($isHomeMatch) {
    $matchTitle = sprintf(
        "AGF - %s %s d. %s kl. %s", // Home format
        $match['opponent'],
        $dayName,
        $matchDatetime->format('j. F'), // e.g., 13. April
        $matchDatetime->format('H:i')
    );
} else {
     $matchTitle = sprintf(
        "%s - AGF %s d. %s kl. %s", // Away format
        $match['opponent'],
        $dayName,
        $matchDatetime->format('j. F'),
        $matchDatetime->format('H:i')
    );
}


$sectionPrices = [
    'ULTRA' => 180.00,
    'FAMILY' => 200.00,
    'AWAY' => 220.00,
    'VIP' => 450.00, 
];

// Function to get price, falling back to a default if section not found
function getSectionPrice($sectionName, $pricesArray, $defaultPrice = 150.00) {
    return $pricesArray[strtoupper($sectionName)] ?? $defaultPrice;
}

?>
<!DOCTYPE html>
<html lang="da">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AGF Billetvalg - <?php echo htmlspecialchars($match['opponent']); ?></title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* Base styles */
    body { font-family: Arial, sans-serif; }
    .stadium-container { position: relative; margin: 20px auto; width: 100%; max-width: 800px; aspect-ratio: 4/3; }
    #stadiumCanvas { display: block; width: 100%; height: 100%; border: 1px solid #ddd; background-color: #eee; }
    .popup { position: absolute; background: rgba(0,0,0,0.85); color: white; padding: 8px 12px; border-radius: 5px; font-size: 13px; display: none; z-index: 100; pointer-events: none; max-width: 220px; white-space: normal; }
    .popup h3 { margin: 0 0 5px 0; font-size: 15px; font-weight: bold; }
    .popup p { margin: 0 0 3px 0; }
    .legend { display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; margin-top: 15px; max-width: 800px; padding: 0 10px; }
    .legend-item { display: flex; align-items: center; font-size: 12px; }
    .legend-color { width: 18px; height: 18px; margin-right: 5px; border: 1px solid #ccc; display: inline-block; }
    /* Selection area styles */
    #selection-area { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-top: 20px; max-width: 800px; }
    #selection-area h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 10px; }
    #selection-area p { margin-bottom: 5px; }
    /* Add subtle animation */
    #selection-area { transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out; opacity: 0; transform: translateY(10px); }
    #selection-area.visible { opacity: 1; transform: translateY(0); }
  </style>
</head>
<body class="bg-gray-100 text-gray-800">
  <?php include '../layout/navbar.php'; ?>

  <main class="container mx-auto my-8 px-4">
    <?php displayFlashMessages(); // Show flash messages ?>
    <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
      <!-- Navigation Tabs (optional, keep if desired) -->
      <!-- <nav class="mb-6 pb-4 border-b border-gray-200"> ... </nav> -->

      <!-- Match Title -->
      <h2 class="text-xl md:text-2xl font-semibold mb-4 text-center md:text-left">
          Vælg Plads: <?php echo htmlspecialchars($matchTitle); ?>
      </h2>

      <?php if ($isHomeMatch): ?>
        <!-- Home Match: Show dynamic seating map -->
        <p class="text-center text-gray-600 mb-4 text-sm">Klik på en sektion på kortet for at vælge plads.</p>
        <div class="stadium-container">
          <canvas id="stadiumCanvas"></canvas>
          <div id="popup" class="popup"></div>
        </div>
        <div class="legend">
        </div>

        <!-- Selection Area (Hidden initially) -->
        <div id="selection-area" class="hidden">
          <h3>Din Valgte Sektion</h3>
          <p><strong>Sektion:</strong> <span id="selected-section-name"></span></p>
          <p><strong>Pris pr. billet:</strong> <span id="selected-section-price"></span> kr.</p>
          <p class="text-xs text-gray-500 mt-2">Række og sædenummer tildeles automatisk ved tilføjelse til kurv.</p>
          <form action="add_to_cart.php" method="post" class="mt-4">
            <input type="hidden" name="item_type" value="ticket">
            <input type="hidden" name="match_id" value="<?php echo (int)$matchId; ?>">
            <input type="hidden" name="section" id="form-section-name" value="">
            <input type="hidden" name="price" id="form-section-price" value="">
            <input type="hidden" name="ticket_type" id="form-ticket-type" value="Voksen"> <!-- Default type, adjust if needed -->
            <input type="hidden" name="display_title" value="<?php echo htmlspecialchars($matchTitle); ?>">

            <!-- Add quantity selector if needed later -->
            <!-- <label for="quantity">Antal:</label> <input type="number" id="quantity" name="quantity" value="1" min="1" max="10"> -->

            <button type="submit" class="inline-flex justify-center py-2 px-5 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
              <i class="fas fa-cart-plus mr-2"></i> Tilføj til Kurv
            </button>
          </form>
        </div>

      <?php else: ?>
        <!-- Away Match: Show informational message (copied from previous answer) -->
         <div class="text-center p-6 border rounded-lg bg-blue-50 border-blue-200">
            <h3 class="text-lg font-semibold text-blue-800 mb-3">Information om Udebanekamp</h3>
            <p class="mb-2 text-gray-700">
                Dette er en udekamp mod
                <strong><?php echo htmlspecialchars($match['opponent']); ?></strong>
                på
                <strong><?php echo htmlspecialchars($match['stadium']); ?></strong>.
            </p>
            <p class="text-gray-700">
                Billetter til udebaneafsnittet sælges typisk via
                <strong><?php echo htmlspecialchars($match['opponent']); ?>'s</strong>
                officielle hjemmeside eller billetportal. Vi anbefaler, at du besøger deres side for information om billetsalg til udebanefans.
            </p>
            <a href="https://www.google.com/search?q=<?php echo urlencode($match['opponent'] . ' billetter'); ?>" target="_blank" rel="noopener noreferrer" class="mt-4 inline-block text-blue-600 hover:text-blue-800 hover:underline">
                Søg efter <?php echo htmlspecialchars($match['opponent']); ?> billetter <i class="fas fa-external-link-alt fa-xs ml-1"></i>
            </a>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php include '../layout/footer.php'; ?>

  <?php if ($isHomeMatch): ?>
  <script>
    const ORIGINAL_WIDTH = 800;
    const ORIGINAL_HEIGHT = 600;

    const canvas = document.getElementById('stadiumCanvas');
    const ctx = canvas.getContext('2d');
    const popup = document.getElementById('popup');
    const legendContainer = document.querySelector('.legend');
    const selectionArea = document.getElementById('selection-area');
    const selectedSectionNameEl = document.getElementById('selected-section-name');
    const selectedSectionPriceEl = document.getElementById('selected-section-price');
    const formSectionNameInput = document.getElementById('form-section-name');
    const formSectionPriceInput = document.getElementById('form-section-price');
    const formTicketTypeInput = document.getElementById('form-ticket-type'); // Added

    const stadiumImg = new Image();
    stadiumImg.onload = resizeCanvas; // Draw once image is loaded
    stadiumImg.onerror = () => { console.error("Stadium image failed to load."); resizeCanvas(); }; // Draw fallback on error
    stadiumImg.src = '../assets/skovensarena.jpg'; // Ensure this path is correct

    // Get section prices from PHP
    const sectionPrices = <?php echo json_encode($sectionPrices); ?>;

    // Function to safely get price
    function getPriceForSection(sectionName) {
        const upperCaseName = sectionName.toUpperCase();
        return sectionPrices[upperCaseName] !== undefined ? parseFloat(sectionPrices[upperCaseName]).toFixed(2) : 'N/A';
    }

    let zones = [
      { id: "family", name: "FAMILY", color: "rgba(95, 158, 160, 0.1)", hoverColor: "rgba(95, 158, 160, 0.3)", legendColor: "#5F9EA0", description: "Familieafsnit", path: [[532.5,207.5625],[577.5,131.5625],[648.5,164.5625],[659.5,171.5625],[668.5,180.5625],[678.5,191.5625],[683.5,207.5625],[687.5,220.5625],[687.5,231.5625],[682.5,242.5625],[677.5,251.5625],[671.5,258.5625],[481.5,443.5625],[471.5,452.5625],[456.5,466.5625],[445.5,473.5625],[435.5,481.5625],[424.5,485.5625],[404.5,490.5625],[392.5,493.5625],[380.5,496.5625],[372.5,497.5625],[368.5,497.5625],[367.5,467.5625],[383.5,462.5625],[391.5,460.5625],[398.5,449.5625],[408.5,441.5625],[419.5,432.5625],[499.5,354.5625],[574.5,285.5625],[600.5,261.5625],[601.5,247.5625],[594.5,241.5625]], clickable: true },
      { id: "away", name: "AWAY", color: "rgba(166, 82, 88, 0.1)", hoverColor: "rgba(166, 82, 88, 0.3)", legendColor: "#A65258", description: "Udebaneafsnit", path: [[532.5,207.5625],[579.5,130.5625],[481.5,84.5625],[449.5,73.5625],[427.5,70.5625],[409.5,71.5625],[389.5,73.5625],[399.5,130.5625],[426.5,124.5625],[424.5,152.5625],[439.5,156.5625],[452.5,165.5625]], clickable: true },
      { id: "ultra", name: "ULTRA", color: "rgba(125, 154, 120, 0.1)", hoverColor: "rgba(125, 154, 120, 0.3)", legendColor: "#7D9A78", description: "Stemningsafsnit", path: [[149.5,341.5625],[181.5,341.5625],[183.5,349.5625],[190.5,355.5625],[349.5,463.5625],[367.5,466.5625],[368.5,497.5625],[345.5,495.5625],[328.5,493.5625],[312.5,488.5625],[294.5,479.5625],[276.5,468.5625],[122.5,357.5625],[105.5,343.5625],[93.5,322.5625],[91.5,312.5625],[99.5,276.5625],[153.5,309.5625],[150.5,324.5625]], clickable: true },
      { id: "vip", name: "VIP", color: "rgba(240, 192, 96, 0.1)", hoverColor: "rgba(240, 192, 96, 0.3)", legendColor: "#F0C060", description: "VIP", path: [[182.5,325.5625],[155.5,307.5625],[136.5,296.5625],[119.5,286.5625],[108.5,278.5625],[110.5,268.5625],[382.5,80.5625],[388.5,96.5625],[395.5,121.5625],[400.5,141.5625],[404.5,158.5625],[361.5,189.5625],[310.5,230.5625],[301.5,217.5625],[282.5,227.5625],[291.5,241.5625],[241.5,277.5625]], clickable: false } // VIP usually not clickable here
    ];

    let currentHoverZone = null; // Track which zone is being hovered

    // Function to build the legend dynamically
    function buildLegend() {
        legendContainer.innerHTML = ''; // Clear existing
        zones.filter(zone => zone.clickable).forEach(zone => { // Only show clickable zones in legend
            const price = getPriceForSection(zone.name);
            const legendItem = document.createElement('div');
            legendItem.className = 'legend-item';
            legendItem.innerHTML = `
                <span class="legend-color" style="background-color: ${zone.legendColor}; opacity: 0.7;"></span>
                <span>${zone.name} (${price} kr.)</span>
            `;
            legendContainer.appendChild(legendItem);
        });
         // Add non-clickable zone legend items if desired
         zones.filter(zone => !zone.clickable).forEach(zone => {
             const legendItem = document.createElement('div');
             legendItem.className = 'legend-item text-gray-500'; // Style non-clickable differently
             legendItem.innerHTML = `
                 <span class="legend-color" style="background-color: ${zone.legendColor}; opacity: 0.7;"></span>
                 <span>${zone.name}</span>
             `;
             legendContainer.appendChild(legendItem);
         });
    }

    // Function to scale path points
    function scalePath(path, scaleX, scaleY) {
        return path.map(p => [p[0] * scaleX, p[1] * scaleY]);
    }

    // Resize canvas and redraw
    function resizeCanvas() {
      const container = document.querySelector('.stadium-container');
      const rect = container.getBoundingClientRect();
      canvas.width = rect.width; // Use container width
      canvas.height = rect.height; // Use container height
      drawStadium(); // Redraw after resizing
    }

    // Draw stadium image and zones
    function drawStadium() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const scaleX = canvas.width / ORIGINAL_WIDTH;
        const scaleY = canvas.height / ORIGINAL_HEIGHT;

        // Draw background image or fallback
        if (stadiumImg.complete && stadiumImg.naturalWidth > 0) {
             ctx.drawImage(stadiumImg, 0, 0, canvas.width, canvas.height);
        } else {
            // Simple fallback if image fails
            ctx.fillStyle = "#e0e0e0";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = "#a0a0a0";
            ctx.font = `${16 * Math.min(scaleX, scaleY)}px Arial`;
            ctx.textAlign = "center";
            ctx.fillText("Stadion Kort Ikke Tilgængeligt", canvas.width / 2, canvas.height / 2);
        }

        // Draw zones
        zones.forEach(zone => {
            const scaledPath = scalePath(zone.path, scaleX, scaleY);
            ctx.beginPath();
            if (scaledPath.length > 0) {
                ctx.moveTo(scaledPath[0][0], scaledPath[0][1]);
                for (let i = 1; i < scaledPath.length; i++) {
                    ctx.lineTo(scaledPath[i][0], scaledPath[i][1]);
                }
                ctx.closePath();

                // Apply hover effect if this zone is being hovered
                if (zone === currentHoverZone && zone.clickable) {
                    ctx.fillStyle = zone.hoverColor; // Default hover color
                    ctx.fill();
                } else {
                    ctx.fillStyle = zone.color;
                    ctx.fill();
                }
            }
        });
    }

    // Check if point is inside a polygon (using scaled coordinates)
    function isPointInScaledPath(x, y, scaledPath) {
        let inside = false;
        for (let i = 0, j = scaledPath.length - 1; i < scaledPath.length; j = i++) {
            const xi = scaledPath[i][0], yi = scaledPath[i][1];
            const xj = scaledPath[j][0], yj = scaledPath[j][1];
            const intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
        }
        return inside;
    }


    // --- Event Listeners ---

    // Mouse Move for Hover Effects and Popup
    canvas.addEventListener('mousemove', (e) => {
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const scaleX = canvas.width / ORIGINAL_WIDTH;
        const scaleY = canvas.height / ORIGINAL_HEIGHT;

        let foundZone = null;
        for (const zone of zones) {
            const scaledPath = scalePath(zone.path, scaleX, scaleY);
            if (isPointInScaledPath(x, y, scaledPath)) {
                foundZone = zone;
                break;
            }
        }

        if (foundZone) {
             const price = getPriceForSection(foundZone.name);
             popup.innerHTML = `
                 <h3>${foundZone.name}</h3>
                 <p>${foundZone.description}</p>
                 <p><strong>Pris: ${price} kr.</strong></p>
             `;
             popup.style.display = 'block';
             // Position popup near cursor, preventing overflow
             const popupRect = popup.getBoundingClientRect();
             let left = (e.clientX - rect.left + 15);
             let top = (e.clientY - rect.top + 15);
             if (left + popupRect.width > canvas.width) left = x - popupRect.width - 15;
             if (top + popupRect.height > canvas.height) top = y - popupRect.height - 15;
             popup.style.left = `${left}px`;
             popup.style.top = `${top}px`;

             if (foundZone.clickable) {
                canvas.style.cursor = 'pointer';
                if (currentHoverZone !== foundZone) {
                    currentHoverZone = foundZone;
                    drawStadium(); // Redraw for hover effect
                }
             } else {
                 canvas.style.cursor = 'not-allowed';
                 if (currentHoverZone !== null) {
                     currentHoverZone = null;
                     drawStadium(); // Redraw to remove previous hover
                 }
             }
        } else {
            popup.style.display = 'none';
            canvas.style.cursor = 'default';
            if (currentHoverZone !== null) {
                currentHoverZone = null;
                drawStadium(); // Redraw to remove hover effect
            }
        }
    });

    // Mouse Leave
    canvas.addEventListener('mouseleave', () => {
        popup.style.display = 'none';
        canvas.style.cursor = 'default';
        if (currentHoverZone !== null) {
             currentHoverZone = null;
             drawStadium(); // Redraw to remove hover effect
        }
    });

    // Click Listener for Section Selection
    canvas.addEventListener('click', (e) => {
         const rect = canvas.getBoundingClientRect();
         const x = e.clientX - rect.left;
         const y = e.clientY - rect.top;
         const scaleX = canvas.width / ORIGINAL_WIDTH;
         const scaleY = canvas.height / ORIGINAL_HEIGHT;

         let clickedZone = null;
         for (const zone of zones) {
             const scaledPath = scalePath(zone.path, scaleX, scaleY);
             if (isPointInScaledPath(x, y, scaledPath)) {
                 clickedZone = zone;
                 break;
             }
         }

         if (clickedZone && clickedZone.clickable) {
             console.log("Clicked Zone:", clickedZone.name);
             const price = getPriceForSection(clickedZone.name);

             // Update selection area UI
             selectedSectionNameEl.textContent = clickedZone.name;
             selectedSectionPriceEl.textContent = price;

             // Update hidden form fields
             formSectionNameInput.value = clickedZone.name; // Use the zone name for section
             formSectionPriceInput.value = parseFloat(price); // Send numeric price
             formTicketTypeInput.value = clickedZone.name; // Or keep 'Voksen', or derive from section

             // Show the selection area
             selectionArea.classList.remove('hidden');
             void selectionArea.offsetWidth; // Trigger reflow for animation
             selectionArea.classList.add('visible');

             // Scroll to the selection area (optional)
             selectionArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

         } else if (clickedZone && !clickedZone.clickable) {
             console.log("Clicked non-purchasable zone:", clickedZone.name);
              // Optionally hide selection area if a non-clickable zone is clicked after a clickable one
             selectionArea.classList.remove('visible');
             setTimeout(() => { selectionArea.classList.add('hidden'); }, 300); // Hide after animation
         } else {
             console.log("Clicked outside any zone.");
              // Optionally hide selection area if background is clicked
              selectionArea.classList.remove('visible');
              setTimeout(() => { selectionArea.classList.add('hidden'); }, 300);
         }
    });


    // --- Initial Setup ---
    window.addEventListener('resize', resizeCanvas);
    buildLegend(); // Build the legend on load
    // Initial draw is handled by image onload/onerror
  </script>
  <?php endif; ?>
</body>
</html>