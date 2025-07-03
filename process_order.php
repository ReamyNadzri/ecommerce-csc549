<?php
session_start();
require 'config/connection.php';

// ### STEP 1: AUTHENTICATION & VALIDATION ###
// If the user isn't logged in, kick them to the login page.
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // You can add a message here, e.g., $_SESSION['error'] = "Please log in to complete your order.";
    header('Location: auth/auth.php');
    exit();
}

// Make sure we're receiving data from the form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request method.";
    exit();
}

// ### STEP 2: HANDLE FILE UPLOAD SAFELY ###
$upload_error = '';
$target_file_path = '';

if (isset($_FILES["paymentProof"]) && $_FILES["paymentProof"]["error"] == 0) {
    $target_dir = "sources/proof/";
    // Create the directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // Create a unique filename to prevent overwrites (e.g., userid_timestamp_filename.jpg)
    $user_id = $_SESSION['user_id'];
    $timestamp = time();
    $unique_filename = $user_id . "_" . $timestamp . "_" . basename($_FILES["paymentProof"]["name"]);
    $target_file_path = $target_dir . $unique_filename;

    // Security checks
    $check = getimagesize($_FILES["paymentProof"]["tmp_name"]);
    if ($check === false) {
        $upload_error = "File is not a valid image.";
    }
    // You can add more checks here (file size, file type, etc.)

    // Try to move the uploaded file
    if (empty($upload_error) && !move_uploaded_file($_FILES["paymentProof"]["tmp_name"], $target_file_path)) {
        $upload_error = "Sorry, there was an error uploading your file.";
    }
} else {
    $upload_error = "No payment proof was uploaded or an error occurred.";
}

// If upload failed, stop everything
if (!empty($upload_error)) {
    die("Error: " . $upload_error);
}

// ### STEP 3: SAVE ORDER TO DATABASE USING A TRANSACTION ###
// A transaction ensures that BOTH the order and its items are saved, or nothing is.
$conn->begin_transaction();

try {
    // Get data from the form
    $customer_name = $_POST['customerName'];
    $phone_number = $_POST['phoneNumber'];
    $payment_method = $_POST['payment_method'];
    $total_price = (float)$_POST['total_price'];
    $cart_items = json_decode($_POST['cart_data'], true);
    $order_id = 'ORD-' . strtoupper(uniqid()); // Generate a unique Order ID

    // A. Insert into the 'orders' table
    $sql_order = "INSERT INTO orders (OrderID, UserID, TotalAmount, OrderStatus, PaymentStatus, PaymentMethod, PaymentProofPath) VALUES (?, ?, ?, 'Pending', 'Paid', ?, ?)";
    $stmt_order = $conn->prepare($sql_order);
    $stmt_order->bind_param("ssdss", $order_id, $user_id, $total_price, $payment_method, $target_file_path);
    $stmt_order->execute();

    // B. Loop through cart items and insert into the 'order_items' table
    $sql_items = "INSERT INTO order_items (OrderItemID, OrderID, ProductID, Quantity, PriceAtPurchase) VALUES (?, ?, ?, ?, ?)";
    $stmt_items = $conn->prepare($sql_items);

    foreach ($cart_items as $item) {
        $order_item_id = 'ITEM-' . strtoupper(uniqid());
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $price_at_purchase = $item['price'];

        $stmt_items->bind_param("sssid", $order_item_id, $order_id, $product_id, $quantity, $price_at_purchase);
        $stmt_items->execute();

        // This query updates stock AND deactivates the product if stock reaches 0.
        $sql_stock = "UPDATE products 
                  SET 
                      StockQuantity = StockQuantity - ?, 
                      IsActive = CASE 
                                     WHEN (StockQuantity - ?) <= 0 THEN 0 
                                     ELSE IsActive 
                                 END
                  WHERE ProductID = ?";

        $stmt_stock = $conn->prepare($sql_stock);
        // Note: We bind the quantity twice because it's used twice in the query
        $stmt_stock->bind_param("iis", $item['quantity'], $item['quantity'], $item['product_id']);
        $stmt_stock->execute();
    }

    // If everything was successful, commit the changes to the database
    $conn->commit();

    // ### STEP 4: CLEAN UP AND REDIRECT ###
    // Clear the user's cart
    $_SESSION['cart'] = [];

    // Redirect to a success page
    header('Location: order_success.php?order_id=' . $order_id);
    exit();
} catch (mysqli_sql_exception $exception) {
    // If any part of the database insert failed, roll back all changes
    $conn->rollback();

    // You can log the detailed error for debugging: error_log($exception->getMessage());
    die("There was a database error. Please try again.");
}

$conn->close();
