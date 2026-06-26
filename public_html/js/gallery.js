/* ===================================
   Community Liberation – Gallery & Lightbox
   =================================== */

(function () {
  'use strict';

  // ===== BUILD LIGHTBOX DOM =====
  function buildLightbox() {
    if (document.querySelector('.lightbox-overlay')) return;
    const lb = document.createElement('div');
    lb.className = 'lightbox-overlay';
    lb.innerHTML = `
      <button class="lightbox-close" aria-label="Close">&times;</button>
      <button class="lightbox-nav lightbox-prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
      <button class="lightbox-nav lightbox-next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
      <div class="lightbox-inner">
        <img src="" alt="" />
        <p class="lightbox-caption"></p>
      </div>
      <div class="lightbox-counter"></div>`;
    document.body.appendChild(lb);
    return lb;
  }

  // ===== LIGHTBOX CONTROLLER =====
  let currentIndex = 0;
  let items = [];

  function openLightbox(index) {
    const lb = document.querySelector('.lightbox-overlay') || buildLightbox();
    currentIndex = index;
    updateLightbox(lb);
    lb.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    const lb = document.querySelector('.lightbox-overlay');
    if (lb) {
      lb.classList.remove('active');
      document.body.style.overflow = '';
    }
  }

  function updateLightbox(lb) {
    const img = lb.querySelector('img');
    const cap = lb.querySelector('.lightbox-caption');
    const counter = lb.querySelector('.lightbox-counter');
    const item = items[currentIndex];
    img.style.opacity = '0';
    img.src = item.img;
    img.alt = item.caption;
    img.onload = () => { img.style.transition = 'opacity 0.3s ease'; img.style.opacity = '1'; };
    cap.textContent = item.caption;
    counter.textContent = (currentIndex + 1) + ' / ' + items.length;
  }

  function prevItem() {
    currentIndex = (currentIndex - 1 + items.length) % items.length;
    updateLightbox(document.querySelector('.lightbox-overlay'));
  }

  function nextItem() {
    currentIndex = (currentIndex + 1) % items.length;
    updateLightbox(document.querySelector('.lightbox-overlay'));
  }

  // ===== INITIALISE GALLERY ITEMS =====
  function initGallery() {
    const galleryItems = document.querySelectorAll('.gallery-item[data-img]');
    if (!galleryItems.length) return;

    items = Array.from(galleryItems).map(el => ({
      img: el.getAttribute('data-img'),
      caption: el.getAttribute('data-caption') || ''
    }));

    buildLightbox();

    galleryItems.forEach((el, i) => {
      el.addEventListener('click', () => openLightbox(i));
      el.style.cursor = 'pointer';
    });

    // Lightbox events
    document.addEventListener('click', function (e) {
      if (e.target.closest('.lightbox-close') || (e.target.classList.contains('lightbox-overlay') && !e.target.closest('.lightbox-inner'))) {
        closeLightbox();
      }
      if (e.target.closest('.lightbox-prev')) prevItem();
      if (e.target.closest('.lightbox-next')) nextItem();
    });

    document.addEventListener('keydown', function (e) {
      const lb = document.querySelector('.lightbox-overlay');
      if (!lb || !lb.classList.contains('active')) return;
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowLeft') prevItem();
      if (e.key === 'ArrowRight') nextItem();
    });
  }

  // ===== GALLERY FILTER (full gallery page) =====
  function initFilter() {
    const filterBtns = document.querySelectorAll('.gallery-filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-grid .gallery-item');
    if (!filterBtns.length || !galleryItems.length) return;

    filterBtns.forEach(btn => {
      btn.addEventListener('click', function () {
        filterBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.getAttribute('data-filter');
        galleryItems.forEach(item => {
          const cat = item.getAttribute('data-category') || 'all';
          const show = filter === 'all' || cat === filter;
          item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
          if (show) {
            item.style.opacity = '1';
            item.style.transform = 'scale(1)';
            item.style.display = '';
          } else {
            item.style.opacity = '0';
            item.style.transform = 'scale(0.95)';
            setTimeout(() => {
              if (item.style.opacity === '0') item.style.display = 'none';
            }, 300);
          }
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initGallery();
    initFilter();
  });
})();
