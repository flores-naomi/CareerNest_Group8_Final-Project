<?php require_once 'includes/header.php'; //contact.php ?>

<div class="container py-5">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="text-primary-custom mb-3">Contact Us</h1>
                <p class="lead">We'd love to hear from you! Reach out to us with any questions or feedback.</p>
            </div>
        </div>
    </div>

    <div class="row mt-5 gx-5">
        <!-- Contact Information -->
        <div class="col-md-5">
            <div class="card card-custom p-4 h-100">
                <h3 class="section-heading text-primary-custom">Get in Touch</h3>
                
                <div class="contact-info mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="contact-icon me-3">
                            <i class="fas fa-envelope text-primary-custom"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">Email</h5>
                            <p class="mb-0">info@careernest.com</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="contact-icon me-3">
                            <i class="fas fa-phone-alt text-primary-custom"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">Phone</h5>
                            <p class="mb-0">(123) 456-7890</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <div class="contact-icon me-3">
                            <i class="fas fa-map-marker-alt text-primary-custom"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">Address</h5>
                            <p class="mb-0">123 Career Street, Suite 100<br>San Francisco, CA 94104</p>
                        </div>
                    </div>
                </div>
                
                <div class="social-links mt-4">
                    <h5 class="mb-3">Follow Us</h5>
                    <div class="d-flex">
                        <a href="#" class="btn btn-outline-custom me-2"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="btn btn-outline-custom me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="btn btn-outline-custom me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="btn btn-outline-custom"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Form -->
        <div class="col-md-7">
            <div class="card card-custom p-4">
                <h3 class="section-heading text-primary-custom">Send Us a Message</h3>
                
                <form id="contactForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" placeholder="Your name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" placeholder="Your email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="subject" placeholder="What is this regarding?">
                    </div>
                    
                    <div class="mb-4">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" rows="5" placeholder="Your message here..." required></textarea>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" value="" id="newsletter">
                        <label class="form-check-label" for="newsletter">
                            Subscribe to our newsletter for career tips and updates
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary-custom px-4 py-2">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.btn-primary-custom {
    background: #4B654F;
    color: #fff;
    border-radius: 6px;
    font-weight: bold;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary-custom:hover {
    background: #3A463A;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.btn-outline-custom {
    border: 1.5px solid #4B654F;
    color: #4B654F;
    border-radius: 6px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-outline-custom:hover {
    background: #4B654F;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.text-primary-custom {
    color: #4B654F !important;
}

.text-secondary-custom {
    color: #3A463A !important;
}

.card-custom {
    border: 1px solid #BFCABF !important;
    color: #3A463A;
    transition: all 0.3s ease;
    border-radius: 8px;
}

.card-custom:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.section-heading {
    position: relative;
    padding-bottom: 15px;
    margin-bottom: 30px;
}

.section-heading:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 70px;
    height: 3px;
    background: #4B654F;
}

.hero-section {
    background-color: #E6EFE6;
    border-radius: 10px;
    padding: 60px 30px;
    margin-bottom: 60px;
    position: relative;
    overflow: hidden;
}

.hero-section:before {
    content: '';
    position: absolute;
    top: -30px;
    right: -30px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background-color: rgba(75, 101, 79, 0.1);
    z-index: 0;
}

.form-control:focus {
    border-color: #4B654F;
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25);
}

.form-check-input:checked {
    background-color: #4B654F;
    border-color: #4B654F;
}

.contact-icon {
    width: 40px;
    height: 40px;
    background: #E6EFE6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.contact-icon i {
    font-size: 18px;
}
</style>

<?php require_once 'includes/footer.php'; ?>