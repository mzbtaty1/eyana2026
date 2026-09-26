{{--
    Money fields (input.js-money): Arabic-Indic / Persian digits and the Arabic decimal
    separator (٫) become 0-9 and '.' as you type, so «١٢٫٥» is 12.5. Nothing else is
    removed -- a comma, a minus or a letter stays visible and the field is flagged, so a
    value is never silently turned into a different number (the old «12,5» -> 125).
    The server does the same (Requests\Concerns\NormalizesMoneyInput) and has the final say.
--}}
<script>
(function () {
    var MSG = 'اكتب المبلغ بالأرقام ونقطة للكسور، مثال: 1500 أو 12.5 (بدون فاصلة وبدون سالب)';
    document.querySelectorAll('input.js-money').forEach(function (el) {
        var check = function () {
            var v = el.value
                .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
                .replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
                .replace(/٫/g, '.');
            if (v !== el.value) { el.value = v; }
            var t = v.trim();
            var ok = t === '' || /^\d+(\.\d{1,2})?$/.test(t);
            el.setCustomValidity(ok ? '' : MSG);
            el.classList.toggle('is-invalid', !ok);
            var fb = el.parentNode.querySelector('.js-money-feedback');
            if (!ok && !fb) {
                fb = document.createElement('div');
                fb.className = 'invalid-feedback js-money-feedback';
                fb.textContent = MSG;
                el.insertAdjacentElement('afterend', fb);
            } else if (ok && fb) {
                fb.remove();
            }
        };
        el.addEventListener('input', check);
        if (el.value.trim() !== '') { check(); }
    });
})();
</script>
