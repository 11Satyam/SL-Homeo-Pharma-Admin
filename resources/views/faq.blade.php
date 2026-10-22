@extends('layouts.app')

@section('title', 'FAQ - Frequently Asked Questions | SL Pharma')
@section('keywords', 'SL Pharma FAQ, homeopathic manufacturer questions, mother tinctures FAQ, bulk order homeopathic')
@section('description', 'Frequently asked questions about SL Pharma homeopathic products, ordering, private labeling, and manufacturing services.')

@section('content')

    <!-- Hero -->
    <div class="container-fluid pb-5 bg-primary hero-header">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6 text-center text-lg-start"><h1 class="display-1 mb-0 animated slideInLeft text-white">FAQ</h1></div>
                <div class="col-lg-6 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-white" href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item text-white active">FAQ</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Content -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-9">

                    <div class="text-center mb-5 wow fadeIn" data-wow-delay="0.1s">
                        <h2 class="mb-3">Frequently Asked <span class="text-primary">Questions</span></h2>
                        <p class="text-muted">Everything you need to know about our products, ordering, and services.</p>
                    </div>

                    @php
                    $faqs = [
                        [
                            'category' => 'About SL Pharma',
                            'items' => [
                                ['What is SL Pharma?', 'SL Pharma (S.L. Homoeo Pharma) is a GMP-certified homeopathic manufacturing company based in Sultanpur, Uttar Pradesh, India. We specialize in producing high-quality homeopathic medicines including mother tinctures, dilutions, biochemic tablets, ointments, and specialty kits for B2B clients across India.'],
                                ['Is SL Pharma GMP certified?', 'Yes. SL Pharma is fully GMP (Good Manufacturing Practice) certified and complies with the standards set by the Homeopathic Pharmacopoeia of India (HPI). All our products are manufactured under strict quality control conditions.'],
                                ['How many years of experience does SL Pharma have?', 'SL Pharma has over 25 years of experience in homeopathic manufacturing. Our deep expertise ensures consistent quality and reliability in every product we produce.'],
                            ],
                        ],
                        [
                            'category' => 'Products',
                            'items' => [
                                ['What products does SL Pharma manufacture?', 'We manufacture a comprehensive range of homeopathic medicines including: 500+ Mother Tinctures (Q), Full potency CH & LM Dilutions, All 12 Biochemic Tissue Salts, Triturations, Homeopathic Ointments & Creams, Specialty Homeopathic Kits, and Private Label products.'],
                                ['How many mother tinctures do you offer?', 'We offer 500+ mother tinctures covering plant, mineral, and nosode sources — prepared using classical homeopathic methods with authenticated raw materials.'],
                                ['Do you offer all potencies in dilutions?', 'Yes. We offer the complete range of CH potencies (from 6CH to CM) and LM potencies. Custom potency manufacturing is also available for bulk orders.'],
                                ['Are your raw materials authentic?', 'Absolutely. We source only the finest authenticated raw materials from verified suppliers. All raw materials are tested for identity, purity, and quality before use in manufacturing.'],
                            ],
                        ],
                        [
                            'category' => 'Ordering & Supply',
                            'items' => [
                                ['Is there a minimum order quantity (MOQ)?', 'Yes, MOQ applies and varies by product category. Please contact our sales team for specific MOQ details, pricing, and bulk discount structures for your requirements.'],
                                ['Do you supply pan India?', 'Yes. We supply to distributors, pharmacies, clinics, hospitals, and homeopathic institutions across all 28+ states in India. Timely and reliable delivery is a core commitment.'],
                                ['How do I place an order?', 'You can place an order by calling us at +91 94504 26807, emailing us at care@slhomeopharma.com, or by filling out the contact form on our website. Our team will respond with pricing, availability, and delivery details.'],
                                ['How can I get your product catalogue and price list?', 'Simply contact us via phone, email, or the contact form on our website and request our product catalogue. We will share the complete product list with current pricing promptly.'],
                            ],
                        ],
                        [
                            'category' => 'Private Label & Contract Manufacturing',
                            'items' => [
                                ['Do you offer private label services?', 'Yes. We offer complete private labeling services. You can market our products under your own brand name. We handle manufacturing, quality testing, packaging, and labeling — you just need to provide your brand details and artwork.'],
                                ['Do you do contract manufacturing?', 'Yes. We offer full contract manufacturing services for homeopathic brands, distributors, and healthcare companies. This includes mother tincture production, potentization, tablet pressing, packaging, and labeling under one roof.'],
                                ['What is the process to start a private label partnership?', 'Contact us with your requirements — product list, quantities, and branding details. Our team will share pricing, MOQ, lead times, and the process for label approval. We make the onboarding process simple and smooth.'],
                            ],
                        ],
                        [
                            'category' => 'Quality & Compliance',
                            'items' => [
                                ['How do you ensure product quality?', 'Every batch undergoes rigorous in-house quality testing including HPLC and TLC analysis for identity, purity, and potency. Products are only dispatched after passing all quality checks. Our in-house QC laboratory ensures complete batch traceability.'],
                                ['What should I do if I receive a damaged or incorrect product?', 'Please contact us within 7 days of receipt with photographic evidence. We will investigate and arrange a replacement or credit note as appropriate. Quality issues are treated with the highest priority.'],
                                ['Are your products safe to use?', 'Yes. All SL Pharma products are prepared according to Homeopathic Pharmacopoeia standards using GMP-certified processes. Homeopathic medicines are gentle, non-toxic, and free from side effects when used as directed. Always consult a qualified homeopathic practitioner for treatment guidance.'],
                            ],
                        ],
                        [
                            'category' => 'Contact & Support',
                            'items' => [
                                ['How can I contact SL Pharma?', 'You can reach us by phone at +91 94504 26807, by email at care@slhomeopharma.com, or visit our office at Hanumanganj Market (Meeranpur), Lambhua, Sultanpur, U.P. - 228001. Our office hours are Monday to Saturday, 9:00 AM to 6:00 PM.'],
                                ['What are your office hours?', 'Our office is open Monday to Saturday from 9:00 AM to 6:00 PM IST. You can also reach us via email outside business hours and we will respond the next working day.'],
                            ],
                        ],
                    ];
                    @endphp

                    @foreach($faqs as $catIndex => $category)
                    <div class="faq-category mb-5 wow fadeIn" data-wow-delay="{{ 0.1 + ($catIndex * 0.1) }}s">
                        <h5 class="faq-category-title mb-3">
                            <i class="fa fa-tag text-primary me-2"></i>{{ $category['category'] }}
                        </h5>
                        <div class="accordion" id="faqAccordion{{ $catIndex }}">
                            @foreach($category['items'] as $i => $faq)
                            @php $id = 'faq-' . $catIndex . '-' . $i; @endphp
                            <div class="accordion-item faq-item">
                                <h2 class="accordion-header" id="heading-{{ $id }}">
                                    <button class="accordion-button {{ $catIndex === 0 && $i === 0 ? '' : 'collapsed' }}" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapse-{{ $id }}"
                                        aria-expanded="{{ $catIndex === 0 && $i === 0 ? 'true' : 'false' }}"
                                        aria-controls="collapse-{{ $id }}">
                                        {{ $faq[0] }}
                                    </button>
                                </h2>
                                <div id="collapse-{{ $id }}" class="accordion-collapse collapse {{ $catIndex === 0 && $i === 0 ? 'show' : '' }}"
                                    aria-labelledby="heading-{{ $id }}" data-bs-parent="#faqAccordion{{ $catIndex }}">
                                    <div class="accordion-body text-muted">
                                        {{ $faq[1] }}
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach

                    <!-- CTA -->
                    <div class="bg-light p-5 rounded text-center mt-4 wow fadeIn" data-wow-delay="0.2s">
                        <h4 class="mb-3">Still have questions?</h4>
                        <p class="text-muted mb-4">Our team is happy to help. Reach out to us directly for any queries about products, pricing, or partnerships.</p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <a href="{{ url('/contact') }}" class="btn btn-primary px-4"><i class="fa fa-envelope me-2"></i>Contact Us</a>
                            <a href="tel:+919450426807" class="btn btn-outline-primary px-4"><i class="fa fa-phone me-2"></i>+91 94504 26807</a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

@endsection
