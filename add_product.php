<?php
// Step 1: Include the database connection file
require 'config/connection.php'; //

// Initialize a message variable to give feedback to the admin
$message = '';

// Step 2: Check if the form was submitted by checking the request method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Get Text Data ---
    // Get all the data from the form fields and use htmlspecialchars for security
    $brandName = htmlspecialchars($_POST['brandName']);
    $flavour = htmlspecialchars($_POST['flavour']);
    $description = htmlspecialchars($_POST['description']);
    $price = (float)$_POST['price']; // Cast price to a float
    $stockQuantity = (int)$_POST['stockQuantity']; // Cast quantity to an integer
    $productType = 'Food'; // Set default as 'Food' for now
    $isActive = 1; // Set default as active
    $imagePath = ''; // Initialize imagePath variable

    // --- Handle the Image Upload ---
    // Check if a file was uploaded without any errors
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        
        // The folder where images will be saved
        $target_dir = "sources/products/"; 
        
        // Create a unique filename to prevent overwriting files with the same name
        $unique_name = uniqid() . '_' . basename($_FILES["product_image"]["name"]);
        $target_file = $target_dir . $unique_name;
        
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // --- Validation Checks ---
        // 1. Check if the file is a real image
        $check = getimagesize($_FILES["product_image"]["tmp_name"]);
        if ($check === false) {
            $message = "Error: File is not an image.";
        }
        // 2. Check file size (let's say 5MB maximum)
        elseif ($_FILES["product_image"]["size"] > 5000000) {
            $message = "Error: Your file is too large (Max 5MB).";
        }
        // 3. Allow only certain file formats
        elseif (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            $message = "Error: Only JPG, JPEG, PNG & GIF files are allowed.";
        }
        // 4. If all checks pass, try to move the file
        else {
            if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                // If file is uploaded successfully, the path is the $target_file
                $imagePath = $target_file;

                // --- Save to Database ---
                // Now we insert the product data AND the image path into the database
                // Using prepared statements is the SAFE way to prevent SQL injection attacks!
                $sql = "INSERT INTO products (BrandName, Flavour, Description, PriceMYR, StockQuantity, ProductType, IsActive, ImagePath) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $condb->prepare($sql);
                
                if ($stmt) {
                    // Bind the variables to the placeholders
                    $stmt->bind_param("sssdissi", $brandName, $flavour, $description, $price, $stockQuantity, $productType, $isActive, $imagePath);
                    
                    // Execute the statement
                    if ($stmt->execute()) {
                        $message = "New product added successfully!";
                    } else {
                        $message = "Error: Could not execute the query. " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $message = "Error: Could not prepare the query. " . $condb->error;
                }

            } else {
                $message = "Error: There was a problem uploading your file.";
            }
        }
    } else {
        $message = "Error: No image was uploaded or an error occurred.";
    }
    
    // Close the database connection
    $condb->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Admin - Add Product</title>
</head>
<body>
    <div class="container mt-5">
        <h2>Add a New Product</h2>
        <p>This is the admin page to add new items to the shop.</p>
        <hr>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="add_product.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="brandName" class="form-label">Brand Name</label>
                <input type="text" class="form-control" id="brandName" name="brandName" required>
            </div>
            <div class="mb-3">
                <label for="flavour" class="form-label">Flavour</label>
                <input type="text" class="form-control" id="flavour" name="flavour" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="price" class="form-label">Price (RM)</label>
                    <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="stockQuantity" class="form-label">Stock Quantity</label>
                    <input type="number" class="form-control" id="stockQuantity" name="stockQuantity" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="product_image" class="form-label">Product Image</label>
                <input class="form-control" type="file" id="product_image" name="product_image" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Add Product</button>
        </form>
    </div>
</body>
</html>