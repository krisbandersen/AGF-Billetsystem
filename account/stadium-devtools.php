<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stadium Zone Map</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    
    .stadium-container {
      position: relative;
      margin: 20px auto;
      width: 800px;
      height: 600px;
    }
    
    #stadiumCanvas {
      border: 1px solid #ddd;
    }
    
    .popup {
      position: absolute;
      background: rgba(0, 0, 0, 0.8);
      color: white;
      padding: 10px 15px;
      border-radius: 5px;
      font-size: 14px;
      display: none;
      z-index: 100;
      pointer-events: none;
      max-width: 250px;
    }
    
    .popup h3 {
      margin: 0 0 8px 0;
      font-size: 16px;
    }
    
    .popup p {
      margin: 0;
    }
    
    .legend {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
      max-width: 800px;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      margin-right: 15px;
      margin-bottom: 8px;
    }
    
    .legend-color {
      width: 20px;
      height: 20px;
      margin-right: 5px;
      border: 1px solid #ccc;
    }
    
    h1 {
      text-align: center;
      margin-bottom: 20px;
    }
    
    /* New UI for zone editing */
    .zone-adjustment-container {
      margin-top: 30px;
      border: 1px solid #ccc;
      padding: 15px;
      width: 800px;
    }
    
    .zone-adjustment-container select {
      margin-bottom: 10px;
      font-size: 16px;
    }
    
    .slider-row {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
    }
    .slider-row label {
      width: 70px;
    }
    .slider-row input[type="range"] {
      margin: 0 10px;
    }
    
    /* Edit mode button styling */
    .edit-mode-button {
      margin-top: 20px;
      padding: 10px 15px;
      font-size: 16px;
      cursor: pointer;
    }
    
    /* Export UI styling */
    .export-container {
      margin-top: 20px;
      width: 800px;
    }
    .export-container textarea {
      width: 100%;
      height: 150px;
      font-family: monospace;
      padding: 10px;
      box-sizing: border-box;
    }
    .export-container button {
      margin-top: 10px;
      padding: 8px 12px;
      font-size: 16px;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <h1>Stadium Zone Map</h1>
  
  <div class="stadium-container">
    <canvas id="stadiumCanvas" width="800" height="600"></canvas>
    <div id="popup" class="popup"></div>
  </div>
  
  <div class="legend">
    <div class="legend-item">
      <div class="legend-color" style="background-color: #7D9A78;"></div>
      <span>ULTRA</span>
    </div>
    <div class="legend-item">
      <div class="legend-color" style="background-color: #5F9EA0;"></div>
      <span>FAMILY</span>
    </div>
    <div class="legend-item">
      <div class="legend-color" style="background-color: #A65258;"></div>
      <span>AWAY</span>
    </div>
    <div class="legend-item">
      <div class="legend-color" style="background-color: #F0C060;"></div>
      <span>VIP</span>
    </div>
  </div>

  <!-- New container for zone adjustments -->
  <div class="zone-adjustment-container">
    <h2>Adjust Zone Coordinates</h2>
    <select id="zoneSelect"></select>
    <div id="slidersContainer"></div>
    <p>
      <em>Tip:</em> Use the button below to toggle “edit mode.” In edit mode every click on the canvas will add a new point to the current zone’s polygon.
    </p>
    <button id="editModeButton" class="edit-mode-button">Enable Edit Mode</button>
    <button id="clearZoneButton" class="edit-mode-button">Clear Current Zone</button>
  </div>

  <!-- Export UI -->
  <div class="export-container">
    <button id="exportButton">Export Zones</button>
    <textarea id="exportOutput" placeholder="Exported zones data will appear here..."></textarea>
  </div>

  <script>
    // Get canvas and context
    const canvas = document.getElementById('stadiumCanvas');
    const ctx = canvas.getContext('2d');
    const popup = document.getElementById('popup');
    
    // Load the stadium image
    const stadiumImg = new Image();
    stadiumImg.src = '../assets/skovensarena.jpg'; 

    // Define initial zones
    let zones = [
    {
        "name": "FAMILY",
        "color": "rgba(95, 158, 160, 0.7)",
        "description": "Special family section with activities for children",
        "path": [
        [
            532.5,
            207.5625
        ],
        [
            577.5,
            131.5625
        ],
        [
            648.5,
            164.5625
        ],
        [
            659.5,
            171.5625
        ],
        [
            668.5,
            180.5625
        ],
        [
            678.5,
            191.5625
        ],
        [
            683.5,
            207.5625
        ],
        [
            687.5,
            220.5625
        ],
        [
            687.5,
            231.5625
        ],
        [
            682.5,
            242.5625
        ],
        [
            677.5,
            251.5625
        ],
        [
            671.5,
            258.5625
        ],
        [
            481.5,
            443.5625
        ],
        [
            471.5,
            452.5625
        ],
        [
            456.5,
            466.5625
        ],
        [
            445.5,
            473.5625
        ],
        [
            435.5,
            481.5625
        ],
        [
            424.5,
            485.5625
        ],
        [
            404.5,
            490.5625
        ],
        [
            392.5,
            493.5625
        ],
        [
            380.5,
            496.5625
        ],
        [
            372.5,
            497.5625
        ],
        [
            368.5,
            497.5625
        ],
        [
            367.5,
            467.5625
        ],
        [
            383.5,
            462.5625
        ],
        [
            391.5,
            460.5625
        ],
        [
            398.5,
            449.5625
        ],
        [
            408.5,
            441.5625
        ],
        [
            419.5,
            432.5625
        ],
        [
            499.5,
            354.5625
        ],
        [
            574.5,
            285.5625
        ],
        [
            600.5,
            261.5625
        ],
        [
            601.5,
            247.5625
        ],
        [
            594.5,
            241.5625
        ]
        ],
        "prices": "From 200 kr. / 120 kr. for children"
    },
    {
        "name": "AWAY",
        "color": "rgba(166, 82, 88, 0.7)",
        "description": "Designated section for away supporters",
        "path": [
        [
            532.5,
            207.5625
        ],
        [
            579.5,
            130.5625
        ],
        [
            481.5,
            84.5625
        ],
        [
            449.5,
            73.5625
        ],
        [
            427.5,
            70.5625
        ],
        [
            409.5,
            71.5625
        ],
        [
            389.5,
            73.5625
        ],
        [
            399.5,
            130.5625
        ],
        [
            426.5,
            124.5625
        ],
        [
            424.5,
            152.5625
        ],
        [
            439.5,
            156.5625
        ],
        [
            452.5,
            165.5625
        ]
        ],
        "prices": "From 220 kr."
    },
    {
        "name": "ULTRA",
        "color": "rgba(125, 154, 120, 0.7)",
        "description": "Section for the most passionate home fans",
        "path": [
        [
            149.5,
            341.5625
        ],
        [
            181.5,
            341.5625
        ],
        [
            183.5,
            349.5625
        ],
        [
            190.5,
            355.5625
        ],
        [
            349.5,
            463.5625
        ],
        [
            367.5,
            466.5625
        ],
        [
            368.5,
            497.5625
        ],
        [
            345.5,
            495.5625
        ],
        [
            328.5,
            493.5625
        ],
        [
            312.5,
            488.5625
        ],
        [
            294.5,
            479.5625
        ],
        [
            276.5,
            468.5625
        ],
        [
            122.5,
            357.5625
        ],
        [
            105.5,
            343.5625
        ],
        [
            93.5,
            322.5625
        ],
        [
            91.5,
            312.5625
        ],
        [
            99.5,
            276.5625
        ],
        [
            153.5,
            309.5625
        ],
        [
            150.5,
            324.5625
        ]
        ],
        "prices": "From 180 kr."
    },
    {
        "name": "VIP",
        "color": "rgba(240, 192, 96, 0.7)",
        "description": "VIP seating with premium views",
        "path": [
        [
            182.5,
            325.5625
        ],
        [
            155.5,
            307.5625
        ],
        [
            136.5,
            296.5625
        ],
        [
            119.5,
            286.5625
        ],
        [
            108.5,
            278.5625
        ],
        [
            110.5,
            268.5625
        ],
        [
            382.5,
            80.5625
        ],
        [
            388.5,
            96.5625
        ],
        [
            395.5,
            121.5625
        ],
        [
            400.5,
            141.5625
        ],
        [
            404.5,
            158.5625
        ],
        [
            361.5,
            189.5625
        ],
        [
            310.5,
            230.5625
        ],
        [
            301.5,
            217.5625
        ],
        [
            282.5,
            227.5625
        ],
        [
            291.5,
            241.5625
        ],
        [
            241.5,
            277.5625
        ]
        ],
        "prices": "From 150 kr."
    }
    ];

    let hoveringZone = false;
    let editMode = false;
    let currentZoneIndex = 0;

    // Draw the stadium and zones
    function drawStadium() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      
      if (stadiumImg.complete) {
        ctx.drawImage(stadiumImg, 0, 0, canvas.width, canvas.height);
      } else {
        ctx.fillStyle = "#f5f5f5";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = "#888";
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.ellipse(400, 350, 300, 200, 0, 0, Math.PI * 2);
        ctx.stroke();
        ctx.strokeStyle = "#000";
        ctx.lineWidth = 1;
        ctx.strokeRect(300, 250, 200, 300);
      }
      
      zones.forEach(zone => {
        ctx.fillStyle = zone.color;
        ctx.beginPath();
        if (zone.path.length > 0) {
          ctx.moveTo(zone.path[0][0], zone.path[0][1]);
          for (let i = 1; i < zone.path.length; i++) {
            ctx.lineTo(zone.path[i][0], zone.path[i][1]);
          }
          ctx.closePath();
          ctx.fill();
          ctx.strokeStyle = "#000";
          ctx.lineWidth = 1;
          ctx.stroke();
        }
      });

      // In edit mode, draw small red circles for each point
      if (editMode) {
        const zone = zones[currentZoneIndex];
        ctx.fillStyle = 'red';
        zone.path.forEach(point => {
          ctx.beginPath();
          ctx.arc(point[0], point[1], 4, 0, Math.PI * 2);
          ctx.fill();
        });
      }
    }

    // Optional grid for positioning
    function drawGrid() {
      ctx.strokeStyle = "#ddd";
      ctx.lineWidth = 0.5;
      for (let x = 0; x <= canvas.width; x += 50) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, canvas.height);
        ctx.stroke();
        ctx.fillStyle = "#999";
        ctx.font = "10px Arial";
        ctx.fillText(x, x + 2, 10);
      }
      for (let y = 0; y <= canvas.height; y += 50) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(canvas.width, y);
        ctx.stroke();
        ctx.fillStyle = "#999";
        ctx.font = "10px Arial";
        ctx.fillText(y, 2, y + 10);
      }
    }
    setTimeout(drawGrid, 1000);

    stadiumImg.onload = drawStadium;
    stadiumImg.onerror = () => {
      console.log("Error loading stadium image, using fallback.");
      drawStadium();
    };
    drawStadium();

    // Helper: Check if a point is inside a polygon
    function isPointInPath(x, y, path) {
      let inside = false;
      for (let i = 0, j = path.length - 1; i < path.length; j = i++) {
        const xi = path[i][0], yi = path[i][1];
        const xj = path[j][0], yj = path[j][1];
        const intersect = ((yi > y) !== (yj > y)) &&
          (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
      }
      return inside;
    }

    // Canvas click event
    canvas.addEventListener('click', (e) => {
      const rect = canvas.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      
      if (editMode) {
        zones[currentZoneIndex].path.push([x, y]);
        drawStadium();
        updateSliders();
        return;
      }
      
      // Normal mode: Show popup if hovering a zone
      for (const zone of zones) {
        if (isPointInPath(x, y, zone.path)) {
          popup.innerHTML = `
            <h3>${zone.name}</h3>
            <p>${zone.description}</p>
            <p><strong>${zone.prices}</strong></p>
          `;
          popup.style.display = 'block';
          popup.style.left = `${e.clientX - rect.left + 15}px`;
          popup.style.top = `${e.clientY - rect.top + 15}px`;
          canvas.style.cursor = 'pointer';
          return;
        }
      }
      popup.style.display = 'none';
      canvas.style.cursor = 'default';
    });

    canvas.addEventListener('mousemove', (e) => {
      if (editMode) return;
      const rect = canvas.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      
      let overZone = false;
      for (const zone of zones) {
        if (isPointInPath(x, y, zone.path)) {
          overZone = true;
          canvas.style.cursor = 'pointer';
          break;
        }
      }
      if (!overZone) {
        canvas.style.cursor = 'default';
        popup.style.display = 'none';
      }
    });

    canvas.addEventListener('mouseleave', () => {
      popup.style.display = 'none';
    });

    // --- Slider UI ---
    const zoneSelect = document.getElementById('zoneSelect');
    const slidersContainer = document.getElementById('slidersContainer');

    function populateZoneSelect() {
      zoneSelect.innerHTML = '';
      zones.forEach((zone, index) => {
        const option = document.createElement('option');
        option.value = index;
        option.textContent = zone.name;
        zoneSelect.appendChild(option);
      });
    }
    populateZoneSelect();

    zoneSelect.addEventListener('change', (e) => {
      currentZoneIndex = parseInt(e.target.value, 10);
      updateSliders();
      drawStadium();
    });

    function updateSliders() {
      slidersContainer.innerHTML = '';
      const zone = zones[currentZoneIndex];
      
      zone.path.forEach((point, pointIndex) => {
        // Slider for X
        const wrapperX = document.createElement('div');
        wrapperX.className = 'slider-row';
        const labelX = document.createElement('label');
        labelX.textContent = `P${pointIndex} X:`;
        wrapperX.appendChild(labelX);
        const sliderX = document.createElement('input');
        sliderX.type = 'range';
        sliderX.min = 0;
        sliderX.max = 800;
        sliderX.value = point[0];
        sliderX.addEventListener('input', () => {
          zone.path[pointIndex][0] = parseInt(sliderX.value, 10);
          xVal.textContent = sliderX.value;
          drawStadium();
        });
        wrapperX.appendChild(sliderX);
        const xVal = document.createElement('span');
        xVal.textContent = point[0];
        wrapperX.appendChild(xVal);
        slidersContainer.appendChild(wrapperX);
        
        // Slider for Y
        const wrapperY = document.createElement('div');
        wrapperY.className = 'slider-row';
        const labelY = document.createElement('label');
        labelY.textContent = `P${pointIndex} Y:`;
        wrapperY.appendChild(labelY);
        const sliderY = document.createElement('input');
        sliderY.type = 'range';
        sliderY.min = 0;
        sliderY.max = 600;
        sliderY.value = point[1];
        sliderY.addEventListener('input', () => {
          zone.path[pointIndex][1] = parseInt(sliderY.value, 10);
          yVal.textContent = sliderY.value;
          drawStadium();
        });
        wrapperY.appendChild(sliderY);
        const yVal = document.createElement('span');
        yVal.textContent = point[1];
        wrapperY.appendChild(yVal);
        slidersContainer.appendChild(wrapperY);
      });
    }
    updateSliders();

    // --- Edit Mode Controls ---
    const editModeButton = document.getElementById('editModeButton');
    const clearZoneButton = document.getElementById('clearZoneButton');
    editModeButton.addEventListener('click', () => {
      editMode = !editMode;
      if (editMode) {
        editModeButton.textContent = 'Disable Edit Mode';
        zones[currentZoneIndex].path = [];
      } else {
        editModeButton.textContent = 'Enable Edit Mode';
      }
      updateSliders();
      drawStadium();
    });

    clearZoneButton.addEventListener('click', () => {
      zones[currentZoneIndex].path = [];
      updateSliders();
      drawStadium();
    });

    // --- Export Zones Functionality ---
    const exportButton = document.getElementById('exportButton');
    const exportOutput = document.getElementById('exportOutput');

    exportButton.addEventListener('click', () => {
      // Format the zones data as a JavaScript array
      const exportStr = 'let zones = ' + JSON.stringify(zones, null, 2) + ';';
      exportOutput.value = exportStr;
    });
  </script>
</body>
</html>
