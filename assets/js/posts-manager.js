/**
 * Posts Manager JavaScript
 * Handles post listing with internal links editing and AJAX operations
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

                data.data.posts.forEach(function (post) {
                    var tr = document.createElement('tr');
                    var linksHtml = post.links.map(function (link, index) {
                        var typeClass = link.type === 'product' ? 'product' : link.type === 'product_cat' ? 'category' : link.type === 'external' ? 'external' : 'post';
                        var typeLabel = link.type === 'product' ? manager.strings.product :
                            link.type === 'product_cat' ? manager.strings.category :
                                link.type === 'external' ? manager.strings.external :
                                    manager.strings.post;
                        var bgColor = link.type === 'product' ? '#e3f2fd' : link.type === 'product_cat' ? '#fff3e0' : link.type === 'external' ? '#ffebee' : '#f3e5f5';
                        var badgeColor = link.type === 'product' ? '#1976d2' : link.type === 'product_cat' ? '#f57c00' : link.type === 'external' ? '#d32f2f' : '#7b1fa2';

                        return '<div style="margin-bottom: 10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: ' + bgColor + ';">' +
                            '<div style="margin-bottom: 5px; font-size: 11px;">' +
                            '<span style="background: ' + badgeColor + '; color: #fff; padding: 2px 8px; border-radius: 3px; font-weight: 600; margin-right: 5px;">' + typeLabel + '</span>' +
                            '<span style="color: #666;">' + (link.text || '(No anchor text)') + '</span>' +
                            '</div>' +
                            '<input type="text" class="tsh-edit-link-url" data-post-id="' + post.id + '" data-link-index="' + index + '" data-old-url="' + escapeHtml(link.url) + '" value="' + escapeHtml(link.url) + '" style="width: 100%; padding: 5px; font-size: 12px; border: 1px solid #ccc;" placeholder="URL" />' +
                            '<div style="margin-top: 5px;"><a href="' + escapeHtml(link.url) + '" target="_blank" class="button button-small">' + manager.strings.viewLink + '</a></div>' +
                            '</div>';
                    }).join('');

                    tr.innerHTML = '<td style="padding:6px;">' + post.id + '</td>' +
                        '<td style="padding:6px;"><strong>' + escapeHtml(post.title) + '</strong></td>' +
                        '<td style="padding:6px;"><div style="max-height: 300px; overflow-y: auto;">' + linksHtml + '</div></td>' +
                        '<td style="padding:6px;">' + post.date + '</td>' +
                        '<td style="padding:6px; white-space: nowrap;"><div style="display: flex; gap: 5px; flex-wrap: wrap;">' +
                        '<button class="tsh-save-links-btn button button-primary" data-id="' + post.id + '">' + manager.strings.saveLinks + '</button>' +
                        '<a href="' + escapeHtml(post.permalink) + '" target="_blank" class="button button-small">' + manager.strings.viewPost + '</a>' +
                        '<a href="' + escapeHtml(post.edit_link) + '" target="_blank" class="button button-small">' + manager.strings.edit + '</a>' +
                        '<button class="tsh-trash-post-btn button tsh-button-link-delete" data-id="' + post.id + '">' + manager.strings.delete + '</button>' +
                        '</div></td>';
                    tbody.appendChild(tr);
                });

                attachEventHandlers();
                var totalPages = Math.ceil(data.data.total / data.data.per_page);
                renderPagination(page, totalPages);
                manager.currentPage = page;
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="5">Error: ' + err.message + '</td></tr>';
            });
    }

    /**
     * Attach event handlers to dynamic elements
     */
    function attachEventHandlers() {
        document.querySelectorAll('.tsh-save-links-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var postId = this.dataset.id;
                var linkInputs = document.querySelectorAll('.tsh-edit-link-url[data-post-id="' + postId + '"]');
                var links = [];
                linkInputs.forEach(function (input) {
                    links.push({
                        old_url: input.dataset.oldUrl,
                        new_url: input.value.trim()
                    });
                });

                var originalText = this.textContent;
                this.disabled = true;
                this.textContent = manager.strings.saving;

                var formData = new FormData();
                formData.append('action', 'update_post_links');
                formData.append('nonce', manager.nonce);
                formData.append('id', postId);
                formData.append('links', JSON.stringify(links));

                fetch(manager.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        this.textContent = data.success ? '✓ ' + manager.strings.saved : manager.strings.error;
                        if (data.success) {
                            linkInputs.forEach(function (input) {
                                input.dataset.oldUrl = input.value.trim();
                            });
                            setTimeout(function () {
                                loadPage(manager.currentPage, manager.lastSearch);
                            }, 1000);
                        }
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

        document.querySelectorAll('.tsh-trash-post-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm(manager.strings.confirmDelete)) return;
                var id = this.dataset.id;

                var formData = new FormData();
                formData.append('action', 'trash_post');
                formData.append('nonce', manager.nonce);
                formData.append('id', id);

                fetch(manager.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.data.message || 'Post deleted');
                            loadPage(manager.currentPage, manager.lastSearch);
                        } else {
                            alert(data.data.message || 'Error deleting post');
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
        var pagination = document.getElementById('tsh-posts-pagination');
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
        // Search button
        document.getElementById('tsh-posts-search-btn').addEventListener('click', function () {
            loadPage(1, document.getElementById('tsh-posts-search-input').value);
        });

        // Clear search button
        document.getElementById('tsh-posts-clear-search-btn').addEventListener('click', function () {
            document.getElementById('tsh-posts-search-input').value = '';
            loadPage(1, '');
        });

        // Search on Enter key
        document.getElementById('tsh-posts-search-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadPage(1, this.value);
            }
        });

        // Load first page
        loadPage(1, '');
    });

})(jQuery);
