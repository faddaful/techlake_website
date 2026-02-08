(function () {
    'use strict';

    var CACHE_KEY = 'techlake_currency';
    var CACHE_DURATION = 3600000; // 1 hour

    var SYMBOL_TO_CODE = { '$': 'USD', '\u00a3': 'GBP', '\u20ac': 'EUR' };

    function getCached() {
        try {
            var data = JSON.parse(sessionStorage.getItem(CACHE_KEY));
            if (data && Date.now() - data.timestamp < CACHE_DURATION) {
                return data;
            }
        } catch (e) { /* ignore */ }
        return null;
    }

    function setCache(currency, rates) {
        try {
            sessionStorage.setItem(CACHE_KEY, JSON.stringify({
                currency: currency,
                rates: rates,
                timestamp: Date.now()
            }));
        } catch (e) { /* ignore */ }
    }

    function detectAndConvert() {
        var cached = getCached();
        if (cached) {
            convertAllPrices(cached.currency, cached.rates);
            return;
        }

        fetch('https://ipapi.co/json/')
            .then(function (r) { return r.json(); })
            .then(function (geo) {
                var currency = geo.currency;
                if (!currency) return;

                fetch('https://open.er-api.com/v6/latest/USD')
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.result !== 'success' || !data.rates) return;
                        setCache(currency, data.rates);
                        convertAllPrices(currency, data.rates);
                    })
                    .catch(function () { /* fail silently */ });
            })
            .catch(function () { /* fail silently */ });
    }

    function convert(amount, fromCode, toCode, rates) {
        if (fromCode === toCode) return amount;
        var fromRate = fromCode === 'USD' ? 1 : rates[fromCode];
        var toRate = toCode === 'USD' ? 1 : rates[toCode];
        if (!fromRate || !toRate) return null;
        return (amount / fromRate) * toRate;
    }

    function formatPrice(amount, currencyCode) {
        // Round to whole number if close, otherwise 2 decimals
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
        // Match currency symbol at the start followed by digits
        var m = text.match(/^([£$€])\s*([\d,]+(?:\.\d+)?)$/);
        if (m) {
            return { symbol: m[1], amount: parseFloat(m[2].replace(/,/g, '')), code: SYMBOL_TO_CODE[m[1]] };
        }
        return null;
    }

    function convertAllPrices(visitorCurrency, rates) {
        rates['USD'] = 1; // base

        // 1. <span class="price">$49</span> / <span class="price">£39</span>
        var priceEls = document.querySelectorAll('.price');
        for (var i = 0; i < priceEls.length; i++) {
            var el = priceEls[i];
            var parsed = parseSymbol(el.textContent.trim());
            if (!parsed || !parsed.code || parsed.code === visitorCurrency) continue;
            if (parsed.amount === 0) {
                el.textContent = formatPrice(0, visitorCurrency);
                continue;
            }
            var converted = convert(parsed.amount, parsed.code, visitorCurrency, rates);
            if (converted !== null) {
                var original = parsed.symbol + parsed.amount;
                el.textContent = formatPrice(converted, visitorCurrency);
                el.setAttribute('title', 'Originally ' + original + ' ' + parsed.code);
            }
        }

        // 2. <span class="currency">$</span> + <span class="amount">49</span>
        var amountContainers = document.querySelectorAll('.pricing-amount');
        for (var j = 0; j < amountContainers.length; j++) {
            var container = amountContainers[j];
            var currencyEl = container.querySelector('.currency');
            var amountEl = container.querySelector('.amount');
            if (!currencyEl || !amountEl) continue;

            var sym = currencyEl.textContent.trim();
            var baseCode = SYMBOL_TO_CODE[sym];
            var amt = parseFloat(amountEl.textContent.trim().replace(/,/g, ''));
            if (!baseCode || baseCode === visitorCurrency || isNaN(amt)) continue;

            if (amt === 0) {
                try {
                    var parts = new Intl.NumberFormat(undefined, { style: 'currency', currency: visitorCurrency, minimumFractionDigits: 0, maximumFractionDigits: 0 }).formatToParts(0);
                    var symPart = '';
                    for (var p = 0; p < parts.length; p++) {
                        if (parts[p].type === 'currency' || parts[p].type === 'literal') symPart += parts[p].value;
                    }
                    currencyEl.textContent = symPart.trim() || visitorCurrency;
                } catch (e) {
                    currencyEl.textContent = visitorCurrency;
                }
                continue;
            }

            var conv = convert(amt, baseCode, visitorCurrency, rates);
            if (conv !== null) {
                amountEl.textContent = Math.round(conv).toLocaleString();
                amountEl.setAttribute('title', 'Originally ' + sym + amt + ' ' + baseCode);
                try {
                    var currParts = new Intl.NumberFormat(undefined, { style: 'currency', currency: visitorCurrency, minimumFractionDigits: 0 }).formatToParts(0);
                    var currSymbol = '';
                    for (var cp = 0; cp < currParts.length; cp++) {
                        if (currParts[cp].type === 'currency') currSymbol = currParts[cp].value;
                    }
                    currencyEl.textContent = currSymbol || visitorCurrency;
                } catch (e) {
                    currencyEl.textContent = visitorCurrency;
                }
            }
        }

        // 3. .price-note elements with embedded prices
        var noteEls = document.querySelectorAll('.price-note');
        for (var k = 0; k < noteEls.length; k++) {
            replaceInlinePrice(noteEls[k], visitorCurrency, rates);
        }

        // 4. Buttons with price text (e.g., "Buy Now - £39")
        var btnEls = document.querySelectorAll('.btn');
        for (var b = 0; b < btnEls.length; b++) {
            replaceInlinePrice(btnEls[b], visitorCurrency, rates);
        }

        // 5. Badge elements with prices (e.g., "Save £27")
        var badgeEls = document.querySelectorAll('.bundle-badge, .product-badge');
        for (var d = 0; d < badgeEls.length; d++) {
            replaceInlinePrice(badgeEls[d], visitorCurrency, rates);
        }

        // 6. CTA guarantee text
        var guEls = document.querySelectorAll('.cta-guarantee');
        for (var g = 0; g < guEls.length; g++) {
            replaceInlinePrice(guEls[g], visitorCurrency, rates);
        }
    }

    function replaceInlinePrice(el, visitorCurrency, rates) {
        var text = el.textContent;
        var regex = /([£$€])([\d,]+(?:\.\d+)?)/g;
        var match;
        var replacements = [];

        while ((match = regex.exec(text)) !== null) {
            var sym = match[1];
            var amt = parseFloat(match[2].replace(/,/g, ''));
            var code = SYMBOL_TO_CODE[sym];
            if (!code || code === visitorCurrency || isNaN(amt)) continue;
            if (amt === 0) continue;

            var conv = convert(amt, code, visitorCurrency, rates);
            if (conv !== null) {
                replacements.push({
                    original: match[0],
                    replacement: formatPrice(conv, visitorCurrency)
                });
            }
        }

        if (replacements.length > 0) {
            var newText = text;
            for (var r = 0; r < replacements.length; r++) {
                newText = newText.replace(replacements[r].original, replacements[r].replacement);
            }
            el.textContent = newText;
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', detectAndConvert);
    } else {
        detectAndConvert();
    }
})();
