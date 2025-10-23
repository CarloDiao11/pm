<?php
define('APP_LOADED', true);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SESSION['role'] !== 'driver' || !isset($_SESSION['driver_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once '../backend/db1.php';
$driver_id = $_SESSION['driver_id'];
$user_id = $_SESSION['user_id'];

// Check ongoing trip
$ongoing_query = "SELECT t.trip_id, t.driver_id, t.vehicle_id, t.trip_type, t.origin, 
                  t.destination, t.status, t.start_time, t.end_time,
                  v.plate_number, v.type as vehicle_type
                  FROM trips t
                  LEFT JOIN vehicles v ON t.vehicle_id = v.vehicle_id
                  WHERE t.driver_id = ? AND t.status = 'ongoing'
                  LIMIT 1";
$ongoing_stmt = $conn->prepare($ongoing_query);
$ongoing_stmt->bind_param("s", $driver_id);
$ongoing_stmt->execute();
$ongoing_result = $ongoing_stmt->get_result();
$ongoing_trip = $ongoing_result->fetch_assoc();
$ongoing_stmt->close();

// Fetch all trips
$trips_query = "SELECT t.trip_id, t.driver_id, t.vehicle_id, t.trip_type, t.origin, 
                t.destination, t.status, t.start_time, t.end_time,
                v.plate_number, v.type as vehicle_type
                FROM trips t
                LEFT JOIN vehicles v ON t.vehicle_id = v.vehicle_id
                WHERE t.driver_id = ?
                ORDER BY t.start_time DESC";
$stmt = $conn->prepare($trips_query);
$stmt->bind_param("s", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$trips = [];
while ($row = $result->fetch_assoc()) {
    $trips[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Trip</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/logout_modal.css">
    
    <style>
        /* Map Container Styles */
        .live-tracking-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0a0a0a;
            z-index: 3000;
            flex-direction: column;
        }
        
        .live-tracking-overlay.active {
            display: flex;
        }
        
        .tracking-header {
            background: #1a1a1a;
            padding: 15px 20px;
            border-bottom: 2px solid #4CAF50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .tracking-title {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }
        
        .tracking-status {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4CAF50;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        #liveMap {
            flex: 1;
            width: 100%;
            min-height: 400px;
        }
        
        .tracking-info-panel {
            background: #1a1a1a;
            padding: 20px;
            border-top: 1px solid #2a2a2a;
            flex-shrink: 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-box {
            background: #222;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        
        .info-label {
            font-size: 11px;
            color: #888;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 700;
            color: #4CAF50;
        }
        
        .complete-trip-btn {
            width: 100%;
            padding: 15px;
            background: #4CAF50;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .complete-trip-btn:hover {
            background: #45a049;
        }
        
        .complete-trip-btn:disabled {
            background: #666;
            cursor: not-allowed;
        }

        /* Custom Leaflet Marker Styles */
        .custom-car-marker {
            background: #2196F3;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(33,150,243,0.6);
            border: 3px solid white;
        }
        
        .custom-dest-marker {
            background: #d32f2f;
            width: 36px;
            height: 45px;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(211,47,47,0.6);
            border: 3px solid white;
        }
        
        .custom-dest-marker i {
            transform: rotate(45deg);
            color: white;
            font-size: 18px;
        }
        
        /* Route info popup styling */
        .leaflet-popup-content-wrapper {
            background: #1a1a1a;
            color: white;
            border: 1px solid #4CAF50;
            border-radius: 8px;
        }
        
        .leaflet-popup-tip {
            background: #1a1a1a;
            border-top: 1px solid #4CAF50;
            border-right: 1px solid #4CAF50;
        }

        /* Trip Cards */
        .content-area {
            flex: 1;
            padding: 30px;
        }
        
        .filter-bar {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #2a2a2a;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
        }
        
        .filter-select {
            background: #2a2a2a;
            border: 1px solid #333;
            color: #fff;
            padding: 10px 15px;
            border-radius: 8px;
            min-width: 150px;
        }
        
        .filter-btn {
            background: #4CAF50;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: auto;
        }
        
        .trips-grid {
            display: grid;
            gap: 20px;
        }
        
        .trip-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .trip-card:hover {
            border-color: #4CAF50;
            transform: translateY(-2px);
        }
        
        .trip-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .trip-id {
            font-size: 12px;
            color: #888;
            font-family: monospace;
        }
        
        .trip-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: rgba(255, 152, 0, 0.1); color: #FF9800; }
        .status-ongoing { background: rgba(33, 150, 243, 0.1); color: #2196F3; }
        .status-completed { background: rgba(76, 175, 80, 0.1); color: #4CAF50; }
        .status-cancelled { background: rgba(211, 47, 47, 0.1); color: #d32f2f; }
        
        .trip-route {
            margin-bottom: 20px;
        }
        
        .route-point {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .route-icon {
            width: 30px;
            height: 30px;
            background: #2a2a2a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .route-icon.origin { color: #4CAF50; }
        .route-icon.destination { color: #d32f2f; }
        
        .route-details {
            flex: 1;
        }
        
        .route-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .route-address {
            font-size: 14px;
            color: #fff;
        }
        
        .trip-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-start { background: #4CAF50; color: #fff; }
        .btn-complete { background: #2196F3; color: #fff; }
        .btn-view { background: #2a2a2a; color: #fff; }
        
        @media (max-width: 768px) {
            .content-area { padding: 20px; }
            .info-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <?php include 'include/sidebar.php'; ?>
    
    <!-- Live Tracking Overlay -->
    <div class="live-tracking-overlay" id="liveTrackingOverlay">
        <div class="tracking-header">
            <div class="tracking-title">
                <i class="fas fa-route"></i> Live Trip Tracking
            </div>
            <div class="tracking-status">
                <span class="status-indicator"></span>
                <span>Tracking Active</span>
            </div>
        </div>
        
        <div id="liveMap"></div>
        
        <div class="tracking-info-panel">
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Distance</div>
                    <div class="info-value" id="liveDistance">0 km</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Duration</div>
                    <div class="info-value" id="liveDuration">00:00</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Speed</div>
                    <div class="info-value" id="liveSpeed">0 km/h</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Est. Fare</div>
                    <div class="info-value" id="liveFare">₱0.00</div>
                </div>
            </div>
            <button class="complete-trip-btn" onclick="completeOngoingTrip()">
                <i class="fas fa-check-circle"></i> Complete Trip
            </button>
        </div>
    </div>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php include 'include/header.php'; ?>
        
        <div class="content-area">
            <div class="filter-bar">
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <button class="filter-btn" onclick="applyFilters()">
                    <i class="fas fa-search"></i> Apply
                </button>
            </div>
            
            <div class="trips-grid" id="tripsGrid"></div>
        </div>
        
        <?php include 'include/footer.php'; ?>
    </main>
    
    <?php include 'include/logout_modal.php'; ?>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
    const allTrips = <?php echo json_encode($trips); ?>;
    const ongoingTrip = <?php echo json_encode($ongoing_trip); ?>;
    const RATE_PER_KM = 35;
    
    let liveMap = null;
    let currentMarker = null;
    let destinationMarker = null;
    let routePolyline = null;
    let actualPathPolyline = null;
    let startTime = null;
    let trackingInterval = null;
    let totalDistance = 0;
    let lastPosition = null;
    let destinationCoords = null;
    let watchId = null;

    if (ongoingTrip) {
        console.log('Ongoing trip detected:', ongoingTrip.trip_id);
    }

    // Geocoding function using Nominatim
    async function geocodeAddress(address) {
        try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address + ', Philippines')}&limit=1`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (data && data.length > 0) {
                return [parseFloat(data[0].lat), parseFloat(data[0].lon)];
            }
            
            console.warn('Geocoding failed for:', address);
            return [14.5995, 120.9842]; // Manila fallback
        } catch (error) {
            console.error('Geocoding error:', error);
            return [14.5995, 120.9842];
        }
    }

    // Get route from OSRM
    async function getOSRMRoute(start, end) {
        try {
            const url = `https://router.project-osrm.org/route/v1/driving/${start[1]},${start[0]};${end[1]},${end[0]}?overview=full&geometries=geojson`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                return data.routes[0];
            }
            return null;
        } catch (error) {
            console.error('OSRM routing error:', error);
            return null;
        }
    }

    // Calculate distance between two coordinates
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth radius in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                 Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                 Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    // Initialize live tracking map
    async function initializeLiveMap() {
        if (liveMap) {
            liveMap.remove();
        }
        
        liveMap = L.map('liveMap', {
            zoomControl: true,
            attributionControl: false
        }).setView([14.5995, 120.9842], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(liveMap);
        
        // Geocode destination
        destinationCoords = await geocodeAddress(ongoingTrip.destination);
        
        // Add destination marker
        const destIcon = L.divIcon({
            className: 'custom-dest-marker',
            html: '<div class="custom-dest-marker"><i class="fas fa-map-marker-alt"></i></div>',
            iconSize: [36, 45],
            iconAnchor: [18, 45]
        });
        
        destinationMarker = L.marker(destinationCoords, { icon: destIcon })
            .addTo(liveMap)
            .bindPopup(`<b>Destination</b><br>${ongoingTrip.destination}`);
        
        // Get current position
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(async (position) => {
                const userLat = position.coords.latitude;
                const userLon = position.coords.longitude;
                
                lastPosition = [userLat, userLon];
                
                // Add current location marker
                const carIcon = L.divIcon({
                    className: 'custom-car-marker',
                    html: '<div class="custom-car-marker"><i class="fas fa-car"></i></div>',
                    iconSize: [40, 40],
                    iconAnchor: [20, 20]
                });
                
                currentMarker = L.marker([userLat, userLon], { icon: carIcon })
                    .addTo(liveMap)
                    .bindPopup('<b>Your Location</b>');
                
                // Initialize actual path polyline (empty at start)
                actualPathPolyline = L.polyline([], {
                    color: '#2196F3',
                    weight: 6,
                    opacity: 0.8,
                    lineJoin: 'round',
                    lineCap: 'round'
                }).addTo(liveMap);
                
                // Get and draw route with ETA
                const route = await getOSRMRoute([userLat, userLon], destinationCoords);
                if (route && route.geometry) {
                    const coords = route.geometry.coordinates.map(c => [c[1], c[0]]);
                    routePolyline = L.polyline(coords, {
                        color: '#FFA500',
                        weight: 5,
                        opacity: 0.7,
                        dashArray: '10, 10'
                    }).addTo(liveMap);
                    
                    // Show route info
                    const distKm = (route.distance / 1000).toFixed(2);
                    const durationMin = Math.round(route.duration / 60);
                    const fare = (distKm * RATE_PER_KM).toFixed(2);
                    
                    // Add route info popup at midpoint
                    const midIdx = Math.floor(coords.length / 2);
                    L.popup({
                        closeButton: false,
                        className: 'route-info-popup'
                    })
                    .setLatLng(coords[midIdx])
                    .setContent(`
                        <div style="text-align:center; padding:8px;">
                            <b>📍 Route Info</b><br>
                            <span style="color:#4CAF50;">Distance: ${distKm} km</span><br>
                            <span style="color:#2196F3;">ETA: ${durationMin} mins</span><br>
                            <span style="color:#FF9800;">Fare: ₱${fare}</span>
                        </div>
                    `)
                    .openOn(liveMap);
                }
                
                // Fit bounds
                const bounds = L.latLngBounds([
                    [userLat, userLon],
                    destinationCoords
                ]);
                liveMap.fitBounds(bounds, { padding: [50, 50] });
                
            }, (error) => {
                console.error('Geolocation error:', error);
                alert('Unable to access your location. Please enable GPS.');
            });
        } else {
            alert('Geolocation is not supported by your browser.');
        }
    }

    // Update route dynamically as driver moves
    let routeUpdateTimeout = null;
    async function updateRouteToDestination(lat, lon) {
        // Debounce route updates (only update every 10 seconds)
        if (routeUpdateTimeout) return;
        
        routeUpdateTimeout = setTimeout(async () => {
            const route = await getOSRMRoute([lat, lon], destinationCoords);
            if (route && route.geometry) {
                // Remove old route line
                if (routePolyline) {
                    liveMap.removeLayer(routePolyline);
                }
                
                // Draw new route
                const coords = route.geometry.coordinates.map(c => [c[1], c[0]]);
                routePolyline = L.polyline(coords, {
                    color: '#FFA500',
                    weight: 5,
                    opacity: 0.7,
                    dashArray: '10, 10'
                }).addTo(liveMap);
            }
            
            routeUpdateTimeout = null;
        }, 10000); // Update every 10 seconds
    }

    // Start location tracking
function startLocationTracking() {
    if (!navigator.geolocation) {
        alert('Geolocation not supported');
        return;
    }
    
    const options = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
    };
    
    watchId = navigator.geolocation.watchPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            const speed = position.coords.speed || 0;
            const accuracy = position.coords.accuracy; // Get accuracy in meters
            
            // IGNORE positions with poor accuracy (more than 50 meters)
            if (accuracy > 50) {
                console.warn('Poor GPS accuracy:', accuracy, 'm - ignoring position');
                return;
            }
            
            // Update marker position
            if (currentMarker) {
                currentMarker.setLatLng([lat, lon]);
                liveMap.panTo([lat, lon]);
            }
            
            // Calculate distance traveled
            if (lastPosition) {
                const dist = calculateDistance(
                    lastPosition[0], lastPosition[1],
                    lat, lon
                );
                
                // Only update if moved > 20m AND speed > 1 km/h (to filter GPS drift when stationary)
                const speedKmh = speed * 3.6;
                if (dist > 0.02 && speedKmh > 1) {
                    totalDistance += dist;
                    document.getElementById('liveDistance').textContent = totalDistance.toFixed(2) + ' km';
                    document.getElementById('liveFare').textContent = '₱' + (totalDistance * RATE_PER_KM).toFixed(2);
                    
                    // Add to actual path
                    if (actualPathPolyline) {
                        actualPathPolyline.addLatLng([lat, lon]);
                    }
                    
                    // Update route dynamically
                    updateRouteToDestination(lat, lon);
                    
                    lastPosition = [lat, lon];
                }
            } else {
                // First position, just set it
                lastPosition = [lat, lon];
            }
            
            // Update speed
            const speedKmh = (speed * 3.6).toFixed(1);
            document.getElementById('liveSpeed').textContent = speedKmh + ' km/h';
            
        }, 
        (error) => {
            console.warn('Position error:', error);
        },
        options
    );
}

    // Update duration counter
    function updateDuration() {
        setInterval(() => {
            if (startTime) {
                const now = new Date();
                const diff = now - startTime;
                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                document.getElementById('liveDuration').textContent = 
                    String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
            }
        }, 1000);
    }

    // Start live tracking
    function startLiveTracking() {
        if (!ongoingTrip) {
            alert('No ongoing trip found');
            return;
        }
        
        document.getElementById('liveTrackingOverlay').classList.add('active');
        startTime = new Date(ongoingTrip.start_time);
        
        setTimeout(() => {
            initializeLiveMap();
            startLocationTracking();
            updateDuration();
        }, 100);
    }

    // Complete trip
    function completeOngoingTrip() {
        if (!confirm('Complete this trip?')) return;
        
        const btn = document.querySelector('.complete-trip-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Completing...';
        
        if (watchId) {
            navigator.geolocation.clearWatch(watchId);
        }
        
        // Calculate final fare based on total distance
        const finalFare = (totalDistance * RATE_PER_KM).toFixed(2);
        
        fetch('../backend/update_trip1.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=complete&trip_id=${ongoingTrip.trip_id}&distance=${totalDistance.toFixed(2)}&fare=${finalFare}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Trip completed successfully!\n\nDistance: ${totalDistance.toFixed(2)} km\nFare Earned: ₱${finalFare}\n\nAmount added to your wallet!`);
                window.location.reload();
            } else {
                alert('Error: ' + data.error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Trip';
            }
        })
        .catch(error => {
            alert('An error occurred');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Trip';
        });
    }

    // Start trip
    function startTrip(tripId) {
        if (confirm('Start this trip now?')) {
            const btn = event.target.closest('.btn-start');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
            
            fetch('../backend/update_trip1.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=start&trip_id=${tripId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-play"></i> Start Trip';
                }
            });
        }
    }

    // Render trips
    function renderTrips(trips) {
        const grid = document.getElementById('tripsGrid');
        if (trips.length === 0) {
            grid.innerHTML = '<div style="text-align:center; padding:60px; color:#666;"><i class="fas fa-route" style="font-size:64px; opacity:0.3; margin-bottom:20px;"></i><h3>No trips found</h3></div>';
            return;
        }
        
        grid.innerHTML = trips.map(trip => `
            <div class="trip-card">
                <div class="trip-header">
                    <div class="trip-id">#${trip.trip_id}</div>
                    <div class="trip-status status-${trip.status}">${trip.status}</div>
                </div>
                <div class="trip-route">
                    <div class="route-point">
                        <div class="route-icon origin"><i class="fas fa-circle"></i></div>
                        <div class="route-details">
                            <div class="route-label">Origin</div>
                            <div class="route-address">${trip.origin}</div>
                        </div>
                    </div>
                    <div class="route-point">
                        <div class="route-icon destination"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="route-details">
                            <div class="route-label">Destination</div>
                            <div class="route-address">${trip.destination}</div>
                        </div>
                    </div>
                </div>
                <div class="trip-actions">
                    ${trip.status === 'pending' ? `<button class="btn btn-start" onclick="startTrip('${trip.trip_id}')"><i class="fas fa-play"></i> Start Trip</button>` : ''}
                    ${trip.status === 'ongoing' ? `<button class="btn btn-complete" onclick="startLiveTracking()"><i class="fas fa-map-marked-alt"></i> View Live Tracking</button>` : ''}
                </div>
            </div>
        `).join('');
    }

    // Apply filters
    function applyFilters() {
        const statusFilter = document.getElementById('statusFilter').value;
        let filtered = [...allTrips];
        if (statusFilter) filtered = filtered.filter(trip => trip.status === statusFilter);
        renderTrips(filtered);
    }

    // Initial render
    renderTrips(allTrips);
</script>
</body>
</html>