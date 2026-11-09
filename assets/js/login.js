/* Login page: draws the official "Continue with Google" button.
   Google's script (gsi/client) is loaded in login.php. */
(function () {
  var box = document.getElementById('googleBtn');
  if (!box) return;

  function draw() {
    if (!window.google || !google.accounts || !google.accounts.id) return false;
    google.accounts.id.initialize({
      client_id: box.dataset.clientId,
      login_uri: box.dataset.loginUri,   // Google POSTs the signed token here
      ux_mode: 'redirect'
    });
    google.accounts.id.renderButton(box, {
      type: 'standard', theme: 'outline', size: 'large',
      text: 'continue_with', shape: 'rectangular', logo_alignment: 'left',
      width: Math.min(400, box.clientWidth || 320)
    });
    return true;
  }

  // wait until Google's script has loaded (up to ~6 seconds)
  var tries = 0;
  var timer = setInterval(function () {
    if (draw() || ++tries > 30) {
      clearInterval(timer);
      if (tries > 30) box.innerHTML = '<p class="hint">Couldn’t load Google sign-in. Check your internet connection and refresh.</p>';
    }
  }, 200);
})();