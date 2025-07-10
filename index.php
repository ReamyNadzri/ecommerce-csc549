<?php
// Start session - good practice for when you add login later
session_start();



$api_base_url = 'http://127.0.0.1:5000';
$recommended_products = [];
$api_error = false;

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => '', 'cart_count' => 0];

    switch ($_POST['action']) {
        case 'add_to_cart':
            $product_id = $_POST['product_id'];
            // ### NEW: Get the quantity from the form, default to 1 ###
            $quantity_to_add = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

            // --- The rest is similar but uses the new quantity variable ---
            $brand_name = $_POST['brand_name'];
            $flavour = $_POST['flavour'];
            $price = (float)$_POST['price'];
            $image = $_POST['image'];
            $description = $_POST['description'] ?? '';

            $found = false;
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['product_id'] == $product_id) {
                    // ### MODIFIED: Instead of ++, we add the specified quantity ###
                    $item['quantity'] += $quantity_to_add;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $_SESSION['cart'][] = [
                    'product_id' => $product_id,
                    'brand_name' => $brand_name,
                    'flavour' => $flavour,
                    'price' => $price,
                    'image' => $image,
                    'description' => $description,
                    // Use the specified quantity for new items too
                    'quantity' => $quantity_to_add
                ];
            }

            $response['success'] = true;
            $response['message'] = 'Item(s) added to cart!';
            $response['cart_count'] = array_sum(array_column($_SESSION['cart'], 'quantity'));
            break;

        case 'remove_from_cart':
            $product_id = $_POST['product_id'];
            $_SESSION['cart'] = array_filter($_SESSION['cart'], function ($item) use ($product_id) {
                return $item['product_id'] != $product_id;
            });
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array

            $response['success'] = true;
            $response['message'] = 'Item removed from cart';
            $response['cart_count'] = array_sum(array_column($_SESSION['cart'], 'quantity'));
            break;

        case 'update_quantity':
            $product_id = $_POST['product_id'];
            $quantity = (int)$_POST['quantity'];

            if ($quantity <= 0) {
                $_SESSION['cart'] = array_filter($_SESSION['cart'], function ($item) use ($product_id) {
                    return $item['product_id'] != $product_id;
                });
                $_SESSION['cart'] = array_values($_SESSION['cart']);
            } else {
                foreach ($_SESSION['cart'] as &$item) {
                    if ($item['product_id'] == $product_id) {
                        $item['quantity'] = $quantity;
                        break;
                    }
                }
            }

            $response['success'] = true;
            $response['message'] = 'Quantity updated';
            $response['cart_count'] = array_sum(array_column($_SESSION['cart'], 'quantity'));
            break;

        case 'clear_cart':
            $_SESSION['cart'] = [];
            $response['success'] = true;
            $response['message'] = 'Cart cleared';
            $response['cart_count'] = 0;
            break;
    }

    // Return JSON response for AJAX requests
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Get cart count
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;


// Fetch initial recommendations
$user_id = $_SESSION['user_id'];
// This endpoint doesn't exist yet, but we can build it later. For now, we get popular.
$api_url = $api_base_url . '/api/recommend/filtered'; 
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


// Check if the user is logged in by looking for the session variable.
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // The user IS logged in. Use their actual user_id.
    $user_id = $_SESSION['user_id'];
    $api_url = $api_base_url . '/api/recommend/' . $user_id . '?n=10';
    $recommendation_title = "Recommended For You"; // Personalize the title
} else {
    // The user is NOT logged in (a guest).
    // We'll get popular recommendations instead of personal ones.
    $api_url = $api_base_url . '/api/popular?n=10';
}


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
    <style>
        #recommendation-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 320px;
            height: 100%;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
            padding: 20px;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            z-index: 1050;
            overflow-y: auto;
        }

        #recommendation-sidebar.open {
            transform: translateX(0);
        }

        #sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 1040;
        }

        #sidebar-overlay.active {
            display: block;
        }

        #open-sidebar-btn {
            position: fixed;
            top: 80px;
            left: 15px;
            z-index: 1030;
            background-color: #0d6efd;
            color: white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .form-label {
            font-weight: 600;
        }
        #price-range {
            height: 12px;
            margin: 20px 5px 30px 5px; /* Add vertical and horizontal spacing */
        }

        .price-range-values {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 0.9em;
            color: #6c757d;
        }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }

        .cart-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
        }

        .quantity-btn:hover {
            background: #e9ecef;
        }

        .cart-total {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }

        .cart-quantity-stepper input {
            width: 50px;
            text-align: center;
            border-left: none;
            border-right: none;
        }

        .cart-quantity-stepper .btn {
            border-color: #dee2e6;
        }

        .cart-product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
        }
    </style>
</head>

<body class="bg-light" style="font-family: 'Poppins', sans-serif;">

<!-- Sidebar Toggle Button -->
    <button id="open-sidebar-btn" class="btn"><i class="bi bi-sliders"></i></button>

    <!-- Sidebar Overlay -->
    <div id="sidebar-overlay"></div>

    <!-- Recommendation Sidebar -->
    <div id="recommendation-sidebar">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Refine Recommendations</h4>
            <button type="button" class="btn-close" id="close-sidebar-btn"></button>
        </div>
        <p>
            Hi, 
            <strong>
            <?php 
                echo isset($_SESSION['username']) && !empty($_SESSION['username']) 
                ? htmlspecialchars($_SESSION['username']) 
                : 'Guest'; 
            ?>
            </strong>! Adjust the settings to find your perfect item.
        </p>
        <hr>
        <form id="rec-form">
            <div class="mb-3">
                <label for="brand-name" class="form-label">Brand Name</label>
                <select class="form-select" id="brand-name" name="brand_name">
                    <option value="">All Brands</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="product-type" class="form-label">Product Type</label>
                <select class="form-select" id="product-type" name="product_type">
                    <option value="">All Types</option>
                </select>
            </div>
             <div class="mb-3">
                <label for="originality" class="form-label">Originality</label>
                <select class="form-select" id="originality" name="originality">
                    <option value="">All Countries</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="spicy-level" class="form-label">Spicy Level: <span id="spicy-level-value">3</span></label>
                <input type="range" class="form-range" id="spicy-level" name="spicy_level" min="0" max="5" step="1" value="3">
            </div>
            <div class="mb-3">
                <label for="price-range" class="form-label">Price Range (MYR)</label>
                <div id="price-range"></div>
                <div class="price-range-values">
                    <span id="price-lower">RM 0</span>
                    <span id="price-upper">RM 100</span>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Availability</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="availability" id="available" value="1" checked>
                    <label class="form-check-label" for="available">Available</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="availability" id="not-available" value="0">
                    <label class="form-check-label" for="not-available">Not Available</label>
                </div>
            </div>
            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary" id="find-btn">Find</button>
                <button type="reset" class="btn btn-outline-secondary" id="reset-btn">Delete/Reset</button>
            </div>
        </form>
    </div>

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
            <li class="nav-item" role="presentation" style="position: relative;">
                <button class="nav-link" id="cart-tab" data-bs-toggle="tab" data-bs-target="#cart" type="button" role="tab" aria-controls="cart" aria-selected="false">
                    <i class="bi bi-cart"></i> Cart
                    <?php if ($cart_count > 0) : ?>
                        <span class="cart-badge" id="cart-badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
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
                                    background: #fff !important;
                                    color: #333;
                                    border: none;
                                    border-radius: 50%;
                                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                                    transition: transform 0.2s cubic-bezier(.4, 2, .6, 1), box-shadow 0.2s;
                                    width: 40px;
                                    height: 40px;
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-size: 1.5rem;
                                    z-index: 2;
                                }

                                .scroll-btn:hover {
                                    transform: scale(1.5);
                                    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
                                }
                            </style>
                            <button class="scroll-btn scroll-btn-left" onclick="scrollMenu('foods', 'left')">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button class="scroll-btn scroll-btn-right" onclick="scrollMenu('foods', 'right')">
                                <i class="bi bi-chevron-right"></i>
                            </button>

                            <!-- Scroll buttons -->
                            <div id="foods-menu" class="scrollable-menu-container">
                                <?php if ($api_error || empty($recommended_products)) : ?>
                                    <div class="w-100 text-center p-4 text-muted">
                                        <p>Could not load special recommendations right now. Please enjoy browsing our products below!</p>
                                    </div>
                                <?php else : ?>
                                    <?php foreach ($recommended_products as $product) : ?>
                                        <div class="scrollable-menu-item">
                                            <div class="food-image-container">
                                                <img src="<?php echo htmlspecialchars($product['ImagePath'] ?? 'sources/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>" class="food-image card-img-top">
                                                <?php if (!empty($product['DietaryTags'])) : ?>
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
                                            <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                                <button onclick="addToCart(<?php echo htmlspecialchars($product['ProductID'] ?? ''); ?>, '<?php echo htmlspecialchars($product['BrandName'] ?? ''); ?>', '<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>', <?php echo htmlspecialchars($product['PriceMYR'] ?? 0); ?>, '<?php echo htmlspecialchars($product['ImagePath'] ?? ''); ?>', '<?php echo htmlspecialchars(addslashes($product['Description'] ?? '')); ?>')" class="add-button" title="Add to Cart">
                                                    <i class="bi bi-cart"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h2 class="align-items-center text-center section-title">Browse More Food!</h2>

                    <div class="menu-container align-items-center bg-white shadow-sm" style="box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
                        <?php foreach ($browse_products as $product) : ?>
                            <div class="scrollable-menu-item"
                                onclick="window.location.href='view_prod.php?product_id=<?php echo htmlspecialchars($product['ProductID'] ?? ''); ?>';"
                                style="cursor: pointer;"
                                title="View Product">

                                <div class="food-image-container">
                                    <img src="<?php echo htmlspecialchars($product['ImagePath'] ?? 'sources/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>" class="food-image card-img-top">
                                    <?php if (!empty($product['DietaryTags'])) : ?>
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

                                <div class="d-flex align-items-center flex-wrap gap-2 mt-2">

                                    <button onclick="event.stopPropagation(); addToCart(<?php echo htmlspecialchars($product['ProductID'] ?? ''); ?>, '<?php echo htmlspecialchars($product['BrandName'] ?? ''); ?>', '<?php echo htmlspecialchars($product['Flavour'] ?? ''); ?>', <?php echo htmlspecialchars($product['PriceMYR'] ?? 0); ?>, '<?php echo htmlspecialchars($product['ImagePath'] ?? ''); ?>', '<?php echo htmlspecialchars($product['Description'] ?? ''); ?>')" class="add-button" title="Add to Cart">
                                        <i class="bi bi-cart"></i>
                                    </button>
                                </div>
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
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="bg-white p-4 shadow-sm" style="box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
                            <h2 class="text-center mb-4">Shopping Cart</h2>

                            <div id="cart-items">
                                <?php if (empty($_SESSION['cart'])) : ?>
                                    <div class="text-center py-5" id="empty-cart">
                                        <i class="bi bi-cart-x text-muted" style="font-size: 4rem;"></i>
                                        <h3 class="mt-3">Your cart is empty</h3>
                                        <p class="text-muted">Add some delicious items to your cart!</p>
                                    </div>
                                <?php else : ?>
                                    <?php
                                    $total = 0;
                                    foreach ($_SESSION['cart'] as $item) :
                                        $item_total = $item['price'] * $item['quantity'];
                                        $total += $item_total;
                                    ?>
                                        <div class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                                            <div class="row">
                                                <div class="col-md-2">
                                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['flavour']); ?>" class="img-fluid rounded">
                                                </div>
                                                <div class="col-md-6">
                                                    <h5><?php echo htmlspecialchars($item['brand_name']); ?> (<?php echo htmlspecialchars($item['flavour']); ?>)</h5>
                                                    <p class="text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                                                    <p class="fw-bold">RM <?php echo number_format($item['price'], 2); ?></p>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="quantity-controls">
                                                        <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, <?php echo $item['quantity'] - 1; ?>)">-</button>
                                                        <span class="quantity-display"><?php echo $item['quantity']; ?></span>
                                                        <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, <?php echo $item['quantity'] + 1; ?>)">+</button>
                                                    </div>
                                                </div>
                                                <div class="col-md-2 d-flex flex-column align-items-end justify-content-between">
                                                    <p class="fw-bold item-total mb-0">RM <?php echo number_format($item_total, 2); ?></p>

                                                    <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(<?php echo $item['product_id']; ?>)">
                                                        <i class="bi bi-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="d-flex justify-content-between align-items-center mt-4">

                                        <div>
                                            <button class="btn btn-outline-danger" onclick="clearCart()">
                                                <i class="bi bi-trash"></i> Clear Cart
                                            </button>
                                        </div>

                                        <div class="d-flex align-items-center">
                                            <div class="text-end me-3">
                                                <span class="text-muted">Total:</span>
                                                <h4 class="fw-bold mb-0">RM <span id="cart-total"><?php echo number_format($total, 2); ?></span></h4>
                                            </div>
                                            <form method="post" action="items.php" class="mb-0">
                                                <input type="hidden" name="cart_checkout" value="1">
                                                <button type="submit" class="btn btn-success btn-lg">
                                                    <i class="bi bi-credit-card"></i> Checkout
                                                </button>
                                            </form>
                                        </div>

                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.5.1/nouislider.min.js"></script>
    </div>

    <script>
        function scrollMenu(menuType, direction) {
            const menu = document.getElementById(`${menuType}-menu`);
            if (!menu.querySelector('.scrollable-menu-item')) return;
            const itemWidth = menu.querySelector('.scrollable-menu-item').offsetWidth + 20; // width + gap
            menu.scrollBy({
                left: direction === 'left' ? -itemWidth : itemWidth,
                behavior: 'smooth'
            });
        }

        // This function will fetch new cart content and update the page
        function refreshCartContent() {
            fetch('get_cart_content.php')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('cart-items').innerHTML = html;
                })
                .catch(error => console.error('Error refreshing cart:', error));
        }

        function handleCartAction(formData) {
            fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateCartBadge(data.cart_count);
                        refreshCartContent(); // Refresh the cart view
                        showNotification(data.message || 'Cart updated!', 'success');
                    } else {
                        showNotification(data.message || 'An error occurred.', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error updating cart.', 'danger');
                });
        }

        function addToCart(productId, brandName, flavour, price, image, description) {
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', productId);
            formData.append('brand_name', brandName);
            formData.append('flavour', flavour);
            formData.append('price', price);
            formData.append('image', image);
            formData.append('description', description);
            formData.append('ajax', '1');
            handleCartAction(formData);
        }

        function removeFromCart(productId) {
            const formData = new FormData();
            formData.append('action', 'remove_from_cart');
            formData.append('product_id', productId);
            formData.append('ajax', '1');
            handleCartAction(formData);
        }

        function updateQuantity(productId, quantity) {
            const formData = new FormData();
            formData.append('action', 'update_quantity');
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            formData.append('ajax', '1');
            handleCartAction(formData);
        }

        function clearCart() {
            if (confirm('Are you sure you want to clear your cart?')) {
                const formData = new FormData();
                formData.append('action', 'clear_cart');
                formData.append('ajax', '1');
                handleCartAction(formData);
            }
        }

        function updateCartBadge(count) {
            let badge = document.getElementById('cart-badge');
            const cartTabButton = document.querySelector('#cart-tab');

            if (!badge && count > 0) {
                badge = document.createElement('span');
                badge.id = 'cart-badge';
                badge.className = 'cart-badge';
                cartTabButton.appendChild(badge);
            }

            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('recommendation-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const openBtn = document.getElementById('open-sidebar-btn');
            const closeBtn = document.getElementById('close-sidebar-btn');
            const recForm = document.getElementById('rec-form');
            const findBtn = document.getElementById('find-btn');
            const resetBtn = document.getElementById('reset-btn');

            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('active');
            }

            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }

            openBtn.addEventListener('click', openSidebar);
            closeBtn.addEventListener('click', closeSidebar);
            overlay.addEventListener('click', closeSidebar);

            // Spicy level slider value display
            const spicySlider = document.getElementById('spicy-level');
            const spicyValue = document.getElementById('spicy-level-value');
            spicySlider.addEventListener('input', () => {
                spicyValue.textContent = spicySlider.value;
            });

            // Price range slider
            const priceSlider = document.getElementById('price-range');
            const priceLower = document.getElementById('price-lower');
            const priceUpper = document.getElementById('price-upper');

            noUiSlider.create(priceSlider, {
                start: [0, 100],
                connect: true,
                range: {
                    'min': 0,
                    'max': 100
                },
                step: 1,
                format: {
                    to: value => Math.round(value),
                    from: value => Number(value)
                }
            });
            
            priceSlider.noUiSlider.on('update', function (values, handle) {
                priceLower.innerHTML = `RM ${values[0]}`;
                priceUpper.innerHTML = `RM ${values[1]}`;
            });


            // --- Populate Filters ---
            async function populateFilters() {
                try {
                    const response = await fetch('<?php echo $api_base_url; ?>/api/products');
                    const data = await response.json();
                    if (!data.success) throw new Error('Failed to fetch products');

                    const products = data.products;
                    const brands = new Set();
                    const types = new Set();
                    const origins = new Set();
                    let maxPrice = 0;

                    products.forEach(p => {
                        brands.add(p.BrandName);
                        types.add(p.ProductType);
                        if(p.Originality) origins.add(p.Originality); // Assuming Originality field exists
                        if (p.PriceMYR > maxPrice) maxPrice = p.PriceMYR;
                    });
                    
                    const brandSelect = document.getElementById('brand-name');
                    brands.forEach(b => brandSelect.add(new Option(b, b)));

                    const typeSelect = document.getElementById('product-type');
                    types.forEach(t => typeSelect.add(new Option(t, t)));

                    const originSelect = document.getElementById('originality');
                    origins.forEach(o => originSelect.add(new Option(o, o)));

                    // Update price slider max
                    priceSlider.noUiSlider.updateOptions({
                        range: {
                            'min': 0,
                            'max': Math.ceil(maxPrice)
                        },
                        start: [0, Math.ceil(maxPrice)]
                    });

                } catch (error) {
                    console.error("Error populating filters:", error);
                    showNotification('Could not load filter options.', 'danger');
                }
            }
            populateFilters();


            // --- Handle Form Submission ---
            recForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                findBtn.disabled = true;
                findBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Finding...`;
                
                const formData = new FormData(recForm);
                const params = new URLSearchParams();
                
                for (const pair of formData.entries()) {
                    params.append(pair[0], pair[1]);
                }
                const priceValues = priceSlider.noUiSlider.get();
                params.append('min_price', priceValues[0]);
                params.append('max_price', priceValues[1]);


                try {
                    const response = await fetch(`<?php echo $api_base_url; ?>/api/recommend/filtered?${params.toString()}`);
                    const data = await response.json();
                    
                    if(data.success) {
                        updateRecommendationsUI(data.recommendations);
                        closeSidebar();
                    } else {
                        showNotification(data.error || 'No products found with these criteria.', 'warning');
                    }

                } catch (error) {
                    console.error('Error fetching filtered recommendations:', error);
                    showNotification('An error occurred while fetching recommendations.', 'danger');
                } finally {
                    findBtn.disabled = false;
                    findBtn.innerHTML = 'Find';
                }
            });

            // --- Reset Form ---
            resetBtn.addEventListener('click', function() {
                // Also reset sliders to their initial state
                priceSlider.noUiSlider.reset();
                spicySlider.value = 3;
                spicyValue.textContent = '3';
                // Potentially re-fetch default recommendations
            });

        });

        function updateRecommendationsUI(products) {
            const container = document.getElementById('foods-menu');
            container.innerHTML = ''; // Clear existing items

            if (!products || products.length === 0) {
                container.innerHTML = `<div class="w-100 text-center p-4 text-muted">
                                          <p>No recommendations match your criteria. Try different settings!</p>
                                       </div>`;
                return;
            }

            products.forEach(product => {
                const productHtml = `
                    <div class="scrollable-menu-item">
                        <div class="food-image-container">
                            <img src="${product.ImagePath || 'sources/placeholder.png'}" alt="${product.Flavour}" class="food-image card-img-top">
                            ${product.DietaryTags ? `<div class="discount-badge">${product.DietaryTags}</div>` : ''}
                        </div>
                        <div>
                            <h2 class="food-title mb-1">${product.BrandName}<br>(${product.Flavour})</h2>
                            <div class="food-price mb-1">
                                RM ${parseFloat(product.PriceMYR).toFixed(2)}
                            </div>
                            <p class="food-description mb-0">${product.Description || ''}</p>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                             <button onclick="addToCart(${product.ProductID}, '${product.BrandName}', '${product.Flavour}', ${product.PriceMYR}, '${product.ImagePath}', '${(product.Description || '').replace(/'/g, "\\'")}')" class="add-button" title="Add to Cart">
                                <i class="bi bi-cart"></i>
                            </button>
                        </div>
                    </div>
                `;
                container.innerHTML += productHtml;
            });
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
            document.body.appendChild(notification);
            setTimeout(() => bootstrap.Alert.getOrCreateInstance(notification).close(), 3000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateCartBadge(<?php echo $cart_count; ?>);
        });
    </script>
</body>

</html>