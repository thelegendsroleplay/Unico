<?php
/**
 * Template Name: Study Abroad CMS
 */

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Abroad - <?php bloginfo('name'); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; color: #4a4a4a; }
        .hero-section {
            background: linear-gradient(rgba(16, 62, 84, 0.8), rgba(16, 62, 84, 0.8)), url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 20px;
            text-align: center;
        }
        .hero-title { font-size: 48px; font-weight: 700; margin-bottom: 20px; }
        .hero-subtitle { font-size: 20px; opacity: 0.9; max-width: 600px; margin: 0 auto 40px; }
        .btn-cta {
            padding: 15px 40px;
            background: #e95134;
            color: white;
            text-decoration: none;
            font-weight: 700;
            border-radius: 50px;
            font-size: 18px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-block;
        }
        .btn-cta:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(233, 81, 52, 0.3); }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; }
        .section-title { text-align: center; margin-bottom: 50px; }
        .section-title h2 { font-size: 36px; color: #194f68; margin-bottom: 15px; }
        .section-title p { color: #6c757d; font-size: 18px; }
        
        .countries-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; }
        .country-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: transform 0.2s; }
        .country-card:hover { transform: translateY(-5px); }
        .country-img { height: 180px; background-color: #eee; position: relative; }
        .country-img img { width: 100%; height: 100%; object-fit: cover; }
        .country-content { padding: 25px; }
        .country-name { font-size: 20px; font-weight: 700; margin-bottom: 10px; color: #194f68; }
        .country-desc { font-size: 14px; color: #6c757d; line-height: 1.6; margin-bottom: 20px; }
        .country-link { color: #e95134; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; margin-top: 40px; }
        .feature-item { text-align: center; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .feature-icon { font-size: 48px; margin-bottom: 20px; color: #103e54; }
        .feature-title { font-size: 20px; font-weight: 700; margin-bottom: 15px; }
        
        @media (max-width: 768px) {
            .hero-title { font-size: 32px; }
            .features-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="hero-section">
    <h1 class="hero-title">Your Global Future Starts Here</h1>
    <p class="hero-subtitle">Expert guidance, university admissions, and visa support for your study abroad journey.</p>
    <a href="<?php echo home_url('/student-application-form'); ?>" class="btn-cta">Book Free Counselling</a>
</div>

<div class="container">
    <div class="section-title">
        <h2>Explore Top Destinations</h2>
        <p>Discover opportunities in the world's leading educational hubs</p>
    </div>
    
    <div class="countries-grid">
        <div class="country-card">
            <div class="country-img" style="background-image: url('https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'); background-size: cover;"></div>
            <div class="country-content">
                <h3 class="country-name">Study in UK</h3>
                <p class="country-desc">Home to world-class universities like Oxford and Cambridge. 2-year post-study work visa available.</p>
                <a href="#" class="country-link">View Universities →</a>
            </div>
        </div>
        <div class="country-card">
            <div class="country-img" style="background-image: url('https://images.unsplash.com/photo-1550565118-3a1498d30d55?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'); background-size: cover;"></div>
            <div class="country-content">
                <h3 class="country-name">Study in USA</h3>
                <p class="country-desc">The #1 destination for international students. STEM programs offer up to 3 years of OPT.</p>
                <a href="#" class="country-link">View Universities →</a>
            </div>
        </div>
        <div class="country-card">
            <div class="country-img" style="background-image: url('https://images.unsplash.com/photo-1490806843957-31f4c9a91c65?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'); background-size: cover;"></div>
            <div class="country-content">
                <h3 class="country-name">Study in Canada</h3>
                <p class="country-desc">Affordable education and a clear pathway to permanent residency. High quality of life.</p>
                <a href="#" class="country-link">View Universities →</a>
            </div>
        </div>
        <div class="country-card">
            <div class="country-img" style="background-image: url('https://images.unsplash.com/photo-1525026198548-4baa812f1183?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'); background-size: cover;"></div>
            <div class="country-content">
                <h3 class="country-name">Study in Australia</h3>
                <p class="country-desc">Excellent education system, beautiful weather, and strong post-study work opportunities.</p>
                <a href="#" class="country-link">View Universities →</a>
            </div>
        </div>
    </div>
</div>

<div style="background: white; padding: 80px 0;">
    <div class="container">
        <div class="section-title">
            <h2>Why Choose Unico?</h2>
            <p>We support you at every step of your application</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">🎓</div>
                <h3 class="feature-title">University Shortlisting</h3>
                <p style="color: #666;">AI-driven recommendations based on your profile and preferences.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📝</div>
                <h3 class="feature-title">Application Support</h3>
                <p style="color: #666;">Expert help with SOPs, LORs, and application forms.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🛂</div>
                <h3 class="feature-title">Visa Assistance</h3>
                <p style="color: #666;">Complete guidance on financial documents and visa interviews.</p>
            </div>
        </div>
    </div>
</div>

<div class="container" style="text-align: center; padding-bottom: 80px;">
    <h2 style="font-size: 32px; color: #103e54; margin-bottom: 20px;">Ready to start your journey?</h2>
    <p style="color: #6c757d; margin-bottom: 30px;">Book a free counselling session with our experts today.</p>
    <a href="<?php echo home_url('/student-application-form'); ?>" class="btn-cta">Apply Now</a>
</div>

<?php get_footer(); ?>
