<?php
session_start();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (empty($_SESSION['cart'])): ?>
    <div class="text-center py-5" id="empty-cart">
        <i class="bi bi-cart-x text-muted" style="font-size: 4rem;"></i>
        <h3 class="mt-3">Your cart is empty</h3>
        <p class="text-muted">Add some delicious items to your cart!</p>
    </div>
<?php else: ?>
    <?php
    $total = 0;
    foreach ($_SESSION['cart'] as $item):
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