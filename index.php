<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <link rel="stylesheet" href="assets/css/index.css?v=27">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="img/fsuu%20dental.jpg" alt="Logo" class="navbar-logo me-2">
                FSUU Dental Clinic
            </a>
            <div class="navbar-right">
                <a class="btn navbar-auth-btn" href="auth/login.php">Login</a>
                <a class="btn navbar-auth-btn" href="auth/register.php">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" style="background-image: url('img/insidefsuudental.jpg');">
        <div class="hero-overlay"></div>
        <div class="container hero-body">
            <h2 class="hero-title">Welcome to FSUU Dental Clinic</h2>
            <p class="hero-subtitle">Provides primary health care services to students and staff of Fr. Saturnino Urios University.</p>
            <div class="hero-cta mt-4 text-center">
                <a href="auth/register.php" class="btn hero-btn-primary">Book an Appointment</a>
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
            <div class="row services-row">
                <div class="col-lg-3 col-md-6 service-col">
                    <div class="feature-card">
                        <div class="feature-media">
                            <img src="img/consultation.jpg" alt="Consultation service" class="feature-image">
                        </div>
                        <div class="feature-card-body">
                            <h4>Consultation</h4>
                            <p>Professional dental check-ups and preventive care to keep your smile healthy.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 service-col">
                    <div class="feature-card">
                        <div class="feature-media">
                            <img src="img/extractions.jpg" alt="Tooth extraction service" class="feature-image">
                        </div>
                        <div class="feature-card-body">
                            <h4>Tooth Extraction</h4>
                            <p>Removing a tooth that is damaged, decayed, or impacted.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 service-col">
                    <div class="feature-card">
                        <div class="feature-media">
                            <img src="img/cleaning.jpg" alt="Oral prophylaxis service" class="feature-image">
                        </div>
                        <div class="feature-card-body">
                            <h4>Oral Prophylaxis</h4>
                            <p>Professional cleaning of teeth to remove plaque, tartar, and stains.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 service-col">
                    <div class="feature-card">
                        <div class="feature-media">
                            <img src="img/filling.jpg" alt="Permanent tooth filling service" class="feature-image">
                        </div>
                        <div class="feature-card-body">
                            <h4>Permanent Tooth Filling</h4>
                            <p>Restoring damaged teeth with durable filling materials to maintain function and aesthetics.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="services-nav-mobile">
                <button class="services-nav-btn" id="servicesPrevBtn" aria-label="Previous service">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button class="services-nav-btn" id="servicesNextBtn" aria-label="Next service">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <h2 class="about-title">About FSUU Dental Clinic</h2>
                    <p class="about-lead">Located at Father Saturnino Urios University (FSUU) at 1st floor CB Building, our dental clinic provides state-of-the-art dental care with a focus on patient comfort and satisfaction.</p>
                    <p class="about-body">Our team of experienced dentists and staff are committed to delivering high-quality dental services using the latest technology and techniques. We believe that everyone deserves a healthy, beautiful smile.</p>
                    <ul class="about-list list-unstyled">
                        <li><i class="bi bi-check-circle-fill"></i>Modern equipment and technology</li>
                        <li><i class="bi bi-check-circle-fill"></i>Experienced and qualified dentists</li>
                        <li><i class="bi bi-check-circle-fill"></i>Patient-centered care approach</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="about-slideshow">
                        <div class="fac-slide active"><img src="img/insidefsuudental.jpg" alt="Inside FSUU Dental"></div>
                        <div class="fac-slide"><img src="img/outside.jpg" alt="Outside FSUU Dental"></div>
                        <div class="fac-slide"><img src="img/counter1.jpg" alt="Clinic Counter"></div>
                        <button class="fac-arrow fac-prev" onclick="facSlide(-1)"><i class="bi bi-chevron-left"></i></button>
                        <button class="fac-arrow fac-next" onclick="facSlide(1)"><i class="bi bi-chevron-right"></i></button>
                        <div class="fac-dots">
                            <span class="fac-dot active" onclick="facGoTo(0)"></span>
                            <span class="fac-dot" onclick="facGoTo(1)"></span>
                            <span class="fac-dot" onclick="facGoTo(2)"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section-wrap">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-4">
                    <h2 class="display-4 fw-bold text-primary">Contact Us</h2>
                    <p class="lead text-muted">Get in touch for appointments or inquiries</p>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-cards">
                        <div class="contact-card-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <div>
                                <div class="contact-label">Address</div>
                                <div class="contact-value">San Francisco Street, Butuan City, Philippines, 8600</div>
                            </div>
                        </div>
                        <div class="contact-card-item">
                            <i class="bi bi-telephone-fill"></i>
                            <div>
                                <div class="contact-label">Phone</div>
                                <div class="contact-value">+63 951 250 4812</div>
                            </div>
                        </div>
                        <div class="contact-card-item">
                            <i class="bi bi-envelope-fill"></i>
                            <div>
                                <div class="contact-label">Email</div>
                                <div class="contact-value">clinic.triage@urios.edu.ph</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <div class="footer-brand">
                        <h5 class="footer-brand-name">FSUU Dental Clinic</h5>
                        <p class="footer-tagline">Committed to providing excellent dental care with compassion and professionalism.</p>
                        <div class="footer-contact-mini">
                            <span><i class="bi bi-geo-alt-fill"></i> CB Bldg 1F, FSUU, Butuan City</span>
                            <span><i class="bi bi-telephone-fill"></i> +63 951 250 4812</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <h5>Quick Links</h5>
                    <ul class="footer-links list-unstyled">
                        <li><a href="auth/login.php"><i class="bi bi-chevron-right"></i> Login</a></li>
                        <li><a href="auth/register.php"><i class="bi bi-chevron-right"></i> Register</a></li>
                        <li><a href="#features"><i class="bi bi-chevron-right"></i> Services</a></li>
                        <li><a href="#about"><i class="bi bi-chevron-right"></i> About</a></li>
                        <li><a href="#contact"><i class="bi bi-chevron-right"></i> Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5>Follow Us</h5>
                    <p class="footer-social-desc">Stay updated with our latest news and announcements.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/p/FSUU-Medical-Dental-Clinic-100057208429140/" target="_blank" class="social-icon-btn">
                            <i class="bi bi-facebook"></i> Facebook
                        </a>
                    </div>
                </div>
            </div>
            <hr class="footer-divider">
            <div class="text-center">
                <p class="mb-0 footer-copy">&copy; 2026 FSUU Dental Clinic. All rights reserved.</p>
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

        // Hero Slideshow
        let facIdx = 0;
        const facSlides = document.querySelectorAll('.fac-slide');
        const facDots   = document.querySelectorAll('.fac-dot');
        function facGoTo(n) {
            facSlides[facIdx].classList.remove('active');
            facDots[facIdx].classList.remove('active');
            facIdx = (n + facSlides.length) % facSlides.length;
            facSlides[facIdx].classList.add('active');
            facDots[facIdx].classList.add('active');
        }
        function facSlide(dir) { facGoTo(facIdx + dir); }
        setInterval(() => facSlide(1), 5000);

        // Mobile services slider arrows
        const servicesRow = document.querySelector('.services-row');
        const servicesPrevBtn = document.getElementById('servicesPrevBtn');
        const servicesNextBtn = document.getElementById('servicesNextBtn');
        if (servicesRow && servicesPrevBtn && servicesNextBtn) {
            const isMobile = () => window.matchMedia('(max-width: 991px)').matches;
            const getStep = () => {
                const firstCard = servicesRow.querySelector('.service-col');
                return firstCard ? firstCard.getBoundingClientRect().width : servicesRow.clientWidth;
            };
            const updateButtons = () => {
                if (!isMobile()) return;
                const maxScroll = servicesRow.scrollWidth - servicesRow.clientWidth;
                servicesPrevBtn.disabled = servicesRow.scrollLeft <= 5;
                servicesNextBtn.disabled = servicesRow.scrollLeft >= (maxScroll - 5);
            };

            servicesPrevBtn.addEventListener('click', () => {
                servicesRow.scrollBy({ left: -getStep(), behavior: 'smooth' });
            });
            servicesNextBtn.addEventListener('click', () => {
                servicesRow.scrollBy({ left: getStep(), behavior: 'smooth' });
            });
            servicesRow.addEventListener('scroll', updateButtons, { passive: true });
            window.addEventListener('resize', updateButtons);
            updateButtons();
        }


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
