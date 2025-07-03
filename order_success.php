<?php
session_start();
$order_id = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Successful!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .success-container { max-width: 600px; margin-top: 100px; }
    </style>
</head>
<body>
    <div class="container text-center success-container">
        <div class="card shadow-lg p-5">
            <i class="bi bi-patch-check-fill text-success" style="font-size: 80px;"></i>
            <h1 class="mt-3">Thank You!</h1>
            <p class="lead">Your order has been placed successfully.</p>
            <hr>
            <p>Your Order ID is:</p>
            <h3 class="bg-light p-2 rounded"><?php echo $order_id; ?></h3>
            <p class="mt-3 text-muted">We will process your order shortly. You can check your order status in your profile.</p>
            <div class="mt-4">
                <a href="index.php" class="btn btn-primary">Continue Shopping</a>
            </div>
        </div>
    </div>
</body>
</html>