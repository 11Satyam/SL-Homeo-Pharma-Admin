@extends('layouts.app')

@section('title', 'About Us - SL Pharma | Homeopathic Manufacturing Company')
@section('keywords', 'SL Pharma, about homeopathic company, GMP homeopathic, natural medicine manufacturer')
@section('description', 'Learn about SL Pharma - a trusted homeopathic manufacturing company dedicated to producing high-quality, GMP-certified homeopathic medicines.')

@section('content')

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start"><h1 class="display-1 mb-0 animated slideInLeft text-white">About Us</h1></div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item text-white active">About Us</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- About -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="row g-5">
                <div class="col-lg-6">
                    <div class="row">
                        <div class="col-6 wow fadeIn" data-wow-delay="0.1s"><img class="about-img-fixed" src="{{ asset('public/img/about-1.jpg') }}" alt="SL Pharma Manufacturing"></div>
                        <div class="col-6 wow fadeIn" data-wow-delay="0.3s">
                            <img class="about-img-fixed" style="height:320px;" src="{{ asset('public/img/about-2.jpg') }}" alt="Quality Lab Testing">
                            <div class="d-flex align-items-center justify-content-center text-center bg-primary px-4" style="height:100px;"><h4 class="text-white lh-base mb-0">Trusted Homeopathic Manufacturer Company</h4></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 wow fadeIn" data-wow-delay="0.5s">
                    <h1 class="mb-5"><span class="text-uppercase text-primary bg-light px-2">Welcome to</span> SL Pharma</h1>
                    <p class="mb-4">S.L Homoeo Pharma is a dedicated homeopathic manufacturing company committed to producing the highest quality homeopathic medicines using time-tested classical methods combined with modern pharmaceutical technology.</p>
                    <p class="mb-4">Established with a vision to bring natural healing to every household, we specialize in manufacturing a comprehensive range of homeopathic products including mother tinctures, potentized dilutions, biochemic tablets, triturations, and specialty formulations.</p>
                    <p class="mb-5">Our GMP-certified manufacturing facility, stringent quality control processes, and experienced team of homeopathic pharmacists ensure that every product we manufacture meets the highest standards of purity, potency, and efficacy.</p>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <h6 class="mb-3"><i class="fa fa-check text-primary me-2"></i>GMP Certified </h6>
                            <h6 class="mb-0"><i class="fa fa-check text-primary me-2"></i>In-House Quality Testing</h6>
                        </div>
                        <div class="col-sm-6">
                            <h6 class="mb-3"><i class="fa fa-check text-primary me-2"></i>100% Natural Ingredients</h6>
                            <h6 class="mb-0"><i class="fa fa-check text-primary me-2"></i>Pharmacopoeia Compliant</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vision & Mission -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row g-5">
                <div class="col-lg-6 wow fadeIn" data-wow-delay="0.1s">
                    <h1 class="mb-4"><span class="text-uppercase text-primary bg-light px-2">Our</span> Vision</h1>
                    <p class="mb-4">Our vision is to be India's most trusted homeopathic manufacturing company, making natural healing accessible to every individual. We aspire to set new benchmarks through innovation, quality, and ethical practices.</p>
                    <p class="mb-0">We are committed to advancing the science of homeopathy by investing in research, adopting the latest pharmaceutical technologies, and continuously improving our manufacturing processes.</p>
                </div>
                <div class="col-lg-6 wow fadeIn" data-wow-delay="0.3s">
                    <h1 class="mb-4"><span class="text-uppercase text-primary bg-light px-2">Our</span> Mission</h1>
                    <p class="mb-4">Our mission is to manufacture and supply the finest quality homeopathic medicines while adhering to the strictest pharmaceutical standards. We strive to support healthcare practitioners, pharmacies, and distributors.</p>
                    <p class="mb-0">We are dedicated to preserving the classical principles of homeopathy while embracing modern manufacturing excellence and building lasting partnerships with our clients.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Why Choose Us -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="text-center wow fadeIn" data-wow-delay="0.1s"><h1 class="mb-5">Why Choose <span class="text-uppercase text-primary bg-light px-2">SL Pharma</span></h1></div>
            <div class="row g-5 align-items-center text-center">
                @foreach([
                    ['fa-certificate','GMP Certified','Our manufacturing plant is certified under Good Manufacturing Practices, ensuring every product is made in a controlled, hygienic environment.'],
                    ['fa-flask','Quality Lab Testing','All products undergo comprehensive laboratory testing including identity, purity, potency, and safety checks before market release.'],
                    ['fa-leaf','Natural & Pure','We use only authenticated, naturally sourced raw materials to maintain the integrity and efficacy of our homeopathic preparations.'],
                    ['fa-boxes','Wide Product Range','500+ mother tinctures, full CH and LM dilutions, biochemic salts, triturations, ointments, and specialty kits.'],
                    ['fa-truck','Reliable Supply','Our efficient production and distribution network ensures timely supply to distributors, wholesalers, clinics, and pharmacies across India.'],
                    ['fa-user-md','Expert Team','Our team of qualified homeopathic pharmacists, scientists, and QA professionals brings decades of experience.'],
                ] as $i => $r)
                <div class="col-md-6 col-lg-4 wow fadeIn" data-wow-delay="{{ [0.1,0.3,0.5,0.1,0.3,0.5][$i] }}s">
                    <i class="fa {{ $r[0] }} fa-5x text-primary mb-4"></i>
                    <h4>{{ $r[1] }}</h4>
                    <p class="mb-0">{!! $r[2] !!}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    <!-- CTA -->
    <div class="container-fluid bg-primary newsletter p-0">
        <div class="container p-0">
            <div class="row">            
                <div class="col-md-5 ps-lg-0 wow fadeIn" data-wow-delay="0.2s"><img class="img-fluid w-100" src="{{ asset('public/img/newsletter.jpg') }}" alt="SL Pharma"></div>
                <div class="col-md-7 py-5 newsletter-text wow fadeIn" data-wow-delay="0.5s">
                    <div class="p-5">
                        <h1 class="mb-4">Partner With <span class="text-uppercase text-primary bg-white px-2">SL Pharma</span></h1>
                        <p class="mb-4">Interested in bulk orders, private labeling, or contract manufacturing? Contact our team for customized solutions.</p>
                        <p class="mb-2"><i class="fa fa-phone-alt me-3"></i><strong>Phone:</strong> +91 94504 26807</p>
                        <p class="mb-4"><i class="fa fa-envelope me-3"></i><strong>Email:</strong> care@slhomeopharma.com</p>
                        <a href="{{ url('/contact') }}" class="btn btn-light px-4">Contact Us Today</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
