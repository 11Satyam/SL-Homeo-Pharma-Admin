<?php
http_response_code(404);
$title       = '404 - Page Not Found | SL Pharma';
$keywords    = 'SL Pharma 404, page not found';
$description = 'The page you are looking for does not exist.';
$active      = '';
require 'includes/head.php';
require 'includes/navbar.php';
?>

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container py-5">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start">
                    <h1 class="display-1 mb-0 animated slideInLeft text-white">404 Error</h1>
                </div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="index.php">Home</a></li>
                            <li class="breadcrumb-item text-white active">404 Error</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- 404 Content -->
    <div class="container-fluid py-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <i class="bi bi-exclamation-triangle display-1 text-primary"></i>
                    <h1 class="display-1">404</h1>
                    <h1 class="mb-4">Page Not Found</h1>
                    <p class="mb-4">We're sorry, the page you are looking for does not exist. Please return to our homepage or contact us for assistance.</p>
                    <a class="btn btn-primary py-3 px-4 me-2" href="index.php">Go Back To Home</a>
                    <a class="btn btn-outline-primary py-3 px-4" href="contact.php">Contact Us</a>
                </div>
            </div>
        </div>
    </div>

<?php require 'includes/footer.php'; ?>
</body>
</html>
