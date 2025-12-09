<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Barberu Management System</title>
  <link rel="stylesheet" href="joinus.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    html {
      scroll-behavior: smooth;
    }
  </style>
</head>
<body>
  <div class="bg-shape shape1"></div>
  <div class="bg-shape shape2"></div>
  <div class="bg-shape shape6"></div>
  <div class="bg-shape shape7"></div>
  <div class="bg-shape shape8"></div>
  <div class="bg-shape shape9"></div>
  <div class="bg-shape shape10"></div>
  <div class="bg-shape shape11"></div>
  <div class="bg-shape shape12"></div>

  <div class="container">
  <header style="width: 100vw; position: fixed; top: 0; left: 0; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,0.06); z-index: 1000;">
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 2px 16px; min-height: 40px;">
      <div style="flex: 0 0 auto; margin-left: 0;">
        <h2 style="font-weight: 800; margin: 0; letter-spacing: 1px; font-size: 1.05rem; white-space: nowrap;">BARBERU: BARBERSHOP MANAGEMENT SYSTEM</h2>
      </div>
      <nav style="display: flex; align-items: center; gap: 20px; padding-right: 20px;">
          <a href="index.php" class="nav-link">Home</a>
          <a href="#features" class="nav-link">Features</a>
          <a href="#haircuts" class="nav-link">Haircuts</a>
          <a href="#informatics" class="nav-link">Information</a>
          <a href="#developers" class="nav-link">Developers</a>
          <a href="roles.php" class="nav-link">Login</a>
          <a href="apply.php" class="apply-btn">Apply Now!</a>
      </nav>
    </div>
  </header>
    <style>
      .nav-link {
        font-weight: 700;
        color: #555;
        text-decoration: none;
        transition: color 0.2s;
        font-size: 1rem;
        padding: 8px 0;
        line-height: 1.2;
      }
      .nav-link:hover {
        color: #007bff;
        text-decoration: none;
      }
      .nav-link.active {
        color: #007bff;
      }
      .apply-btn {
        font-weight: 700;
        color: #fff;
        background: #00aaff;
        padding: 8px 18px;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,170,255,0.15);
        text-decoration: none;
        margin-left: 10px;
        font-size: 1rem;
        height: 32px;
        display: flex;
        align-items: center;
      }
    </style>

  <br><br><br><br><br><br><br>
  
  <main style="margin-top: 44px;" id="home">
      <div class="hero-content">
        <p class="tagline">All-in One Solution</p>
        <h1>Manage your barbershop<br>with ease.</h1>
        <p class="description">
          Streamline appointments, track inventory, <br>
          and boost customer satisfaction - all with BARBERU system.
        </p>
        <div class="action-buttons">
          <a href="apply.php" class="btn btn-primary">Apply Now!</a>
          <a href="#informatics" class="btn btn-secondary">Learn More</a>
        </div>
      </div>
      <div class="hero-image">
        <img src="Resources/gemini.png" alt="Collage showing online learning and happy people">
      </div>
    </main>

    <br><br><br><br><br><br><br><br><br><br><br>

    <!-- FEATURES SECTION -->
    <section id="features" class="features-section">
      <h2 class="section-title">Features</h2>
      <div class="slider-container">
        <button class="slider-btn prev-btn features-prev-btn">‹</button>
        <div class="slider-wrapper features-wrapper">
          <div class="feature-item active">
            <div class="feature-image">
              <img src="Resources/feat2_1.png" alt="Feat1">
            </div>
            <div class="feature-info">
              <h3>Appointment Scheduling</h3>
              <p>Our intuitive online booking system lets clients easily view your availability, select a service, and book an appointment in just a few clicks.</p>
            </div>
          </div>

          <div class="feature-item">
            <div class="feature-image">
              <img src="Resources/feat1_2.png" alt="Feat2">
            </div>
            <div class="feature-info">
              <h3>Inventory Management</h3>
              <p>Keep a real-time track of your products and supplies.</p>
            </div>
          </div>

          <div class="feature-item">
            <div class="feature-image">
              <img src="Resources/feat3.jpg" alt="Feat3">
            </div>
            <div class="feature-info">
              <h3>Mobile Application Features</h3>
              <p>Our app features AR Filter Camera, 2D Interactive Map, and a Queueing System.</p>
            </div>
          </div>

          <div class="feature-item">
            <div class="feature-image">
              <img src="Resources/featureapplication.png" alt="Feat4">
            </div>
            <div class="feature-info">
              <h3>User-friendly Application Form</h3>
              <p>Our streamlined application form makes it easy for new barbers to join your team.</p>
            </div>
          </div>
        </div>
        <button class="slider-btn next-btn features-next-btn">›</button>
      </div>
    </section>

    <!-- HAIRCUTS SECTION -->
    <section id="haircuts" class="haircuts-section">
      <h2 class="section-title">Sample Haircuts</h2>
      <div class="slider-container">
        <button class="slider-btn prev-btn haircuts-prev-btn">‹</button>
        <div class="slider-wrapper haircuts-wrapper">
          <div class="haircut-item"><img src="Resources/crop.png" alt="Buzz Cut"></div>
          <div class="haircut-item"><img src="Resources/buzz.png" alt="Burst Fade"></div>
          <div class="haircut-item"><img src="Resources/faded.png" alt="Wolf Cut"></div>
          <div class="haircut-item"><img src="Resources/burst.png" alt="Faded"></div>
          <div class="haircut-item"><img src="Resources/oppa.png" alt="Bald Cut"></div>
        </div>
        <button class="slider-btn next-btn haircuts-next-btn">›</button>
      </div>
    </section>

    <!-- INFORMATION SECTION -->
    <section id="informatics" class="informatics-section">
      <h2 class="section-title">Information</h2>
      <div class="informatics-content">
        <p>The <strong>Barberu Management System</strong> is designed to modernize the way barbershops handle their daily operations.</p>
        <br>
        <p>With its <strong>web platform</strong>, barbers can easily organize appointments, reduce no-shows, and keep track of product stocks in real time.</p>
        <br>
        <p>Beyond customer convenience, the system also simplifies <strong>recruitment</strong>...</p>
      </div>
    </section>

    <!-- DEVELOPERS SECTION -->
    <section id="developers" class="developers-section">
      <h2 class="section-title">Our Developers</h2>
      <div class="developers-grid">
        <div class="developer-card">
          <div class="developer-image">
            <img src="Resources/dev1.jpg" alt="Developer 1">
          </div>
          <div class="developer-info">
            <h3>Kharl Angelo I. Santos</h3>
            <p>Age: 21 Years Old</p>
            <p>Role: Web Developer / Web Designer</p>
            <p>Email: santoskharl1404@gmail.com</p>
            <p>Number: 09194113702</p>
          </div>
        </div>

        <div class="developer-card">
          <div class="developer-image">
            <img src="Resources/dev2.jpg" alt="Developer 2">
          </div>
          <div class="developer-info">
            <h3>Lester S. Tinao</h3>
            <p>Age: 21 Years Old</p>
            <p>Role: Leader / Web Developer</p>
            <p>Email: lstrtinao2nd@gmail.com</p>
            <p>Number: 09684238682</p>
          </div>
        </div>

        <div class="developer-card">
          <div class="developer-image">
            <img src="Resources/dev3.jpg" alt="Developer 3">
          </div>
          <div class="developer-info">
            <h3>Paul James M. Castillo</h3>
            <p>Age: 22 Years Old</p>
            <p>Role: Mobile Application Developer</p>
            <p>Email: pauljcastillo02@gmail.com</p>
            <p>Number: 09694239564</p>
          </div>
        </div>

        <div class="developer-card">
          <div class="developer-image">
            <img src="Resources/dev4.png" alt="Developer 4">
          </div>
          <div class="developer-info">
            <h3>Francis Julian M. De Regla</h3>
            <p>Age: 21 Years Old</p>
            <p>Role: Mobile Application Developer</p>
            <p>Email: shishiroaki123@gmail.com</p>
            <p>Number: 09276063179</p>
          </div>
        </div>
      </div>
    </section>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {

    const featuresWrapper = document.querySelector('.features-wrapper');
    const featuresItems = document.querySelectorAll('.features-section .feature-item');
    const featuresNextBtn = document.querySelector('.features-next-btn');
    const featuresPrevBtn = document.querySelector('.features-prev-btn');
    let featuresCurrentIndex = 0;
    let featuresSlideInterval;

    function updateFeaturesSlider() {
      const offset = -featuresCurrentIndex * 100;
      featuresWrapper.style.transform = `translateX(${offset}%)`;
    }

    function nextFeaturesSlide() {
      featuresCurrentIndex = (featuresCurrentIndex + 1) % featuresItems.length;
      updateFeaturesSlider();
    }

    function startFeaturesAutoSlide() {
      featuresSlideInterval = setInterval(nextFeaturesSlide, 5000);
    }

    function stopFeaturesAutoSlide() {
      clearInterval(featuresSlideInterval);
    }

    featuresNextBtn.addEventListener('click', () => {
      stopFeaturesAutoSlide();
      nextFeaturesSlide();
      startFeaturesAutoSlide();
    });

    featuresPrevBtn.addEventListener('click', () => {
      stopFeaturesAutoSlide();
      featuresCurrentIndex = (featuresCurrentIndex - 1 + featuresItems.length) % featuresItems.length;
      updateFeaturesSlider();
      startFeaturesAutoSlide();
    });

    const haircutsWrapper = document.querySelector('.haircuts-wrapper');
    const haircutsItems = document.querySelectorAll('.haircuts-section .haircut-item');
    const haircutsNextBtn = document.querySelector('.haircuts-next-btn');
    const haircutsPrevBtn = document.querySelector('.haircuts-prev-btn');
    let haircutsCurrentIndex = 0;

    function updateHaircutsSlider() {
      const offset = -haircutsCurrentIndex * 100;
      haircutsWrapper.style.transform = `translateX(${offset}%)`;
    }

    haircutsNextBtn.addEventListener('click', () => {
      haircutsCurrentIndex = (haircutsCurrentIndex + 1) % haircutsItems.length;
      updateHaircutsSlider();
    });

    haircutsPrevBtn.addEventListener('click', () => {
      haircutsCurrentIndex = (haircutsCurrentIndex - 1 + haircutsItems.length) % haircutsItems.length;
      updateHaircutsSlider();
    });

    const navLinks = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('section, main');
    const headerOffset = 80;

    navLinks.forEach(link => {
      const href = link.getAttribute('href');

      if (href === "index.php") {
        link.addEventListener('click', e => {
          e.preventDefault();
          window.scrollTo({
            top: 0,
            behavior: "smooth"
          });
        });
      }

      if (href.startsWith('#')) {
        link.addEventListener('click', e => {
          e.preventDefault();
          const targetId = href.substring(1);
          const targetEl = document.getElementById(targetId);
          if (targetEl) {
            const elementPosition = targetEl.getBoundingClientRect().top + window.scrollY;
            const offsetPosition = elementPosition - headerOffset;
            window.scrollTo({
              top: offsetPosition,
              behavior: "smooth"
            });
          }
        });
      }
    });


    function updateActiveNavLink() {
      let currentSection = '';
      sections.forEach(section => {
        const sectionTop = section.offsetTop - headerOffset - 10;
        if (window.scrollY >= sectionTop) {
          currentSection = section.getAttribute('id');
        }
      });
      navLinks.forEach(link => {
        link.classList.remove('active');
        if (currentSection && link.getAttribute('href').includes(currentSection)) {
          link.classList.add('active');
        }
        if (!currentSection && link.getAttribute('href') === 'index.php') {
          link.classList.add('active');
        }
      });
    }

    window.addEventListener('scroll', updateActiveNavLink);

    updateFeaturesSlider();
    startFeaturesAutoSlide();
    updateHaircutsSlider();
    updateActiveNavLink();
  });
</script>

<footer class="footer">
      <div class="footer-container">
        <div class="footer-about">
          <h3>BARBERU</h3>
          <p>Barbershop Management System — simplifying scheduling, inventory, and client management for modern barbershops.</p>
        </div>

        <div class="footer-links">
          <h4>Quick Links</h4>
          <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="#features">Features</a></li>
            <li><a href="#haircuts">Haircuts</a></li>
            <li><a href="#informatics">Information</a></li>
            <li><a href="#developers">Developers</a></li>
            <li><a href="login.php">Login</a></li>
            <li><a href="apply.php">Apply</a></li>
          </ul>
        </div>

        <div class="footer-social">
  <h4>Connect With Us</h4>
  <div class="social-icons">

    <a href="https://github.com/harudayodev/barberuapp" target="_blank" aria-label="GitHub">
      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 .297c-6.63 
        0-12 5.373-12 12 0 5.303 
        3.438 9.8 8.205 11.387.6.113.82-.258.82-.577 
        0-.285-.01-1.04-.015-2.04-3.338.726-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.084-.729.084-.729 
        1.205.084 1.84 1.236 1.84 1.236 
        1.07 1.835 2.809 1.305 3.495.998.108-.775.418-1.305.762-1.605-2.665-.305-5.466-1.334-5.466-5.93 
        0-1.31.468-2.381 1.236-3.221-.124-.303-.536-1.523.117-3.176 
        0 0 1.008-.322 3.301 1.23a11.52 
        11.52 0 013.003-.404c1.018.005 2.043.138 
        3.003.404 2.292-1.552 3.299-1.23 
        3.299-1.23.655 1.653.243 2.873.119 
        3.176.77.84 1.235 1.911 1.235 
        3.221 0 4.609-2.803 5.624-5.475 
        5.921.43.372.823 1.102.823 
        2.222 0 1.606-.015 2.896-.015 
        3.286 0 .317.218.687.825.57C20.565 
        22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
      </svg>
    </a>
  </div>
</div>

      <div class="footer-bottom">
        <p>© 2025 Barberu Management System. All rights reserved.</p>
      </div>
    </footer>
</body>
</html>
