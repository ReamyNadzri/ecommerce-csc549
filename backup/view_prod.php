<?php
session_start();
require_once '../config/connection.php';

// 1. Check if a product ID was provided in the URL
if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    die("Error: No product selected.");
}

$product_id = $_GET['product_id'];

// 2. Prepare a secure query to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM products WHERE ProductID = ? AND IsActive = 1");
$stmt->bind_param("s", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: Product not found or is no longer available.");
}

// 3. Fetch the single product's data into the $product array
$product = $result->fetch_assoc();

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['BrandName'] . ' - ' . $product['Flavour']); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <?php
    // Header from your original structure
    include("../includes/header.php");
    ?>

    <style>
    body { background-color: #f8f9fa; }
    .product-image-container { height: 24rem; }
    .product-image-container img { width: 100%; height: 100%; object-fit: cover; }

    /* Updated Gradient to Shopee's Orange Theme */
    .price-gradient {
        background-image: linear-gradient(to right, #F53D2D, #FF6633);
    }

    /* Updated Button Outline Color to Shopee Orange */
    .btn-custom-outline {
        --bs-btn-color: #EE4D2D;
        --bs-btn-border-color: #EE4D2D;
        --bs-btn-hover-color: #fff;
        --bs-btn-hover-bg: #EE4D2D;
        --bs-btn-hover-border-color: #EE4D2D;
    }

    /* Updated Button Solid Color to Shopee Orange */
    .btn-custom-solid {
        --bs-btn-color: #fff;
        --bs-btn-bg: #EE4D2D;
        --bs-btn-border-color: #EE4D2D;
        --bs-btn-hover-bg: #d94325; /* A slightly darker shade for hover */
        --bs-btn-hover-border-color: #d94325;
    }

    /* Unchanged styles */
    .rating-stars .lucide-star { fill: #facc15; color: #facc15; }
    .lucide-award { color: #f97316; }
</style>



</head>
<body>

    <div class="container-xxl p-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-lg-5">
                <div class="row g-5">
                    <div class="col-lg-6">
                        <div class="bg-light rounded-3 overflow-hidden product-image-container">
                            <img src="<?php echo htmlspecialchars((!empty($product['ImagePath']) ? (strpos($product['ImagePath'], '/') === 0 ? $product['ImagePath'] : '../' . $product['ImagePath']) : 'sources/placeholder.png')); ?>" alt="<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>" class="img-fluid">
                        </div>
                    </div>

                    <div class="col-lg-6 d-flex flex-column gap-4">
                        <div>
                            <h1 class="fs-4 fw-semibold text-dark mb-2"><?php echo htmlspecialchars($product['Flavour']); ?></h1>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <span class="small text-muted">Brand:</span>
                                <a href="#" class="small text-decoration-none"><?php echo htmlspecialchars($product['BrandName']); ?></a>
                            </div>
                        </div>

                        <div class="price-gradient text-white p-3 rounded-3">
                             <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="fs-4 fw-bold">RM <?php echo htmlspecialchars(number_format($product['PriceMYR'], 2)); ?></span>
                                <?php if (!empty($product['DietaryTags'])): ?>
                                    <span class="badge bg-warning text-dark rounded-pill small fw-semibold"><?php echo htmlspecialchars($product['DietaryTags']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="small fw-semibold text-dark mb-3">Specifications:</h3>
                            <div class="d-flex flex-column gap-2">
                                <div class="bg-light p-2 rounded-2">
                                    <div class="small text-muted mb-1">Description:</div>
                                    <div class="small fw-semibold"><?php echo htmlspecialchars($product['Description']); ?></div>
                                </div>
                                <div class="bg-light p-2 rounded-2 d-flex align-items-center gap-2">
                                    <div class="small text-muted mb-1">Product Type:</div>
                                    <div class="small fw-semibold d-flex align-items-center gap-2">
                                        <?php
                                            $type = strtolower($product['ProductType']);
                                            $icons = [
                                                'noodle' => '<img src="https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f35c.svg" alt="Noodle" width="24" title="Noodle">',
                                                'kuetiau' => '<img src="https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f35d.svg" alt="Kuetiau" width="24" title="Kuetiau">',
                                                'bihun' => '<img src="https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f35f.svg" alt="Bihun" width="24" title="Bihun">',
                                                'porridge' => '<img src="https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f35a.svg" alt="Porridge" width="24" title="Porridge">',
                                                'soup' => '<img src="https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f372.svg" alt="Soup" width="24" title="Soup">',
                                                'noodle(dry)' => '<img src="https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f35c.svg" alt="Dry Noodle" width="24" title="Dry Noodle">',
                                            ];
                                            echo isset($icons[$type]) ? $icons[$type] : '';
                                            echo htmlspecialchars($product['ProductType']);
                                        ?>
                                    </div>
                                </div>
                                <div class="bg-light p-2 rounded-2">
                                    <div class="small text-muted mb-1">Key Features:</div>
                                    <div class="small fw-semibold">
                                        <?php
                                            if (!empty($product['KeyFeatures'])) {
                                                // Assume comma-separated
                                                $features = explode(',', $product['KeyFeaturesNotes']);
                                                foreach ($features as $feature) {
                                                    echo '<span class="badge bg-info text-dark me-1 mb-1">' . htmlspecialchars(trim($feature)) . '</span>';
                                                }
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="bg-light p-2 rounded-2">
                                    <div class="small text-muted mb-1">Originality:</div>
                                    <div class="small fw-semibold">
                                        <?php
                                            if (isset($product['Originality']) && $product['Originality'] !== null) {
                                                echo $product['Originality'] ? 'Original' : 'Not Original';
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="bg-light p-2 rounded-2">
                                    <div class="small text-muted mb-1">Updated At:</div>
                                    <div class="small fw-semibold">
                                        <?php
                                            if (!empty($product['UpdatedAt'])) {
                                                echo htmlspecialchars(date('d M Y, H:i', strtotime($product['UpdatedAt'])));
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="bg-light p-2 rounded-2">
                                    <div class="small text-muted mb-1">Spicy Level:</div>
                                    <div class="small fw-semibold">
                                        <?php
                                            $spicyLevel = (int)$product['SpicyLevel'];
                                            $maxLevel = 5;
                                            for ($i = 1; $i <= $maxLevel; $i++) {
                                                if ($i <= $spicyLevel) {
                                                    // Filled fire (orange)
                                                    echo '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:2px;"><path d="M12 2C12 2 17 7.5 17 12.5C17 15.5376 14.5376 18 11.5 18C8.46243 18 6 15.5376 6 12.5C6 7.5 12 2 12 2Z" fill="#FF9800"/><path d="M12 22C14.7614 22 17 19.7614 17 17C17 14.2386 14.7614 12 12 12C9.23858 12 7 14.2386 7 17C7 19.7614 9.23858 22 12 22Z" fill="#FFB74D"/></svg>';
                                                } else {
                                                    // Outlined fire (gray)
                                                    echo '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:2px;"><path d="M12 2C12 2 17 7.5 17 12.5C17 15.5376 14.5376 18 11.5 18C8.46243 18 6 15.5376 6 12.5C6 7.5 12 2 12 2Z" fill="#e0e0e0"/><path d="M12 22C14.7614 22 17 19.7614 17 17C17 14.2386 14.7614 12 12 12C9.23858 12 7 14.2386 7 17C7 19.7614 9.23858 22 12 22Z" fill="#f5f5f5"/></svg>';
                                                }
                                            }
                                        ?>
                                    </div>
                                </div>
                                 <div class="bg-light p-2 rounded-2">
                                    <div class="small text-muted mb-1">Vegetarian:</div>
                                    <div class="small fw-semibold"><?php echo $product['IsVegetarian'] ? 'Yes' : 'No'; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-4">
                            <span class="small text-muted">Quantity:</span>
                            <div class="row g-2 align-items-center" style="width: 180px;">
                                <div class="col-auto px-0">
                                    <button class="btn btn-outline-secondary" type="button" id="minus-btn" disabled>
                                        <i data-lucide="minus" width="16" height="16"></i>
                                    </button>
                                </div>
                                <div class="col px-0">
                                    <input type="text" class="form-control text-center" value="1" id="quantity-input" data-stock="<?php echo htmlspecialchars($product['StockQuantity']); ?>" autocomplete="off">
                                </div>
                                <div class="col-auto px-0">
                                    <button class="btn btn-outline-secondary" type="button" id="plus-btn">
                                        <i data-lucide="plus" width="16" height="16"></i>
                                    </button>
                                </div>
                            </div>
                            <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                lucide.createIcons();
                                const minusBtn = document.getElementById('minus-btn');
                                const plusBtn = document.getElementById('plus-btn');
                                const quantityInput = document.getElementById('quantity-input');
                                const stock = parseInt(quantityInput.getAttribute('data-stock'), 10);

                                function updateButtons(quantity) {
                                    minusBtn.disabled = quantity <= 1 || stock === 0;
                                    plusBtn.disabled = quantity >= stock || stock === 0;
                                }

                                minusBtn.addEventListener('click', function () {
                                    let qty = parseInt(quantityInput.value, 10) || 1;
                                    if (qty > 1) {
                                        qty--;
                                        quantityInput.value = qty;
                                        updateButtons(qty);
                                    }
                                });

                                plusBtn.addEventListener('click', function () {
                                    let qty = parseInt(quantityInput.value, 10) || 1;
                                    if (qty < stock) {
                                        qty++;
                                        quantityInput.value = qty;
                                        updateButtons(qty);
                                    }
                                });

                                quantityInput.addEventListener('input', function () {
                                    let qty = parseInt(quantityInput.value.replace(/\D/g, ''), 10) || 1;
                                    if (qty < 1) qty = 1;
                                    if (qty > stock) qty = stock;
                                    quantityInput.value = qty;
                                    updateButtons(qty);
                                });

                                // Initial check
                                updateButtons(parseInt(quantityInput.value, 10));
                            });
                            </script>
                            <span class="small text-muted"><?php echo htmlspecialchars($product['StockQuantity']); ?> available</span>
                        </div>

                        <div class="d-flex gap-3">
                            
                            <button class="btn btn-custom-solid w-100 py-2 fw-medium d-flex align-items-center justify-content-center gap-2">
                                <i data-lucide="shopping-cart" width="20" height="20"></i>
                                <span>Add to Cart</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            lucide.createIcons();
            const minusBtn = document.getElementById('minus-btn');
            const plusBtn = document.getElementById('plus-btn');
            const quantityInput = document.getElementById('quantity-input');
            const stock = parseInt(quantityInput.getAttribute('data-stock'), 10);

            function updateButtons(quantity) {
                minusBtn.disabled = quantity <= 1;
                plusBtn.disabled = quantity >= stock;
                 if (stock === 0) { // disable both if out of stock
                    minusBtn.disabled = true;
                    plusBtn.disabled = true;
                }
            }

            minusBtn.addEventListener('click', function () { /* ... same logic ... */ });
            plusBtn.addEventListener('click', function () { /* ... same logic ... */ });
            quantityInput.addEventListener('change', function () { /* ... same logic ... */ });

            // Initial check
            updateButtons(parseInt(quantityInput.value, 10));
        });
    </script>
</body>
</html>