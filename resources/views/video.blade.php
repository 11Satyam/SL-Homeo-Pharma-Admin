@extends('layouts.app')

@section('title', 'Videos - SL Pharma | Homeopathic Manufacturing Company')
@section('keywords', 'SL Pharma videos, homeopathic manufacturing, product videos')
@section('description', 'Watch videos of SL Pharma homeopathic manufacturing process and products.')

@section('content')

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start"><h1 class="display-1 mb-0 animated slideInLeft text-white">Videos</h1></div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item text-white active">Videos</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Section -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="text-center wow fadeIn" data-wow-delay="0.1s">
                <h1 class="mb-5">Our <span class="text-uppercase text-primary bg-light px-2">Videos</span></h1>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-lg-8 wow fadeIn" data-wow-delay="0.3s">
                    <div class="ratio ratio-16x9 shadow rounded">
                        <video controls class="rounded">
                            <source src="{{ asset('public/video/ai_video.mp4') }}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
