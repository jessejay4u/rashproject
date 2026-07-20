document.addEventListener('DOMContentLoaded', function () {
  // Highlight the active nav link based on current path
  var path = window.location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('.gma-navbar .nav-link').forEach(function (link) {
    var href = link.getAttribute('href');
    if (href === path) {
      link.classList.add('active');
    }
  });

  // Back-to-top button
  var backToTop = document.getElementById('gmaBackToTop');
  if (backToTop) {
    window.addEventListener('scroll', function () {
      backToTop.classList.toggle('show', window.scrollY > 500);
    });
    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Duplicate ticker content so the CSS marquee loop has no visible seam
  var track = document.querySelector('.gma-ticker__track');
  if (track && track.children.length) {
    track.innerHTML += track.innerHTML;
  }
});
