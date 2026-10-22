@extends('layouts.app')

@section('title', $product['name'] . ' - SL Pharma')
@section('keywords', $product['name'] . ', ' . $product['category'] . ', homeopathic medicine, SL Pharma')
@section('description', 'Buy ' . $product['name'] . ' - ' . $product['category'] . '. Homeopathic formulation by SL Pharma.')

@section('content')

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start">
                    <h1 class="display-4 mb-0 animated slideInLeft text-white">{{ $product['name'] }}</h1>
                    <p class="text-white-50 mt-2 mb-0">{{ $product['category'] }}</p>
                </div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/project') }}">Products</a></li>
                            <li class="breadcrumb-item text-white active">{{ $product['name'] }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Detail -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="row g-5 align-items-start">

                <!-- Left -->
                <div class="col-lg-5 wow fadeIn" data-wow-delay="0.1s">
                    <div class="rounded overflow-hidden shadow mb-4">
                        <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="w-100" style="max-height:420px;object-fit:cover;">
                    </div>
                    <div class="bg-light p-4 rounded">
                        <h5 class="fw-bold mb-3 text-primary">Quick Info</h5>
                        <table class="table table-borderless mb-0 small">
                            <tr><th class="ps-0 text-muted" style="width:38%">Product Name</th><td class="fw-semibold">{{ $product['name'] }}</td></tr>
                            <tr><th class="ps-0 text-muted">Category</th><td>{{ $product['category'] }}</td></tr>
                            <tr><th class="ps-0 text-muted">Dosage</th><td>{{ $product['dosage'] }}</td></tr>
                            <tr>
                                <th class="ps-0 text-muted">Certifications</th>
                                <td>@foreach($product['certifications'] as $cert)<span class="badge bg-primary me-1">{{ $cert }}</span>@endforeach</td>
                            </tr>
                        </table>
                    </div>
                    <div class="mt-4 d-flex gap-2 flex-wrap">
                        <a href="{{ url('/contact') }}" class="btn btn-primary px-4">Enquire Now</a>
                        <a href="{{ url('/project') }}" class="btn btn-outline-primary px-4">All Products</a>
                    </div>
                </div>

                <!-- Right -->
                <div class="col-lg-7 wow fadeIn" data-wow-delay="0.3s">
                    <h2 class="fw-bold mb-2">{{ $product['name'] }}</h2>
                    <span class="badge bg-primary mb-4" style="font-size:13px;">{{ $product['category'] }}</span>

                    <div class="mb-5">
                        <h4 class="fw-bold mb-3 text-primary"><i class="fa fa-check-circle me-2"></i>Key Benefits / Indications</h4>
                        <div class="row g-2">
                            @foreach($product['benefits'] as $b)
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center bg-light rounded px-3 py-2">
                                    <i class="fa fa-check text-primary me-2 flex-shrink-0"></i>
                                    <span class="small">{{ $b }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-5">
                        <h4 class="fw-bold mb-3 text-primary"><i class="fa fa-flask me-2"></i>Composition</h4>
                        <div class="row g-2">
                            @foreach($product['composition'] as $c)
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center border rounded px-3 py-2">
                                    <i class="fa fa-leaf text-primary me-2 flex-shrink-0" style="font-size:12px;"></i>
                                    <span class="small fst-italic">{{ $c }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-primary text-white rounded p-4">
                        <h5 class="fw-bold mb-2"><i class="fa fa-clock me-2"></i>Dosage</h5>
                        <p class="mb-0">{{ $product['dosage'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Other Products -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-3">
            <h4 class="fw-bold mb-4 text-center">Other Products You May Like</h4>
            <div class="row g-4">
                @foreach(array_slice(array_filter($products, fn($p) => $p['id'] !== $product['id']), 0, 3) as $i => $op)
                <div class="col-md-4 wow fadeIn" data-wow-delay="{{ round(0.1 + $i * 0.2, 1) }}s">
                    <div class="product-card h-100 d-flex flex-column shadow-sm rounded overflow-hidden border bg-white">
                        <div class="product-card-img position-relative overflow-hidden">
                            <img src="{{ $op['image'] }}" alt="{{ $op['name'] }}" class="w-100">
                            <span class="product-badge position-absolute top-0 end-0 m-2 badge bg-primary">{{ $op['category'] }}</span>
                        </div>
                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <h6 class="fw-bold mb-1">{{ $op['name'] }}</h6>
                            <p class="text-muted small mb-2">{{ $op['category'] }}</p>
                            <a href="{{ url('/product/' . $op['id']) }}" class="btn btn-outline-primary btn-sm mt-auto">View Details</a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

@endsection
