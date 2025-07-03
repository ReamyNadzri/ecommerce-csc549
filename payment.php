<?php
session_start();
require 'config/connection.php';

// Security check: only allow access via POST and if the cart is not empty
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit();
}

// Get data submitted from the review page (items.php)
$customer_name = $_POST['customerName'] ?? 'Guest';
$phone_number = $_POST['phoneNumber'] ?? '';
$payment_method = $_POST['payment_method'] ?? 'touch_n_go'; // Default to TnG

// Determine which QR code image to show based on the choice
$qr_image_path = ($payment_method === 'duit_now') ? 'sources/qrduitnow.jpg' : 'sources/qrtng.jpg';

// Get cart items and total price from the session
$cart_items = $_SESSION['cart'];
$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}
$price = number_format($total_price, 2);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AF Platform - Make Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/items.css" />
    <link rel="stylesheet" href="css/index.css" />
    <style>
        /* Paste the same custom CSS from items.php here for consistency */
    </style>
    <?php include 'includes/header.php'; ?>
</head>
<body>
    <div class="container mt-4 mb-5" style="max-width: 1200px;">
        <div class="checkout-steps">
            <div class="step">
                <div class="step-circle">1</div> Review Details
            </div>
            <div class="step-divider"></div>
            <div class="step active">
                <div class="step-circle">2</div> Make Payment
            </div>
        </div>

        <form action="process_order.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="customerName" value="<?php echo htmlspecialchars($customer_name); ?>">
            <input type="hidden" name="phoneNumber" value="<?php echo htmlspecialchars($phone_number); ?>">
            <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($payment_method); ?>">
            <input type="hidden" name="total_price" value="<?php echo $total_price; ?>">
            <input type="hidden" name="cart_data" value='<?php echo json_encode($cart_items); ?>'>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card shadow-sm">
                         </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow-sm">
                        <div class="card-body p-4 text-center">
                            <h4 class="mb-3">
                                <i class="bi bi-qr-code-scan card-header-icon text-primary"></i>
                                Scan to Pay
                            </h4>
                            <p>Please scan the QR code below to pay a total of <strong>RM <?php echo $price; ?></strong>.</p>
                            
                            <img src="<?php echo $qr_image_path; ?>" alt="Payment QR Code" class="img-fluid rounded my-3" style="max-width: 250px;">
                            
                            <div class="text-start">
                                <label for="paymentProof" class="form-label fw-bold">Upload Payment Receipt</label>
                                <input type="file" class="form-control" id="paymentProof" name="paymentProof" accept="image/*" required>
                                <small class="text-muted">Please upload a screenshot of your successful transaction.</small>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button class="btn btn-success btn-lg" type="submit">
                                    I Have Paid, Complete Order <i class="bi bi-check-circle-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>