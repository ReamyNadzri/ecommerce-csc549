<head>
    <meta charset="UTF-8">
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


    <title>AF Platform</title>

    <header class="header-main" style="">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="index.html">
                    <i class="fas fa-shopping-bag shopping-icon"></i>|
                    <span class="typing-text" id="brand-text"></span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.html"><i class="fas fa-home"></i> Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index2.html"><i class="fas fa-store"></i> Shop</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-info-circle"></i> About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link border rounded px-4 py-1 pt-2 pb-2" href="#" data-bs-toggle="modal" data-bs-target="#loginRegisterModal" style="border-width:2px;">
                                <i class="fas fa-sign-in-alt"></i> Login / Register
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/header.js"></script>


</head>

<body>
<div class="modal fade" id="loginRegisterModal" tabindex="-1" aria-labelledby="loginRegisterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginRegisterModalLabel">Account Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab" aria-controls="login" aria-selected="true">Login</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab" aria-controls="register" aria-selected="false">Register</button>
                    </li>
                </ul>
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                        <form class="mt-4">
                            <div class="mb-3">
                                <label for="loginUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" id="loginUsername" required>
                            </div>
                            <div class="mb-3">
                                <label for="loginPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" id="loginPassword" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                        <form class="mt-4">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="registerUsername" class="form-label">Username <i class="fas fa-key text-danger"></i></label>
                                    <input type="text" class="form-control" id="registerUsername" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="registerEmail" class="form-label">Email <i class="fas fa-key text-danger"></i></label>
                                    <input type="email" class="form-control" id="registerEmail" placeholder="you@example.com" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="registerPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" id="registerPassword" required>
                            </div>
                             <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="registerFirstName" class="form-label">FirstName</label>
                                    <input type="text" class="form-control" id="registerFirstName" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="registerLastName" class="form-label">LastName</label>
                                    <input type="text" class="form-control" id="registerLastName" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Register</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>