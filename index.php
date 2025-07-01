<?php
// Start session - good practice for when you add login later
session_start();

// --- API-POWERED RECOMMENDATIONS (Logic from index.php) ---
$api_base_url = 'http://127.0.0.1:5000';
$recommended_products = [];
$api_error = false;

// Set the user_id to 1
$user_id = 1;
$api_url = $api_base_url . '/api/recommend/' . $user_id . '?n=10';

// Call the Python API
$json_data = @file_get_contents($api_url);

if ($json_data === false) {
    $api_error = true;
} else {
    $api_response = json_decode($json_data, true);
    if ($api_response && isset($api_response['recommendations'])) {
        $recommended_products = $api_response['recommendations'];
    } else {
        $api_error = true;
    }
}


// --- DATABASE-POWERED PRODUCTS FOR Browse (Logic from index.php) ---
require_once 'config/connection.php';

$browse_products = []; // Array for all browseable products

$sql = "SELECT * FROM products WHERE IsActive = 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $browse_products[] = array(
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
    <?php
    // Header from your original structure
    include("includes/header.php");
    ?>
</head>

<body class="bg-light" style="font-family: 'Poppins', sans-serif;">

    <div class="container mt-4 pb-4" style="width: 140vh;">

        <div id="carouselExampleAutoplaying" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="sources/slide/1.png" class="d-block w-100" alt="Slide 1">
                </div>
                <div class="carousel-item">
                    <img src="sources/slide/2.png" class="d-block w-100" alt="Slide 2">
                </div>
                <div class="carousel-item">
                    <img src="sources/slide/3.png" class="d-block w-100" alt="Slide 3">
                </div>
                <div class="carousel-item">
                    <img src="sources/slide/4.png" class="d-block w-100" alt="Slide 4">
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
        <hr>

        <ul class="nav nav-tabs pt-3" id="productTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="food-tab" data-bs-toggle="tab" data-bs-target="#food" type="button" role="tab" aria-controls="food" aria-selected="true">
                    <i class="bi bi-box"></i> Foods
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="drinks-tab" data-bs-toggle="tab" data-bs-target="#drinks" type="button" role="tab" aria-controls="drinks" aria-selected="false">
                    <i class="bi bi-cup-straw"></i> Drinks
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cart-tab" data-bs-toggle="tab" data-bs-target="#cart" type="button" role="tab" aria-controls="cart" aria-selected="false">
                    <i class="bi bi-cart"></i> Carts
                </button>
            </li>
        </ul>

        <div class="tab-content" id="productTabContent">

            <div class="tab-pane fade show active" id="food" role="tabpanel">
                <div class="row mt-4">
                    <div class="menu-section bg-white shadow-sm" style="box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
                        <h2 class=" text-center section-title">Recommended For You</h2>
                        <div class="position-relative">
                            <style>
                                .scroll-btn {
                                    background: #fff !important; color: #333; border: none;
                                    border-radius: 50%; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                                    transition: transform 0.2s cubic-bezier(.4, 2, .6, 1), box-shadow 0.2s;
                                    width: 40px; height: 40px; display: inline-flex;
                                    align-items: center; justify-content: center; font-size: 1.5rem; z-index: 2;
                                }
                                .scroll-btn:hover { transform: scale(1.5); box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15); }
                            </style>
                            <button class="scroll-btn scroll-btn-left" onclick="scrollMenu('foods', 'left')">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button class="scroll-btn scroll-btn-right" onclick="scrollMenu('foods', 'right')">
                                <i class="bi bi-chevron-right"></i>
                            </button>

                            <div id="foods-menu" class="scrollable-menu-container">
                                <?php if ($api_error || empty($recommended_products)): ?>
                                    <div class="w-100 text-center p-4 text-muted">
                                        <p>Could not load special recommendations right now. Please enjoy Browse our products below!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recommended_products as $product): ?>
                                        <div class="scrollable-menu-item">
                                            <form method="get" action="items.php">
                                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['ProductID'] ?? ''); ?>">
                                                <input type="hidden" name="brand_name" value="<?php echo htmlspecialchars($product['BrandName'] ?? ''); ?>">
                                                <input type="hidden" name="flavour" value="<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>">
                                                <input type="hidden" name="price" value="<?php echo htmlspecialchars($product['PriceMYR'] ?? ''); ?>">
                                                <input type="hidden" name="image" value="<?php echo htmlspecialchars($product['ImagePath'] ?? ''); ?>">
                                                <a href="javascript:;" onclick="this.closest('form').submit();" style="text-decoration: none; color: inherit;">
                                                    <div class="food-image-container">
                                                        <img src="<?php echo htmlspecialchars($product['ImagePath'] ?? 'sources/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>" class="food-image card-img-top">
                                                        <?php if (!empty($product['DietaryTags'])): ?>
                                                            <div class="discount-badge"><?php echo htmlspecialchars($product['DietaryTags'] ?? ''); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h2 class="food-title mb-1"><?php echo htmlspecialchars($product['BrandName'] ?? ''); ?><br>(<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>)</h2>
                                                        <div class="food-price mb-1">
                                                            RM <?php echo htmlspecialchars(number_format((float)($product['PriceMYR'] ?? 0), 2)); ?>
                                                        </div>
                                                        <p class="food-description mb-0"><?php echo htmlspecialchars($product['Description'] ?? ''); ?></p>
                                                    </div>
                                                </a>
                                                <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                                    <button type="submit" class="add-button ms-2" title="Buy Now">→</button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h2 class="align-items-center text-center section-title">Browse More Food!</h2>

                    <div class="menu-container align-items-center bg-white shadow-sm" style="box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
                        <?php foreach ($browse_products as $product): ?>
                            <div class="scrollable-menu-item">
                                <form method="get" action="items.php">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['ProductID'] ?? ''); ?>">
                                    <input type="hidden" name="brand_name" value="<?php echo htmlspecialchars($product['BrandName'] ?? ''); ?>">
                                    <input type="hidden" name="flavour" value="<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>">
                                    <input type="hidden" name="price" value="<?php echo htmlspecialchars($product['PriceMYR'] ?? ''); ?>">
                                    <input type="hidden" name="image" value="<?php echo htmlspecialchars($product['ImagePath'] ?? ''); ?>">
                                    <a href="javascript:;" onclick="this.closest('form').submit();" style="text-decoration: none; color: inherit;">
                                        <div class="food-image-container">
                                            <img src="<?php echo htmlspecialchars($product['ImagePath'] ?? 'sources/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>" class="food-image card-img-top">
                                            <?php if (!empty($product['DietaryTags'])): ?>
                                                <div class="discount-badge"><?php echo htmlspecialchars($product['DietaryTags'] ?? ''); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h2 class="food-title mb-1"><?php echo htmlspecialchars($product['BrandName'] ?? ''); ?><br>(<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>)</h2>
                                            <div class="food-price mb-1">
                                                RM <?php echo htmlspecialchars(number_format((float)($product['PriceMYR'] ?? 0), 2)); ?>
                                            </div>
                                            <p class="food-description mb-0"><?php echo htmlspecialchars($product['Description'] ?? ''); ?></p>
                                        </div>
                                    </a>
                                    <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                        <button type="submit" class="add-button ms-2" title="Buy Now">→</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="drinks" role="tabpanel">
                <div class="tab-pane fade show active" id="food" role="tabpanel">
                    <div class="row mt-4">
                        <div class="menu-container">
                            <div class="menu-item comingsoon"><a href="items.html">
                                    <div class="drink-image-container">
                                        <img src="sources/soyakotak.png" alt="" class="drink-image card-img-top">
                                    </div>
                                    <small class="w3-padding w3-display-right text-muted">250ml</small>
                                    <h2 class="food-title">Yeo's Soya</h2>
                                    <p class="food-price">RM 1.50</p>
                                    <p class="food-description">Minuman soya yang menyegarkan dan kaya dengan protein.</p>
                                </a>
                            </div>
                            <div class="menu-item comingsoon"><a href="item02.html"></a>
                                <div class="drink-image-container">
                                    <img src="sources/tehkotak.png" alt="Beef food" class="drink-image">
                                </div>
                                <small class="w3-padding w3-display-right text-muted">250ml</small>
                                <h2 class="food-title">Yeo's Teh<br>Bunga</h2>
                                <p class="food-price">RM 1.50</p>
                                <p class="food-description">Teh bunga yang harum dan menenangkan, sesuai untuk diminum pada bila-bila masa.</p></a>
                            </div>
                            <div class="menu-item comingsoon"><a href="item03.html">
                                    <div class="drink-image-container">
                                        <img src="sources/lycheekotak.png" alt="Beef food" class="drink-image">
                                    </div>
                                    <small class="w3-padding w3-display-right text-muted">250ml</small>
                                    <h2 class="food-title">Yeo's Lychee</h2>
                                    <p class="food-price">RM 1.50</p>
                                    <p class="food-description">Minuman lychee yang manis dan menyegarkan, sesuai untuk menghilangkan dahaga.</p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="cart" role="tabpanel">
                <div class="text-center py-5">
                    <i class="bi bi-exclamation-circle text-warning" style="font-size: 4rem;"></i>
                    <h3 class="mt-3">Cart Feature Not Available</h3>
                    <p class="text-muted">This feature is currently under development. Please check back later.</p>
                </div>
            </div>

        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    </div>

    <script>
        function scrollMenu(menuType, direction) {
            const menu = document.getElementById(`${menuType}-menu`);
            const itemWidth = menu.querySelector('.scrollable-menu-item').offsetWidth + 20; // width + gap

            if (direction === 'left') {
                menu.scrollLeft -= itemWidth;
            } else {
                menu.scrollLeft += itemWidth;
            }
        }
    </script>
    <a href="https://wa.me/601133028432" target="_blank" class="whatsapp-btn">
        <svg class="whatsapp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
            <path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z" />
        </svg>
    </a>
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
// Close the database connection at the end of the script
$conn->close();
?>