/**
 * SL Pharma - Animations & Interactive Enhancements
 */
(function ($) {
    "use strict";

    /* =========================================================
       1. COUNTER ANIMATION (Stats Section)
    ========================================================= */
    function animateCounter($el) {
        var target   = parseInt($el.data('target'), 10);
        var duration = parseInt($el.data('duration') || 2000, 10);
        var suffix   = $el.data('suffix') || '';
        var start    = 0;
        var increment = target / (duration / 16);

        var timer = setInterval(function () {
            start += increment;
            if (start >= target) {
                start = target;
                clearInterval(timer);
            }
            $el.text(Math.floor(start) + suffix);
        }, 16);
    }

    /* Trigger counters when stats section enters viewport */
    var countersAnimated = false;

    function checkCounters() {
        if (countersAnimated) return;
        var $stats = $('.stats-section');
        if ($stats.length === 0) return;

        var sectionTop = $stats.offset().top;
        var scrollBottom = $(window).scrollTop() + $(window).height();

        if (scrollBottom > sectionTop + 80) {
            countersAnimated = true;
            $('.counter-number').each(function () {
                animateCounter($(this));
            });
        }
    }

    /* =========================================================
       2. SCROLL FADE-UP ANIMATION
    ========================================================= */
    function checkFadeUp() {
        $('.fade-up').each(function () {
            var elTop = $(this).offset().top;
            var scrollBottom = $(window).scrollTop() + $(window).height();
            if (scrollBottom > elTop + 60) {
                $(this).addClass('is-visible');
            }
        });
    }

    /* =========================================================
       3. ANIMATED UNDERLINE FOR SECTION HEADINGS
    ========================================================= */
    function checkUnderlines() {
        $('.animated-underline').each(function () {
            var elTop = $(this).offset().top;
            var scrollBottom = $(window).scrollTop() + $(window).height();
            if (scrollBottom > elTop + 30) {
                $(this).addClass('is-visible');
            }
        });
    }

    /* =========================================================
       4. HERO SECTION STAGGER ANIMATION
    ========================================================= */
    function initHeroStagger() {
        var $hero = $('.hero-header');
        if ($hero.length === 0) return;

        /* Stagger-in children with delay */
        $hero.find('[data-stagger]').each(function (i) {
            var $el = $(this);
            var delay = (i * 150) + 'ms';
            $el.css({ opacity: 0, transform: 'translateY(25px)', transition: 'opacity 0.7s ease ' + delay + ', transform 0.7s ease ' + delay });
            setTimeout(function () {
                $el.css({ opacity: 1, transform: 'translateY(0)' });
            }, 300);
        });
    }

    /* =========================================================
       5. FEATURE CARD ENTRANCE (stagger on scroll)
    ========================================================= */
    function checkFeatureCards() {
        $('.feature-card').each(function (i) {
            var elTop  = $(this).offset().top;
            var scroll = $(window).scrollTop() + $(window).height();
            if (scroll > elTop + 40 && !$(this).hasClass('entered')) {
                $(this).addClass('entered');
                var delay = (i % 3) * 120;
                $(this).css({ opacity: 0, transform: 'translateY(30px)', transition: 'opacity 0.55s ease ' + delay + 'ms, transform 0.55s ease ' + delay + 'ms, box-shadow 0.4s, border-color 0.4s' });
                setTimeout(function ($card) {
                    $card.css({ opacity: 1, transform: 'translateY(0)' });
                }, 50, $(this));
            }
        });
    }

    /* =========================================================
       6. PARALLAX TINT ON HERO (subtle depth)
    ========================================================= */
    function heroParallax() {
        var scrolled = $(window).scrollTop();
        $('.hero-header .col-lg-6:first-child').css('transform', 'translateY(' + (scrolled * 0.08) + 'px)');
    }

    /* =========================================================
       7. PRODUCT / PROJECT ITEM SHINE EFFECT
    ========================================================= */
    function initShineEffect() {
        $('.project-item').on('mousemove', function (e) {
            var $this  = $(this);
            var offset = $this.offset();
            var x = ((e.pageX - offset.left) / $this.width())  * 100;
            var y = ((e.pageY - offset.top)  / $this.height()) * 100;
            $this.find('img').css('transform-origin', x + '% ' + y + '%');
        });
    }

    /* =========================================================
       8. SMOOTH SECTION REVEAL (service / about images)
    ========================================================= */
    function initImageReveal() {
        $('img.img-fluid').each(function () {
            $(this).css({ transition: 'opacity 0.6s ease, transform 0.6s ease' });
        });
    }

    /* =========================================================
       9. BACK-TO-TOP SMOOTH PROGRESS RING
    ========================================================= */
    function updateBackToTop() {
        var scrollTop  = $(window).scrollTop();
        var docHeight  = $(document).height() - $(window).height();
        var progress   = (scrollTop / docHeight) * 100;
        $('.back-to-top').css('background', 'conic-gradient(var(--primary) ' + progress + '%, transparent ' + progress + '%)');
    }

    /* =========================================================
       INIT ON DOCUMENT READY
    ========================================================= */
    $(document).ready(function () {
        initHeroStagger();
        initShineEffect();
        initImageReveal();

        /* Initial check */
        checkCounters();
        checkFadeUp();
        checkUnderlines();
        checkFeatureCards();
    });

    /* =========================================================
       SCROLL HANDLER
    ========================================================= */
    $(window).on('scroll', function () {
        checkCounters();
        checkFadeUp();
        checkUnderlines();
        checkFeatureCards();
        updateBackToTop();

        /* Only parallax on larger screens for performance */
        if ($(window).width() > 992) {
            heroParallax();
        }
    });

})(jQuery);
