<style>
    :root {
        --primary-color: #4B654F;
        --primary-dark: #3A463A;
        --primary-light: #D6EFD6;
        --accent-color: #E9F5E9;
        --text-primary: #333333;
        --text-muted: #6c757d;
        --light-gray: #f8f9fa;
        --border-radius: 15px;
        --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    
    /* Footer Styles */
    footer {
        background-color: white;
        box-shadow: var(--box-shadow);
        border-top: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    footer h5 {
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }
    
    footer ul.list-unstyled li {
        margin-bottom: 0.5rem;
    }
    
    footer ul.list-unstyled li a {
        color: var(--text-primary);
        transition: all 0.2s ease;
        text-decoration: none;
    }
    
    footer ul.list-unstyled li a:hover {
        color: var(--primary-color);
        padding-left: 5px;
    }
    
    footer hr {
        border-color: rgba(0, 0, 0, 0.05);
        margin: 1.5rem 0;
    }
    
    footer p {
        color: var(--text-muted);
    }
</style>
</div>

<!-- Footer Section -->
<footer class="bg-white shadow-sm py-4 mt-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <h5 class="fw-bold">CareerNest</h5>
                <p class="mb-0">Your trusted partner in career growth and job search.</p>
            </div>
            <div class="col-md-6 mb-3 mb-md-0">
                <h5 class="fw-bold">Quick Links</h5>
                <div class="row">
                    <div class="col-sm-6">
                        <ul class="list-unstyled">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="search_jobs.php">Jobs</a></li>
                        </ul>
                    </div>
                    <div class="col-sm-6">
                        <ul class="list-unstyled">
                            <li><a href="about.php">About Us</a></li>
                            <li><a href="contact.php">Contact</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <div class="text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> CareerNest. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Custom JavaScript -->
<script>
    // Enable Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Auto-hide alerts after 5 seconds
    window.setTimeout(function () {
        $(".alert").fadeTo(500, 0).slideUp(500, function () {
            $(this).remove();
        });
    }, 5000);
</script>
</body>
</html>