<!-- Meal Planner Section -->
<div class="content-section" id="mealPlannerSection">
    <div class="section-header">
        <i class="fas fa-calendar-alt"></i>
        <div>
            <h2>Weekly Meal Planner</h2>
            <p style="font-size: 0.9rem; opacity: 0.9;">Plan your meals for the week ahead</p>
        </div>
    </div>

    <?php
    // Organize meal plans by day and meal_type
    $mealPlanMap = [];
    foreach ($meal_plans as $plan) {
        $mealPlanMap[$plan['day_of_week']][$plan['meal_type']] = [
            'recipe_id' => $plan['recipe_id'],
            'recipe_title' => $plan['recipe_title'] ?? 'Untitled Recipe'
        ];
    }

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $meals = ['Breakfast', 'Lunch', 'Dinner'];
    ?>

    <div class="meal-planner-grid">
        <?php foreach ($days as $day): ?>
            <div class="day-card">
                <div class="day-header"><?= htmlspecialchars($day) ?></div>
                <?php foreach ($meals as $meal): ?>
                    <?php if (isset($mealPlanMap[$day][$meal])): ?>
                        <?php
                        $recipe = $mealPlanMap[$day][$meal];
                        $recipeId = $recipe['recipe_id'] ?? 0;
                        $title = htmlspecialchars($recipe['recipe_title']);
                        $link = $recipeId ? "view_recipe.php?id={$recipeId}" : "#";
                        ?>
                        <div class="meal-slot filled">
                            <a href="<?= $link ?>" style="color: inherit; text-decoration: none;">
                                <?= $title ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="meal-slot" onclick="openAddMealModal('<?= $day ?>', '<?= $meal ?>')">
                            + <?= htmlspecialchars($meal) ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Optional: Add modal for assigning recipes (you can implement this later) -->
<script>
function openAddMealModal(day, mealType) {
    alert(`Assign a recipe for ${mealType} on ${day}`);
    // In the future, you can open a modal with recipe suggestions here
}
</script>