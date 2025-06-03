<?php require_once 'includes/header.php'; //about.php ?>

<style>
.btn-primary-custom {
    background: #4B654F;
    color: #fff;
    border-radius: 6px;
    font-weight: bold;
    transition: all 0.3s ease;
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

.team-member {
    text-align: center;
    margin-bottom: 30px;
}

.team-member img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid #E6EFE6;
    margin-bottom: 15px;
}

.value-icon {
    width: 80px;
    height: 80px;
    background: #E6EFE6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.value-icon i {
    font-size: 32px;
    color: #4B654F;
}

</style>

<div class="container py-5">
    <!-- Hero Section -->
    <div class="hero-section shadow-sm">
        <div class="row align-items-center">
            <div class="col-md-8 offset-md-2 text-center">
                <h1 class="text-primary-custom mb-4">About Us</h1>
                <p class="lead text-secondary-custom">We are dedicated to connecting talented individuals with their dream careers and helping businesses find their perfect match.</p>
                <a href="search_jobs.php" class="btn btn-primary-custom mt-3">Explore Jobs</a>
            </div>
        </div>
    </div>
    
    <!-- Mission & Vision Section -->
    <div class="row mb-5">
        <div class="col-md-6 mb-4 mb-md-0">
            <div class="card h-100 border-0 shadow-sm card-custom">
                <div class="card-body p-4">
                    <h3 class="section-heading text-primary-custom">Our Mission</h3>
                    <p class="text-secondary-custom">Our mission is to revolutionize the job search experience by creating a platform that seamlessly connects job seekers with their ideal opportunities. We strive to empower individuals to pursue fulfilling careers while helping businesses find exceptional talent that drives their success.</p>
                    <p class="text-secondary-custom">We believe that finding the right job shouldn't be complicated. That's why we've built a user-friendly platform with powerful tools that make job searching efficient and effective.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 border-0 shadow-sm card-custom">
                <div class="card-body p-4">
                    <h3 class="section-heading text-primary-custom">Our Vision</h3>
                    <p class="text-secondary-custom">We envision a world where everyone can find their perfect job match, regardless of their background or experience level. Our goal is to create a global ecosystem where talent and opportunity meet without barriers.</p>
                    <p class="text-secondary-custom">By leveraging innovative technology and a deep understanding of the job market, we aim to transform how people discover careers and how employers build their teams in the digital age.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Our Values Section -->
    <h2 class="text-center text-primary-custom mb-5">Our Core Values</h2>
    <div class="row mb-5">
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="card h-100 border-0 shadow-sm card-custom">
                <div class="card-body p-4 text-center">
                    <div class="value-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h4 class="text-primary-custom mb-3">Integrity</h4>
                    <p class="text-secondary-custom">We believe in transparency and honesty in everything we do. Our commitment to ethical practices builds trust with our users and partners.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="card h-100 border-0 shadow-sm card-custom">
                <div class="card-body p-4 text-center">
                    <div class="value-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h4 class="text-primary-custom mb-3">Innovation</h4>
                    <p class="text-secondary-custom">We constantly push boundaries and explore new technologies to improve the job searching and recruiting experience.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm card-custom">
                <div class="card-body p-4 text-center">
                    <div class="value-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4 class="text-primary-custom mb-3">Inclusivity</h4>
                    <p class="text-secondary-custom">We embrace diversity and create equal opportunities for all job seekers, regardless of their background or experience.</p>
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- Call to Action Section -->
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card border-0 shadow-sm card-custom">
                <div class="card-body p-5 text-center">
                    <h3 class="text-primary-custom mb-3">Ready to Start Your Journey?</h3>
                    <p class="text-secondary-custom mb-4">Whether you're searching for your dream job or looking to hire exceptional talent, we're here to help you succeed.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="search_jobs.php" class="btn btn-primary-custom">Find Jobs</a>
                        <a href="contact.php" class="btn btn-outline-custom">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>