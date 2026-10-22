@extends('layouts.app')

@section('title', 'Terms & Conditions - SL Pharma')
@section('keywords', 'SL Pharma terms and conditions, homeopathic manufacturer terms')
@section('description', 'Terms and Conditions for SL Pharma - homeopathic manufacturing company.')

@section('content')

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start"><h1 class="display-1 mb-0 animated slideInLeft text-white">Terms &amp; Conditions</h1></div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item text-white active">Terms &amp; Conditions</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <p class="text-muted mb-5"><small>Last updated: {{ date('d F Y') }}</small></p>

                    @foreach([
                        ['Acceptance of Terms', 'By accessing or using the SL Pharma website (slpharma.in) or placing any order with us, you agree to be bound by these Terms and Conditions. If you do not agree to these terms, please do not use our website or services. SL Pharma reserves the right to update these terms at any time without prior notice.'],
                        ['Products & Orders', 'All products listed on our website are subject to availability. SL Pharma manufactures homeopathic medicines for B2B clients including distributors, pharmacies, clinics, and hospitals. Minimum order quantities (MOQ) may apply. Product specifications, pricing, and availability are subject to change without notice. All orders are subject to acceptance by SL Pharma.'],
                        ['Pricing & Payment', 'All prices are in Indian Rupees (INR) and are exclusive of applicable taxes (GST) unless stated otherwise. Prices are subject to change without prior notice. Payment must be made as per the agreed terms. For online payments, we use Razorpay — a secure, PCI-DSS compliant payment gateway. SL Pharma does not store any card or banking details.'],
                        ['Shipping & Delivery', 'SL Pharma ships across India. Delivery timelines depend on order size, product availability, and destination. We are not responsible for delays caused by courier partners, natural disasters, or other circumstances beyond our control. Risk of loss passes to the buyer upon dispatch.'],
                        ['Quality & Returns', 'All products are manufactured under GMP-certified conditions and undergo quality testing before dispatch. In case of a genuine quality issue or damaged goods, please contact us within 7 days of receipt with photographic evidence. Returns are accepted only for manufacturing defects. Products damaged in transit must be reported to the courier at the time of delivery.'],
                        ['Intellectual Property', 'All content on this website including text, images, logos, and product information is the intellectual property of SL Pharma. Unauthorized reproduction, distribution, or use of any content is strictly prohibited without prior written consent.'],
                        ['Limitation of Liability', 'SL Pharma shall not be liable for any indirect, incidental, or consequential damages arising from the use of our products or website. Our liability is limited to the value of the products supplied. Homeopathic medicines are complementary health products — consult a qualified homeopathic practitioner for medical advice.'],
                        ['Governing Law', 'These Terms and Conditions are governed by the laws of India. Any disputes shall be subject to the exclusive jurisdiction of the courts in the state where SL Pharma is registered.'],
                        ['Contact Us', 'For any questions regarding these Terms and Conditions, please contact us at: <a href="mailto:care@slhomeopharma.com">care@slhomeopharma.com</a> or call <a href="tel:+919450426807">+91 94504 26807</a>.'],
                    ] as $i => $section)
                    <div class="mb-5 wow fadeIn" data-wow-delay="0.1s">
                        <h4 class="text-primary mb-3">{{ $i + 1 }}. {{ $section[0] }}</h4>
                        <p class="mb-0">{!! $section[1] !!}</p>
                    </div>
                    @endforeach

                    <div class="bg-light p-4 rounded mt-4">
                        <p class="mb-0 text-muted"><i class="fa fa-info-circle text-primary me-2"></i>These terms are subject to change. Please review them periodically. Continued use of our services constitutes acceptance of any updated terms.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
