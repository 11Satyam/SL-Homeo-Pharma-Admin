<div class="container-fluid sticky-top">
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light border-bottom border-2 border-white">
            <a href="{{ url('/') }}" class="navbar-brand">
                <img src="{{ asset('public/img/slpharma-logo.png') }}" alt="SL Pharma" class="navbar-logo">
            </a>
            <button type="button" class="navbar-toggler ms-auto me-0" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <div class="navbar-nav ms-auto">
                    <a href="{{ url('/') }}"       class="nav-item nav-link {{ request()->is('/') ? 'active' : '' }}">Home</a>
                    <a href="{{ url('/about') }}"   class="nav-item nav-link {{ request()->is('about') ? 'active' : '' }}">About Us</a>
                    <a href="{{ url('/service') }}" class="nav-item nav-link {{ request()->is('service') ? 'active' : '' }}">Manufacturing</a>
                    <a href="{{ url('/project') }}" class="nav-item nav-link {{ request()->is('project') ? 'active' : '' }}">Products</a>
                     <a href="{{ url('/video') }}"   class="nav-item nav-link {{ request()->is('video') ? 'active' : '' }}">Videos</a>
                    <a href="{{ url('/contact') }}" class="nav-item nav-link {{ request()->is('contact') ? 'active' : '' }}">Contact</a>
                </div>
            </div>
        </nav>
    </div>
</div>
