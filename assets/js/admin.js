(function () {
  'use strict';

  var input = document.getElementById('simply_send_api_key');
  var toggle = document.querySelector('.ss-key-toggle');
  if (!input || !toggle) {
    return;
  }

  var text = toggle.querySelector('.ss-key-toggle-text');
  var icon = toggle.querySelector('.dashicons');
  toggle.hidden = false;
  toggle.addEventListener('click', function () {
    var visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
    toggle.setAttribute('aria-label', visible ? toggle.dataset.hideLabel : toggle.dataset.showLabel);
    text.textContent = visible ? toggle.dataset.hide : toggle.dataset.show;
    icon.classList.toggle('dashicons-visibility', !visible);
    icon.classList.toggle('dashicons-hidden', visible);
  });
}());
