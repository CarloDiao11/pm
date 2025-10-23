<?php
// trip-create.php
define('APP_LOADED', true);
include('../../backend/db1.php');
include('../../backend/db.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trip_id = uniqid();
    $driver_id = $_POST['driver_id'];
    $trip_type = $_POST['trip_type'];
    $origin = $_POST['origin'];
    $destination = $_POST['destination'];
    $status = $_POST['status'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // Insert query (removed vehicle_id)
    $sql = "INSERT INTO trips (trip_id, driver_id, trip_type, origin, destination, status, start_time, end_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssss",
        $trip_id,
        $driver_id,
        $trip_type,
        $origin,
        $destination,
        $status,
        $start_time,
        $end_time
    );

    if ($stmt->execute()) {
        echo "<script>alert('✅ Trip created successfully!'); window.location.href='trip-create.php';</script>";
    } else {
        echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Trip</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<div class="max-w-2xl mx-auto mt-10 bg-white p-8 rounded-2xl shadow">
  <h2 class="text-2xl font-bold mb-6 text-gray-800">🚌 Create New Trip</h2>
  <form method="POST" class="space-y-4">

    <!-- DRIVER ID (Manual Input) -->
    <div>
      <label class="block text-gray-700 font-semibold mb-1">Driver ID</label>
      <input type="text" name="driver_id" placeholder="Enter Driver ID (e.g. 1.6850343)" 
             class="w-full border-gray-300 rounded-lg p-2" required>
      <p class="text-sm text-gray-500 mt-1">Tip: You can copy the Driver ID from the Drivers module.</p>
    </div>

    <!-- TRIP TYPE -->
    <div>
      <label class="block text-gray-700 font-semibold mb-1">Trip Type</label>
      <select name="trip_type" required class="w-full border-gray-300 rounded-lg p-2">
        <option value="">-- Select Type --</option>
        <option value="Pickup">Pickup</option>
        <option value="Delivery">Delivery</option>
        <option value="Return">Return</option>
      </select>
    </div>

    <!-- ORIGIN -->
    <div>
      <label class="block text-gray-700 font-semibold mb-1">Origin</label>
      <textarea name="origin" required class="w-full border-gray-300 rounded-lg p-2"></textarea>
    </div>

    <!-- DESTINATION -->
    <div>
      <label class="block text-gray-700 font-semibold mb-1">Destination</label>
      <textarea name="destination" required class="w-full border-gray-300 rounded-lg p-2"></textarea>
    </div>

    <!-- STATUS -->
    <div>
      <label class="block text-gray-700 font-semibold mb-1">Status</label>
      <select name="status" class="w-full border-gray-300 rounded-lg p-2">
        <option value="pending">Pending</option>
        <option value="ongoing">Ongoing</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <!-- START TIME -->
    <div>
      <label class="block text-gray-700 font-semibold mb-1">Start Time</label>
      <input type="datetime-local" name="start_time" class="w-full border-gray-300 rounded-lg p-2">
    </div>

    <!-- END TIME -->
    <div>
      <label class="block text-gray-700 font-semibold mb-1">End Time</label>
      <input type="datetime-local" name="end_time" class="w-full border-gray-300 rounded-lg p-2">
    </div>

    <!-- SUBMIT BUTTON -->
    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
      Create Trip
    </button>
  </form>
</div>

</body>
</html>
