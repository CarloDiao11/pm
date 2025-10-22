<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$recipe_id = (int)$_GET['id'];

// Fetch recipe details
$recipe_query = "SELECT * FROM recipes WHERE id = ?";
$stmt = $conn->prepare($recipe_query);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$recipe_result = $stmt->get_result();
$recipe = $recipe_result->fetch_assoc();
$stmt->close();

if (!$recipe) {
    echo json_encode(['success' => false, 'message' => 'Recipe not found']);
    exit();
}

// Fetch ingredients
$ingredients_query = "SELECT * FROM recipe_ingredients WHERE recipe_id = ?";
$stmt = $conn->prepare($ingredients_query);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$ingredients_result = $stmt->get_result();
$ingredients = [];
while ($row = $ingredients_result->fetch_assoc()) {
    $ingredients[] = $row;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'recipe' => $recipe,
    'ingredients' => $ingredients
]);

$conn->close();
?>