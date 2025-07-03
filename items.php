<?php
// ### This PHP logic at the top remains the same ###
session_start();
require 'config/connection.php';

// Check if the user is trying to check out AND is not logged in.
if ((isset($_POST['cart_checkout']) || isset($_GET['product_id'])) && (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']))) {
    
    // Set a message to show on the login page (optional, but good UX)
    $_SESSION['login_message'] = "Please log in or create an account to continue your purchase.";
    
    // Use JavaScript to show an alert, then redirect.
    echo "<script>
            alert('You need to be logged in to proceed with checkout.');
            window.location.href = 'auth/auth.php';
          </script>";
    exit(); // Stop the rest of the page from loading
}

$is_cart_mode = false;
$product = null;
$cart_items = [];
$total_price = 0;

if ((isset($_POST['cart_checkout']) || isset($_GET['product_id'])) && isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    
    $is_cart_mode = true;
    $cart_items = $_SESSION['cart'];
    foreach ($cart_items as $item) {
        $total_price += $item['price'] * $item['quantity'];
    }
} 
elseif (isset($_GET['product_id'])) {
    $is_cart_mode = false;
    $productId = $_GET['product_id'];
    $sql = "SELECT * FROM products WHERE ProductID = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            // For single item mode, create a cart-like structure for consistency
            $cart_items = [[
                'product_id' => $product['ProductID'],
                'brand_name' => $product['BrandName'],
                'flavour' => $product['Flavour'],
                'price' => (float)$product['PriceMYR'],
                'image' => $product['ImagePath'],
                'quantity' => 1
            ]];
            $total_price = (float)$product['PriceMYR'];
        }
        $stmt->close();
    }
} 
else {
    header('Location: index.php');
    exit();
}

$price = number_format($total_price, 2);
$mainName = $is_cart_mode ? "Checkout" : htmlspecialchars($product['BrandName'] . ' (' . $product['Flavour'] . ')');

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AF Platform - Checkout</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/items.css" />
    <link rel="stylesheet" href="css/index.css" />

    <?php include 'includes/header.php'; ?>

    <style>
        /* This CSS is the same as before, providing the look for the steps */
        body { background-color: #f8f9fa; }
        .checkout-steps { display: flex; justify-content: center; margin-bottom: 2rem; font-size: 1.1rem; }
        .step { display: flex; align-items: center; color: #6c757d; cursor: pointer; }
        .step.active { color: #0d6efd; font-weight: 600; }
        .step.disabled { color: #6c757d; cursor: not-allowed; }
        .step .step-circle { width: 30px; height: 30px; border-radius: 50%; background-color: #e9ecef; color: #6c757d; display: flex; align-items: center; justify-content: center; margin-right: 0.5rem; border: 2px solid #ced4da; transition: all 0.3s ease; }
        .step.active .step-circle { background-color: #cfe2ff; color: #0d6efd; border-color: #0d6efd; }
        .step-divider { height: 2px; width: 50px; background-color: #ced4da; margin: 0 1rem; }
        .card-header-icon { font-size: 1.5rem; margin-right: 0.75rem; vertical-align: middle; }
        .list-group-item img { width: 70px; height: 70px; object-fit: cover; }
    </style>
</head>

<body>
    <div class="container mt-4 mb-5" style="max-width: 1200px;">
        <a href="index.php" class="text-decoration-none text-muted mb-3 d-inline-block">
            <i class="bi bi-arrow-left"></i> Back to Shop
        </a>

        <div class="checkout-steps">
            <div id="step1" class="step active" onclick="goToStep(1)">
                <div class="step-circle">1</div> Review Details
            </div>
            <div class="step-divider"></div>
            <div id="step2" class="step disabled" onclick="goToStep(2)">
                <div class="step-circle">2</div> Make Payment
            </div>
        </div>

        <form id="checkoutForm" action="process_order.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="total_price" value="<?php echo $total_price; ?>">
            <input type="hidden" name="cart_data" value='<?php echo json_encode($cart_items); ?>'>

            <div id="reviewStepContent">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3"><h4 class="mb-0"><i class="bi bi-card-list card-header-icon text-primary"></i>Order Summary</h4></div>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($cart_items as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" class="rounded me-3">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($item['brand_name'] . ' (' . $item['flavour'] . ')'); ?></h6>
                                                <small class="text-muted">Quantity: <?php echo htmlspecialchars($item['quantity']); ?></small>
                                            </div>
                                        </div>
                                        <span class="fw-bold">RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="card-footer bg-white d-flex justify-content-between fs-5 fw-bold py-3">
                                <span>Total</span>
                                <span>RM <?php echo $price; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card shadow-sm">
                            <div class="card-body p-4">
                                <h4 class="mb-3"><i class="bi bi-person-lines-fill card-header-icon text-primary"></i>Your Details</h4>
                                <div class="mb-3">
                                    <label for="customerName" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="customerName" name="customerName" placeholder="Enter your name" required>
                                </div>
                                <div class="mb-4">
                                    <label for="phoneNumber" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phoneNumber" name="phoneNumber" placeholder="e.g., 0123456789" required>
                                </div>
                                <h4 class="mb-3"><i class="bi bi-wallet2 card-header-icon text-primary"></i>Payment Method</h4>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="eWalletTouchNGo" value="touch_n_go" checked>
                                    <label class="form-check-label" for="eWalletTouchNGo"> Touch 'n Go eWallet</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="eWalletDuitNow" value="duit_now">
                                    <label class="form-check-label" for="eWalletDuitNow"> DuitNow QR</label>
                                </div>
                                <div class="d-grid mt-4">
                                    <button class="btn btn-primary btn-lg" type="button" onclick="goToStep(2)">
                                        Proceed to Payment <i class="bi bi-arrow-right-circle-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="paymentStepContent" style="display: none;">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card shadow-sm">
                           <div class="card-header bg-white py-3"><h4 class="mb-0"><i class="bi bi-card-list card-header-icon text-primary"></i>Order Summary</h4></div>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($cart_items as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" class="rounded me-3">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($item['brand_name'] . ' (' . $item['flavour'] . ')'); ?></h6>
                                                <small class="text-muted">Quantity: <?php echo htmlspecialchars($item['quantity']); ?></small>
                                            </div>
                                        </div>
                                        <span class="fw-bold">RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="card-footer bg-white d-flex justify-content-between fs-5 fw-bold py-3">
                                <span>Total</span>
                                <span>RM <?php echo $price; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card shadow-sm">
                            <div class="card-body p-4 text-center">
                                <h4 class="mb-3"><i class="bi bi-qr-code-scan card-header-icon text-primary"></i>Scan to Pay</h4>
                                <p>Please scan the QR code to pay <strong>RM <?php echo $price; ?></strong>.</p>
                                <img id="qrCodeImage" src="" alt="Payment QR Code" class="img-fluid rounded my-3" style="max-width: 250px;">
                                <div class="text-start mt-3">
                                    <label for="paymentProof" class="form-label fw-bold">Upload Payment Receipt</label>
                                    <input type="file" class="form-control" id="paymentProof" name="paymentProof" accept="image/*,application/pdf" required>
                                    <small class="text-muted">Upload a screenshot or PDF of your transaction.</small>
                                </div>
                                <div class="d-grid mt-4">
                                    <button class="btn btn-success btn-lg" type="submit">
                                        Complete Order <i class="bi bi-check-circle-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const reviewStepContent = document.getElementById('reviewStepContent');
        const paymentStepContent = document.getElementById('paymentStepContent');
        const qrCodeImage = document.getElementById('qrCodeImage');
        let isStep2Unlocked = false;

        function goToStep(stepNumber) {
            if (stepNumber === 1) {
                // Always allow going back to step 1
                reviewStepContent.style.display = 'block';
                paymentStepContent.style.display = 'none';
                step1.classList.add('active');
                step2.classList.remove('active');
            } else if (stepNumber === 2) {
                // Validate details before proceeding to step 2
                const customerName = document.getElementById('customerName').value;
                const phoneNumber = document.getElementById('phoneNumber').value;

                if (!customerName || !phoneNumber) {
                    alert('Please fill in your Full Name and Phone Number before proceeding.');
                    return; // Stop the function
                }

                // If valid, proceed to step 2
                isStep2Unlocked = true;
                step2.classList.remove('disabled');

                reviewStepContent.style.display = 'none';
                paymentStepContent.style.display = 'block';
                step1.classList.remove('active');
                step2.classList.add('active');

                // Set the correct QR code image
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
                qrCodeImage.src = (paymentMethod === 'duit_now') ? 'sources/qrduitnow.jpg' : 'sources/qrtng.jpg';
            }
        }
        
        // Add an extra check for the step 2 tab click
        step2.addEventListener('click', function() {
            if (!isStep2Unlocked) {
                 alert('Please fill in your details and click "Proceed to Payment" first.');
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>