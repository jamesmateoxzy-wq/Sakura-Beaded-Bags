/* My Profile + Account Settings
   Forms marked data-demo only validate and show a message.
   Backend: delete the data-demo attribute on a form and it will post normally. */
(function () {
  document.querySelectorAll('form[data-demo]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.checkValidity()) { form.reportValidity(); return; }   // browser shows what is wrong
      window.Sakura.toast('Saved (demo only until the backend is connected)');
    });
  });
})();
