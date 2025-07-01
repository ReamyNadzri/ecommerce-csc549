<?php
include("includes/header.php");
// --- Database Connection ---
require_once 'config/connection.php';

// --- 1. GET SITE-WIDE COUNTS (UNCHANGED) ---
$total_items = 0;
$available_items = 0;
$unavailable_items = 0;
$count_sql = "
    SELECT
        COUNT(ProductID) as total,
        SUM(CASE WHEN IsActive = 1 THEN 1 ELSE 0 END) as available
    FROM products
";
$count_result = $conn->query($count_sql);
if ($count_result) {
    $counts = $count_result->fetch_assoc();
    $total_items = $counts['total'] ?? 0;
    $available_items = $counts['available'] ?? 0;
    $unavailable_items = $total_items - $available_items;
}

// --- 2. CAPTURE AND VALIDATE FILTER INPUTS ---
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$availability_filter = $_GET['availability'] ?? '';
$sort_by = $_GET['sort'] ?? 'name';
$sort_order = $_GET['order'] ?? 'ASC';

// Whitelist for sorting to prevent SQL injection
$sort_columns_whitelist = [
    'name' => 'BrandName',
    'price' => 'PriceMYR',
    'category' => 'ProductType',
    'created_at' => 'ProductID' // Assuming higher ID is newer
];
$sort_column = $sort_columns_whitelist[$sort_by] ?? 'BrandName';
$sort_order_safe = (strtoupper($sort_order) === 'DESC') ? 'DESC' : 'ASC';

// --- 3. BUILD DYNAMIC SQL QUERY ---
$sql = "SELECT * FROM products";
$where_conditions = [];
$params = [];
$types = '';

// Add search condition
if (!empty($search)) {
    $where_conditions[] = "(BrandName LIKE ? OR Flavour LIKE ?)";
    $searchTerm = "%" . $search . "%";
    array_push($params, $searchTerm, $searchTerm);
    $types .= 'ss';
}
// Add category condition
if (!empty($category_filter)) {
    $where_conditions[] = "ProductType = ?";
    $params[] = $category_filter;
    $types .= 's';
}
// Add availability condition
if ($availability_filter !== '') {
    $where_conditions[] = "IsActive = ?";
    $params[] = $availability_filter;
    $types .= 'i';
}

// Append WHERE clause if there are conditions
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}

// Append ORDER BY clause
$sql .= " ORDER BY $sort_column $sort_order_safe";

// --- 4. EXECUTE QUERY WITH PREPARED STATEMENTS ---
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC); // Fetch all results into the array
} else {
    // Handle SQL error
    $products = [];
}
$filtered_item_count = count($products);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        .truncate-one-line { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .truncate-three-lines { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
    </style>
</head>
<body class="bg-light" style="font-family: 'Poppins', sans-serif;">
    <div class="container my-4">
        <div class="row text-center text-white g-3">
            <div class="col-md-4"><div class="p-3 bg-primary rounded"><h3><?php echo $total_items; ?></h3><p class="mb-0">Total Items</p></div></div>
            <div class="col-md-4"><div class="p-3 bg-success rounded"><h3><?php echo $available_items; ?></h3><p class="mb-0">Available</p></div></div>
            <div class="col-md-4"><div class="p-3 bg-danger rounded"><h3><?php echo $unavailable_items; ?></h3><p class="mb-0">Unavailable</p></div></div>
        </div>

        <div class="bg-white p-3 rounded shadow-sm my-4">
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end;">
                <div>
                    <label for="search" class="form-label fw-bold">Search Items:</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div>
                    <label for="category" class="form-label fw-bold">Category:</label>
                    <select name="category" id="category" class="form-select">
                        <option value="">All Categories</option>
                        <option value="Noodle" <?php if ($category_filter == 'Noodle') echo 'selected'; ?>>Noodle</option>
                        <option value="Kuetiau" <?php if ($category_filter == 'Kuetiau') echo 'selected'; ?>>Kuetiau</option>
                        <option value="Bihun" <?php if ($category_filter == 'Bihun') echo 'selected'; ?>>Bihun</option>
                        <option value="Porridge" <?php if ($category_filter == 'Porridge') echo 'selected'; ?>>Porridge</option>
                    </select>
                </div>
                <div>
                    <label for="availability" class="form-label fw-bold">Availability:</label>
                    <select name="availability" id="availability" class="form-select">
                        <option value="">All Items</option>
                        <option value="1" <?php if ($availability_filter === '1') echo 'selected'; ?>>Available Only</option>
                        <option value="0" <?php if ($availability_filter === '0') echo 'selected'; ?>>Unavailable Only</option>
                    </select>
                </div>
                <div>
                    <label for="sort" class="form-label fw-bold">Sort By:</label>
                    <select name="sort" id="sort" class="form-select">
                        <option value="name" <?php if ($sort_by == 'name') echo 'selected'; ?>>Name</option>
                        <option value="price" <?php if ($sort_by == 'price') echo 'selected'; ?>>Price</option>
                        <option value="category" <?php if ($sort_by == 'category') echo 'selected'; ?>>Category</option>
                        <option value="created_at" <?php if ($sort_by == 'created_at') echo 'selected'; ?>>Date Added</option>
                    </select>
                </div>
                <div>
                    <label for="order" class="form-label fw-bold">Order:</label>
                    <select name="order" id="order" class="form-select">
                        <option value="ASC" <?php if ($sort_order == 'ASC') echo 'selected'; ?>>ASC</option>
                        <option value="DESC" <?php if ($sort_order == 'DESC') echo 'selected'; ?>>DESC</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary w-100">Clear</a>
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center my-4">
            <div>
              <a href="add_product.php" class="btn btn-success">+ Add New Item</a>
            </div>
            <div class="text-muted">Showing <?php echo $filtered_item_count; ?> items</div>
        </div>
    </div>

    <div class="container mt-4 pb-4" style="width: 140vh;">
        <div class="tab-content" id="productTabContent">
            <div class="tab-pane fade show active" id="food" role="tabpanel">
                <div class="row mt-4">
                    <hr>
                    <div class="menu-container align-items-center bg-white shadow-sm" style="box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
                        <?php if (empty($products)) : ?>
                            <p class="text-center p-4">No products found matching your criteria.</p>
                        <?php else : ?>
                            <?php foreach ($products as $product) : ?>
                                <div class="scrollable-menu-item">
                                    <form method="get" action="items.php">
                                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['ProductID']); ?>">
                                        <a href="javascript:;" onclick="this.closest('form').submit();" style="text-decoration: none; color: inherit;">
                                            <div class="food-image-container">
                                                <img src="<?php echo htmlspecialchars($product['ImagePath']); ?>" alt="<?php echo htmlspecialchars($product['Flavour']); ?>" class="food-image card-img-top" style="object-fit: cover;">
                                                <?php if (!empty($product['DietaryTags'])) : ?>
                                                    <div class="discount-badge"><?php echo htmlspecialchars($product['DietaryTags']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body flex-grow-1">
                                                <h2 class="food-title mb-1 truncate-three-lines" title="<?php echo htmlspecialchars($product['BrandName']); ?> (<?php echo htmlspecialchars($product['Flavour']); ?>)">
                                                    <?php echo htmlspecialchars($product['BrandName']); ?><br>(<?php echo htmlspecialchars($product['Flavour']); ?>)
                                                </h2>
                                                <div class="food-price mb-1">RM <?php echo htmlspecialchars($product['PriceMYR']); ?></div>
                                                <p class="food-description mb-0 truncate-one-line" title="<?php echo htmlspecialchars($product['Description']); ?>">
                                                    <?php echo htmlspecialchars($product['Description']); ?>
                                                </p>
                                            </div>
                                        </a>
                                        <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                            <button type="button" class="add-button ms-1" title="Delete" disabled>
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    </div>
    <a href="https://wa.me/601133028432" target="_blank" class="whatsapp-btn">
    </a>
    <script>
    </script>
</body>
<footer class="mt-5">
    <div class="container-fluid bg-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <img src="sources/ابيفيوان.png" alt="" style="width:200px">
                    <p class="text-muted mb-1">© 2025 AF Studios. All rights reserved.</p>
                    <small class="text-muted">Made with <i class="bi bi-heart-fill text-danger"></i> for our customers</small>
                </div>
            </div>
        </div>
    </div>
</footer>
</html>
<?php
$conn->close();
?>