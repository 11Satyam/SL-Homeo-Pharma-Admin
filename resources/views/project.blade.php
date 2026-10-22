@extends('layouts.app')

@section('title', 'Our Products - SL Pharma | Homeopathic Medicine Range')
@section('keywords', 'homeopathic medicines, mother tinctures, homeopathic dilutions, biochemic tablets, homeopathic kits')
@section('description', 'Explore the complete range of homeopathic products by SL Pharma.')

@section('content')

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start"><h1 class="display-1 mb-0 animated slideInLeft text-white">Our Products</h1></div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item text-white active">Products</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Intro -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5 wow fadeIn" data-wow-delay="0.1s">
                    <h1 class="mb-4">Complete Homeopathic <span class="text-uppercase text-primary bg-light px-2">Product Range</span></h1>
                    <p class="mb-4">SL Pharma offers a comprehensive portfolio of homeopathic products manufactured under the strictest quality standards.</p>
                    <p class="mb-5">Every product is manufactured in our GMP-certified facility using authenticated raw materials and classical preparation methods.</p>
                </div>
                <div class="col-lg-7">
                    <div class="row g-4">
                        @foreach([
                            ['fa-tint','500+ Mother Tinctures','Coming Soon...'],
                            ['fa-vial','Full Potency Range','3X potencies from 6C to CM, LM potencies, and special high potencies'],
                            ['fa-pills','Biochemic Tablets','Coming Soon...'],
                            ['fa-box-open','Specialty Kits','Ready-to-use homeopathic kits for family health, travel, and specific conditions'],
                        ] as $i => $h)
                        <div class="col-md-6 wow fadeIn" data-wow-delay="{{ 0.2 + ($i * 0.2) }}s">
                            <div class="bg-light p-4 text-center h-100">
                                <i class="fa {{ $h[0] }} fa-3x text-primary mb-3"></i>
                                <h4>{{ $h[1] }}</h4>
                                <p class="mb-0">{{ $h[2] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Branded Products -->
    <div class="container-xxl py-5">
        <div class="container py-5">
            <div class="text-center mb-5 wow fadeIn" data-wow-delay="0.1s">
                <h1 class="mb-4">Our <span class="text-uppercase text-primary bg-light px-2">Branded Products</span></h1>
                <p>Targeted homeopathic formulations for common health conditions — ISO &amp; GMP certified</p>
            </div>

            <div class="owl-carousel product-carousel mb-5 wow fadeIn" data-wow-delay="0.2s">
                @foreach($products as $p)
                <div class="px-2">
                    <div class="product-card h-100 d-flex flex-column shadow-sm rounded overflow-hidden border bg-white">
                        <div class="product-card-img position-relative overflow-hidden">
                            <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" class="w-100" style="height:220px;object-fit:cover;">
                            <span class="product-badge position-absolute top-0 end-0 m-2 badge bg-primary">{{ $p['category'] }}</span>
                        </div>
                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <h5 class="fw-bold mb-1">{{ $p['name'] }}</h5>
                            <p class="text-muted small mb-3">{{ $p['category'] }}</p>
                            <a href="{{ url('/product/' . $p['id']) }}" class="btn btn-primary btn-sm px-3 mt-auto">View Details</a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="row g-4">
                @foreach($products as $i => $p)
                <div class="col-md-6 col-lg-4 wow fadeIn" data-wow-delay="{{ round(0.1 + ($i % 3) * 0.2, 1) }}s">
                    <div class="product-card h-100 d-flex flex-column shadow-sm rounded overflow-hidden border">
                        <div class="product-card-img position-relative overflow-hidden">
                            <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" class="w-100">
                            <span class="product-badge position-absolute top-0 end-0 m-2 badge bg-primary">{{ $p['category'] }}</span>
                        </div>
                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <h5 class="fw-bold mb-1">{{ $p['name'] }}</h5>
                            <p class="text-muted small mb-2">{{ $p['category'] }}</p>
                            <ul class="small mb-3 ps-3 flex-grow-1">
                                @foreach(array_slice($p['benefits'], 0, 4) as $b)
                                <li>{{ $b }}</li>
                                @endforeach
                                @if(count($p['benefits']) > 4)
                                <li class="text-muted">+{{ count($p['benefits']) - 4 }} more...</li>
                                @endif
                            </ul>
                            <div class="d-flex align-items-center justify-content-between mt-auto">
                                <div>
                                    @foreach($p['certifications'] as $cert)
                                    <span class="badge bg-light text-dark border me-1">{{ $cert }}</span>
                                    @endforeach
                                </div>
                                <a href="{{ url('/product/' . $p['id']) }}" class="btn btn-primary btn-sm px-3">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Product Categories -->
    <div class="container-xxl pb-5">
        <div class="container pb-5">
            <div class="text-center mb-5 wow fadeIn" data-wow-delay="0.1s">
                <h1 class="mb-4">Product <span class="text-uppercase text-primary bg-light px-2">Categories</span></h1>
            </div>
            <div class="row g-5">
                <div class="col-lg-6 wow fadeIn" data-wow-delay="0.2s">
                    <div class="bg-primary p-5 h-100">
                        <div class="text-center mb-4"><i class="fa fa-tint fa-4x text-white mb-3"></i><h2 class="text-white mb-3">Mother Tinctures &amp; Dilutions</h2></div>
                        <ul class="text-white mb-4">
                            <li class="mb-2"><i class="fa fa-check me-2"></i>3X dilutions: 6C, 12C, 30C, 200C, 1M, 10M, CM</li>
                            <li class="mb-2"><i class="fa fa-check me-2"></i>LM (Q) potencies: LM1 to LM30</li>
                            <li class="mb-2"><i class="fa fa-check me-2"></i>Available in drops, globules, and tablets</li>
                        </ul>
                        <div class="text-center"><a href="{{ url('/contact') }}" class="btn btn-light px-4">Request Catalogue</a></div>
                    </div>
                </div>
                <div class="col-lg-6 wow fadeIn" data-wow-delay="0.4s">
                    <div class="bg-light p-5 h-100">
                        <div class="text-center mb-4"><i class="fa fa-pills fa-4x text-primary mb-3"></i><h2 class="text-primary mb-3">Biochemic &amp; Specialty Products</h2></div>
                        <ul class="mb-4">
                            <li class="mb-2"><i class="fa fa-check text-primary me-2"></i>All 12 biochemic tissue salts</li>
                            <li class="mb-2"><i class="fa fa-check text-primary me-2"></i>Compound biochemic formulations</li>
                            <li class="mb-2"><i class="fa fa-check text-primary me-2"></i>Homeopathic ointments &amp; creams</li>
                            <li class="mb-2"><i class="fa fa-check text-primary me-2"></i>Multi-remedy homeopathic kits</li>
                        </ul>
                        <div class="text-center"><a href="{{ url('/contact') }}" class="btn btn-primary px-4">Get Price List</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
