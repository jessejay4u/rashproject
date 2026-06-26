/* ===================================
   Community Liberation - Main JS
   =================================== */

document.addEventListener('DOMContentLoaded', function () {

  // ===== PRELOADER =====
  const preloader = document.querySelector('.preloader');
  if (preloader) {
    window.addEventListener('load', function () {
      preloader.classList.add('hidden');
      setTimeout(() => preloader.remove(), 600);
    });
    setTimeout(() => {
      preloader.classList.add('hidden');
      setTimeout(() => preloader.remove(), 600);
    }, 3000);
  }

  // ===== STICKY HEADER =====
  const header = document.querySelector('.header');
  if (header) {
    window.addEventListener('scroll', function () {
      if (window.scrollY > 80) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    });
  }

  // ===== ACTIVE NAV LINK =====
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPage || (currentPage === '' && href === 'index.html')) {
      link.classList.add('active');
    }
  });

  // ===== BACK TO TOP =====
  const backToTop = document.querySelector('.back-to-top');
  if (backToTop) {
    window.addEventListener('scroll', function () {
      if (window.scrollY > 300) {
        backToTop.classList.add('visible');
      } else {
        backToTop.classList.remove('visible');
      }
    });
    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // ===== COUNTER ANIMATION =====
  function animateCounter(el) {
    const target = parseInt(el.getAttribute('data-target'));
    const duration = 2000;
    const step = target / (duration / 16);
    let current = 0;
    const timer = setInterval(() => {
      current += step;
      if (current >= target) {
        current = target;
        clearInterval(timer);
      }
      el.textContent = Math.floor(current).toLocaleString();
    }, 16);
  }

  const counterSection = document.querySelector('.counter-section');
  if (counterSection) {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          document.querySelectorAll('.count-up').forEach(animateCounter);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });
    observer.observe(counterSection);
  }

  // ===== SCROLL ANIMATIONS =====
  const animElements = document.querySelectorAll('[data-animate]');
  if (animElements.length > 0) {
    const animObserver = new IntersectionObserver(function (entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const delay = entry.target.getAttribute('data-delay') || 0;
          setTimeout(() => {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'none';
          }, delay);
          animObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    animElements.forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(30px)';
      el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
      animObserver.observe(el);
    });
  }

  // ===== DONATION AMOUNT BUTTONS =====
  document.querySelectorAll('.amount-btn, .donate-amount-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const group = this.closest('.amount-btns, .donate-amount-grid');
      if (group) {
        group.querySelectorAll('.amount-btn, .donate-amount-btn').forEach(b => b.classList.remove('active'));
      }
      this.classList.add('active');
      const customInput = document.querySelector('#customAmount, #donateAmount');
      if (customInput) {
        customInput.value = this.textContent.trim().replace(/[£$]/g, '');
      }
    });
  });

  // ===== NEWSLETTER FORM =====
  const newsletterForms = document.querySelectorAll('.newsletter-form');
  newsletterForms.forEach(form => {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const input = this.querySelector('input[type="email"]');
      if (input && input.value) {
        const btn = this.querySelector('button');
        btn.textContent = 'Subscribed!';
        btn.style.backgroundColor = '#28a745';
        input.value = '';
        setTimeout(() => {
          btn.textContent = 'Subscribe';
          btn.style.backgroundColor = '';
        }, 3000);
      }
    });
  });

  // ===== CONTACT FORM =====
  const contactForm = document.querySelector('#contactForm');
  if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const btn = this.querySelector('button[type="submit"]');
      btn.textContent = 'Message Sent!';
      btn.style.backgroundColor = '#28a745';
      btn.style.borderColor = '#28a745';
      this.reset();
      setTimeout(() => {
        btn.textContent = 'Send Message';
        btn.style.backgroundColor = '';
        btn.style.borderColor = '';
      }, 4000);
    });
  }

  // ===== DONATE FORM =====
  const donateForm = document.querySelector('#donateForm');
  if (donateForm) {
    donateForm.addEventListener('submit', function (e) {
      e.preventDefault();
      window.open('https://flutterwave.com/donate/jtnkqdilnoyf', '_blank');
    });
  }

  // ===== PROGRESS BARS =====
  const progressBars = document.querySelectorAll('.progress-bar[data-width]');
  if (progressBars.length > 0) {
    const pbObserver = new IntersectionObserver(function (entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.width = entry.target.getAttribute('data-width') + '%';
          pbObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    progressBars.forEach(pb => {
      pb.style.width = '0%';
      pb.style.transition = 'width 1.5s ease';
      pbObserver.observe(pb);
    });
  }

  // ===== MOBILE MENU CLOSE ON LINK CLICK =====
  document.querySelectorAll('.navbar-nav .nav-link:not(.dropdown-toggle)').forEach(link => {
    link.addEventListener('click', function () {
      const navbarCollapse = document.querySelector('.navbar-collapse');
      if (navbarCollapse && navbarCollapse.classList.contains('show')) {
        const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
        if (bsCollapse) bsCollapse.hide();
      }
    });
  });

  // ===== HERO SLIDE ANIMATION =====
  const heroCarousel = document.querySelector('#heroCarousel');
  if (heroCarousel) {
    heroCarousel.addEventListener('slid.bs.carousel', function (e) {
      const activeSlide = e.relatedTarget;
      const content = activeSlide.querySelector('.hero-content');
      if (content) {
        content.style.opacity = '0';
        content.style.transform = 'translateY(30px)';
        content.style.transition = 'opacity 0.7s ease 0.2s, transform 0.7s ease 0.2s';
        setTimeout(() => {
          content.style.opacity = '1';
          content.style.transform = 'translateY(0)';
        }, 50);
      }
    });
  }

});
