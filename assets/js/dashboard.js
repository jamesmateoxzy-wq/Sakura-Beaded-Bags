/* Dashboard only: reload when the month changes + one-time bar animation */
(function () {
  var monthInput = document.getElementById('monthInput');
  if (monthInput) {
    monthInput.addEventListener('change', function () {
      if (monthInput.value) document.getElementById('monthForm').submit();
    });
  }

  // bars grow once on load (skipped if the user prefers reduced motion)
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.bar').forEach(function (bar, i) {
      bar.style.animationDelay = (i * 70) + 'ms';
      bar.classList.add('grow');
    });
  }
})();