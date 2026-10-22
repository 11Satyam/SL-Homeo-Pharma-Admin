@extends('layouts.app')

@section('title', 'SL Pharma - Homeopathic Manufacturing Company | Quality Natural Medicines')
@section('keywords', 'SL Pharma, homeopathic manufacturer India, mother tinctures, dilutions, biochemic tablets, GMP certified')
@section('description', 'S.L Homoeo Pharma is a leading GMP-certified homeopathic manufacturing company in India.')

@section('content')

    <!-- Hero Start -->
    <div class="container-fluid hero-header bg-light hero-enhanced" id="hero-section">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                        <div class="hero-founder-float">
                            <div class="hero-avatar-ring">
                                <img src="{{ asset('public/img/Samuel.jpg') }}" alt="SL Pharma Founder" class="hero-avatar-img">
                            </div>
                            <div class="hero-founder-info">
                                <strong>Dr. S.L. Samuel</strong>
                                <small>Father of Homeopathy</small>
                            </div>
                        </div>

                    <!-- Heading -->
                    <h1 class="display-4 fw-bold mb-3 animated slideInDown hero-heading">
                        Leading Homeopathic <span class="gradient-text">Manufacturer</span> in India
                    </h1>

                    <!-- Description -->
                    <p class="hero-subtext mb-4" data-stagger>
                        Trusted by 1,200+ practitioners &amp; distributors across 28 states.
                        GMP-certified facility producing 500+ mother tinctures, full CH &amp; LM dilution range, biochemic salts &amp; more.
                    </p>

                    <!-- Trust Pills -->
                    <div class="hero-pills mb-4" data-stagger>
                        <span class="hero-pill"><i class="fa fa-certificate text-primary me-1"></i> GMP Certified</span>
                        <span class="hero-pill"><i class="fa fa-certificate text-primary me-1"></i> ISO Certified</span>
                        <span class="hero-pill"><i class="fa fa-flask text-primary me-1"></i> 500+ Products</span>
                        <span class="hero-pill"><i class="fa fa-star text-primary me-1"></i> 5+ Years</span>
                    </div>

                    <!-- CTA Buttons -->
                    <div class="d-flex flex-wrap gap-3 mb-4" data-stagger>
                        <a href="{{ url('/contact') }}" class="btn btn-primary btn-lg px-4">
                            <i class="fa fa-file-alt me-2"></i>Get Catalogue
                        </a>
                        <a href="{{ url('/project') }}" class="btn btn-outline-primary btn-lg px-4">
                            <i class="fa fa-boxes me-2"></i>Our Products
                        </a>
                    </div>

                    <!-- Contact Chips -->
                    <div class="hero-contacts" data-stagger>
                        <a href="tel:+919450426807" class="hero-contact-chip text-decoration-none">
                            <span class="hero-contact-icon-wrap"><i class="fa fa-phone text-primary"></i></span>
                            <div>
                                <small class="text-muted hero-chip-label">Call Us</small>
                                <strong class="text-dark hero-chip-value">+91 94504 26807</strong>
                            </div>
                        </a>
                        <a href="mailto:aabhiarjun8090@gmail.com" class="hero-contact-chip text-decoration-none">
                            <span class="hero-contact-icon-wrap"><i class="fa fa-envelope text-primary"></i></span>
                            <div>
                                <small class="text-muted hero-chip-label">Email Us</small>
                                <strong class="text-dark hero-chip-value">aabhiarjun8090@gmail.com</strong>
                            </div>
                        </a>
                        <a href="{{ url('/contact') }}" class="hero-contact-chip text-decoration-none">
                            <span class="hero-contact-icon-wrap"><i class="fa fa-file-alt text-primary"></i></span>
                            <div>
                                <small class="text-muted hero-chip-label">Catalogue</small>
                                <strong class="text-dark hero-chip-value">Price List</strong>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Right: Carousel + Founder Float -->
                <div class="col-lg-6">
                    <div class="hero-media-wrap" data-stagger>
                        <div class="owl-carousel header-carousel">
                            @foreach([
                                ['1.jpg.jpeg', 'Mother Tinctures', '500+ Remedies'],
                                ['2.jpg.jpeg', 'CH &amp; LM Dilutions', 'Full Potency Range'],
                                ['3.jpg.jpeg', 'Biochemic Tablets', 'All 12 Tissue Salts'],
                                ['4.jpg.jpeg', 'Homeopathic Kits', 'Ready-to-Use Sets'],
                                ['5.jpg.jpeg', 'Ointments &amp; Creams', 'Topical Formulations'],
                                ['6.jpg.jpeg', 'Private Label', 'Custom Branding'],
                                ['7.jpg.jpeg', 'Private Label', 'Custom Branding'],
                            ] as $slide)
                            <div class="hero-slide-item position-relative overflow-hidden rounded-3">
                                <img class="img-fluid w-100" src="{{ asset('public/img/' . $slide[0]) }}" alt="{{ $slide[1] }}">
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="container-fluid stats-section">
        <div class="container">
            <div class="row g-0">
                <div class="col-6 col-md-3 stat-item fade-up">
                    <span class="stat-icon"><i class="fa fa-flask"></i></span>
                    <div><span class="stat-number"><span class="counter-number" data-target="500" data-suffix="+">0+</span></span><span class="stat-label">Mother Tinctures</span></div>
                </div>
                <div class="col-6 col-md-3 stat-item fade-up">
                    <span class="stat-icon"><i class="fa fa-certificate"></i></span>
                    <div><span class="stat-number"><span class="counter-number" data-target="25" data-suffix="+">0+</span></span><span class="stat-label">Years Experience</span></div>
                </div>
                <div class="col-6 col-md-3 stat-item fade-up">
                    <span class="stat-icon"><i class="fa fa-handshake"></i></span>
                    <div><span class="stat-number"><span class="counter-number" data-target="1200" data-suffix="+">0+</span></span><span class="stat-label">Happy Clients</span></div>
                </div>
                <div class="col-6 col-md-3 stat-item fade-up">
                    <span class="stat-icon"><i class="fa fa-map-marker-alt"></i></span>
                    <div><span class="stat-number"><span class="counter-number" data-target="28" data-suffix="+">0+</span></span><span class="stat-label">States Supplied</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- About -->
    <div class="container-fluid py-5" id="about-section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-6 wow fadeIn" data-wow-delay="0.1s">
                    <img src="{{ asset('public/img/slpharma-logo.png') }}" alt="SL Pharma" class="img-fluid w-100" style="object-fit:contain; max-height:480px;">
                </div>
                <div class="col-lg-6 wow fadeIn" data-wow-delay="0.5s">
                    <span class="section-eyebrow">About Us</span>
                    <h1 class="mb-4"><span class="text-uppercase text-primary bg-light px-2">Welcome to</span> SL Pharma</h1>
                       <div style="background: #f1f8e9; padding: 15px 20px; border-radius: 10px; border-left: 5px solid #4caf50; max-width: 500px;">
                     <p style="margin: 0; font-size: 18px; color: #2e7d32; font-weight: 500;">
                       🌿 सुरक्षित इलाज, स्थायी आराम – होम्योपैथी के साथ।
                     </p>
                   </div>
                    <p class="mb-3">S.L Homoeo Pharma is a GMP-certified homeopathic manufacturing company dedicated to producing the highest quality homeopathic medicines. We combine classical preparation methods with modern pharmaceutical technology.</p>
                    <p class="mb-4">Our comprehensive product range includes 500+ mother tinctures, full potency dilutions (CH and LM), biochemic tissue salts, triturations, ointments, and specialty homeopathic kits — all manufactured under strict quality control.</p>
                    
                    <div class="about-features-grid mb-2">
                        <div class="about-feature-card">
                            <div class="about-feature-icon-box"><i class="fa fa-certificate text-primary"></i></div>
                            <span>GMP Certified</span>
                        </div>
                        <div class="about-feature-card">
                            <div class="about-feature-icon-box"><i class="fa fa-flask text-primary"></i></div>
                            <span>In-House Quality Testing</span>
                        </div>
                        <div class="about-feature-card">
                            <div class="about-feature-icon-box"><i class="fa fa-book text-primary"></i></div>
                            <span>Pharmacopoeia Compliant</span>
                        </div>
                        <div class="about-feature-card">
                            <div class="about-feature-icon-box"><i class="fa fa-tags text-primary"></i></div>
                            <span>Private Label Available</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mt-5">
                        <a class="btn btn-primary px-4 me-2" href="{{ url('/about') }}">Read More</a>
                        <a class="btn btn-outline-primary btn-square border-2 me-2" href="mailto:aabhiarjun8090@gmail.com"><i class="fa fa-envelope"></i></a>
                        <a class="btn btn-outline-primary btn-square border-2 me-2" href="tel:+919450426807"><i class="fa fa-phone"></i></a>
                        <a class="btn btn-outline-primary btn-square border-2" href="{{ url('/contact') }}"><i class="fa fa-file-alt"></i></a>
                    </div>
                </div>
            </div>
            <div class="row align-items-center mt-5">
                <div class="col-lg-12 wow fadeIn mb-5" data-wow-delay="0.1s">
                    <span class="section-eyebrow">Partner With Us</span>
                    <div style="text-align:center; padding:20px; background:#f1f8e9; border-radius:12px;"> <h2 style="color:#2e7d32; margin-bottom:10px;"> Build Your Brand with Our Trusted Manufacturing Facility </h2> <p style="color:#555; font-size:14px;"> High-Quality | Reliable | Affordable Solutions </p> </div>
        </div>
    </div>
        </div>


    <!-- Certificates Section -->
    <div class="container-fluid py-5" id="certificates-section">
        <div class="container py-3">
            <div class="text-center mb-5 wow fadeIn" data-wow-delay="0.1s">
                <span class="section-eyebrow">Certifications</span>
                <h1>Our <span class="text-uppercase text-primary bg-light px-2">Certificates</span></h1>
                <p class="text-muted mx-auto mt-2" style="max-width:520px;">Certified for quality, safety, and compliance — our credentials reflect our commitment to excellence.</p>
            </div>
            <!--<div class="row g-4 justify-content-center">-->
                <!-- Certificate 1 - PDF -->
            <!--    <div class="col-md-4 wow fadeIn" data-wow-delay="0.1s">-->
            <!--        <div class="certificate-card text-center">-->
            <!--            <div class="certificate-thumb">-->
            <!--                <iframe src="{{ asset('public/img/certificate/certificate-1.pdf') }}#toolbar=0&navpanes=0&scrollbar=0&page=1&view=FitH"-->
            <!--                    class="cert-pdf-preview" scrolling="no" frameborder="0"></iframe>-->
            <!--                <div class="cert-iframe-overlay"-->
            <!--                    onclick="openCertModal('pdf', '{{ asset('public/img/certificate/certificate-1.pdf') }}', 'GMP Certificate')"></div>-->
            <!--            </div>-->
            <!--            <p class="fw-semibold mb-0 mt-2 cert-label">GMP Certificate</p>-->
            <!--            <div class="certificate-actions">-->
            <!--                <button class="btn btn-primary btn-sm"-->
            <!--                    onclick="openCertModal('pdf', '{{ asset('public/img/certificate/certificate-1.pdf') }}', 'GMP Certificate')">-->
            <!--                    <i class="fa fa-eye me-1"></i> View Certificate-->
            <!--                </button>-->
            <!--            </div>-->
            <!--        </div>-->
            <!--    </div>-->

                <!-- Certificate 2 - PDF -->
            <!--    <div class="col-md-4 wow fadeIn" data-wow-delay="0.3s">-->
            <!--        <div class="certificate-card text-center">-->
            <!--            <div class="certificate-thumb">-->
            <!--                <iframe src="{{ asset('public/img/certificate/certificate-2.pdf') }}#toolbar=0&navpanes=0&scrollbar=0&page=1&view=FitH"-->
            <!--                    class="cert-pdf-preview" scrolling="no" frameborder="0"></iframe>-->
            <!--                <div class="cert-iframe-overlay"-->
            <!--                    onclick="openCertModal('pdf', '{{ asset('public/img/certificate/certificate-2.pdf') }}', 'ISO Certificate')"></div>-->
            <!--            </div>-->
            <!--            <p class="fw-semibold mb-0 mt-2 cert-label">ISO Certificate</p>-->
            <!--            <div class="certificate-actions">-->
            <!--                <button class="btn btn-primary btn-sm"-->
            <!--                    onclick="openCertModal('pdf', '{{ asset('public/img/certificate/certificate-2.pdf') }}', 'ISO Certificate')">-->
            <!--                    <i class="fa fa-eye me-1"></i> View Certificate-->
            <!--                </button>-->
            <!--            </div>-->
            <!--        </div>-->
            <!--    </div>-->

                <!-- Certificate 3 - Image -->
            <!--    <div class="col-md-4 wow fadeIn" data-wow-delay="0.5s">-->
            <!--        <div class="certificate-card text-center">-->
            <!--            <div class="certificate-thumb">-->
            <!--                <img src="{{ asset('public/img/certificate/certificate-3.jpeg') }}" alt="Quality Certificate" class="certificate-img">-->
            <!--            </div>-->
            <!--            <p class="fw-semibold mb-0 mt-2 cert-label">Quality Certificate</p>-->
            <!--            <div class="certificate-actions">-->
            <!--                <button class="btn btn-primary btn-sm"-->
            <!--                    onclick="openCertModal('image', '{{ asset('public/img/certificate/certificate-3.jpeg') }}', 'Quality Certificate')">-->
            <!--                    <i class="fa fa-eye me-1"></i> View Certificate-->
            <!--                </button>-->
            <!--            </div>-->
            <!--        </div>-->
            <!--    </div>-->
            <!--</div>-->
             <div class="row g-4 justify-content-center">
                <!-- Certificate 1 -->
                <div class="col-md-4 wow fadeIn" data-wow-delay="0.1s">
                    <div class="certificate-card text-center">
                        <div class="certificate-thumb">
                            <img src="{{ asset('public/img/certificate/certificate-1.jpg') }}" alt="GMP Certificate" class="certificate-img">
                        </div>
                        <p class="fw-semibold mb-0 mt-2 cert-label">GMP Certificate</p>
                        <div class="certificate-actions">
                            <button class="btn btn-primary btn-sm" onclick="openCertModal('image', '{{ asset('public/img/certificate/certificate-1.jpg') }}', 'GMP Certificate')">
                                <i class="fa fa-eye me-1"></i> View Certificate
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Certificate 2 -->
                <div class="col-md-4 wow fadeIn" data-wow-delay="0.3s">
                    <div class="certificate-card text-center">
                        <div class="certificate-thumb">
                            <img src="{{ asset('public/img/certificate/certificate-2.jpg') }}" alt="ISO Certificate" class="certificate-img">
                        </div>
                        <p class="fw-semibold mb-0 mt-2 cert-label">ISO Certificate</p>
                        <div class="certificate-actions">
                            <button class="btn btn-primary btn-sm" onclick="openCertModal('image', '{{ asset('public/img/certificate/certificate-2.jpg') }}', 'ISO Certificate')">
                                <i class="fa fa-eye me-1"></i> View Certificate
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Certificate 3 - Image -->
                <div class="col-md-4 wow fadeIn" data-wow-delay="0.5s">
                    <div class="certificate-card text-center">
                        <div class="certificate-thumb">
                            <img src="{{ asset('public/img/certificate/certificate-3.jpeg') }}" alt="Quality Certificate" class="certificate-img">
                        </div>
                        <p class="fw-semibold mb-0 mt-2 cert-label">Quality Certificate</p>
                        <div class="certificate-actions">
                            <button class="btn btn-primary btn-sm"
                                onclick="openCertModal('image', '{{ asset('public/img/certificate/certificate-3.jpeg') }}', 'Quality Certificate')">
                                <i class="fa fa-eye me-1"></i> View Certificate
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Certificate Modal -->
    <div class="modal fade cert-modal" id="certModal" tabindex="-1" aria-labelledby="certModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered cert-modal-dialog">
            <div class="modal-content cert-modal-content">

                <!-- Header -->
                <div class="cert-modal-header">
                    <div class="cert-modal-header-left">
                        <div class="cert-modal-icon-wrap" id="certModalIcon">
                            <i class="fa fa-file-pdf"></i>
                        </div>
                        <div>
                            <p class="cert-modal-eyebrow mb-0">SL Pharma — Official Document</p>
                            <h5 class="cert-modal-title mb-0" id="certModalLabel"></h5>
                        </div>
                    </div>
                    <button type="button" class="cert-modal-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fa fa-times"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="cert-modal-body" id="certModalBody">
                    <div class="cert-modal-loader" id="certModalLoader">
                        <div class="cert-loader-spinner"></div>
                        <p class="mt-3 text-muted">Loading certificate…</p>
                    </div>
                    <div id="certModalContent"></div>
                </div>

                <!-- Footer -->
                <div class="cert-modal-footer">
                    <span class="cert-modal-badge"><i class="fa fa-shield-alt me-1"></i> Verified &amp; Authentic</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm cert-modal-close-btn" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i> Close
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script>
    function openCertModal(type, src, title) {
        document.getElementById('certModalLabel').textContent = title;

        const icon = document.getElementById('certModalIcon');
        const loader = document.getElementById('certModalLoader');
        const content = document.getElementById('certModalContent');

        // Reset state
        content.innerHTML = '';
        loader.style.display = 'flex';
        icon.className = 'cert-modal-icon-wrap';

        if (type === 'pdf') {
            icon.classList.add('cert-modal-icon-pdf');
            icon.innerHTML = '<i class="fa fa-file-pdf"></i>';
            const iframe = document.createElement('iframe');
            iframe.src = src + '#toolbar=1&navpanes=0';
            iframe.className = 'cert-modal-iframe';
            iframe.onload = () => { loader.style.display = 'none'; };
            content.appendChild(iframe);
        } else {
            icon.classList.add('cert-modal-icon-img');
            icon.innerHTML = '<i class="fa fa-image"></i>';
            const img = document.createElement('img');
            img.src = src;
            img.alt = title;
            img.className = 'cert-modal-img';
            img.onload = () => { loader.style.display = 'none'; };
            content.appendChild(img);
        }

        var modal = new bootstrap.Modal(document.getElementById('certModal'));
        modal.show();

        document.getElementById('certModal').addEventListener('hidden.bs.modal', function () {
            content.innerHTML = '';
            loader.style.display = 'flex';
        }, { once: true });
    }
    </script>

    <!-- Feature Cards -->
    <div class="container-fluid py-5 bg-light">
        <div class="container py-3">
            <div class="text-center mb-5 wow fadeIn" data-wow-delay="0.1s">
                <span class="section-eyebrow">Our Advantages</span>
                <h1>Why Choose <span class="text-uppercase text-primary bg-white px-2 animated-underline">SL Pharma</span></h1>
            </div>
            <div class="row g-4">
                @foreach([
                    ['fa-certificate','GMP Certified Manufacturing','Fully GMP certified facility ensuring every product is produced under strictly controlled pharmaceutical-grade conditions.'],
                    ['fa-flask','In-House Quality Lab','Every batch undergoes rigorous HPLC &amp; TLC testing for identity, purity, and potency before dispatch.'],
                    ['fa-leaf','100% Authentic Raw Materials','Finest authenticated natural ingredients from verified suppliers, following classical homeopathic preparation standards.'],
                    ['fa-boxes','Extensive Product Range','500+ mother tinctures, full CH &amp; LM dilution range, 12 biochemic salts, ointments, and specialty kits.'],
                    ['fa-tags','Private Label Services','Complete private labeling and contract manufacturing for brands, distributors, and healthcare companies.'],
                    ['fa-truck','Pan India Supply','Reliable, timely supply to distributors, pharmacies, clinics, and hospitals across India with competitive pricing.'],
                ] as $f)
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon-circle"><i class="fa {{ $f[0] }} fa-2x text-primary"></i></div>
                        <h5 class="mb-3">{{ $f[1] }}</h5>
                        <p class="mb-0 text-muted">{!! $f[2] !!}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Products -->
    <div class="container-fluid mt-5">
        <div class="container mt-5">
            <div class="row g-0">
                <div class="col-lg-5 wow fadeIn" data-wow-delay="0.1s">
                    <div class="d-flex flex-column justify-content-center bg-primary h-100 p-5" style="background: linear-gradient(160deg, #2E7D32 0%, #1B5E20 100%) !important; position:relative; overflow:hidden;">
                        <span class="section-eyebrow section-eyebrow-light mb-3">Product Range</span>
                        <h1 class="text-white mb-4">Our Homeopathic <span class="text-uppercase text-primary bg-light px-2">Products</span></h1>
                        <h4 class="text-white mb-4">Complete range for practitioners, pharmacies, and distributors</h4>
                        <p class="text-white mb-4">Request our product catalogue and price list. We offer flexible MOQ and bulk discounts.</p>
                        <div class="d-flex">
                            <a href="{{ url('/contact') }}" class="btn btn-light px-4 me-2">Get Catalogue</a>
                            <a href="{{ url('/project') }}" class="btn btn-outline-light px-4">View Products</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="row g-0">
                        @foreach([
                            ['product-1.jpg','Mother Tinctures','500+ Remedies'],
                            ['product-2.jpg','CH &amp; LM Dilutions','Full Potency Range'],
                            ['product-3.jpg','Biochemic Tablets','All 12 Tissue Salts'],
                            ['product-4.jpg','Homeopathic Kits','Ready-to-Use Sets'],
                            ['product-5.jpg','Ointments &amp; Creams','Topical Formulations'],
                            ['product-6.jpg','Private Label','Custom Branding'],
                        ] as $i => $p)
                        <div class="col-md-6 wow fadeIn" data-wow-delay="{{ 0.2 + ($i * 0.1) }}s">
                            <div class="project-item position-relative overflow-hidden">
                                <img class="img-fluid w-100" src="{{ asset('public/img/' . $p[0]) }}" alt="{{ $p[1] }}">
                                <a class="project-overlay text-decoration-none" href="{{ url('/project') }}">
                                    <h4 class="text-white">{{ $p[1] }}</h4>
                                    <small class="text-white">{!! $p[2] !!}</small>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manufacturing -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5 wow fadeIn" data-wow-delay="0.1s">
                    <span class="section-eyebrow">Manufacturing</span>
                    <h1 class="mb-4">Our <span class="text-uppercase text-primary bg-light px-2">Manufacturing</span> Capabilities</h1>
                    <p>SL Pharma operates a fully integrated homeopathic manufacturing facility with modern equipment and strict quality systems.</p>
                    <p class="mb-5">We offer complete manufacturing services including mother tincture production, potentization, trituration, tablet pressing, packaging, and labeling — all under one roof.</p>
                    <div class="d-flex align-items-center bg-light">
                        <div class="btn-square flex-shrink-0 bg-primary" style="width:100px;height:100px;"><i class="fa fa-phone fa-2x text-white"></i></div>
                        <div class="px-3"><h5>+91 94504 26807</h5><h6 class="mb-0">aabhiarjun8090@gmail.com</h6><span>Call or email for bulk orders</span></div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="row g-0">
                        @foreach([
                            ['service-1.jpg','Mother Tincture Production','Classical maceration and extraction for 500+ plant, mineral, and nosode mother tinctures.','bg-primary'],
                            ['service-2.jpg','Potentization &amp; Dilutions','Precision succussion and dilution for CH and LM potencies from low to high.','bg-light'],
                            ['service-3.jpg','Biochemic &amp; Trituration','Pharmaceutical-grade trituration and tablet pressing for all 12 biochemic tissue salts.','bg-light'],
                            ['service-4.jpg','Quality Control Lab','In-house analytical laboratory with HPLC and TLC equipment for complete testing.','bg-primary'],
                        ] as $i => $s)
                        <div class="col-md-6 wow fadeIn" data-wow-delay="{{ 0.2 + ($i * 0.2) }}s">
                            <div class="service-item h-100 d-flex flex-column justify-content-center {{ $s[3] }}">
                                <a href="{{ url('/service') }}" class="service-img position-relative mb-4">
                                    <img class="img-fluid w-100" src="{{ asset('public/img/' . $s[0]) }}" alt="{{ $s[1] }}">
                                    <h3>{{ $s[1] }}</h3>
                                </a>
                                <p class="mb-0">{!! $s[2] !!}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- How We Work / Process Steps -->
    <div class="container-fluid py-5 process-section">
        <div class="container py-3">
            <div class="text-center mb-5 wow fadeIn" data-wow-delay="0.1s">
                <span class="section-eyebrow">Our Process</span>
                <h1 class="mb-2">How We <span class="text-uppercase text-primary bg-light px-2">Work</span></h1>
                <p class="text-muted mx-auto" style="max-width:520px;">From sourcing raw materials to final dispatch — every step follows strict pharmaceutical standards.</p>
            </div>
            <div class="row g-4 justify-content-center">
                @foreach([
                    ['fa-leaf','Source & Authenticate','We procure 100% authentic raw materials from verified, traceable suppliers across India and abroad.'],
                    ['fa-flask','Manufacture & Potentize','Classical preparation using modern GMP-compliant equipment with precision potentization for every remedy.'],
                    ['fa-microscope','Test & Certify','Each batch is tested in our in-house HPLC & TLC lab — released only after meeting strict quality standards.'],
                    ['fa-truck','Pack & Dispatch','Custom packaging and labeling, then timely dispatch to distributors, pharmacies, and clinics across India.'],
                ] as $step)
                <div class="col-6 col-md-3 wow fadeIn" data-wow-delay="0.1s">
                    <div class="process-step-wrap">
                        <div class="process-step-icon">
                            <i class="fa {{ $step[0] }} fa-lg text-primary"></i>
                        </div>
                        <h5>{{ $step[1] }}</h5>
                        <p>{{ $step[2] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Testimonials -->
    <div class="container-xxl">
        <div class="container py-5">
            <div class="text-center mb-5 wow fadeIn" data-wow-delay="0.1s">
                <span class="section-eyebrow">Client Testimonials</span>
                <h1 class="mb-3">What Our <span class="text-uppercase text-primary bg-light px-2">Clients Say</span></h1>
            </div>
            <div class="row justify-content-center">
                <div class="col-md-12 col-lg-9">
                    <div class="owl-carousel testimonial-carousel wow fadeIn" data-wow-delay="0.2s">
                        @foreach([
                            ['testimonial-1.jpg','Excellent Product Quality',"SL Pharma's mother tinctures are exceptional. The consistency and potency are always reliable. Highly recommended for practitioners.",'Dr. Ramesh Patel','Homeopathic Physician, Ahmedabad'],
                            ['testimonial-2.jpg','Reliable Supply Partner','As a distributor, consistency is paramount. SL Pharma delivers on time, every time. Quality is consistent across every batch.','Mr. Suresh Agarwal','Wholesale Distributor, Delhi'],
                            ['testimonial-3.jpg','Best Contract Manufacturer','Their attention to GMP compliance and responsive customer service makes SL Pharma our first choice for contract manufacturing.','Mrs. Kavita Singh','Director, Natural Wellness Brands'],
                        ] as $t)
                        <div class="testimonial-item">
                            <div class="row g-5 align-items-center">
                                <div class="col-md-6"><div class="testimonial-img"><img class="img-fluid" src="{{ asset('public/img/' . $t[0]) }}" alt="{{ $t[3] }}"></div></div>
                                <div class="col-md-6">
                                    <div class="testimonial-text pb-5 pb-md-0">
                                        <span class="testimonial-quote-mark">&ldquo;</span>
                                        <div class="testimonial-stars mb-2">
                                            <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                                        </div>
                                        <h3>{{ $t[1] }}</h3>
                                        <p>{{ $t[2] }}</p>
                                        <h5 class="mb-0">{{ $t[3] }}</h5>
                                        <small class="text-muted">{{ $t[4] }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="container-fluid bg-primary newsletter">
            <div class="row">
                <div class="col-md-5 ps-lg-0 wow fadeIn" data-wow-delay="0.2s"><img class="img-fluid w-100" src="{{ asset('public/img/newsletter.jpg') }}" alt="SL Pharma Products"></div>
                <div class="col-md-7 py-5 newsletter-text wow fadeIn" data-wow-delay="0.5s">
                    <div class="p-5">
                        <h1 class="mb-4">Request Our <span class="text-uppercase text-primary bg-white px-2">Product Catalogue</span></h1>
                        <p class="mb-4">Get our complete product list with pricing for all homeopathic medicines.</p>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><div class="d-flex align-items-center">
                                <i class="fa fa-phone-alt fs-4 me-3"></i><div><h6 class="text-white mb-0">+91 94504 26807</h6><small class="text-white">Call for bulk orders</small></div></div></div>
                            <div class="col-md-6"><div class="d-flex align-items-center">
                                <i class="fa fa-envelope fs-4 me-3"></i><div><small class="text-white">aabhiarjun8090@gmail.com</small></div></div></div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ url('/contact') }}" class="btn btn-light px-4">Get Catalogue</a>
                            <a href="mailto:aabhiarjun8090@gmail.com" class="btn btn-outline-light px-4">Email Now</a>
                            <a href="{{ url('/project') }}" class="btn btn-outline-light px-4">View Products</a>
                        </div>
                    </div>
                </div>
        </div>
    </div>

@endsection
