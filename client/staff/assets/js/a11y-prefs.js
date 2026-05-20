// client/staff/assets/js/a11y-prefs.js
// Large text is permanently enabled at CSS level (html { font-size: 20px }).
// The largeText class is applied on boot so any legacy rem-based rules resolve.
// High Contrast pref is retained in localStorage — buttons removed from UI.
(() => {
  const KEY_CONTRAST = "staff_pref_contrast";
  function getPref(key) { return localStorage.getItem(key) === "1"; }

  function apply() {
    // largeText always on — baked into CSS default, class kept for compat
    document.documentElement.classList.add("largeText");
    document.documentElement.classList.toggle("contrast", getPref(KEY_CONTRAST));
  }

  // No button binding — buttons removed from all headers
  apply();
  document.addEventListener("DOMContentLoaded", apply);
})();