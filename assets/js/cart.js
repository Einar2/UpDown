(function () {
    'use strict';

    var BASE = window.BASE_URL || '';
    var counterEl = document.getElementById('cartCount');

    function setCounter(n) {
        if (!counterEl) return;
        counterEl.textContent = n;
        counterEl.dataset.count = n;
    }

    function post(action, id, qty) {
        var data = new FormData();
        data.append('action', action);
        data.append('id', id);
        if (qty !== undefined) data.append('qty', qty);

        return fetch(BASE + '/cart-action.php', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
        }).then(function (r) { return r.json(); });
    }

    // ============ Добавить в корзину ============
    document.querySelectorAll('.js-add-to-cart').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            if (!id) return;

            btn.disabled = true;
            post('add', id, 1).then(function (res) {
                if (res.ok) {
                    setCounter(res.count);
                    btn.textContent = 'Добавлено';
                    btn.disabled = false;              // разблокировали сразу

                    setTimeout(function () {
                        btn.textContent = 'В корзину'; // только текст вернули позже
                    }, 1200);
                } else {
                    btn.disabled = false;
                }
            }).catch(function () {
                btn.disabled = false;
            });
        });
    });

    // ============ Корзина: +, −, удалить ============
    var cartList = document.querySelector('.cart-list');
    if (cartList) {
        cartList.addEventListener('click', function (e) {
            var item = e.target.closest('.cart-item');
            if (!item) return;
            var id = item.dataset.id;
            var input = item.querySelector('.js-qty');
            var qty = parseInt(input.value, 10) || 1;

            if (e.target.closest('.js-qty-plus')) {
                qty += 1;
                updateItem(id, qty, item);
            } else if (e.target.closest('.js-qty-minus')) {
                qty = Math.max(1, qty - 1);
                updateItem(id, qty, item);
            } else if (e.target.closest('.js-remove')) {
                post('remove', id).then(function (res) {
                    if (!res.ok) return;

                    // Если корзина стала пустой — сразу перезагружаем
                    if (res.count === 0) {
                        location.reload();
                        return;
                    }

                    item.remove();
                    setCounter(res.count);

                    var totalEl = document.getElementById('cartTotal');
                    if (totalEl && res.total_formatted) {
                        totalEl.textContent = res.total_formatted;
                    }
                }).catch(function (err) {
                    console.error('Ошибка удаления:', err);
                    location.reload();
                });
            }
        });
    }

    function updateItem(id, qty, item) {
        post('update', id, qty).then(function (res) {
            if (!res.ok) return;
            var input = item.querySelector('.js-qty');
            input.value = qty;

            if (res.item_sum_formatted) {
                var priceEl = item.querySelector('.cart-item__price-value');
                if (priceEl) priceEl.textContent = res.item_sum_formatted;
            }
            if (res.total_formatted) {
                var totalEl = document.getElementById('cartTotal');
                if (totalEl) totalEl.textContent = res.total_formatted;
            }
            setCounter(res.count);
        });
    }
})();