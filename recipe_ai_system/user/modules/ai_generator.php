<?php
// Helper function to convert relative path to full URL
function getFullMediaUrl($relativePath) {
    if (empty($relativePath)) return '';
    if (strpos($relativePath, 'http') === 0) {
        return $relativePath;
    }

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    if ($basePath === '/') {
        $basePath = '';
    }

    $fullPath = $basePath . '/' . $relativePath;
    $parts = explode('/', $fullPath);
    $resolved = [];
    foreach ($parts as $part) {
        if ($part === '.' || $part === '') continue;
        if ($part === '..') {
            array_pop($resolved);
        } else {
            $resolved[] = $part;
        }
    }

    $finalPath = implode('/', $resolved);
    return $protocol . '://' . $host . '/' . ltrim($finalPath, '/');
}
?>

<div class="container">
    <div class="content-section ai-chat-section active" id="aiSection">
        <div class="section-header">
            <i class="fas fa-robot"></i>
            <div>
                <h2>AI Recipe Generator</h2>
                <p style="font-size: 0.9rem; opacity: 0.9;">Enter your ingredients and get instant recipe suggestions!</p>
            </div>
        </div>

        <!-- AI Chat Messages -->
        <div class="chat-messages" id="aiChatMessages">
            <?php if (empty($_POST['ingredients'])): ?>
                <div class="message">
                    <div class="message-avatar"><i class="fas fa-robot"></i></div>
                    <div class="message-content">
                        <p>Hello! 👋 I'm your Recipe AI. Type ingredients like <strong>"chicken, garlic, soy sauce, vinegar, onion"</strong> to get started!</p>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem; opacity: 0.8;">💡 Tip: The more ingredients you provide, the better matches you'll get!</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($_POST['ingredients'])): ?>
                <?php
                $input = trim($_POST['ingredients']);
                $userIngredients = array_filter(array_map('trim', explode(',', strtolower($input))));

                $recipes = [];
                if (!empty($userIngredients)) {
                    // Sanitize
                    $userIngredients = array_map('strtolower', $userIngredients);

                    // Build placeholders
                    $placeholders = str_repeat('?,', count($userIngredients) - 1) . '?';

                    // ✅ USE FLEXIBLE MATCHING (like your API)
                    $sql = "
                        SELECT 
                            r.id,
                            r.title,
                            r.instructions,
                            r.time,
                            r.difficulty,
                            r.servings,
                            r.image_url,
                            r.video_url,
                            GROUP_CONCAT(ri.ingredient_name ORDER BY ri.id) as ingredient_list
                        FROM recipes r
                        INNER JOIN recipe_ingredients ri ON r.id = ri.recipe_id
                        WHERE LOWER(ri.ingredient_name) IN ($placeholders)
                        GROUP BY r.id, r.title, r.instructions, r.time, r.difficulty, r.servings, r.image_url, r.video_url
                        HAVING COUNT(DISTINCT ri.ingredient_name) >= 3
                           OR (COUNT(DISTINCT ri.ingredient_name) / (
                                SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = r.id
                              )) >= 0.5
                        ORDER BY COUNT(DISTINCT ri.ingredient_name) DESC
                        LIMIT 5
                    ";

                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $types = str_repeat('s', count($userIngredients));
                        $stmt->bind_param($types, ...$userIngredients);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $recipes = $result->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                    }
                }

                // User message
                ?>
                <div class="message user">
                    <div class="message-avatar"><?= htmlspecialchars($current_user['initials']) ?></div>
                    <div class="message-content">
                        <p><?= htmlspecialchars($_POST['ingredients']) ?></p>
                    </div>
                </div>

                <?php if (empty($recipes)): ?>
                    <div class="message">
                        <div class="message-avatar"><i class="fas fa-robot"></i></div>
                        <div class="message-content">
                            <p>Sorry, I couldn't find any recipes with those ingredients. 😔<br><br>Try common Filipino ingredients like: <strong>chicken, pork, soy sauce, vinegar, garlic, onion, tomato, fish sauce, rice</strong></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="message">
                        <div class="message-avatar"><i class="fas fa-robot"></i></div>
                        <div class="message-content">
                            <p>Great! I found <strong><?= count($recipes) ?> recipe<?= count($recipes) > 1 ? 's' : '' ?></strong> you can make! 🍳<br><br>Based on your ingredients: <strong><?= htmlspecialchars($_POST['ingredients']) ?></strong></p>
                        </div>
                    </div>

                    <?php foreach ($recipes as $recipe): 
                        $recipeIngredients = array_map('trim', explode(',', $recipe['ingredient_list']));
                        $matched = array_filter($recipeIngredients, function($ing) use ($userIngredients) {
                            foreach ($userIngredients as $userIng) {
                                if (strpos(strtolower($ing), $userIng) !== false || strpos($userIng, strtolower($ing)) !== false) {
                                    return true;
                                }
                            }
                            return false;
                        });
                        $matched = array_values($matched);
                        $needs = array_values(array_diff($recipeIngredients, $matched));
                        $matchPercentage = count($recipeIngredients) > 0 ? round((count($matched) / count($recipeIngredients)) * 100) : 0;
                    ?>

                    <div class="message">
                        <div class="message-avatar"><i class="fas fa-robot"></i></div>
                        <div class="message-content" style="max-width: 90%;">
                            <div class="recipe-card">
                                <div class="recipe-card-header">
                                    <div>
                                        <div class="recipe-title"><?= htmlspecialchars($recipe['title']) ?></div>
                                        <div class="recipe-meta">
                                            <div class="recipe-meta-item"><i class="fas fa-clock"></i> <?= htmlspecialchars($recipe['time']) ?></div>
                                            <div class="recipe-meta-item"><i class="fas fa-signal"></i> <?= htmlspecialchars($recipe['difficulty']) ?></div>
                                            <div class="recipe-meta-item"><i class="fas fa-users"></i> <?= htmlspecialchars($recipe['servings']) ?></div>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($recipe['image_url'])): ?>
                                <div class="recipe-media">
                                    <img src="<?= htmlspecialchars(getFullMediaUrl($recipe['image_url'])) ?>" alt="<?= htmlspecialchars($recipe['title']) ?>" class="recipe-image" onerror="this.style.display='none'">
                                </div>
                                <?php endif; ?>

                                <div style="margin: 1rem 0; padding: 0.75rem; background: var(--bg-primary); border-radius: 8px;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                        <div style="flex: 1; height: 8px; background: var(--border-color); border-radius: 10px; overflow: hidden;">
                                            <div style="width: <?= $matchPercentage ?>%; height: 100%; background: linear-gradient(90deg, var(--accent-green), var(--accent-orange)); border-radius: 10px;"></div>
                                        </div>
                                        <span style="font-weight: bold; color: var(--accent-orange); font-size: 0.9rem;"><?= $matchPercentage ?>% Match</span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                        You have <strong style="color: var(--accent-green);"><?= count($matched) ?></strong> of <strong><?= count($recipeIngredients) ?></strong> ingredients
                                    </div>
                                </div>

                                <?php if (!empty($matched)): ?>
                                <div style="margin-bottom: 0.75rem;">
                                    <div style="font-weight: 600; color: var(--accent-green); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-check-circle"></i> You Have (<?= count($matched) ?>):
                                    </div>
                                    <div class="recipe-ingredients">
                                        <?php foreach ($matched as $ing): ?>
                                            <span class="ingredient-tag" style="background: rgba(74, 124, 78, 0.1); border-color: var(--accent-green); color: var(--accent-green);">
                                                <i class="fas fa-check" style="font-size: 0.7rem;"></i> <?= htmlspecialchars($ing) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($needs)): ?>
                                <div style="margin-bottom: 0.75rem;">
                                    <div style="font-weight: 600; color: var(--accent-orange); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-shopping-cart"></i> You Need (<?= count($needs) ?>):
                                    </div>
                                    <div class="recipe-ingredients">
                                        <?php foreach ($needs as $ing): ?>
                                            <span class="ingredient-tag" style="background: rgba(255, 107, 53, 0.1); border-color: var(--accent-orange); color: var(--accent-orange);">
                                                <i class="fas fa-plus" style="font-size: 0.7rem;"></i> <?= htmlspecialchars($ing) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <button class="recipe-select-btn" onclick="toggleRecipeDetails(this, <?= (int)$recipe['id'] ?>)">
                                    <i class="fas fa-book-open"></i> View Full Recipe
                                </button>

                                <div class="recipe-details" id="recipe-details-<?= (int)$recipe['id'] ?>">
                                    <?php if (!empty($recipe['image_url'])): ?>
                                    <div class="recipe-media-full">
                                        <img src="<?= htmlspecialchars(getFullMediaUrl($recipe['image_url'])) ?>" alt="<?= htmlspecialchars($recipe['title']) ?>" class="recipe-image-full">
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($recipe['video_url'])): ?>
                                    <div class="recipe-media-full">
                                        <video controls class="recipe-video" preload="metadata">
                                            <source src="<?= htmlspecialchars(getFullMediaUrl($recipe['video_url'])) ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    </div>
                                    <?php endif; ?>

                                    <div class="recipe-instructions">
                                        <?= nl2br(htmlspecialchars($recipe['instructions'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Input Form -->
        <form method="POST" action="#aiSection" class="chat-input-area">
            <input type="text" name="ingredients" class="chat-input" placeholder="e.g., chicken, soy sauce, vinegar, garlic, onion..." required>
            <button type="submit" class="send-btn">
                <i class="fas fa-paper-plane"></i> Generate
            </button>
        </form>
    </div>
</div>

<script>
    function getFullMediaUrl(relativePath) {
        if (!relativePath) return '';
        if (relativePath.startsWith('http')) return relativePath;
        const protocol = window.location.protocol;
        const host = window.location.host;
        const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
        const cleanPath = relativePath.replace(/^(\.\.\/)+/, '');
        return `${protocol}//${host}${basePath}/${cleanPath}`;
    }

    function toggleRecipeDetails(button, recipeId) {
        const details = document.getElementById(`recipe-details-${recipeId}`);
        if (!details) return;

        details.classList.toggle('active');
        if (details.classList.contains('active')) {
            button.innerHTML = '<i class="fas fa-times"></i> Hide Recipe';
            button.style.background = 'var(--text-secondary)';
        } else {
            button.innerHTML = '<i class="fas fa-book-open"></i> View Full Recipe';
            button.style.background = 'var(--accent-orange)';
        }
    }
</script>