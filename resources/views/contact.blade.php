@extends('layouts.app')

@section('title', 'Contact Us - SL Pharma')
@section('keywords', 'Contact SL Pharma, homeopathic manufacturer contact, bulk order inquiry, private label inquiry')
@section('description', 'Get in touch with SL Pharma for bulk orders, contract manufacturing, private labeling, and product inquiries.')

@section('content')

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start">
                    <h1 class="display-1 mb-0 animated slideInLeft text-white">Contact Us</h1>
                </div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item text-white active">Contact</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Info -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="text-center wow fadeIn" data-wow-delay="0.1s">
                <h1 class="mb-3">Get In <span class="text-uppercase text-primary bg-light px-2">Touch</span></h1>
                <p class="mb-5">We're here to help! Contact us for bulk orders, manufacturing inquiries, private labeling, and partnerships.</p>
            </div>
            <div class="row g-4 mb-5">
                @foreach([
                    ['fa-map-marker-alt','Our Location','Hanumanganj Market (Meeranpur), Lambhua, Sultanpur, U.P. - 228001', '0.1s'],
                    ['fa-phone-alt','Call Us','<a href="tel:+919450426807" class="text-dark">+91 94504 26807</a><br><small class="text-muted">Mon–Sat, 9am–6pm</small>', '0.3s'],
                    ['fa-envelope','Email Us','<a href="mailto:care@slhomeopharma.com" class="text-dark">care@slhomeopharma.com</a><br><a href="mailto:orders@slpharma.in" class="text-dark">orders@slpharma.in</a>', '0.5s'],
                    ['fa-clock','Business Hours','Monday – Saturday<br>9:00 AM – 6:00 PM', '0.7s'],
                ] as $c)
                <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="{{ $c[3] }}">
                    <div class="bg-light h-100 p-4 text-center rounded">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary rounded-circle mb-4" style="width:80px;height:80px;">
                            <i class="fa {{ $c[0] }} fa-2x text-white"></i>
                        </div>
                        <h5 class="mb-3">{{ $c[1] }}</h5>
                        <p class="mb-0">{!! $c[2] !!}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Google Map -->
    <div class="container-fluid px-0 wow fadeIn" data-wow-delay="0.1s">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3579.123456789!2d82.0800!3d26.1500!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sHanumanganj+Market+Meeranpur+Lambhua+Sultanpur+UP!5e0!3m2!1sen!2sin!4v1700000000000!5m2!1sen!2sin"
            width="100%" height="400" style="border:0; display:block;"
            allowfullscreen="" loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="S.L Homoeo Pharma Location">
        </iframe>
    </div>

    <!-- Contact Form -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row g-5 justify-content-center">
                <div class="col-lg-6">
                    <div class="text-center mb-5 wow fadeIn" data-wow-delay="0.1s">
                        <h1 class="mb-3">Send Us <span class="text-uppercase text-primary bg-light px-2">A Message</span></h1>
                        <p>Fill out the form below and our team will get back to you within 24 hours</p>
                    </div>
                    <form id="inquiryForm">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="inq_name" placeholder="Your Full Name" required>
                                    <label for="inq_name">Full Name *</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="inq_company" placeholder="Company / Clinic Name">
                                    <label for="inq_company">Company / Clinic Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="inq_phone" placeholder="Phone Number" required>
                                    <label for="inq_phone">Phone Number *</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="inq_email" placeholder="Email Address" required>
                                    <label for="inq_email">Email Address *</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <select class="form-select" id="inq_subject" required>
                                        <option value="">Select Inquiry Type</option>
                                        <option value="bulk-order">Bulk Order Inquiry</option>
                                        <option value="contract-mfg">Contract Manufacturing</option>
                                        <option value="private-label">Private Label / White Label</option>
                                        <option value="product-catalogue">Request Product Catalogue</option>
                                        <option value="price-list">Request Price List</option>
                                        <option value="general">General Inquiry</option>
                                    </select>
                                    <label for="inq_subject">Inquiry Type *</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" placeholder="Your message here" id="inq_message" style="height:150px" required></textarea>
                                    <label for="inq_message">Your Message *</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary w-100 py-3" type="submit">Send Inquiry</button>
                            </div>
                            <div class="col-12 text-center">
                                <p class="text-muted mb-0"><small>For urgent inquiries, please call us directly at +91 94504 26807</small></p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="container-fluid bg-primary py-5">
        <div class="container py-5">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6 text-center text-lg-start wow fadeIn" data-wow-delay="0.1s">
                    <h2 class="text-white mb-4">Ready to Partner with SL Pharma?</h2>
                    <p class="text-white mb-4">Whether you need bulk homeopathic medicines, contract manufacturing, or private labeling — we have a solution for you.</p>
                    <div class="d-flex justify-content-center justify-content-lg-start gap-3 mb-4">
                        <div class="bg-white rounded p-3"><span class="fw-bold text-primary">Bulk Orders</span></div>
                        <div class="bg-white rounded p-3"><span class="fw-bold text-primary">Private Label</span></div>
                        <div class="bg-white rounded p-3"><span class="fw-bold text-primary">Contract Mfg</span></div>
                    </div>
                </div>
                <div class="col-lg-6 text-center text-lg-end wow fadeIn" data-wow-delay="0.3s">
                    <h3 class="text-white mb-4">Download Our Catalogue</h3>
                    <p class="text-white mb-4">Request our complete product catalogue and price list for your reference.</p>
                    <div class="d-flex justify-content-center justify-content-lg-end gap-3">
                        <a href="mailto:care@slhomeopharma.com" class="btn btn-light btn-lg px-4"><i class="fa fa-envelope me-2"></i>Email Us</a>
                        <a href="tel:+919450426807" class="btn btn-outline-light btn-lg px-4"><i class="fa fa-phone me-2"></i>Call Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
document.getElementById('inquiryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Thank you! We will get back to you within 24 hours.');
    this.reset();
});
</script>
@endpush
