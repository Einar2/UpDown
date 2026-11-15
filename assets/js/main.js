(function () {
    'use strict';

    // ============ 18+ ============
    var ageModal = document.getElementById('ageModal');
    if (ageModal) {
        var confirmed = localStorage.getItem('age_confirmed');
        if (!confirmed) {
            ageModal.hidden = false;
        }
        var yes = document.getElementById('ageYes');
        var no = document.getElementById('ageNo');
        if (yes) yes.addEventListener('click', function () {
            localStorage.setItem('age_confirmed', '1');
            ageModal.hidden = true;
        });
        if (no) no.addEventListener('click', function () {
            location.href = 'https://ya.ru';
        });
    }

    // ============ Галерея на странице товара ============
    var mainImg = document.getElementById('productMain');
    var thumbs = document.querySelectorAll('.js-thumb');
    if (mainImg && thumbs.length) {
        thumbs.forEach(function (t) {
            t.addEventListener('click', function () {
                mainImg.src = t.dataset.src;
                thumbs.forEach(function (x) { x.classList.remove('is-active'); });
                t.classList.add('is-active');
            });
        });
    }

    // ============ Радио-кнопки в оформлении заказа ============
    document.querySelectorAll('.field--radio input[type="radio"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var name = r.name;
            document.querySelectorAll('.field--radio input[name="' + name + '"]').forEach(function (x) {
                x.closest('.field--radio').classList.toggle('is-checked', x.checked);
            });
        });
        if (r.checked) r.closest('.field--radio').classList.add('is-checked');
    });
})();