/**
 * Products Manager JavaScript
 * Handles product listing, editing, and AJAX operations
 */

(function ($) {
    'use strict';

    // Global manager object will be populated by wp_localize_script
    var manager = window.hupunaProductsManager || {};

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return (text || '').replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    /**
     * Load products page
     */
    function loadPage(page, search) {
        manager.lastSearch = search;
        var container = document.getElementById('tsh-products-container');
        container.innerHTML = '<div class="tsh-products-loading">' + manager.strings.loading + '</div>';

        var formData = new FormData();
        formData.append('action', 'get_product_prices');
        formData.append('nonce', manager.nonce);
        formData.append('page', page);
        formData.append('search', search);

        fetch(manager.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                container.innerHTML = '';
                if (!data.success || data.data.products.length === 0) {
                    container.innerHTML = '<div class="tsh-products-empty">' + manager.strings.noProducts + '</div>';
                    renderPagination(page, 0);
                    return;
                }

                data.data.products.forEach(function (p) {
                    var card = document.createElement('div');
                    card.className = 'tsh-product-card';

                    if (p.type === manager.strings.variant) {
                        // Variable product with variants
                        var headerHTML = '<div class="tsh-product-header">' +
                            '<div class="tsh-product-name"><textarea class="tsh-edit-name" data-id="' + p.id + '">' + escapeHtml(p.name) + '</textarea></div>' +
                            '<div class="tsh-product-actions">' +
                            '<button class="tsh-delete-btn button tsh-button-link-delete" data-id="' + p.id + '">' + manager.strings.delete + '</button>' +
                            '<button class="tsh-save-all-btn button button-primary" data-id="' + p.id + '">' + manager.strings.saveAll + '</button>' +
                            '<a href="' + escapeHtml(p.permalink || '/?p=' + p.id) + '" target="_blank" class="button button-small">' + manager.strings.view + '</a>' +
                            '</div></div>';

                        var tableHTML = '<table class="tsh-variant-table"><thead><tr>' +
                            '<th>' + manager.strings.variant + '</th>' +
                            '<th style="width: 150px;">' + manager.strings.regularPrice + '</th>' +
                            '<th style="width: 150px;">' + manager.strings.salePrice + '</th>' +
                            '</tr></thead><tbody>';

                        p.variants.forEach(function (v) {
                            tableHTML += '<tr>' +
                                '<td><strong>' + escapeHtml(v.name) + '</strong></td>' +
                                '<td><input type="number" value="' + (v.regular_price || '') + '" data-id="' + v.id + '" data-type="regular" placeholder="' + manager.strings.regularPrice + '" style="width:100%;"/></td>' +
                                '<td><input type="number" value="' + (v.sale_price || '') + '" data-id="' + v.id + '" data-type="sale" placeholder="' + manager.strings.salePrice + '" style="width:100%;"/></td>' +
                                '</tr>';
                        });

                        tableHTML += '</tbody></table>';
                        card.innerHTML = headerHTML + tableHTML;
                    } else {
                        // Simple product
                        var headerHTML = '<div class="tsh-product-header">' +
                            '<div class="tsh-product-name"><textarea class="tsh-edit-name" data-id="' + p.id + '">' + escapeHtml(p.name) + '</textarea></div>' +
                            '<div class="tsh-product-actions">' +
                            '<button class="tsh-delete-btn button tsh-button-link-delete" data-id="' + p.id + '">' + manager.strings.delete + '</button>' +
                            '<button class="tsh-save-btn button button-primary" data-id="' + p.id + '">' + manager.strings.save + '</button>' +
                            '<a href="' + escapeHtml(p.permalink || '/?p=' + p.id) + '" target="_blank" class="button button-small">' + manager.strings.view + '</a>' +
                            '</div></div>';

                        var tableHTML = '<table class="tsh-variant-table"><tbody><tr>' +
                            '<td><strong>' + manager.strings.regularPrice + '</strong></td>' +
                            '<td><input type="number" value="' + (p.regular_price || '') + '" data-id="' + p.id + '" data-type="regular" style="width:100%;"/></td>' +
                            '<td><strong>' + manager.strings.salePrice + '</strong></td>' +
                            '<td><input type="number" value="' + (p.sale_price || '') + '" data-id="' + p.id + '" data-type="sale" style="width:100%;"/></td>' +
                            '</tr></tbody></table>';

                        card.innerHTML = headerHTML + tableHTML;
                    }

                    container.appendChild(card);
                });

                attachEventHandlers();
                var totalPages = Math.ceil(data.data.total / data.data.per_page);
                renderPagination(page, totalPages);
                manager.currentPage = page;
            })
            .catch(err => {
                container.innerHTML = '<div class="tsh-products-empty">Error: ' + err.message + '</div>';
            });
    }

    /**
     * Attach event handlers to dynamic elements
     */
    function attachEventHandlers() {
        // Save single price
        document.querySelectorAll('.tsh-save-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var productId = this.dataset.id;
                var card = this.closest('.tsh-product-card');
                var regularInput = card.querySelector('input[data-type="regular"]');
                var saleInput = card.querySelector('input[data-type="sale"]');

                if (!regularInput) return;

                var regular_price = regularInput.value || '';
                var sale_price = saleInput ? saleInput.value || '' : '';

                var originalText = this.textContent;
                this.disabled = true;
                this.textContent = manager.strings.saving;

                var formData = new FormData();
                formData.append('action', 'update_product_price');
                formData.append('nonce', manager.nonce);
                formData.append('id', productId);
                formData.append('regular_price', regular_price);
                formData.append('sale_price', sale_price);

                fetch(manager.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        this.textContent = data.success ? '✓ ' + manager.strings.saved : manager.strings.error;
                        setTimeout(function () {
                            this.textContent = originalText;
                            this.disabled = false;
                        }.bind(this), 2000);
                    })
                    .catch(() => {
                        this.textContent = manager.strings.error;
                        setTimeout(function () {
                            this.textContent = originalText;
                            this.disabled = false;
                        }.bind(this), 2000);
                    });
            });
        });

        // Save all variants
        document.querySelectorAll('.tsh-save-all-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var productId = this.dataset.id;
                var card = this.closest('.tsh-product-card');
                var regularInputs = card.querySelectorAll('input[data-type="regular"]');
                var saleInputs = card.querySelectorAll('input[data-type="sale"]');
                var updates = [];

                regularInputs.forEach(function (input, i) {
                    var id = input.dataset.id;
                    var regular_price = input.value || '';
                    var sale_price = saleInputs[i] ? saleInputs[i].value || '' : '';

                    if (id) {
                        updates.push({
                            id: id,
                            regular_price: regular_price,
                            sale_price: sale_price
                        });
                    }
                });

                var originalText = this.textContent;
                this.disabled = true;
                this.textContent = manager.strings.saving;

                var formData = new FormData();
                formData.append('action', 'update_multiple_prices');
                formData.append('nonce', manager.nonce);
                formData.append('updates', JSON.stringify(updates));

                fetch(manager.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        this.textContent = data.success ? '✓ ' + manager.strings.saved : manager.strings.error;
                        setTimeout(function () {
                            this.textContent = originalText;
                            this.disabled = false;
                        }.bind(this), 2000);
                    })
                    .catch(() => {
                        this.textContent = manager.strings.error;
                        setTimeout(function () {
                            this.textContent = originalText;
                            this.disabled = false;
                        }.bind(this), 2000);
                    });
            });
        });

        // Update product name
        document.querySelectorAll('.tsh-edit-name').forEach(function (textarea) {
            textarea.addEventListener('blur', function () {
                var id = this.dataset.id;
                var name = this.value;

                var formData = new FormData();
                formData.append('action', 'update_product_name');
                formData.append('nonce', manager.nonce);
                formData.append('id', id);
                formData.append('name', name);

                fetch(manager.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            alert('❌ ' + (data.data && data.data.message ? data.data.message : 'Error updating product name'));
                        }
                    })
                    .catch(() => {
                        alert('❌ Error updating product name');
                    });
            });
        });

        // Delete product
        document.querySelectorAll('.tsh-delete-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm(manager.strings.confirmDelete)) return;

                var id = this.dataset.id;
                var card = this.closest('.tsh-product-card');

                var formData = new FormData();
                formData.append('action', 'delete_product');
                formData.append('nonce', manager.nonce);
                formData.append('id', id);

                fetch(manager.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.data && data.data.message ? data.data.message : 'Product deleted');
                            loadPage(manager.currentPage, manager.lastSearch);
                        } else {
                            alert(data.data && data.data.message ? data.data.message : 'Error deleting product');
                        }
                    })
                    .catch(err => {
                        alert('Error: ' + err.message);
                    });
            });
        });
    }

    /**
     * Render pagination
     */
    function renderPagination(page, totalPages) {
        var pagination = document.getElementById('tsh-products-pagination');
        pagination.innerHTML = '';
        if (totalPages <= 1) return;

        function createButton(label, pageNum, disabled) {
            var btn = document.createElement('button');
            btn.textContent = label;
            btn.className = 'button';
            btn.style.margin = '0 2px';
            if (label === page.toString()) {
                btn.className += ' button-primary';
                btn.disabled = true;
            }
            btn.disabled = disabled || false;
            btn.onclick = function () { loadPage(pageNum, manager.lastSearch); };
            return btn;
        }

        if (page > 1) pagination.appendChild(createButton(manager.strings.prev, page - 1));
        for (var i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || Math.abs(i - page) <= 1) {
                pagination.appendChild(createButton(i, i, i === page));
            } else if ((i === 2 && page > 3) || (i === totalPages - 1 && page < totalPages - 2)) {
                var dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.margin = '0 5px';
                pagination.appendChild(dots);
            }
        }
        if (page < totalPages) pagination.appendChild(createButton(manager.strings.next, page + 1));
    }

    /**
     * Initialize on document ready
     */
    document.addEventListener('DOMContentLoaded', function () {
        var searchBtn = document.getElementById('tsh-products-search-btn');
        var searchInput = document.getElementById('tsh-products-search-input');
        var container = document.getElementById('tsh-products-container');

        // Only initialize if products manager elements exist
        if (!searchBtn || !searchInput || !container) {
            return;
        }

        // Search button
        searchBtn.addEventListener('click', function () {
            loadPage(1, searchInput.value);
        });

        // Search on Enter key
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadPage(1, this.value);
            }
        });

        // Load first page
        loadPage(1, '');
    });

})(jQuery);
