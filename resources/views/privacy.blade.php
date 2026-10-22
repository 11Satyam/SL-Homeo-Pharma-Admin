@extends('layouts.app')

@section('title', 'Privacy Policy - SL Pharma')
@section('keywords', 'SL Pharma privacy policy, data protection, homeopathic manufacturer privacy')
@section('description', 'Privacy Policy for SL Pharma. Learn how we collect, use, and protect your personal information.')

@section('content')

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start"><h1 class="display-1 mb-0 animated slideInLeft text-white">Privacy Policy</h1></div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item text-white active">Privacy Policy</li>
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

                    <div class="mb-5 wow fadeIn" data-wow-delay="0.1s">
                        <p>SL Pharma ("we", "our", "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website or interact with our services.</p>
                    </div>

                    @foreach([
                        ['Information We Collect', '<strong>Personal Information:</strong> When you contact us, place an order, or request a catalogue, we may collect your name, company name, email address, phone number, and postal address.<br><br><strong>Payment Information:</strong> Payments are processed through Razorpay. We do not store your card details, bank account numbers, or UPI IDs.<br><br><strong>Usage Data:</strong> We may collect information about how you access and use our website, including your IP address, browser type, pages visited, and time spent on pages.'],
                        ['How We Use Your Information', 'We use the information we collect to:<ul class="mt-2"><li>Process and fulfill your orders</li><li>Respond to your inquiries and provide customer support</li><li>Send product catalogues, price lists, and business communications</li><li>Improve our website and services</li><li>Comply with legal obligations</li><li>Prevent fraud and ensure security</li></ul>'],
                        ['Information Sharing', 'We do not sell, trade, or rent your personal information to third parties. We may share your information with:<ul class="mt-2"><li><strong>Service Providers:</strong> Courier partners, payment processors (Razorpay), and IT service providers</li><li><strong>Legal Requirements:</strong> When required by law, court order, or government authority</li><li><strong>Business Transfers:</strong> In the event of a merger, acquisition, or sale of assets</li></ul>'],
                        ['Cookies', 'Our website may use cookies to enhance your browsing experience. Cookies are small files stored on your device. You can instruct your browser to refuse all cookies. However, some parts of our website may not function properly without cookies.'],
                        ['Data Security', 'We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.'],
                        ['Data Retention', 'We retain your personal information for as long as necessary to fulfill the purposes outlined in this policy and comply with legal obligations. Order records are typically retained for 7 years as required by Indian tax laws.'],
                        ['Your Rights', 'You have the right to:<ul class="mt-2"><li>Access the personal information we hold about you</li><li>Request correction of inaccurate information</li><li>Request deletion of your personal information</li><li>Opt out of marketing communications at any time</li></ul>To exercise any of these rights, contact us at <a href="mailto:care@slhomeopharma.com">care@slhomeopharma.com</a>.'],
                        ['Third-Party Links', 'Our website may contain links to third-party websites. We are not responsible for the privacy practices or content of those websites.'],
                        ["Children's Privacy", 'Our website and services are not directed to individuals under the age of 18. We do not knowingly collect personal information from minors.'],
                        ['Changes to This Policy', 'We may update this Privacy Policy from time to time. Your continued use of our website after any changes constitutes your acceptance of the updated policy.'],
                        ['Contact Us', 'If you have any questions regarding this Privacy Policy:<br><br><strong>SL Pharma</strong><br>Hanumanganj Market (Meeranpur), Lambhua, Sultanpur, U.P. - 228001<br>Email: <a href="mailto:care@slhomeopharma.com">care@slhomeopharma.com</a><br>Phone: <a href="tel:+919450426807">+91 94504 26807</a>'],
                    ] as $i => $section)
                    <div class="mb-5 wow fadeIn" data-wow-delay="0.1s">
                        <h4 class="text-primary mb-3">{{ $i + 1 }}. {{ $section[0] }}</h4>
                        <p class="mb-0">{!! $section[1] !!}</p>
                    </div>
                    @endforeach

                    <div class="bg-light p-4 rounded mt-4">
                        <p class="mb-0 text-muted"><i class="fa fa-shield-alt text-primary me-2"></i>Your privacy matters to us. We are committed to handling your personal information with care and transparency.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
