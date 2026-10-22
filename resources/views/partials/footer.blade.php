<div class="container-fluid footer pt-5">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.1s">
                <a href="{{ url('/') }}" class="d-inline-block mb-2">
                    <img src="{{ asset('public/img/slpharma-logo.png') }}" alt="SL Pharma" style="height:80px;width:auto;max-width:220px;object-fit:contain;display:block;filter:brightness(0) invert(1);">
                </a>
                <p class="text-white-50 mb-1" style="font-size:13px;">Healing Through Nature's Wisdom</p>
                <p class="text-white-50" style="font-size:13.5px;">Leading homeopathic manufacturer committed to quality, purity, and efficacy in every product.</p>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="footer-cert-badge"><div class="footer-cert-circle"><img src="{{ asset('public/img/gmp.png') }}" alt="GMP Certified" class="footer-cert-img"></div></div>
                    <div class="footer-cert-badge"><div class="footer-cert-circle"><img src="{{ asset('public/img/iso.png') }}" alt="ISO Certified" class="footer-cert-img"></div></div>
                </div>
                <div class="footer-social">
                    <a href="" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="https://wa.me/919450426807" target="_blank" aria-label="WhatsApp">
  <i class="fab fa-whatsapp"></i>
</a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.3s">
                <h6 class="footer-heading">Get In Touch</h6>
                <ul class="footer-contact list-unstyled">
                    <li><span class="footer-contact-icon"><i class="fa fa-map-marker-alt"></i></span><span>Hanumanganj Market (Meeranpur),<br>Lambhua, Sultanpur, U.P. - 228001</span></li>
                    <li><span class="footer-contact-icon"><i class="fa fa-phone-alt"></i></span><a href="tel:+919450426807">+91 94504 26807</a></li>
                    <!--<li><span class="footer-contact-icon"><i class="fa fa-envelope"></i></span><a href="mailto:aabhiarjun8090@gmail.com">aabhiarjun8090@gmail.com</a></li>-->
                    <li><span class="footer-contact-icon"><i class="fa fa-envelope"></i></span><a href="mailto:care@slhomeopharma.com">care@slhomeopharma.com</a></li>
                    <li><span class="footer-contact-icon"><i class="fa fa-clock"></i></span><span>Mon – Sat: 9:00 AM – 6:00 PM</span></li>
                </ul>
            </div>
            <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.5s">
                <h6 class="footer-heading">Quick Links</h6>
                <ul class="footer-links list-unstyled">
                    <li><a href="{{ url('/about') }}"><i class="fa fa-chevron-right"></i> About Us</a></li>
                    <li><a href="{{ url('/service') }}"><i class="fa fa-chevron-right"></i> Manufacturing</a></li>
                    <li><a href="{{ url('/project') }}"><i class="fa fa-chevron-right"></i> Our Products</a></li>
                    <li><a href="{{ url('/contact') }}"><i class="fa fa-chevron-right"></i> Contact Us</a></li>
                    <li><a href="{{ url('/contact') }}"><i class="fa fa-chevron-right"></i> Get a Quote</a></li>
                    <li><a href="{{ url('/faq') }}"><i class="fa fa-chevron-right"></i> FAQ</a></li>
                </ul>
            </div>
            <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.7s">
                <h6 class="footer-heading">Our Products</h6>
                <ul class="footer-links list-unstyled">
                    <li><a href="{{ url('/project') }}"><i class="fa fa-chevron-right"></i> Mother Tinctures</a></li>
                    <li><a href="{{ url('/project') }}"><i class="fa fa-chevron-right"></i> Homeopathic Dilutions</a></li>
                    <li><a href="{{ url('/project') }}"><i class="fa fa-chevron-right"></i> Biochemic Tablets</a></li>
                    <li><a href="{{ url('/project') }}"><i class="fa fa-chevron-right"></i> Homeopathic Kits</a></li>
                    <li><a href="{{ url('/project') }}"><i class="fa fa-chevron-right"></i> Ointments &amp; Creams</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center py-3">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    &copy; <a href="{{ url('/') }}">SL Pharma</a> {{ date('Y') }}. All Rights Reserved.
                    Designed by <a href="https://debuglifewithsonam.lovestoblog.com/sonam">Sonam Jaiswal</a>
                </div>
                <div class="col-md-6 text-center text-md-end footer-bottom-links">
                    <a href="{{ url('/') }}">Home</a>
                    <a href="{{ url('/contact') }}">Contact</a>
                    <a href="{{ url('/terms') }}">Terms &amp; Conditions</a>
                    <a href="{{ url('/privacy') }}">Privacy Policy</a>
                </div>
            </div>
        </div>
    </div>
</div>

<a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>

<!-- Floating WhatsApp Button -->
<a href="https://wa.me/919450426807" target="_blank" rel="noopener noreferrer" class="whatsapp-float" aria-label="Chat on WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('public/lib/wow/wow.min.js') }}"></script>
<script src="{{ asset('public/lib/easing/easing.min.js') }}"></script>
<script src="{{ asset('public/lib/waypoints/waypoints.min.js') }}"></script>
<script src="{{ asset('public/lib/owlcarousel/owl.carousel.min.js') }}"></script>
<script src="{{ asset('public/js/main.js') }}"></script>
<script src="{{ asset('public/js/animation.js') }}"></script>
</body>
</html>
