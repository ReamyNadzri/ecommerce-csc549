<?php
    // Start session and include connection before any output
    if (session_status() === PHP_SESSION_NONE) {
        // If no session is active, set the cookie path and start the session
        ini_set('session.cookie_path', '/');
        session_start();
    }
    include_once __DIR__ . '/../config/connection.php';
?>
<head>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/w3-css/4.1.0/w3.min.css">
    <link href="https://fonts.cdnfonts.com/css/product-sans" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .enlarge-image {
    transition: transform 0.2s;
}

.enlarge-image:hover {
    transform: scale(1.2);
}
button{
    width: 100%;
}
a {
    text-decoration: none;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Arial', sans-serif;
}

.menu-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
    max-width: 1320px;
}

.menu-item {
    background-color: white;
    border-radius: 10px;
    padding: 20px;
    width: 250px;
    text-align: center;
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    padding-top: 140px; /* Space for the image */
    margin-top: 140px; /* Offset for the image */
}

.menu-item:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

/* Unavailable item styling*/
.menu-item.unavailable {
    opacity: 0.5;
    pointer-events: none;
}

.menu-item.unavailable::after {
    content: 'Stok Habis :(';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    font-weight: bold;
}

/* Coming soon item styling*/
.menu-item.comingsoon {
    opacity: 0.7;
    pointer-events: none;
}

.menu-item.comingsoon::after {
    content: 'Akan Datang :)';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    font-weight: bold;
}

.food-image-container {
    position: absolute;
    top: -140px;
    left: 0;
    width: 100%;
    height: 280px;
    display: flex;
    justify-content: center;
    overflow: visible;
}

.food-image {
    width: 350px;
    height: 350px;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.drink-image-container {
    position: absolute;
    top: -140px;
    left: 0;
    width: 100%;
    height: 280px;
    display: flex;
    justify-content: center;
    overflow: visible;
}

.drink-image {
    width: 300px;
    height: 300px;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.menu-item:hover .food-image {
    transform: translateY(-10px);
}

.food-title {
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 5px;
}

.food-price {
    color: #e74c3c;
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 10px;
}

.food-description {
    color: #666;
    font-size: 12px;
    line-height: 1.5;
    margin-bottom: 20px;
}

.add-button {
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 40px;
    background-color: #e74c3c;
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: background-color 0.3s ease, transform 0.2s ease;
    
}

.add-button:hover {
    background-color: #c0392b;
    transform: translateX(-50%) scale(1.1);
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .menu-item {
        width: calc(33.33% - 30px);
    }
}

@media (max-width: 900px) {
    .menu-item {
        width: calc(50% - 30px);
    }
}

@media (max-width: 600px) {
    .menu-item {
        width: 100%;
    }
    
    .container {
        max-width: 100%;
    }
}
/* Tab navigation styling */
.nav-tabs {
    border: none;
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
}

.nav-tabs .nav-item {
    margin: 0 10px;
}

.nav-tabs .nav-link {
    border: none;
    background-color: #f8f9fa;
    color: #777;
    font-weight: 600;
    padding: 12px 25px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
    background-color: #e74c3c;
    color: white;
    transform: translateY(-3px);
}

.nav-tabs .nav-link.active {
    background-color: #e74c3c;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
}

.header-main {
    background: linear-gradient(135deg, #ff8a00 0%, #ff4e50 100%);
    font-family: 'Poppins', sans-serif;
    padding: 1rem 0;
}
.navbar {
    background: transparent !important;
}
.navbar-brand {
    color: white !important;
    font-size: 1.5rem;
    font-weight: 600;
}
.typing-text {
    border-right: 2px solid #fff;
    padding-right: 5px;
    animation: blink 0.75s step-end infinite;
    color: white;
}
.shopping-icon {
    color: white;
    font-size: 1.8rem;
    margin-right: 0.8rem;
}
@keyframes blink {
    from, to { border-color: transparent }
    50% { border-color: #fff; }
}
.navbar-nav .nav-link {
    color: white !important;
    margin: 0 1rem;
    transition: all 0.3s ease;
}
.navbar-nav .nav-link:hover {
    transform: translateY(-2px);
}
.discount-badge {
    position: absolute;
    top: 250px;
    right: 10px;
    background-color: #e74c3c;
    color: white;
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 50px;
    font-size: 14px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transform: rotate(5deg);
  }
  .original-price {
    text-decoration: line-through;
    color: #888;
    font-size: 14px;
    margin-right: 5px;
  }
  .whatsapp-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 60px;
    height: 60px;
    background-color: #25D366; /* WhatsApp green color */
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    cursor: pointer;
    z-index: 1000;
    transition: transform 0.3s ease;
}

.whatsapp-btn:hover {
    transform: scale(1.1);
}

.whatsapp-icon {
    width: 35px;
    height: 35px;
    fill: white;
}
.menu-section {
            position: relative;
        }

        .scroll-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0,0,0,0.1);
            color: #333;
            border: none;
            z-index: 10;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .scroll-btn:hover {
            background-color: rgba(0,0,0,0.2);
        }

        .scroll-btn-left {
            left: -30px;
        }

        .scroll-btn-right {
            right: -30px;
        }

        .scrollable-menu-container {
            display: flex;
            overflow-x: hidden;
            gap: 20px;
            padding: 20px 15px;
            scroll-behavior: smooth;
            position: relative;
        }

        .scrollable-menu-item {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            width: 210px;
            height: 300px;
            text-align: center;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            padding-top: 140px; /* Space for the image */
            margin-top: 140px; /* Offset for the image */
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #f0f0f0;
            flex: 0 0 250px;
        }

        .scrollable-menu-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .scrollable-menu-item-image {
            width: 200px;
            height: 200px;
            object-fit: contain;
            margin-bottom: 15px;
        }

        .scrollable-menu-item-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .scrollable-menu-item-price {
            color: #e74c3c;
            font-size: 16px;
            font-weight: bold;
        }

        .scrollable-menu-item-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
        }

        .section-title {
            text-align: center;
            margin: 20px 0;
            font-weight: bold;
            color: #333;
        }
  

    </style>

<script>
    const texts = ["AF Shopping", "One Stop Center", "Shop Now"];
        const brandText = document.getElementById('brand-text');
        let textIndex = 0;
        let charIndex = 0;

        function typeText() {
            if (charIndex < texts[textIndex].length) {
                brandText.textContent += texts[textIndex].charAt(charIndex);
                charIndex++;
                setTimeout(typeText, 150);
            } else {
                setTimeout(eraseText, 2000);
            }
        }

        function eraseText() {
            if (brandText.textContent.length > 0) {
                brandText.textContent = brandText.textContent.slice(0, -1);
                setTimeout(eraseText, 50);
            } else {
                textIndex = (textIndex + 1) % texts.length;
                charIndex = 0;
                setTimeout(typeText, 500);
            }
        }

        typeText();
</script>

    <title>AF Platform</title>

    <header class="header-main" style="">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="/ecommerce-csc549/index.php">
                    <i class="fas fa-shopping-bag shopping-icon"></i>|
                    <span class="typing-text" id="brand-text"></span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/ecommerce-csc549/index.php"><i class="fas fa-home"></i> Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/ecommerce-csc549/index.php"><i class="fas fa-store"></i> Shop</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-info-circle"></i> About</a>
                        </li>
                        <?php if (isset($_SESSION['user_id']) && isset($_SESSION['username'])): ?>
                            <li class="nav-item">
                                <span class="nav-link">
                                    <i class="fas fa-user"></i> Hi, <?php echo htmlspecialchars($_SESSION['username']); ?>
                                </span>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link border rounded px-4 py-1 pt-2 pb-2" href="/ecommerce-csc549/auth/logout.php" style="border-width:2px;">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link border rounded px-4 py-1 pt-2 pb-2" href="/ecommerce-csc549/auth/auth.php" style="border-width:2px;">
                                    <i class="fas fa-sign-in-alt"></i> Login / Register
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/header.js"></script>


</head>

