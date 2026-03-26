(function () {
    'use strict';

    /* ---- Membership tier conditional show/hide ---- */
    function initTierConditional() {
        var typeSelect      = document.getElementById('iicm-membership-type');
        var tierSelect      = document.getElementById('iicm-membership-tier');
        var tierOrdinary    = document.getElementById('iicm-tier-ordinary');
        var tierAssociate   = document.getElementById('iicm-tier-associate');
        if (!typeSelect || !tierSelect) return;

        function updateTierOptions() {
            var val = typeSelect.value;
            if (val === 'ordinary') {
                if (tierOrdinary)  tierOrdinary.style.display  = '';
                if (tierAssociate) tierAssociate.style.display = 'none';
                var cur = tierSelect.value;
                if (cur === 'local' || cur === 'foreign') tierSelect.value = '';
            } else if (val === 'associate') {
                if (tierOrdinary)  tierOrdinary.style.display  = 'none';
                if (tierAssociate) tierAssociate.style.display = '';
                var cur2 = tierSelect.value;
                if (cur2 === 'tier1' || cur2 === 'tier2' || cur2 === 'tier3') tierSelect.value = '';
            } else {
                if (tierOrdinary)  tierOrdinary.style.display  = '';
                if (tierAssociate) tierAssociate.style.display = '';
            }
        }

        typeSelect.addEventListener('change', updateTierOptions);
        updateTierOptions();
    }

    /* ---- AJAX update (textContent only — no innerHTML) ---- */
    function initUpdateBtn() {
        var btn = document.getElementById('iicm-update-btn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var id             = btn.getAttribute('data-id');
            var statusEl       = document.getElementById('iicm-status-select');
            var typeEl         = document.getElementById('iicm-membership-type');
            var tierEl         = document.getElementById('iicm-membership-tier');
            var notesEl        = document.getElementById('iicm-admin-notes');
            var noticeEl       = document.getElementById('iicm-update-notice');

            var status         = statusEl ? statusEl.value         : '';
            var membershipType = typeEl   ? typeEl.value           : '';
            var membershipTier = tierEl   ? tierEl.value           : '';
            var adminNotes     = notesEl  ? notesEl.value          : '';

            if (noticeEl) {
                noticeEl.textContent = '';
                noticeEl.className   = 'iicm-admin-notice';
                noticeEl.style.display = 'none';
            }

            btn.disabled      = true;
            btn.textContent   = 'Saving\u2026';

            var params = new URLSearchParams();
            params.append('action',          'iicm_update_application');
            params.append('nonce',           iicmAdmin.nonce);
            params.append('id',              id);
            params.append('status',          status);
            params.append('membership_type', membershipType);
            params.append('membership_tier', membershipTier);
            params.append('admin_notes',     adminNotes);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', iicmAdmin.ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                btn.disabled    = false;
                btn.textContent = 'Update';

                var message = '';
                var success = false;
                try {
                    var res = JSON.parse(xhr.responseText);
                    success = !!res.success;
                    message = (res.data && res.data.message) ? res.data.message : (success ? 'Updated.' : 'Error.');
                } catch (e) {
                    message = 'An unexpected error occurred.';
                }

                if (noticeEl) {
                    noticeEl.textContent   = message; // textContent only — XSS safe
                    noticeEl.className     = 'iicm-admin-notice ' + (success ? 'iicm-notice-success' : 'iicm-notice-error');
                    noticeEl.style.display = 'block';
                }

                // Update the status badge without innerHTML
                if (success) {
                    var badge = document.querySelector('.iicm-admin-topbar .iicm-status-badge');
                    if (badge && status) {
                        badge.className    = 'iicm-status-badge iicm-status-' + status;
                        badge.textContent  = status.charAt(0).toUpperCase() + status.slice(1);
                    }
                }
            };

            xhr.send(params.toString());
        });
    }

    function init() {
        initTierConditional();
        initUpdateBtn();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
