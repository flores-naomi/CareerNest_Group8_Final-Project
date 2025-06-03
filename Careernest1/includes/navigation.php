<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = getUserRole();
?>

<nav class="navbar navbar-expand-lg shadow-sm rounded mb-3" style="background:#fff; border-bottom:1px solid #BFCABF;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php" style="color:#3A463A;">CareerNest</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" 
                       href="index.php">Home</a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <?php if ($user_role === 'admin'): ?>
                        <!-- Admin Navigation -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'admin_dashboard.php' ? 'active' : ''; ?>" 
                               href="admin_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'manage_users.php' ? 'active' : ''; ?>" 
                               href="manage_users.php">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'manage_companies.php' ? 'active' : ''; ?>" 
                               href="manage_companies.php">Companies</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'manage_jobs.php' ? 'active' : ''; ?>" 
                               href="manage_jobs.php">Jobs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'manage_schedules.php' ? 'active' : ''; ?>" 
                               href="manage_schedules.php">Schedule</a>
                        </li>
                    <?php elseif ($user_role === 'company'): ?>
                        <!-- Company Navigation -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'company_dashboard.php' ? 'active' : ''; ?>" 
                               href="company_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'post_job.php' ? 'active' : ''; ?>" 
                               href="post_job.php">Post Job</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'company_profile.php' ? 'active' : ''; ?>" 
                               href="company_profile.php">Profile</a>
                        </li>
                    <?php else: ?>
                        <!-- User Navigation -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'user_dashboard.php' ? 'active' : ''; ?>" 
                               href="user_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'search_jobs.php' ? 'active' : ''; ?>" 
                               href="search_jobs.php">Find Jobs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'user_profile.php' ? 'active' : ''; ?>" 
                               href="user_profile.php">Profile</a>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'search_jobs.php' ? 'active' : ''; ?>" 
                           href="search_jobs.php">Find Jobs</a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars(getUserName()); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($user_role === 'admin'): ?>
                                <li><a class="dropdown-item" href="admin_dashboard.php">Dashboard</a></li>
                            <?php elseif ($user_role === 'company'): ?>
                                <li><a class="dropdown-item" href="company_dashboard.php">Dashboard</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="user_dashboard.php">Dashboard</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'login.php' ? 'active' : ''; ?>" 
                           href="login.php">Login</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo ($current_page === 'register_company.php' || $current_page === 'register_individual.php') ? 'active' : ''; ?>" href="#" id="registerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Register
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="registerDropdown">
                            <li><a class="dropdown-item" href="register_company.php">Register as Company</a></li>
                            <li><a class="dropdown-item" href="register_individual.php">Register as Individual</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 