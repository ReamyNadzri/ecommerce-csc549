<?php
// Set default values in case data is not passed
$itemName = "Unknown Item";
$price = 0.00;
$quantity = 1; // For now, quantity is always 1

// Check if data exists before using it
if (isset($_GET['brand_name']) && isset($_GET['flavour'])) {
    $itemName = htmlspecialchars($_GET['brand_name']) . ' (' . htmlspecialchars($_GET['flavour']) . ')';
}

if (isset($_GET['price'])) {
    $price = (float)htmlspecialchars($_GET['price']);
}

// Calculate total amount
$totalAmount = $price * $quantity;

// Generate a unique reference number (e.g., ORD-1687878787)
$reference = "ORD-" . time();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link href="https://fonts.cdnfonts.com/css/product-sans" rel="stylesheet">
    
    <title>E-commerce Platform - Payment</title>

    <header>
        <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top" style="height: 14vh; position: sticky; top: 0; z-index: 1020;">
            <div class="container-fluid" style="width: 130vh;">
                <a class="navbar-brand mx-auto" style="font-size: 2rem;" href="index.php">E-commerce Platform</a>
                <btn class="btn btn-outline-success" type="button">Login</btn>
            </div>
        </nav>
    </header>

    <style>
        button{
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container mt-2" style="width: 140vh">
        <div class="row border w3-round-xlarge bg-light shadow-sm">
            <div class="col-md-5 d-flex flex-column">
                <div class="w3-margin w3-padding w3-round-xlarge bg-white shadow-sm">
                    <h2 class="text-primary">Payment Details</h2>
                    <p>Scan the QR code below to make your payment. Make sure the amount and reference number are correct.</p>
                </div>
            </div>
            <div class="col-md-7"></div>
            <div class="col-md-5 d-flex flex-column align-items-center">
                <img id="productImage" src="sources/qrduitnow.jpg" class="card-img-top enlarge-image mb-auto w3-padding" alt="DuitNow QR" >
            </div>
            <div class="col-md-7 d-flex flex-column">   
                <div class="w3-margin w3-padding w3-round-xlarge bg-white shadow-sm">
                    <h3 class="text-primary">Receipt</h3>
                    <p><strong>Item:</strong> <?php echo $itemName; ?></p>
                    <p><strong>Quantity:</strong> <?php echo $quantity; ?></p>
                    <p><strong>Price per item:</strong> RM <?php echo number_format($price, 2); ?></p>
                    <hr>
                    <p><strong>Total Amount:</strong> RM <?php echo number_format($totalAmount, 2); ?></p>
                    <p><strong>Reference:</strong> <?php echo $reference; ?></p>
                </div>
                <div class="col-md-7 d-flex flex-column">   
                    <div class="w3-margin w3-padding w3-round-xlarge bg-white shadow-sm">
                        <p><strong>Please Scan to Pay</strong></p>
                        <p>Amount: RM <?php echo number_format($totalAmount, 2); ?></p>
                        <p>Reference: <?php echo $reference; ?></p>
                    </div>
                </div>
                <div class="w3-margin col-md-12 d-flex justify-content-center mt-3" style="width:  30vh;">
                    <button class="btn btn-primary">Confirm Payment</button>
                </div>
            </div>
            
        </div>       
    </div>
</body>
</html>