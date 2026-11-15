<?php
require __DIR__ . '/auth.php';

// Категории для фильтра
$categories = $pdo->query("
    SELECT id, name, parent_id
    FROM categories
    ORDER BY
        COALESCE(parent_id, id),
        parent_id IS NOT NULL,
        sort_order, id
")->fetchAll();

$categoryFilter = (int) ($_GET['category'] ?? 0);
$search = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if ($categoryFilter > 0) {
    // Проверяем, основная это категория или подкатегория
    $stmt = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
    $stmt->execute([$categoryFilter]);
    $cat = $stmt->fetch();

    if ($cat && $cat['parent_id'] === null) {
        // Основная — товары из неё + из всех её подкатегорий (через product_categories)
        $where[] = 'p.id IN (
            SELECT pc.product_id
            FROM product_categories pc
            JOIN categories c ON c.id = pc.category_id
            WHERE pc.category_id = ?
               OR c.parent_id = ?
        )';
        $params[] = $categoryFilter;
        $params[] = $categoryFilter;
    } else {
        // Подкатегория — только товары, привязанные к ней
        $where[] = 'p.id IN (
            SELECT product_id FROM product_categories WHERE category_id = ?
        )';
        $params[] = $categoryFilter;
    }
}
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.price, p.discount, p.is_active, p.category_id, c.name AS cat_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $whereSql
    ORDER BY p.id DESC
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Первые картинки товаров
attachImages($pdo, $products);

$pageTitle = 'Товары';
require __DIR__ . '/admin-header.php';
?>

<div class="admin-top">
    <h1 class="admin-h1">Товары (<?= count($products) ?>)</h1>
    <a href="<?= BASE_URL ?>/admin/product-edit.php" class="admin-btn admin-btn--primary">
        + Добавить товар
    </a>
</div>

<?php if (!empty($_GET['ok'])): ?>
    <div class="admin-alert admin-alert--ok">Изменения сохранены</div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
    <div class="admin-alert admin-alert--ok">Товар удалён</div>
<?php endif; ?>

<form method="get" class="admin-filters">
    <input type="text" name="q" placeholder="Поиск по названию" value="<?= ae($search) ?>">
    <select name="category">
        <option value="0">Все категории</option>
        <?php $curParent = null; ?>
        <?php foreach ($categories as $c): ?>
            <?php if ($c['parent_id'] === null): ?>
                <?php if ($curParent !== null): ?>
                    </optgroup>
                <?php endif; ?>
                <optgroup label="<?= ae($c['name']) ?>">
                    <option value="<?= (int) $c['id'] ?>" <?= $categoryFilter === (int) $c['id'] ? 'selected' : '' ?>>
                        — Вся категория
                    </option>
                    <?php $curParent = $c['id']; ?>
                <?php else: ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $categoryFilter === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= ae($c['name']) ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($curParent !== null): ?>
            </optgroup>
        <?php endif; ?>
    </select>
    <button type="submit" class="admin-btn">Применить</button>
    <a href="<?= BASE_URL ?>/admin/products.php" class="admin-btn admin-btn--ghost">Сбросить</a>
</form>

<table class="admin-table">
    <thead>
        <tr>
            <th style="width:80px;">Фото</th>
            <th style="width:60px;">ID</th>
            <th>Название</th>
            <th>Категория</th>
            <th style="width:100px;">Цена</th>
            <th style="width:80px;">Скидка</th>
            <th style="width:90px;">Активен</th>
            <th style="width:180px;">Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$products): ?>
            <tr>
                <td colspan="8" style="text-align:center;padding:30px;">Товаров не найдено</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($products as $p): ?>
            <tr>
                <td>
                    <img src="<?= ae(productImage($p['images'][0] ?? null)) ?>" alt="" class="admin-thumb">
                </td>
                <td><?= (int) $p['id'] ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/product.php?id=<?= (int) $p['id'] ?>" target="_blank">
                        <?= ae($p['name']) ?>
                    </a>
                </td>
                <td><?= ae($p['cat_name'] ?? '—') ?></td>
                <td><?= number_format((float) $p['price'], 0, '.', ' ') ?> ₽</td>
                <td><?= (int) $p['discount'] ?>%</td>
                <td>
                    <?php if ($p['is_active']): ?>
                        <span class="admin-badge admin-badge--ok">Да</span>
                    <?php else: ?>
                        <span class="admin-badge admin-badge--no">Нет</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/product-edit.php?id=<?= (int) $p['id'] ?>"
                        class="admin-btn admin-btn--sm">Изменить</a>
                    <a href="<?= BASE_URL ?>/admin/product-delete.php?id=<?= (int) $p['id'] ?>&csrf=<?= ae($_SESSION['csrf']) ?>"
                        class="admin-btn admin-btn--sm admin-btn--danger"
                        onclick="return confirm('Удалить товар «<?= ae($p['name']) ?>»?')">
                        Удалить
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/admin-footer.php'; ?>