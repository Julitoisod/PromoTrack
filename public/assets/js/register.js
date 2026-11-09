/* PromoTrack sign-up form behaviour (app/Views/auth/register.php).
 *
 * Everything here is progressive: without JavaScript the form still submits
 * and the server validates every rule. This file only narrows the dropdowns,
 * shows the fields that apply, and keeps the Registration Summary current.
 * Loaded as a file because the Content-Security-Policy refuses inline script
 * without a nonce. */
(function () {
    'use strict';

    var form = document.getElementById('registerForm');
    if (!form) {
        return;
    }

    var $ = function (selector) { return form.querySelector(selector); };

    var cdc = $('#unitInput');
    var company = $('#companyInput');
    var category = $('#rankCategoryInput');
    var rank = $('#rankInput');
    var degree = $('#degreeInput');
    var yearLevel = $('#yearLevelInput');
    var rcdgNotice = document.getElementById('rcdgNotice');
    var rcdgModalEl = document.getElementById('rcdgModal');
    var lastRcdgRank = null;

    function selectedText(select) {
        var option = select.options[select.selectedIndex];
        return option && option.value !== '' ? option.textContent.trim() : '';
    }

    // Shows only the options whose data-<key> matches value; clears the
    // selection when it no longer belongs.
    function filterOptions(select, key, value) {
        Array.prototype.forEach.call(select.querySelectorAll('option[data-' + key + ']'), function (option) {
            var match = value !== '' && option.dataset[key] === value;
            option.hidden = !match;
            option.disabled = !match;
            if (!match && option.selected) {
                select.value = '';
            }
        });
        Array.prototype.forEach.call(select.querySelectorAll('optgroup[data-' + key + ']'), function (group) {
            group.hidden = value === '' || group.dataset[key] !== value;
        });
    }

    function attainment() {
        var checked = form.querySelector('input[name="educational_attainment"]:checked');
        return checked ? checked.value : '';
    }

    function syncAttainment() {
        var value = attainment();

        Array.prototype.forEach.call(form.querySelectorAll('[data-show-for]'), function (block) {
            var show = block.dataset.showFor.split(' ').indexOf(value) !== -1;
            block.classList.toggle('d-none', !show);
        });

        yearLevel.required = value === 'undergraduate';

        var needsDegree = value === 'bachelor' || value === 'others';
        degree.required = needsDegree;
        form.querySelector('[data-degree-required]').classList.toggle('d-none', !needsDegree);
        form.querySelector('[data-degree-label]').textContent = {
            bachelor: "Bachelor's Degree (which one)",
            masteral: 'Masteral Degree (optional)',
            doctorate: 'Doctorate (optional)',
            others: 'Please specify'
        }[value] || 'Degree';
    }

    function syncRcdg(fromUser) {
        var option = rank.options[rank.selectedIndex];
        var isRcdg = !!option && option.dataset.rcdg === '1';

        rcdgNotice.classList.toggle('d-none', !isRcdg);

        if (isRcdg && fromUser && lastRcdgRank !== rank.value && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(rcdgModalEl).show();
        }
        lastRcdgRank = isRcdg ? rank.value : null;
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }
        var parts = value.split('-');
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[parseInt(parts[1], 10) - 1] + ' ' + parseInt(parts[2], 10) + ', ' + parts[0];
    }

    // "Dela Cruz, Juan P. Jr." -- only the middle initial is printed, as the
    // client asked, and the same shape the system uses everywhere else.
    function fullName() {
        var first = $('#firstNameInput').value.trim();
        var middle = $('#middleNameInput').value.trim();
        var last = $('#lastNameInput').value.trim();
        var suffix = $('#suffixInput').value.trim();
        if (!first && !last) {
            return '-';
        }
        var name = last + (last && first ? ', ' : '') + first;
        if (middle) {
            name += ' ' + middle.charAt(0).toUpperCase() + '.';
        }
        if (suffix) {
            name += ' ' + suffix;
        }
        return name;
    }

    function syncSummary() {
        var track = form.querySelector('input[name="educational_attainment"]:checked');
        var trackText = track ? track.dataset.label : '';
        if (attainment() === 'undergraduate' && yearLevel.value) {
            trackText += ' (' + yearLevel.value + ')';
        } else if (degree.value.trim() && attainment() !== 'undergraduate' && attainment() !== 'none') {
            trackText += ' (' + degree.value.trim() + ')';
        }

        document.getElementById('sumName').textContent = fullName();
        document.getElementById('sumCdc').textContent = selectedText(cdc) || '-';
        document.getElementById('sumCompany').textContent = (selectedText(company) || '-').replace(/\s*\(.*\)$/, '');
        document.getElementById('sumRank').textContent = selectedText(rank) || '-';
        document.getElementById('sumTrack').textContent = trackText || '-';
        document.getElementById('sumEnlistment').textContent = formatDate($('#enlistmentInput').value);
        document.getElementById('sumPromotion').textContent = formatDate($('#lastPromotionInput').value);
    }

    cdc.addEventListener('change', function () {
        filterOptions(company, 'parent', cdc.value);
    });
    category.addEventListener('change', function () {
        filterOptions(rank, 'category', category.value);
        syncRcdg(false);
    });
    rank.addEventListener('change', function () {
        syncRcdg(true);
    });
    Array.prototype.forEach.call(form.querySelectorAll('input[name="educational_attainment"]'), function (radio) {
        radio.addEventListener('change', syncAttainment);
    });

    form.addEventListener('input', syncSummary);
    form.addEventListener('change', syncSummary);

    // First paint, including after a failed submit that re-filled the form.
    filterOptions(company, 'parent', cdc.value);
    filterOptions(rank, 'category', category.value);
    syncAttainment();
    syncRcdg(false);
    syncSummary();
})();
