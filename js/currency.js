(function () {
    'use strict';

    var CACHE_KEY = 'techlake_currency';
    var PREF_KEY = 'techlake_currency_pref';
    var CACHE_DURATION = 3600000; // 1 hour

    var SYMBOL_TO_CODE = { '$': 'USD', '\u00a3': 'GBP', '\u20ac': 'EUR' };

    // Popular currencies shown at the top of the selector
    var POPULAR_CODES = [
        'GBP', 'USD', 'EUR', 'CAD', 'AUD', 'JPY', 'CNY', 'INR', 'NGN',
        'CHF', 'NZD', 'SGD', 'HKD', 'KRW', 'BRL', 'ZAR', 'AED', 'MXN'
    ];

    var allRates = null;
    var activeCurrency = null;
    var originals = [];
    var selectorBuilt = false;
    var listEl = null;
    var dropdownEl = null;
    var searchInputEl = null;
    var wrapperEl = null;
    var btnLabelEl = null;

    // ---- Cache helpers ----

    function getCached() {
        try {
            var data = JSON.parse(sessionStorage.getItem(CACHE_KEY));
            if (data && Date.now() - data.timestamp < CACHE_DURATION) {
                return data;
            }
        } catch (e) { /* ignore */ }
        return null;
    }

    function setCache(rates) {
        try {
            sessionStorage.setItem(CACHE_KEY, JSON.stringify({
                rates: rates,
                timestamp: Date.now()
            }));
        } catch (e) { /* ignore */ }
    }

    function getUserPref() {
        try { return localStorage.getItem(PREF_KEY); } catch (e) { return null; }
    }

    function setUserPref(code) {
        try { localStorage.setItem(PREF_KEY, code); } catch (e) { /* ignore */ }
    }

    // ---- Currency name helper ----

    function getCurrencyName(code) {
        try {
            var names = new Intl.DisplayNames(['en'], { type: 'currency' });
            return names.of(code);
        } catch (e) {
            return code;
        }
    }

    function getCurrencySymbol(code) {
        try {
            var parts = new Intl.NumberFormat('en', {
                style: 'currency', currency: code,
                minimumFractionDigits: 0, maximumFractionDigits: 0
            }).formatToParts(0);
            for (var i = 0; i < parts.length; i++) {
                if (parts[i].type === 'currency') return parts[i].value;
            }
        } catch (e) { /* ignore */ }
        return code;
    }

    // ---- Conversion logic ----

    function convert(amount, fromCode, toCode, rates) {
        if (fromCode === toCode) return amount;
        var fromRate = fromCode === 'USD' ? 1 : rates[fromCode];
        var toRate = toCode === 'USD' ? 1 : rates[toCode];
        if (!fromRate || !toRate) return null;
        return (amount / fromRate) * toRate;
    }

    function formatPrice(amount, currencyCode) {
        var rounded = Math.round(amount);
        var fractionDigits = Math.abs(amount - rounded) < 0.01 ? 0 : 2;
        try {
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: currencyCode,
                minimumFractionDigits: fractionDigits,
                maximumFractionDigits: fractionDigits
            }).format(rounded);
        } catch (e) {
            return currencyCode + ' ' + rounded;
        }
    }

    function parseSymbol(text) {
        var m = text.match(/^([\u00a3$\u20ac])\s*([\d,]+(?:\.\d+)?)$/);
        if (m) {
            return { symbol: m[1], amount: parseFloat(m[2].replace(/,/g, '')), code: SYMBOL_TO_CODE[m[1]] };
        }
        return null;
    }

    // ---- Store originals on first pass ----

    function storeOriginals() {
        originals = [];

        var priceEls = document.querySelectorAll('.price');
        for (var i = 0; i < priceEls.length; i++) {
            var el = priceEls[i];
            var parsed = parseSymbol(el.textContent.trim());
            if (parsed && parsed.code) {
                originals.push({ type: 'price', el: el, code: parsed.code, amount: parsed.amount, symbol: parsed.symbol });
            }
        }

        var amountContainers = document.querySelectorAll('.pricing-amount');
        for (var j = 0; j < amountContainers.length; j++) {
            var container = amountContainers[j];
            var currencyEl = container.querySelector('.currency');
            var amountEl = container.querySelector('.amount');
            if (!currencyEl || !amountEl) continue;
            var sym = currencyEl.textContent.trim();
            var baseCode = SYMBOL_TO_CODE[sym];
            var amt = parseFloat(amountEl.textContent.trim().replace(/,/g, ''));
            if (baseCode && !isNaN(amt)) {
                originals.push({ type: 'split', currencyEl: currencyEl, amountEl: amountEl, code: baseCode, amount: amt, symbol: sym });
            }
        }

        var inlineSelectors = ['.price-note', '.btn', '.bundle-badge', '.product-badge', '.cta-guarantee'];
        for (var s = 0; s < inlineSelectors.length; s++) {
            var els = document.querySelectorAll(inlineSelectors[s]);
            for (var k = 0; k < els.length; k++) {
                var text = els[k].textContent;
                var regex = /([\u00a3$\u20ac])([\d,]+(?:\.\d+)?)/g;
                var match;
                var matches = [];
                while ((match = regex.exec(text)) !== null) {
                    var code = SYMBOL_TO_CODE[match[1]];
                    if (code) {
                        matches.push({ full: match[0], symbol: match[1], amount: parseFloat(match[2].replace(/,/g, '')), code: code });
                    }
                }
                if (matches.length > 0) {
                    originals.push({ type: 'inline', el: els[k], originalText: text, matches: matches });
                }
            }
        }
    }

    // ---- Apply conversion ----

    function applyConversion(targetCurrency, rates) {
        rates['USD'] = 1;

        for (var i = 0; i < originals.length; i++) {
            var o = originals[i];

            if (o.type === 'price') {
                if (o.code === targetCurrency) {
                    o.el.textContent = o.symbol + o.amount;
                    o.el.removeAttribute('title');
                } else if (o.amount === 0) {
                    o.el.textContent = formatPrice(0, targetCurrency);
                } else {
                    var conv = convert(o.amount, o.code, targetCurrency, rates);
                    if (conv !== null) {
                        o.el.textContent = formatPrice(conv, targetCurrency);
                        o.el.setAttribute('title', 'Originally ' + o.symbol + o.amount + ' ' + o.code);
                    }
                }
            }

            if (o.type === 'split') {
                if (o.code === targetCurrency) {
                    o.currencyEl.textContent = o.symbol;
                    o.amountEl.textContent = o.amount.toLocaleString();
                    o.amountEl.removeAttribute('title');
                } else if (o.amount === 0) {
                    o.currencyEl.textContent = getCurrencySymbol(targetCurrency);
                } else {
                    var c = convert(o.amount, o.code, targetCurrency, rates);
                    if (c !== null) {
                        o.amountEl.textContent = Math.round(c).toLocaleString();
                        o.amountEl.setAttribute('title', 'Originally ' + o.symbol + o.amount + ' ' + o.code);
                        o.currencyEl.textContent = getCurrencySymbol(targetCurrency);
                    }
                }
            }

            if (o.type === 'inline') {
                var newText = o.originalText;
                for (var m = 0; m < o.matches.length; m++) {
                    var p = o.matches[m];
                    if (p.code === targetCurrency || p.amount === 0) continue;
                    var cv = convert(p.amount, p.code, targetCurrency, rates);
                    if (cv !== null) {
                        newText = newText.replace(p.full, formatPrice(cv, targetCurrency));
                    }
                }
                o.el.textContent = newText;
            }
        }
    }

    // ---- Currency selector UI ----

    function buildSelector() {
        if (selectorBuilt) return;
        selectorBuilt = true;

        wrapperEl = document.createElement('div');
        wrapperEl.className = 'currency-selector';
        wrapperEl.id = 'currencySelector';

        var btn = document.createElement('button');
        btn.className = 'currency-selector-btn';
        btn.setAttribute('aria-label', 'Change currency');
        btn.setAttribute('title', 'Change currency');

        var svgIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';

        btnLabelEl = document.createElement('span');
        btnLabelEl.className = 'currency-selector-label';
        btnLabelEl.textContent = activeCurrency || 'GBP';

        btn.innerHTML = svgIcon;
        btn.appendChild(btnLabelEl);

        dropdownEl = document.createElement('div');
        dropdownEl.className = 'currency-dropdown';
        dropdownEl.style.display = 'none';

        // Search input
        var searchWrap = document.createElement('div');
        searchWrap.className = 'currency-search-wrap';
        searchInputEl = document.createElement('input');
        searchInputEl.type = 'text';
        searchInputEl.className = 'currency-search';
        searchInputEl.placeholder = 'Search currencies\u2026';
        searchInputEl.setAttribute('autocomplete', 'off');
        searchWrap.appendChild(searchInputEl);
        dropdownEl.appendChild(searchWrap);

        // Currency list container
        listEl = document.createElement('div');
        listEl.className = 'currency-list';

        // Show loading state if rates haven't arrived yet
        if (allRates) {
            populateList();
        } else {
            var loading = document.createElement('div');
            loading.className = 'currency-section-header';
            loading.textContent = 'Loading currencies\u2026';
            loading.id = 'currencyLoading';
            listEl.appendChild(loading);
        }

        dropdownEl.appendChild(listEl);
        wrapperEl.appendChild(btn);
        wrapperEl.appendChild(dropdownEl);
        document.body.appendChild(wrapperEl);

        // Toggle dropdown
        function toggleDropdown(show) {
            var isOpen = show !== undefined ? show : dropdownEl.style.display === 'none';
            dropdownEl.style.display = isOpen ? 'flex' : 'none';
            if (isOpen) {
                searchInputEl.value = '';
                filterList('');
                searchInputEl.focus();
            }
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleDropdown();
        });

        document.addEventListener('click', function (e) {
            if (!wrapperEl.contains(e.target)) {
                toggleDropdown(false);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') toggleDropdown(false);
        });

        searchInputEl.addEventListener('input', function () {
            filterList(this.value);
        });

        dropdownEl.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    function populateList() {
        if (!listEl || !allRates) return;

        // Clear existing content (e.g. loading message)
        listEl.innerHTML = '';

        var codes = Object.keys(allRates).sort(function (a, b) {
            return getCurrencyName(a).localeCompare(getCurrencyName(b));
        });

        function createItem(code, section) {
            var item = document.createElement('button');
            item.className = 'currency-item';
            if (code === activeCurrency) item.classList.add('active');
            item.setAttribute('data-code', code);
            item.setAttribute('data-section', section);

            var symbol = getCurrencySymbol(code);
            var name = getCurrencyName(code);
            item.innerHTML = '<span class="currency-item-symbol">' + symbol + '</span>' +
                '<span class="currency-item-name">' + name + '</span>' +
                '<span class="currency-item-code">' + code + '</span>';

            item.addEventListener('click', function () {
                selectCurrency(code);
                dropdownEl.style.display = 'none';
            });

            return item;
        }

        // Popular section
        var popularHeader = document.createElement('div');
        popularHeader.className = 'currency-section-header';
        popularHeader.textContent = 'Popular';
        listEl.appendChild(popularHeader);

        for (var p = 0; p < POPULAR_CODES.length; p++) {
            if (allRates[POPULAR_CODES[p]]) {
                listEl.appendChild(createItem(POPULAR_CODES[p], 'popular'));
            }
        }

        // All currencies section
        var allHeader = document.createElement('div');
        allHeader.className = 'currency-section-header';
        allHeader.textContent = 'All currencies';
        listEl.appendChild(allHeader);

        for (var i = 0; i < codes.length; i++) {
            listEl.appendChild(createItem(codes[i], 'all'));
        }
    }

    function filterList(query) {
        if (!listEl) return;
        var q = query.toLowerCase();
        var items = listEl.querySelectorAll('.currency-item');
        var headers = listEl.querySelectorAll('.currency-section-header');
        var hasPopular = false;
        var hasAll = false;

        for (var i = 0; i < items.length; i++) {
            var code = items[i].getAttribute('data-code').toLowerCase();
            var name = items[i].querySelector('.currency-item-name').textContent.toLowerCase();
            var symbol = items[i].querySelector('.currency-item-symbol').textContent.toLowerCase();
            var match = !q || code.indexOf(q) !== -1 || name.indexOf(q) !== -1 || symbol.indexOf(q) !== -1;
            items[i].style.display = match ? '' : 'none';

            if (match) {
                if (items[i].getAttribute('data-section') === 'popular') hasPopular = true;
                else hasAll = true;
            }
        }

        if (headers.length >= 2) {
            headers[0].style.display = hasPopular ? '' : 'none';
            headers[1].style.display = hasAll ? '' : 'none';
        }
    }

    function selectCurrency(code) {
        activeCurrency = code;
        setUserPref(code);

        if (btnLabelEl) btnLabelEl.textContent = code;

        var items = document.querySelectorAll('.currency-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('active', items[i].getAttribute('data-code') === code);
        }

        if (allRates) {
            applyConversion(code, allRates);
        }
    }

    // ---- Main entry ----

    function init() {
        storeOriginals();

        // Build the selector button immediately so it's always visible
        activeCurrency = getUserPref() || 'GBP';
        buildSelector();

        var cached = getCached();

        if (cached && cached.rates) {
            allRates = cached.rates;
            allRates['USD'] = 1;
            populateList();
            resolveActiveCurrency();
            return;
        }

        // Fetch rates
        fetch('https://open.er-api.com/v6/latest/USD')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.result !== 'success' || !data.rates) return;
                allRates = data.rates;
                allRates['USD'] = 1;
                setCache(allRates);
                populateList();
                resolveActiveCurrency();
            })
            .catch(function () { /* fail silently - button still visible */ });
    }

    function resolveActiveCurrency() {
        var userPref = getUserPref();

        if (userPref && allRates[userPref]) {
            activeCurrency = userPref;
            if (btnLabelEl) btnLabelEl.textContent = activeCurrency;
            applyConversion(activeCurrency, allRates);
            return;
        }

        // No saved preference - detect from geolocation
        fetch('https://ipapi.co/json/')
            .then(function (r) { return r.json(); })
            .then(function (geo) {
                var currency = geo.currency;
                if (currency && allRates && allRates[currency]) {
                    activeCurrency = currency;
                } else {
                    activeCurrency = 'GBP';
                }
                if (btnLabelEl) btnLabelEl.textContent = activeCurrency;
                applyConversion(activeCurrency, allRates);

                // Update active state in the list
                var items = document.querySelectorAll('.currency-item');
                for (var i = 0; i < items.length; i++) {
                    items[i].classList.toggle('active', items[i].getAttribute('data-code') === activeCurrency);
                }
            })
            .catch(function () {
                activeCurrency = 'GBP';
                if (btnLabelEl) btnLabelEl.textContent = activeCurrency;
            });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
