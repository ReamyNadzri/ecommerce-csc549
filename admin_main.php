<?php
include("includes/header.php");
// --- Database Connection ---
require_once 'config/connection.php';

// --- Fetch Products and Build Structured Array ---
$sql = "SELECT * FROM products WHERE IsActive = 1";
$result = $conn->query($sql);
$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = array(
            'ProductID'      => $row['ProductID'],
            'BrandName'      => $row['BrandName'],
            'Flavour'        => $row['Flavour'],
            'SpicyLevel'     => $row['SpicyLevel'],
            'ProductType'    => $row['ProductType'],
            'Description'    => $row['Description'],
            'PriceMYR'       => $row['PriceMYR'],
            'IsVegetarian'   => $row['IsVegetarian'],
            'IngredientList' => $row['IngredientList'],
            'AllergenInfo'   => $row['AllergenInfo'],
            'DietaryTags'    => $row['DietaryTags'],
            'ImagePath'      => $row['ImagePath'],
            'StockQuantity'  => $row['StockQuantity'],
            'IsActive'       => $row['IsActive']
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <style>

        .truncate-one-line {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .truncate-three-lines {
           overflow: hidden;
           display: -webkit-box;
           -webkit-line-clamp: 3;
           -webkit-box-orient: vertical;
        }
    </style>
</head>

<body class="bg-light" style="font-family: 'Poppins', sans-serif;">
    <div class="container my-4">
        <div class="row text-center text-white g-3">
            <div class="col-md-4">
                <div class="p-3 bg-primary rounded">
                    <h3>Value</h3>
                    <p class="mb-0">Total Items</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-success rounded">
                    <h3>Value</h3>
                    <p class="mb-0">Available</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-danger rounded">
                    <h3>Value</h3>
                    <p class="mb-0">Unavailable</p>
                </div>
            </div>
        </div>

        <div class="container my-4">
            <div class="bg-white p-3 rounded shadow-sm">
                <form method="GET" action="">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3 col-md-6">
                            <label for="search" class="form-label fw-bold">Search Items:</label>
                            <input type="text" name="search" id="search" class="form-control" placeholder="Search by name...">
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label for="category" class="form-label fw-bold">Category:</label>
                            <select name="category" id="category" class="form-select">
                                <option value="">All Categories</option>
                                <option value="Food">Food</option>
                                <option value="Drink" selected>Drink</option>
                                <option value="Snack">Snack</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label for="availability" class="form-label fw-bold">Availability:</label>
                            <select name="availability" id="availability" class="form-select">
                                <option value="">All Items</option>
                                <option value="1">Available Only</option>
                                <option value="0">Unavailable Only</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label for="sort" class="form-label fw-bold">Sort By:</label>
                            <select name="sort" id="sort" class="form-select">
                                <option value="name" selected>Name</option>
                                <option value="price">Price</option>
                                <option value="category">Category</option>
                                <option value="created_at">Date Added</option>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-4">
                            <label for="order" class="form-label fw-bold">Order:</label>
                            <select name="order" id="order" class="form-select">
                                <option value="ASC">ASC</option>
                                <option value="DESC">DESC</option>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-6">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-lg-1 col-md-6">
                            <a href="#" class="btn btn-secondary w-100">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center my-4">
            <div>
                <button onclick="showAddForm()" class="btn btn-success">
                    + Add New Item
                </button>
            </div>
            <div class="text-muted">
                Showing 0 of 50 items
            </div>
        </div>
    </div>

    <div class="container mt-4 pb-4" style="width: 140vh;">
        <div class="tab-content" id="productTabContent">
            <div class="tab-pane fade show active" id="food" role="tabpanel">
                <div class="row mt-4">
                    <hr>
                    <div class="menu-container align-items-center bg-white shadow-sm" style="box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
                        <?php foreach ($products as $product) : ?>
                            <div class="scrollable-menu-item">
                                <form method="get" action="items.php">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['ProductID']); ?>">
                                    <input type="hidden" name="brand_name" value="<?php echo htmlspecialchars($product['BrandName']); ?>">
                                    <input type="hidden" name="flavour" value="<?php echo htmlspecialchars($product['Flavour']); ?>">
                                    <input type="hidden" name="price" value="<?php echo htmlspecialchars($product['PriceMYR']); ?>">
                                    <input type="hidden" name="image" value="<?php echo htmlspecialchars($product['ImagePath']); ?>">
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
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="drinks" role="tabpanel">
                <div class="row mt-4">
                    <div class="menu-container">
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    </div>

    <a href="https://wa.me/601133028432" target="_blank" class="whatsapp-btn">
        <svg class="whatsapp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
            <path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z" />
        </svg>
    </a>

    <script>
        function scrollMenu(menuType, direction) {
            const menu = document.getElementById(`${menuType}-menu`);
            const itemWidth = menu.querySelector('.scrollable-menu-item').offsetWidth + 20;
            if (direction === 'left') {
                menu.scrollLeft -= itemWidth;
            } else {
                menu.scrollLeft += itemWidth;
            }
        }
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