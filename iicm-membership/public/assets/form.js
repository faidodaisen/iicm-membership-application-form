(function () {
    'use strict';

    var currentStep = 1;
    var totalSteps  = 3;

    function getStep(n) {
        return document.querySelector('.iicm-form-step[data-step="' + n + '"]');
    }
    function getStepIndicator(n) {
        return document.querySelector('.iicm-step[data-step="' + n + '"]');
    }

    function showStep(n) {
        for (var i = 1; i <= totalSteps; i++) {
            var step = getStep(i);
            if (step) step.style.display = (i === n) ? '' : 'none';
        }
        updateStepIndicators(n);
        currentStep = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function updateStepIndicators(active) {
        for (var i = 1; i <= totalSteps; i++) {
            var ind = getStepIndicator(i);
            if (!ind) continue;
            ind.classList.remove('active', 'done');
            var numEl = ind.querySelector('.iicm-step-num');
            if (i === active) {
                ind.classList.add('active');
                if (numEl) numEl.textContent = String(i);
            } else if (i < active) {
                ind.classList.add('done');
                if (numEl) numEl.textContent = '\u2713';
            } else {
                if (numEl) numEl.textContent = String(i);
            }
        }
    }

    function validateStep(n) {
        var step = getStep(n);
        if (!step) return true;
        var valid = true;
        clearErrors(step);

        var required = step.querySelectorAll('[required]');
        for (var i = 0; i < required.length; i++) {
            var el = required[i];
            if (el.type === 'checkbox' && !el.checked) {
                showFieldError(el, 'This field is required.');
                valid = false;
            } else if (el.type === 'file') {
                if (!el.files || el.files.length === 0) {
                    showFieldError(el, 'Please upload your company profile.');
                    valid = false;
                } else {
                    var file    = el.files[0];
                    var ext     = file.name.split('.').pop().toLowerCase();
                    var allowed = ['pdf','doc','docx','jpg','jpeg','png'];
                    if (allowed.indexOf(ext) === -1) {
                        showFieldError(el, 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG.');
                        valid = false;
                    } else if (file.size > 5 * 1024 * 1024) {
                        showFieldError(el, 'File size must not exceed 5MB.');
                        valid = false;
                    }
                }
            } else if (el.type !== 'radio' && el.value.trim() === '') {
                showFieldError(el, 'This field is required.');
                valid = false;
            }
        }

        if (n === 1) {
            // AUM radio
            var aumRadios = step.querySelectorAll('input[name="aum"]');
            var aumChecked = false;
            for (var r = 0; r < aumRadios.length; r++) {
                if (aumRadios[r].checked) { aumChecked = true; break; }
            }
            if (!aumChecked) {
                var radioGroup = step.querySelector('.iicm-radio-group');
                if (radioGroup) {
                    var span = document.createElement('span');
                    span.className = 'iicm-radio-error';
                    span.style.cssText = 'color:#d93025;font-size:12px;display:block;margin-top:4px;';
                    span.textContent = 'Please select an AUM option.';
                    radioGroup.parentNode.appendChild(span);
                }
                valid = false;
            }

            // At least one category
            var catChecked = step.querySelectorAll('input[name="org_categories[]"]:checked');
            if (catChecked.length === 0) {
                var cbGroup = step.querySelector('.iicm-checkbox-group');
                if (cbGroup) {
                    var span2 = document.createElement('span');
                    span2.className = 'iicm-cat-error';
                    span2.style.cssText = 'color:#d93025;font-size:12px;display:block;margin-top:4px;';
                    span2.textContent = 'Please select at least one category.';
                    cbGroup.parentNode.appendChild(span2);
                }
                valid = false;
            }

            // Email format
            var emailEl = step.querySelector('input[name="email"]');
            if (emailEl && emailEl.value.trim() !== '' && !isValidEmail(emailEl.value)) {
                showFieldError(emailEl, 'Please enter a valid email address.');
                valid = false;
            }
        }

        if (n === 2) {
            var repEmail = step.querySelector('input[name="rep_email"]');
            if (repEmail && repEmail.value.trim() !== '' && !isValidEmail(repEmail.value)) {
                showFieldError(repEmail, 'Please enter a valid email address.');
                valid = false;
            }
        }

        return valid;
    }

    function isValidEmail(val) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    }

    function showFieldError(el, msg) {
        el.classList.add('iicm-error');
        var span = document.createElement('span');
        span.className = 'iicm-field-error-msg';
        span.style.cssText = 'color:#d93025;font-size:12px;display:block;margin-top:4px;';
        span.textContent = msg;
        if (el.parentNode) el.parentNode.appendChild(span);
    }

    function clearErrors(container) {
        var errEls = container.querySelectorAll('.iicm-field-error-msg, .iicm-radio-error, .iicm-cat-error');
        for (var i = 0; i < errEls.length; i++) errEls[i].parentNode.removeChild(errEls[i]);
        var errInputs = container.querySelectorAll('.iicm-error');
        for (var j = 0; j < errInputs.length; j++) errInputs[j].classList.remove('iicm-error');
    }

    function initSameAddress() {
        var cb        = document.getElementById('same_as_registered');
        var corrFields= document.getElementById('correspondence-fields');
        if (!cb || !corrFields) return;
        cb.addEventListener('change', function () {
            if (cb.checked) {
                corrFields.style.display = 'none';
                var map = {
                    'correspondence_address':    'registered_address',
                    'correspondence_postcode':   'registered_postcode',
                    'correspondence_city_state': 'registered_city_state',
                    'correspondence_country':    'registered_country',
                };
                Object.keys(map).forEach(function (target) {
                    var src  = document.getElementById(map[target]);
                    var dest = document.getElementById(target);
                    if (src && dest) dest.value = src.value;
                });
            } else {
                corrFields.style.display = '';
            }
        });
    }

    function initOthersToggle() {
        var otherCb   = document.querySelector('input[name="org_categories[]"][value="others"]');
        var otherWrap = document.getElementById('org-category-other-wrap');
        if (!otherCb || !otherWrap) return;
        otherCb.addEventListener('change', function () {
            otherWrap.style.display = otherCb.checked ? '' : 'none';
        });
    }

    function submitForm(form) {
        var submitBtn = document.getElementById('iicm-submit-btn');
        var errorDiv  = document.getElementById('iicm-form-error');
        submitBtn.disabled    = true;
        submitBtn.textContent = 'Submitting\u2026';
        if (errorDiv) errorDiv.style.display = 'none';

        var formData = new FormData(form);
        formData.append('action', 'iicm_submit_application');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', iicmForm.ajaxurl, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            submitBtn.disabled    = false;
            submitBtn.textContent = 'Submit Application';
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        var wrap = form.closest('.iicm-form-wrap');
                        wrap.querySelector('#iicm-application-form').style.display = 'none';
                        wrap.querySelector('.iicm-steps').style.display = 'none';
                        var successWrap = document.getElementById('iicm-success-message');
                        var successText = document.getElementById('iicm-success-text');
                        if (successText) successText.textContent = res.data.message;
                        if (successWrap) successWrap.style.display = '';
                    } else {
                        if (errorDiv) {
                            errorDiv.textContent = res.data.message || 'Submission failed. Please try again.';
                            errorDiv.style.display = '';
                        }
                    }
                } catch (e) {
                    if (errorDiv) {
                        errorDiv.textContent = 'An unexpected error occurred. Please try again.';
                        errorDiv.style.display = '';
                    }
                }
            } else {
                if (errorDiv) {
                    errorDiv.textContent = 'Server error. Please try again.';
                    errorDiv.style.display = '';
                }
            }
        };
        xhr.send(formData);
    }

    function init() {
        var form = document.getElementById('iicm-application-form');
        if (!form) return;

        initSameAddress();
        initOthersToggle();

        var nextBtns = form.querySelectorAll('.iicm-btn-next');
        for (var i = 0; i < nextBtns.length; i++) {
            nextBtns[i].addEventListener('click', function () {
                if (validateStep(currentStep)) showStep(currentStep + 1);
            });
        }

        var backBtns = form.querySelectorAll('.iicm-btn-back');
        for (var j = 0; j < backBtns.length; j++) {
            backBtns[j].addEventListener('click', function () {
                showStep(currentStep - 1);
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (validateStep(3)) submitForm(form);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
