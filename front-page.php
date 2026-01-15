<?php get_header(); ?>

<main class="home-hero">

    <!-- TOP BADGE -->
    <div class="hero-badge">
        ESTABLISHED 2009 • GLOBAL MOBILITY PROTOCOL
    </div>

    <!-- MAIN HEADING -->
    <h1 class="hero-title">
        <span class="hero-title-primary">GLOBAL EDUCATION HUB</span>
        <span class="hero-title-accent">STUDY ABROAD & TEST PREP</span>
        <span class="hero-title-accent">SPECIALIST</span>
    </h1>

    <!-- SUBTEXT -->
    <p class="hero-subtext">
        Secure your University Admission, Master your Exams with our LMS,
        and Save on Official Vouchers for IELTS, PTE, TOEFL,
        LanguageCert, Duolingo, GRE and more.
    </p>

    <!-- CTA BUTTONS -->
    <div class="hero-actions">
        <a href="#" class="btn btn-primary">
            BUY EXAM VOUCHERS
            <img src="<?php echo get_template_directory_uri(); ?>/assets/img/lock.svg" alt="">
        </a>

        <a href="#" class="btn btn-secondary">
            APPLICATION HUB
            <img src="<?php echo get_template_directory_uri(); ?>/assets/img/arrow-right.svg" alt="">
        </a>
    </div>

    <!-- SLIDER DOTS -->
    <div class="hero-dots">
        <span></span>
        <span class="active"></span>
        <span></span>
    </div>
<section class="partners">
    <div class="partners-line"></div>
    <p class="partners-title">AUTHORIZED PARTNERS</p>

    <div class="partners-marquee">
        <div class="partners-track">
            <span>PEARSON PTE</span>
            <span>BRITISH COUNCIL</span>
            <span>IDP IELTS</span>
            <span>OTHM UK</span>
            <span>OXFORD ELLT</span>
            <span>CAMBRIDGE</span>
            <span>ETS TOEFL</span>
            <span>DUOLINGO</span>
            <span>VFS GLOBAL</span>
            <span>GTEC</span>
            <span>LANGUAGECERT</span>

            <!-- duplicate for seamless loop -->
            <span>PEARSON PTE</span>
            <span>BRITISH COUNCIL</span>
            <span>IDP IELTS</span>
            <span>OTHM UK</span>
            <span>OXFORD ELLT</span>
            <span>CAMBRIDGE</span>
            <span>ETS TOEFL</span>
            <span>DUOLINGO</span>
            <span>VFS GLOBAL</span>
            <span>GTEC</span>
            <span>LANGUAGECERT</span>
        </div>
    </div>
</section>

<section class="excellence">
    <span class="excellence-tag">● THE UNICOU STANDARD ●</span>
    <h2>Operational Excellence</h2>

    <div class="excellence-grid">
        <div class="excellence-card">
            <div class="icon orange">⚡</div>
            <h3>Instant Delivery</h3>
            <p>Vouchers are retrieved from our secure vault and dispatched the second payment is verified.</p>
        </div>

        <div class="excellence-card">
            <div class="icon navy">🔒</div>
            <h3>Secure Storage</h3>
            <p>Our vault uses industry-grade encryption to manage thousands of unique test vouchers.</p>
        </div>

        <div class="excellence-card">
            <div class="icon orange">🏷️</div>
            <h3>Bulk Discounts</h3>
            <p>Our engine calculates automatic discounts for agency partners based on tier level.</p>
        </div>

        <div class="excellence-card">
            <div class="icon navy">✔</div>
            <h3>Global Validity</h3>
            <p>Every code is directly imported from providers, ensuring 100% authenticity.</p>
        </div>
    </div>
</section>
<section class="universities">
    <span class="section-tag">GLOBAL ECOSYSTEM</span>
    <h2>APPLY IN WORLD <span>TOP UNIVERSITIES</span></h2>

    <div class="university-ticker">
        COLUMBIA • AUSTRALIAN NATIONAL UNIVERSITY • UNIVERSITY OF MELBOURNE • OXFORD • CAMBRIDGE
    </div>

    <div class="testimonials">
        <div class="testimonial-card">
            <p>“UNICOU’s automated voucher dispatch system is the fastest we’ve integrated in a decade.”</p>
            <strong>Apex Training Node</strong>
            <span>Global Partner</span>
        </div>

        <div class="testimonial-card">
            <p>“Exceptional student matching and verification protocols.”</p>
            <strong>West Lakes Academy</strong>
            <span>Institution</span>
        </div>

        <div class="testimonial-card">
            <p>“The CRM leads are high-quality and verified.”</p>
            <strong>Future Horizons</strong>
            <span>Recruiter</span>
        </div>
    </div>
</section>
<section class="destinations">
    <span class="section-tag"> </span>
    <h2>STUDY ABROAD <span>DESTINATIONS</span></h2>
    <p class="subtitle">Authoritative intelligence for the world’s premier academic hubs.</p>

    <div class="destination-grid">
        <div class="destination-card">
            <img src="assets/img/destinations/uk.jpg">
            <h3>THE UNITED KINGDOM</h3>
            <div class="meta">Living: £10,539 – £13,761</div>
            <div class="meta">Tuition: £10,000 – £18,000</div>
            <a href="#">Access Node →</a>
        </div>
        <!-- repeat cards -->
    </div>
</section>

</main>

<?php get_footer(); ?>
