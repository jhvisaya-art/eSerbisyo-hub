// client/*/assets/js/a11y.js
(function () {
  const KEY = "eserbisyo_a11y_contrast";

  function applyContrast(on) {
    document.documentElement.classList.toggle("a11y-contrast", !!on);
  }

  // Load saved preference
  const saved = localStorage.getItem(KEY);
  if (saved === "1") applyContrast(true);

  // Expose small API for your buttons
  window.eSerbisyoA11y = {
    toggleContrast: function () {
      const isOn = document.documentElement.classList.toggle("a11y-contrast");
      localStorage.setItem(KEY, isOn ? "1" : "0");
      return isOn;
    },
    setContrast: function (on) {
      applyContrast(on);
      localStorage.setItem(KEY, on ? "1" : "0");
    }
  };
})();
