<?php
/**
 * Главная страница — каталог товаров.
 * Поддерживает:
 *   ?category=slug  — категория (основная или подкатегория)
 *   ?q=строка       — поиск
 *   ?promo=1        — акции (товары со скидкой)
 *   ?page=N         — пагинация
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// ============ 1. ПАРАМЕТРЫ ЗАПРОСА ============
$categorySlug = trim($_GET['category'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');
$isPromo = !empty($_GET['promo']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// ============ 2. ТЕКУЩАЯ КАТЕГОРИЯ ============
$currentCategory = null;
if ($categorySlug !== '') {
    $stmt = $pdo->prepare("
        SELECT id, name, slug, parent_id
        FROM categories
        WHERE slug = ?
    ");
    $stmt->execute([$categorySlug]);
    $currentCategory = $stmt->fetch();

    if (!$currentCategory) {
        http_response_code(404);
        $pageTitle = 'Категория не найдена';
        require __DIR__ . '/includes/header.php';
        echo '<p>Такой категории нет. <a href="' . BASE_URL . '/">Вернуться в каталог</a></p>';
        require __DIR__ . '/includes/footer.php';
        exit;
    }
}

// ============ 3. ПОДКАТЕГОРИИ И РОДИТЕЛЬ ============
$subcategories = [];
$parentCategory = null;

if ($currentCategory) {
    if ($currentCategory['parent_id'] === null) {
        // Открыта основная категория — грузим её подкатегории.
        // 9, 10, 11 (Для женщин/мужчин/пар) скрываем — они дублируют основные разделы.
        $stmt = $pdo->prepare("
        SELECT id, name, slug
        FROM categories
        WHERE parent_id = ?
          AND id NOT IN (9, 10, 11)
        ORDER BY sort_order, id
    ");
        $stmt->execute([$currentCategory['id']]);
        $subcategories = $stmt->fetchAll();
    } else {
        // Открыта подкатегория — грузим родителя
        $stmt = $pdo->prepare("SELECT id, name, slug FROM categories WHERE id = ?");
        $stmt->execute([$currentCategory['parent_id']]);
        $parentCategory = $stmt->fetch();
    }
}

// ============ 4. WHERE-УСЛОВИЯ ============
$where = ['p.is_active = 1'];
$params = [];

if ($currentCategory) {
    if ($currentCategory['parent_id'] === null) {
        // Основная категория: товары из неё + из всех её подкатегорий.
        // Привязки берём из product_categories (many-to-many).
        $where[] = 'p.id IN (
            SELECT pc.product_id
            FROM product_categories pc
            JOIN categories c ON c.id = pc.category_id
            WHERE pc.category_id = :cat_id1
               OR c.parent_id = :cat_id2
        )';
        $params['cat_id1'] = $currentCategory['id'];
        $params['cat_id2'] = $currentCategory['id'];
    } else {
        // Подкатегория: только товары, привязанные к ней
        $where[] = 'p.id IN (
            SELECT product_id FROM product_categories WHERE category_id = :cat_id1
        )';
        $params['cat_id1'] = $currentCategory['id'];
    }
}

if ($isPromo) {
    $where[] = 'p.discount > 0';
}

if ($searchQuery !== '') {
    $where[] = '(p.name LIKE :q1 OR p.description LIKE :q2)';
    $params['q1'] = '%' . $searchQuery . '%';
    $params['q2'] = '%' . $searchQuery . '%';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// ============ 5. ОБЩЕЕ КОЛИЧЕСТВО ============
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalProducts / $perPage));

// ============ 6. ТОВАРЫ ============
$sql = "
    SELECT p.id, p.name, p.slug, p.price, p.discount, p.description
    FROM products p
    $whereSql
    ORDER BY p.id DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

attachImages($pdo, $products);

// ============ 7. ЗАГОЛОВОК ============
if ($isPromo) {
    $pageTitle = 'Акции';
} elseif ($currentCategory) {
    $pageTitle = $currentCategory['name'];
} elseif ($searchQuery !== '') {
    $pageTitle = 'Поиск: ' . $searchQuery;
} else {
    $pageTitle = 'Каталог';
}

require __DIR__ . '/includes/header.php';
?>

<div class="catalog-wrap">

    <?php if ($parentCategory): ?>
        <div class="catalog-back">
            <a href="<?= BASE_URL ?>/?category=<?= urlencode($parentCategory['slug']) ?>">
                ← <?= e($parentCategory['name']) ?>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($currentCategory || $searchQuery !== '' || $isPromo): ?>
        <div class="catalog-top">
            <h1 class="catalog-top__title">
                <?php if ($isPromo): ?>
                     Акции
                <?php elseif ($currentCategory): ?>
                    <?= e($currentCategory['name']) ?>
                <?php else: ?>
                    Результаты поиска: «<?= e($searchQuery) ?>»
                <?php endif; ?>
            </h1>
            <a href="<?= BASE_URL ?>/" class="catalog-top__reset">Сбросить</a>
        </div>
    <?php endif; ?>

    <?php if ($subcategories): ?>
        <div class="subcategories">
            <?php foreach ($subcategories as $sub): ?>
                <a href="<?= BASE_URL ?>/?category=<?= urlencode($sub['slug']) ?>" class="subcategory">
                    <?= e($sub['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$products): ?>
        <div class="catalog-empty">
            <p>Ничего не найдено.</p>
            <a href="<?= BASE_URL ?>/" class="btn btn--outline">В каталог</a>
        </div>
    <?php else: ?>

        <div class="catalog-grid">
            <?php foreach ($products as $p): ?>
                <?php
                $price = finalPrice($p);
                $mainImage = productImage($p['images'][0] ?? null);
                ?>
                <article class="card">
                    <a href="<?= BASE_URL ?>/product.php?id=<?= (int) $p['id'] ?>" class="card__link">
                        <div class="card__image">
                            <img src="<?= e($mainImage) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                        </div>
                    </a>
                    <div class="card__body">
                        <h3 class="card__name">
                            <a href="<?= BASE_URL ?>/product.php?id=<?= (int) $p['id'] ?>">
                                <?= e($p['name']) ?>
                            </a>
                        </h3>
                        <p class="card__desc"><?= e(shortText($p['description'], 70)) ?></p>
                        <div class="card__price">
                            <?php if ((int) $p['discount'] > 0): ?>
                                <s class="card__price-old"><?= formatPrice((float) $p['price']) ?></s>
                            <?php endif; ?>
                            <span class="card__price-final"><?= formatPrice($price) ?></span>
                        </div>
                        <button class="btn btn--cart js-add-to-cart" data-id="<?= (int) $p['id'] ?>" type="button">
                            В корзину
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Страницы">
                <?php
                $qs = $_GET;
                for ($i = 1; $i <= $totalPages; $i++):
                    $qs['page'] = $i;
                    $url = '?' . http_build_query($qs);
                    ?>
                    <a href="<?= e($url) ?>" class="pagination__item<?= $i === $page ? ' is-active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>