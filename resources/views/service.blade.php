@extends('layouts.app')

@section('title', 'Manufacturing - SL Pharma | Homeopathic Production Capabilities')
@section('keywords', 'homeopathic manufacturing, contract manufacturing, GMP facility, mother tincture manufacturing')
@section('description', "Explore SL Pharma's state-of-the-art homeopathic manufacturing capabilities with GMP certification.")

@section('content')

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start"><h1 class="display-1 mb-0 animated slideInLeft text-white">Manufacturing</h1></div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item text-white active">Manufacturing</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Manufacturing Intro -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5 wow fadeIn" data-wow-delay="0.1s">
                    <h1 class="mb-5">Our <span class="text-uppercase text-primary bg-light px-2">Manufacturing</span> Capabilities</h1>
                    <p>At SL Pharma, we operate a fully integrated homeopathic manufacturing facility equipped with modern machinery and technology.</p>
                    <p class="mb-5">From sourcing authenticated raw materials to final packaging, every step is monitored by qualified pharmacists and quality assurance professionals.</p>
                    <div class="d-flex align-items-center bg-light">
                        <div class="btn-square flex-shrink-0 bg-primary" style="width:100px;height:100px;"><i class="fa fa-phone fa-2x text-white"></i></div>
                        <div class="px-3"><h3>+91 94504 26807</h3><span>Call us for manufacturing inquiries and bulk orders</span></div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="row g-0">
                        @foreach([
                            ['service-1.jpg','Mother Tincture Production','Classical extraction and maceration processes for 500+ plant, animal, and mineral sources.','bg-primary'],
                            ['service-2.jpg','Potentization & Dilutions','Precision succussion and dilution processes for CH and LM potencies.','bg-light'],
                            ['service-3.jpg','Biochemic & Trituration','Pharmaceutical-grade trituration and tablet pressing for all 12 biochemic salts.','bg-light'],
                            ['service-4.jpg','Quality Control Lab','In-house analytical laboratory with HPLC, TLC, and other testing equipment.','bg-primary'],
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

    <!-- Additional Services -->
    <div class="container-xxl pb-5">
        <div class="container pb-5">
            <div class="text-center mb-5 wow fadeIn" data-wow-delay="0.1s"><h1 class="mb-4">Additional <span class="text-uppercase text-primary bg-light px-2">Services</span></h1></div>
            <div class="row g-4">
                @foreach([
                    ['fa-tags','Private Label Manufacturing','We offer complete private labeling services. Supply us your brand design and we manufacture, package, and deliver ready-to-sell products under your label.'],
                    ['fa-cogs','Contract Manufacturing','Full-service contract manufacturing for homeopathic brands. We handle everything from formulation to final packaging as per your specifications.'],
                    ['fa-boxes','Bulk &amp; Wholesale Supply','Competitive pricing for bulk orders. We supply to distributors, wholesalers, hospitals, and pharmacy chains with flexible minimum order quantities.'],
                ] as $i => $e)
                <div class="col-md-6 col-lg-4 wow fadeIn" data-wow-delay="{{ 0.2 + ($i * 0.2) }}s">
                    <div class="text-center p-4 bg-light h-100">
                        <i class="fa {{ $e[0] }} fa-3x text-primary mb-3"></i>
                        <h4>{{ $e[1] }}</h4>
                        <p class="mb-0">{!! $e[2] !!}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="container-fluid bg-primary newsletter p-0">
        <div class="container p-0">
            <div class="row g-0 align-items-center">
                <div class="col-md-5 ps-lg-0 wow fadeIn" data-wow-delay="0.2s"><img class="img-fluid w-100" src="{{ asset('public/img/newsletter.jpg') }}" alt="SL Pharma Manufacturing"></div>
                <div class="col-md-7 py-5 newsletter-text wow fadeIn" data-wow-delay="0.5s">
                    <div class="p-5">
                        <h1 class="mb-4">Contact Us for <span class="text-uppercase text-primary bg-white px-2">Manufacturing Details</span></h1>
                        <p class="mb-4">For bulk orders, contract manufacturing, private labeling, and customized formulations, please contact our manufacturing team.</p>
                        <p class="mb-2"><i class="fa fa-phone-alt me-3"></i><strong>Phone:</strong> +91 94504 26807</p>
                        <p class="mb-2"><i class="fa fa-envelope me-3"></i><strong>Email:</strong> care@slhomeopharma.com</p>
                        <p class="mb-4"><i class="fa fa-envelope me-3"></i><strong>Orders:</strong> orders@slpharma.in</p>
                        <a href="{{ url('/contact') }}" class="btn btn-light px-4">Send Inquiry</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
