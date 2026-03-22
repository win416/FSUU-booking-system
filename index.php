<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <link rel="stylesheet" href="assets/css/index.css?v=7">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="img/fsuu%20dental.jpg" alt="Logo" class="navbar-logo me-2">
                FSUU Dental Clinic
            </a>
            <!-- Right side: auth buttons + hamburger -->
            <div class="navbar-right">
                <a class="btn navbar-auth-btn" href="auth/login.php">Login</a>
                <a class="btn navbar-auth-btn" href="auth/register.php">Register</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            <!-- Hamburger dropdown: nav links only -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="container hero-body">
            <h2 class="hero-title">Welcome to FSUU Dental Clinic</h2>
            <p class="hero-subtitle">Professional dental care with modern technology and compassionate service</p>
        </div>
        <!-- Floating action bar -->
        <div class="hero-bar-wrap">
            <div class="hero-bar">
                <div class="hero-bar-item">
                    <span class="hero-bar-label">Location</span>
                    <span class="hero-bar-value">FSUU, CB Bldg 1F, Butuan City</span>
                </div>
                <div class="hero-bar-divider"></div>
                <div class="hero-bar-item">
                    <span class="hero-bar-label">Schedule</span>
                    <span class="hero-bar-value">Mon–Fri: 1:00 PM – 3:30 PM</span>
                </div>
                <div class="hero-bar-divider"></div>
                <div class="hero-bar-item">
                    <span class="hero-bar-label">Saturday</span>
                    <span class="hero-bar-value">9:00 AM – 12:00 PM</span>
                </div>
                <div class="hero-bar-divider"></div>
                <div class="hero-bar-item">
                    <span class="hero-bar-label">Contact</span>
                    <span class="hero-bar-value">+63 951 250 4812</span>
                </div>
                <a href="auth/login.php" class="hero-bar-btn">
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="display-4 fw-bold text-primary">Our Services</h2>
                    <p class="lead text-muted">Comprehensive dental care for the Urian Community</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <h4>Consultation</h4>
                        <p>Professional dental check-ups and preventive care to keep your smile healthy.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <h4>Tooth Extraction</h4>
                        <p>Removing a tooth that is damaged, decayed, or impacted.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <h4>Oral Prophylaxis</h4>
                        <p>Professional cleaning of teeth to remove plaque, tartar, and stains.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <h4>Permanent Tooth Filling</h4>
                        <p>Restoring damaged teeth with durable filling materials to maintain function and aesthetics.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="display-5 fw-bold text-primary mb-4">About FSUU Dental Clinic</h2>
                    <p class="lead mb-4">Located at Father Saturnino Urios University (FSUU) at 1st floor CB Building, our dental clinic provides state-of-the-art dental care with a focus on patient comfort and satisfaction.</p>
                    <p>Our team of experienced dentists and staff are committed to delivering high-quality dental services using the latest technology and techniques. We believe that everyone deserves a healthy, beautiful smile.</p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Modern equipment and technology</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Experienced and qualified dentists</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Patient-centered care approach</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Affordable treatment options</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <img src="img/inside clinic.jpg" alt="Dental Clinic" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="display-4 fw-bold text-primary">Contact Us</h2>
                    <p class="lead text-muted">Get in touch for appointments or inquiries</p>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="row text-center">
                        <div class="col-md-4 mb-4">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-geo-alt"></i>
                            </div>
                            <h5>Address</h5>
                            <p>San Francisco Street, Butuan City, Philippines, 8600</p>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-telephone"></i>
                            </div>
                            <h5>Phone</h5>
                            <p>+63 951 250 4812</p>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-envelope"></i>
                            </div>
                            <h5>Email</h5>
                            <p>clinic.triage@urios.edu.ph</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <h5>FSUU Dental Clinic</h5>
                    <p>Committed to providing excellent dental care with compassion and professionalism.</p>
                </div>
                <div class="col-lg-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="auth/login.php" class="text-white">Login</a></li>
                        <li><a href="auth/register.php" class="text-white">Register</a></li>
                        <li><a href="#features" class="text-white">Services</a></li>
                        <li><a href="#about" class="text-white">About</a></li>
                    </ul>
                </div>
                <div class="col-lg-3">
                    <h5>Follow Us</h5>
                    <div class="social-links">
                        <a href="https://www.facebook.com/p/FSUU-Medical-Dental-Clinic-100057208429140/" target="_blank" class="social-icon-btn">
                            <i class="bi bi-facebook"></i> Facebook
                        </a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; 2026 FSUU Dental Clinic. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });

    </script>
</body>
</html>