/**
 * Posts Manager JavaScript
 * Handles post listing with internal links viewing
 */

(function ($) {
    'use strict';

    // Global manager object will be populated by wp_localize_script
    var manager = window.hupunaPostsManager || {};

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return (text || '').replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    /**
     * Load posts page
     */
    function loadPage(page, search) {
        manager.lastSearch = search;
        var tbody = document.getElementById('tsh-posts-table-body');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5">' + manager.strings.loading + '</td></tr>';

        var formData = new FormData();
        formData.append('action', 'get_posts_with_links');
        formData.append('nonce', manager.nonce);
        formData.append('page', page);
        formData.append('search', search);

        fetch(manager.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                tbody.innerHTML = '';
                if (!data.success || data.data.posts.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5">' + manager.strings.noPosts + '</td></tr>';
                    renderPagination(page, 0);
                    return;
                }

                data.data.posts.forEach(function (item) {
                    var tr = document.createElement('tr');
                    var linksHtml = item.links.map(function (link) {
                        var typeLabel = link.type === 'product' ? manager.strings.product :
                            link.type === 'product_cat' ? manager.strings.category :
                                link.type === 'external' ? manager.strings.external :
                                    manager.strings.post;
                        var bgColor = link.type === 'product' ? '#e3f2fd' : link.type === 'product_cat' ? '#fff3e0' : link.type === 'external' ? '#ffebee' : '#f3e5f5';
                        var badgeColor = link.type === 'product' ? '#1976d2' : link.type === 'product_cat' ? '#f57c00' : link.type === 'external' ? '#d32f2f' : '#7b1fa2';

                        // Badge for location (Excerpt vs Content)
                        var locBadge = link.in_excerpt ? '<span style="background: #607d8b; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">' + (manager.strings.excerpt || 'Excerpt') + '</span>' : '';

                        return '<div style="margin-bottom: 8px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: ' + bgColor + ';">' +
                            '<div style="margin-bottom: 5px; font-size: 11px;">' +
                            '<span style="background: ' + badgeColor + '; color: #fff; padding: 2px 8px; border-radius: 3px; font-weight: 600; margin-right: 5px;">' + typeLabel + '</span>' +
                            '<span style="color: #666;">' + (link.text || manager.strings.noAnchor) + '</span>' +
                            locBadge +
                            '</div>' +
                            '<div style="word-break: break-all; font-size: 12px; color: #1976d2;"><a href="' + escapeHtml(link.url) + '" target="_blank">' + escapeHtml(link.url) + '</a></div>' +
                            '</div>';
                    }).join('');

                    // Display source badge in Title column
                    var sourceLabel = item.source === 'term' ? '<span style="color:#666; font-size:10px; display:block;">[' + (item.sub_type === 'product_cat' ? manager.strings.productCategory || 'Product Category' : manager.strings.newsCategory || 'Category') + ']</span>' :
                        (item.sub_type === 'product' ? '<span style="color:#666; font-size:10px; display:block;">[' + manager.strings.product_singular || 'Product' + ']</span>' : '');

                    tr.innerHTML = '<td style="padding:10px;">' + item.id + '</td>' +
                        '<td style="padding:10px;"><strong>' + escapeHtml(item.title) + '</strong>' + sourceLabel + '</td>' +
                        '<td style="padding:10px;"><div style="max-height: 300px; overflow-y: auto;">' + linksHtml + '</div></td>' +
                        '<td style="padding:10px;">' + item.date + '</td>' +
                        '<td style="padding:10px; white-space: nowrap;"><div style="display: flex; gap: 5px;">' +
                        '<a href="' + escapeHtml(item.permalink) + '" target="_blank" class="button button-primary">' + manager.strings.view + '</a>' +
                        '</div></td>';
                    tbody.appendChild(tr);
                });

                var totalPages = Math.ceil(data.data.total / data.data.per_page);
                renderPagination(page, totalPages);
                manager.currentPage = page;
            })
            .catch(err => {
                if (tbody) tbody.innerHTML = '<tr><td colspan="5">Error: ' + err.message + '</td></tr>';
            });
    }

    /**
     * Render pagination
     */
    function renderPagination(page, totalPages) {
        var pagination = document.getElementById('tsh-posts-pagination');
        if (!pagination) return;
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
        var searchBtn = document.getElementById('tsh-posts-search-btn');
        var clearBtn = document.getElementById('tsh-posts-clear-search-btn');
        var searchInput = document.getElementById('tsh-posts-search-input');
        var tableBody = document.getElementById('tsh-posts-table-body');

        if (!searchBtn || !clearBtn || !searchInput || !tableBody) return;

        searchBtn.addEventListener('click', function () {
            loadPage(1, searchInput.value);
        });

        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            loadPage(1, '');
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadPage(1, this.value);
            }
        });

        loadPage(1, '');
    });

})(jQuery);
