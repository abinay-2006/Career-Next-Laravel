<!DOCTYPE html>
<html lang="en">

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="all,follow" />
    <meta name="author" content="Job Portal" />
    <meta name="copyright" content="Job Portal" />

    <title>JOB PORTAL - Find Your Dream Career</title>
    <meta name="description" content="Connect with top employers and find your perfect job match. Browse thousands of opportunities across various industries." />
    <meta name="keywords" content="jobs, career, employment, job search, recruitment, hiring, professional development" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
    <link rel="stylesheet" href="{{ asset('style.css') }}">
</head>

<body>
    <div id="main" class="box">

        <header class="modern-header">
            <div class="header-content">
                <div class="logo">
                    <h1 class="brand-logo">
                        <img src="{{ asset('images/CareerNext.png') }}" alt="Career Next Logo" width="50" height="50">
                        <span>Career Next</span>
                    </h1>
                </div>
                <div class="header-stats">
                    <h2>
                        <p style = "text-align: center;">Your Gateway to Professional Success</p>
                    </h2>
                </div>
            </div>
        </header>

        <nav class="modern-menu">
            <div class="menu-container">
                <ul class="nav-links">
                    <li><a class="active"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="{{ route('login') }}"><i class="fas fa-search"></i> Find Jobs</a></li>
                    <li><a href="{{ route('login') }}"><i class="fas fa-building"></i> Companies</a></li>
                    <li><a href="{{ route('login') }}"><i class="fas fa-book"></i> Resources</a></li>

                </ul>
                <div class="auth-buttons">
                    <a href="{{ route('login') }}" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="{{route('login')}}" class="btn-register"><i class="fas fa-user-plus"></i> Register</a>
                </div>
            </div>
        </nav>

        <div id="page" class="box">
            <div id="page-in" class="box">

                <div id="content">
                    <div class="article">
                        <h2><span><a href="#">Welcome To Your Career Journey</a></span></h2>

                        <p class="welcome-letter">
                            <span class="style2">W</span>elcome to the online Job Portal designed to connect talented professionals with leading employers.
                        </p>

                        <div class="feature-grid">
                            <div class="feature-card">
                                <i class="fas fa-user-graduate"></i>
                                <h3>For Job Seekers</h3>
                                <p>Create your profile, upload your resume, and apply to thousands of jobs that match your skills.</p>
                            </div>
                            <div class="feature-card">
                                <i class="fas fa-building"></i>
                                <h3>For Employers</h3>
                                <p>Post jobs, manage applications, and find the perfect candidates for your organization.</p>
                            </div>
                            <div class="feature-card">
                                <i class="fas fa-chart-line"></i>
                                <h3>Career Growth</h3>
                                <p>Access resources, tips, and tools to advance your career and professional development.</p>
                            </div>
                            <div class="feature-card">
                                <i class="fas fa-handshake"></i>
                                <h3>Direct Connect</h3>
                                <p>Connect directly with hiring managers and get personalized job recommendations.</p>
                            </div>
                        </div>

                        <p>Our platform provides job seekers with powerful tools to search for positions based on their qualifications, experience, and preferences. Create your detailed profile, showcase your educational background, work experience, and skills to attract potential employers.</p>

                        <p>For employers, we offer a comprehensive recruitment solution. Register your company, post job openings, manage applications efficiently, and communicate with candidates through our integrated messaging system. Find the right talent faster with our smart matching algorithm.</p>

                        <div class="banner-image">
                            <img src="{{ asset('images/work.jpg') }}" alt="Professional Success" width="600" height="300" />
                        </div>

                        <div style="text-align: center; margin-top: 30px;">
                            <a href="{{ route('login') }}" class="btn-register" style="display: inline-block; padding: 12px 35px; font-size: 1.1rem;">
                                <i class="fas fa-rocket"></i> Start Your Journey Today
                            </a>
                        </div>
                    </div>
                </div>

                <div id="col" class="noprint">
                    <div id="col-in">
                        <h3><i class="fas fa-link"></i> Quick Actions</h3>
                        <ul>
                            <li><a href="login.php?tab=login&type=jobseeker"><i class="fas fa-search"></i> Advanced Job Search</a></li>
                            <li><a href="login.php?tab=login&type=employer"><i class="fas fa-plus-circle"></i> Post a New Job</a></li>
                            <li><a href="login.php?tab=login&type=jobseeker"><i class="fas fa-user"></i> Update My Profile</a></li>
                            <li><a href="login.php?tab=login&type=jobseeker"><i class="fas fa-file-alt"></i> Track Applications</a></li>
                            <li><a href="login.php?tab=login&type=jobseeker"><i class="fas fa-bookmark"></i> Saved Jobs</a></li>
                        </ul>



                        <div class="job-alert">
                            <h4><i class="fas fa-bell"></i> Job Alerts</h4>
                            <p>Get notified about new jobs matching your preferences</p>
                            <div class="alert-input">
                                <input type="email" placeholder="Your email address">
                                <button><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer — all links are read-only (pointer-events: none) -->
        <footer class="modern-footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>About Us</h4>
                    <p style="color: #bdc3c7; line-height: 1.6;">We're dedicated to connecting talented professionals with their dream careers and helping companies build amazing teams.</p>

                </div>

                <div class="footer-section">
                    <h4>For Job Seekers</h4>
                    <ul>
                        <li><a>Browse Jobs</a></li>
                        <li><a>Career Advice</a></li>
                        <li><a>Resume Tips</a></li>
                        <li><a>Interview Prep</a></li>
                        <li><a>Salary Guide</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>For Employers</h4>
                    <ul>
                        <li><a>Post a Job</a></li>
                        <li><a>Pricing</a></li>
                        <li><a>Hiring Tips</a></li>
                        <li><a>Talent Search</a></li>
                        <li><a>Employer Branding</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Support</h4>
                    <ul>
                        <li><a>Help Center</a></li>
                        <li><a>Privacy Policy</a></li>
                        <li><a>Terms of Service</a></li>
                        <li><a>FAQ</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2025 JOB PORTAL. All rights reserved. | Made with <i class="fas fa-heart" style="color: #e74c3c;"></i> for better careers</p>
                <p style="margin-top: 10px; font-size: 0.8rem;">
                    <a>Terms</a> | <a>Privacy</a> | <a>Cookies</a> | <a>Accessibility</a>
                </p>
            </div>
        </footer>

    </div>

    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = 1;
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.5
        });

        document.querySelectorAll('.feature-card, .stat-item').forEach(el => {
            el.style.opacity = 0;
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>

</html>