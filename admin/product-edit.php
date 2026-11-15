<?php
require __DIR__ . '/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

$categories = $pdo->query("
    SELECT id, name, parent_id, sort_order
    FROM categories
    ORDER BY
        COALESCE(parent_id, id),
        parent_id IS NOT NULL,
        sort_order, id
")->fetchAll();

$product = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'price' => '',
    'discount' => 0,
    'category_id' => 0,
    'is_active' => 1,
];

$images = [];
$errors = [];
$linkedCategories = [];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        header('Location: ' . BASE_URL . '/admin/products.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT category_id FROM product_categories WHERE product_id = ?");
    $stmt->execute([$id]);
    $linkedCategories = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT id, path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $product['name'] = trim($_POST['name'] ?? '');
    $product['slug'] = trim($_POST['slug'] ?? '');
    $product['description'] = trim($_POST['description'] ?? '');
    $product['price'] = $_POST['price'] ?? '';
    $product['discount'] = (int) ($_POST['discount'] ?? 0);
    $selectedCategories = array_values(array_unique(array_filter(
    array_map('intval', $_POST['categories'] ?? []),
    fn($v) => $v > 0
)));
$product['category_id'] = $selectedCategories[0] ?? 0;
    $product['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    if ($product['name'] === '')
        $errors[] = 'Укажите название';
    if ($product['price'] === '' || !is_numeric($product['price']))
        $errors[] = 'Укажите цену';
    if (!$selectedCategories)
        $errors[] = 'Выберите хотя бы одну категорию';
    if ($product['discount'] < 0 || $product['discount'] > 100)
        $errors[] = 'Скидка от 0 до 100';

    // Автогенерация slug, если пусто
    if ($product['slug'] === '') {
        $product['slug'] = makeSlug($product['name']);
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $stmt = $pdo->prepare("
        UPDATE products
        SET name = :name, slug = :slug, description = :desc,
            price = :price, discount = :disc,
            category_id = :cat, is_active = :active
        WHERE id = :id
    ");
                $stmt->execute([
                    'name' => $product['name'],
                    'slug' => $product['slug'],
                    'desc' => $product['description'],
                    'price' => (float) $product['price'],
                    'disc' => (int) $product['discount'],
                    'cat' => (int) $product['category_id'],
                    'active' => (int) $product['is_active'],
                    'id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare("
        INSERT INTO products (name, slug, description, price, discount, category_id, is_active)
        VALUES (:name, :slug, :desc, :price, :disc, :cat, :active)
    ");
                $stmt->execute([
                    'name' => $product['name'],
                    'slug' => $product['slug'],
                    'desc' => $product['description'],
                    'price' => (float) $product['price'],
                    'disc' => (int) $product['discount'],
                    'cat' => (int) $product['category_id'],
                    'active' => (int) $product['is_active'],
                ]);
                $id = (int) $pdo->lastInsertId();
                $isEdit = true;
            }

                        // ============ СИНХРОНИЗАЦИЯ product_categories ============
            // Полная: удаляем снятые привязки, добавляем отмеченные.
            $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")
                ->execute([$id]);

            if ($selectedCategories) {
                $ins = $pdo->prepare("
                    INSERT INTO product_categories (product_id, category_id)
                    VALUES (?, ?)
                ");
                foreach ($selectedCategories as $catId) {
                    $ins->execute([$id, $catId]);
                }
            }

          

            // ============ ЗАГРУЗКА КАРТИНОК ============
            if (!empty($_FILES['images']['name'][0])) {
                uploadProductImages($pdo, $id, $_FILES['images']);
            }

            // ============ УДАЛЕНИЕ КАРТИНОК ============
            $removeIds = $_POST['remove_images'] ?? [];
            foreach ($removeIds as $imgId) {
                $stmt = $pdo->prepare("SELECT path FROM product_images WHERE id = ? AND product_id = ?");
                $stmt->execute([(int) $imgId, $id]);
                $img = $stmt->fetch();
                if ($img) {
                    deleteProductImageFile($img['path']);
                    $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([(int) $imgId]);
                }
            }

            $pdo->commit();

            header('Location: ' . BASE_URL . '/admin/product-edit.php?id=' . $id . '&ok=1');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Admin product save: ' . $e->getMessage());
            $errors[] = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Редактирование товара' : 'Новый товар';
require __DIR__ . '/admin-header.php';
?>

<div class="admin-top">
    <h1 class="admin-h1">
        <?= $isEdit ? 'Редактирование: ' . ae($product['name']) : 'Новый товар' ?>
    </h1>
    <a href="<?= BASE_URL ?>/admin/products.php" class="admin-btn admin-btn--ghost">← К списку</a>
</div>

<?php if (!empty($_GET['ok'])): ?>
    <div class="admin-alert admin-alert--ok">Товар сохранён</div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
    <div class="admin-alert admin-alert--error"><?= ae($err) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf" value="<?= ae($_SESSION['csrf']) ?>">

    <div class="admin-form__grid">
        <div class="admin-form__main">

            <label class="admin-label">
                Название*
                <input type="text" name="name" required value="<?= ae($product['name']) ?>">
            </label>

            <label class="admin-label">
                Slug (URL). Оставьте пустым — сгенерируется из названия
                <input type="text" name="slug" value="<?= ae($product['slug']) ?>">
            </label>

            <label class="admin-label">
                Описание
                <textarea name="description" rows="8"><?= ae($product['description']) ?></textarea>
            </label>

            <label class="admin-label">
                Цена, ₽*
                <input type="number" name="price" step="0.01" min="0" required
                    value="<?= ae((string) $product['price']) ?>">
            </label>

            <label class="admin-label">
                Скидка, % (0–100)
                <input type="number" name="discount" min="0" max="100" value="<?= (int) $product['discount'] ?>">
            </label>

            <div class="admin-label">
    Категории*
    <div class="admin-cats">
        <?php
        $currentParentId = null;
        $currentParentName = '';
        // Собираем id привязанных категорий для быстрой проверки
        $linkedIds = array_column($linkedCategories, 'category_id');
        ?>
        <?php foreach ($categories as $c): ?>
            <?php if ($c['parent_id'] === null): ?>
                <?php if ($currentParentId !== null): ?>
                    </div>
                <?php endif; ?>
                <div class="admin-cats__group">
                    <div class="admin-cats__group-title"><?= ae($c['name']) ?></div>
                    <label class="admin-checkbox">
                        <input type="checkbox" name="categories[]" value="<?= (int) $c['id'] ?>"
                            <?= in_array((int) $c['id'], $linkedIds, true) ? 'checked' : '' ?>>
                        — Вся категория «<?= ae($c['name']) ?>»
                    </label>
                <?php $currentParentId = $c['id']; ?>
            <?php else: ?>
                <label class="admin-checkbox">
                    <input type="checkbox" name="categories[]" value="<?= (int) $c['id'] ?>"
                        <?= in_array((int) $c['id'], $linkedIds, true) ? 'checked' : '' ?>>
                    <?= ae($c['name']) ?>
                </label>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($currentParentId !== null): ?>
            </div>
        <?php endif; ?>
    </div>
    <p class="admin-hint">Отметьте одну или несколько категорий. Первая отмеченная станет основной.</p>
</div>

            <label class="admin-checkbox">
                <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>>
                Активен (отображается в каталоге)
            </label>

        </div>

        <aside class="admin-form__side">

            <h3 class="admin-h3">Картинки</h3>

            <?php if ($images): ?>
                <div class="admin-images">
                    <?php foreach ($images as $img): ?>
                        <div class="admin-image">
                            <img src="<?= ae(productImage($img['path'])) ?>" alt="">
                            <label class="admin-image__remove">
                                <input type="checkbox" name="remove_images[]" value="<?= (int) $img['id'] ?>">
                                Удалить
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="admin-hint">Картинок пока нет</p>
            <?php endif; ?>

            <label class="admin-label">
                Добавить картинки (можно несколько)
                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
            </label>
            <p class="admin-hint">
                Форматы: JPG, PNG, WebP. Максимум 5 МБ на файл.
            </p>

        </aside>
    </div>

    <button type="submit" class="admin-btn admin-btn--primary admin-btn--lg">
        <?= $isEdit ? 'Сохранить изменения' : 'Создать товар' ?>
    </button>
</form>

<?php
/**
 * Загружает картинки для товара.
 * Проверяет MIME, генерирует имена, сохраняет в uploads/products/.
 */
function uploadProductImages(PDO $pdo, int $productId, array $files): void
{
    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    $maxSize = 5 * 1024 * 1024; // 5 MB
    $uploadDir = __DIR__ . '/../uploads/products/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Узнаём текущий максимальный sort_order
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    $sortStart = (int) $stmt->fetchColumn() + 1;

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK)
            continue;
        if ($files['size'][$i] > $maxSize)
            continue;

        $tmp = $files['tmp_name'][$i];

        // Проверяем, что это реально картинка
        $info = @getimagesize($tmp);
        if ($info === false)
            continue;
        if (!in_array($info[2], $allowed, true))
            continue;

        // Формат
        $ext = image_type_to_extension($info[2], false); // jpg, png, webp

        // Уникальное имя
        $filename = $productId . '_' . uniqid() . '.' . $ext;
        $dest = $uploadDir . $filename;

        if (!move_uploaded_file($tmp, $dest))
            continue;

        $path = '/uploads/products/' . $filename;

        $stmt = $pdo->prepare("INSERT INTO product_images (product_id, path, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$productId, $path, $sortStart + $i]);
    }
}

/**
 * Удаляет файл картинки с диска.
 */
function deleteProductImageFile(string $path): void
{
    $full = __DIR__ . '/..' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}
?>

<?php require __DIR__ . '/admin-footer.php'; ?>