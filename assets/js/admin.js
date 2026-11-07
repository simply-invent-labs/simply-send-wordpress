(function () {
  'use strict';

  var region = document.getElementById('simply_send_region');
  if (region) {
    var key = document.getElementById('simply_send_api_key');
    var keyRequired = key.required;
    var account = document.getElementById('simply_send_account_id');
    account.addEventListener('input', function () { account.value = account.value.toUpperCase(); });
    var notice = document.getElementById('ss-region-unsaved');
    var test = document.querySelector('[name="simply_send_test"]');
    var setup = document.getElementById('simply-send-setup');
    var sender = document.getElementById('simply_send_from_email');
    var stepIds = ['ss-region', 'ss-account', 'ss-sender', 'ss-delivery'];
    var openIndex = -1;
    var completeSteps = [];
    function openStep(index, focus) {
      var chosen = document.getElementById(stepIds[index]);
      if (chosen.getAttribute('aria-disabled') === 'true') { return; }
      openIndex = index;
      stepIds.forEach(function (id, stepIndex) {
        var step = document.getElementById(id);
        var expanded = stepIndex === index;
        step.classList.toggle('ss-step-collapsed', !expanded);
        step.querySelector('.ss-step-toggle').setAttribute('aria-expanded', expanded ? 'true' : 'false');
        var body = step.querySelector('.ss-step-content');
        body.inert = !expanded;
        body.setAttribute('aria-hidden', expanded ? 'false' : 'true');
      });
      if (focus) { chosen.querySelector('.ss-step-toggle').focus({preventScroll: true}); }
    }
    stepIds.forEach(function (id, index) {
      var step = document.getElementById(id);
      var heading = step.querySelector('.ss-step-heading');
      var title = heading.querySelector('h2');
      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'ss-step-toggle';
      toggle.setAttribute('aria-controls', id + '-content');
      var titleText = document.createElement('span');
      titleText.textContent = title.textContent;
      var chevron = document.createElement('span');
      chevron.className = 'ss-step-chevron';
      chevron.setAttribute('aria-hidden', 'true');
      toggle.append(titleText, chevron);
      title.replaceChildren(toggle);
      toggle.addEventListener('click', function () { openStep(index, false); });
      var summary = document.createElement('p');
      summary.className = 'ss-step-summary';
      var summaryText = document.createElement('span');
      summary.append(summaryText);
      heading.firstElementChild.append(summary);
      var body = document.createElement('div');
      body.id = id + '-content';
      body.className = 'ss-step-content';
      var inner = document.createElement('div');
      inner.className = 'ss-step-content-inner';
      Array.from(step.children).forEach(function (child) {
        if (child !== heading && !child.classList.contains('ss-step-number')) { inner.append(child); }
      });
      if (index < 3) {
        var actions = document.createElement('div');
        actions.className = 'ss-actions';
        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'ss-button ss-button-primary ss-step-continue';
        var text = document.createElement('span');
        text.textContent = setup.dataset.continueLabel;
        next.append(text);
        next.addEventListener('click', function () { if (completeSteps[index]) { openStep(index + 1, true); } });
        actions.append(next);
        inner.append(actions);
      }
      body.append(inner);
      step.append(body);
    });

    function syncSteps() {
      var selected = region.querySelector('[name="region"]:checked');
      var accountComplete = !!selected && (!!key.value.trim() || !key.required) && !!account.value.trim();
      var senderComplete = accountComplete && !!sender.value.trim() && sender.validity.valid;
      var completed = [!!selected, accountComplete, senderComplete, senderComplete && setup.querySelector('[name="enabled"]').checked];
      completeSteps = completed;
      var active = completed.indexOf(false);
      if (active === -1) { active = 3; }
      stepIds.forEach(function (id, index) {
        var step = document.getElementById(id);
        var locked = index > active;
        step.classList.toggle('ss-step-locked', locked);
        step.classList.toggle('ss-step-complete', completed[index]);
        step.setAttribute('aria-disabled', locked ? 'true' : 'false');
        if (index === active) { step.setAttribute('aria-current', 'step'); }
        else { step.removeAttribute('aria-current'); }
        step.querySelector('.ss-step-number').textContent = completed[index] ? '✓' : String(index + 1);
        step.querySelectorAll('input, button, select, textarea').forEach(function (control) { control.disabled = locked; });
        var next = step.querySelector('.ss-step-continue');
        if (next) { next.disabled = locked || !completed[index]; }
        var summary = step.querySelector('.ss-step-summary span');
        if (index === 0 && selected) {
          var card = selected.closest('label');
          summary.textContent = card.querySelector('.ss-region-country').textContent + ' · ' + card.querySelector('.ss-region-location').textContent;
        } else if (index === 1) { summary.textContent = completed[index] ? setup.dataset.accountSummary : ''; }
        else if (index === 2) { summary.textContent = completed[index] ? sender.value : ''; }
        else { summary.textContent = completed[index] ? setup.dataset.deliverySummary : ''; }

        var nav = document.querySelector('.ss-progress a[href="#' + id + '"]');
        if (nav) {
          nav.setAttribute('aria-disabled', locked ? 'true' : 'false');
          if (locked) { nav.setAttribute('tabindex', '-1'); }
          else { nav.removeAttribute('tabindex'); }
        }
      });
      if (openIndex === -1) { openStep(keyRequired && !account.value.trim() ? 0 : active, false); }
      else if (openIndex > active) { openStep(active, false); }
    }
    setup.addEventListener('input', function () {
      if (test) { test.disabled = true; }
      syncSteps();
    });
    setup.addEventListener('change', function () {
      if (test) { test.disabled = true; }
      syncSteps();
    });
    document.querySelector('.ss-progress').addEventListener('click', function (event) {
      var link = event.target.closest('a');
      if (link) {
        event.preventDefault();
        if (link.getAttribute('aria-disabled') !== 'true') { openStep(stepIds.indexOf(link.getAttribute('href').slice(1)), true); }
      }
    });

    region.addEventListener('change', function (event) {
      var option = event.target;
      if (option.name !== 'region' || !option.checked) { return; }
      var changed = option.value !== region.dataset.savedRegion;
      var regionStep = document.getElementById('ss-region');
      regionStep.classList.add('ss-step-complete');
      regionStep.querySelector('.ss-step-number').textContent = '✓';
      ['ss-account', 'ss-sender', 'ss-delivery'].forEach(function (id, index) {
        var step = document.getElementById(id);
        step.classList.remove('ss-step-complete');
        step.querySelector('.ss-step-number').textContent = String(index + 2);
        var badge = step.querySelector('.ss-step-badge');
        if (badge) { badge.hidden = true; }
      });
      document.querySelectorAll('[data-region-link]').forEach(function (link) {
        if (option.dataset.portal) {
          link.href = option.dataset.portal + link.dataset.regionLink;
          link.removeAttribute('aria-disabled');
        } else {
          link.removeAttribute('href');
          link.setAttribute('aria-disabled', 'true');
        }
      });
      document.getElementById('simply_send_api_url').textContent = option.dataset.apiUrl || '';
      key.value = '';
      key.required = changed || keyRequired;
      account.value = '';
      document.getElementById('simply_send_from_email').value = '';
      notice.hidden = false;
      if (test) { test.disabled = true; }
      syncSteps();
      openStep(1, true);
    });
    syncSteps();
  }

  var accountToggle = document.querySelector('.ss-account-toggle');
  if (accountToggle) {
    var accountValue = document.getElementById('ss-account-value');
    var maskedAccount = accountValue.textContent;
    var accountError = document.querySelector('.ss-account-error');
    function setAccountVisibility(visible, value) {
      accountValue.textContent = visible ? value : maskedAccount;
      accountToggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
      accountToggle.setAttribute('aria-label', visible ? accountToggle.dataset.hideLabel : accountToggle.dataset.showLabel);
      accountToggle.querySelector('.ss-account-toggle-text').textContent = visible ? accountToggle.dataset.hide : accountToggle.dataset.show;
      var icon = accountToggle.querySelector('.dashicons');
      icon.classList.toggle('dashicons-visibility', !visible);
      icon.classList.toggle('dashicons-hidden', visible);
    }
    accountToggle.hidden = false;
    accountToggle.addEventListener('click', function () {
      accountError.textContent = '';
      if (accountToggle.getAttribute('aria-pressed') === 'true') { setAccountVisibility(false); return; }
      accountToggle.disabled = true;
      fetch(accountToggle.dataset.url, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'simply_send_reveal_account_id', _ajax_nonce: accountToggle.dataset.nonce}).toString()
      }).then(function (response) {
        if (!response.ok) { throw new Error('reveal failed'); }
        return response.json();
      }).then(function (result) {
        if (!result.success || !result.data || typeof result.data.accountId !== 'string') { throw new Error('reveal failed'); }
        setAccountVisibility(true, result.data.accountId);
      }).catch(function () { accountError.textContent = accountToggle.dataset.error; })
        .finally(function () { accountToggle.disabled = false; });
    });
  }

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
