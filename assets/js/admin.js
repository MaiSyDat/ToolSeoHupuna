(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Elements
        var $scanButton = $('#tool-seo-hupuna-scan-button');
        var $progressWrap = $('#tool-seo-hupuna-progress-wrap');
        var $progressFill = $('#tool-seo-hupuna-progress-fill');
        var $progressText = $('#tool-seo-hupuna-progress-text');
        var $results = $('#tool-seo-hupuna-scan-results');
        var $resultsContent = $('#tool-seo-hupuna-results-content');
        
        // State
        var allResults = [];
        var isScanning = false;
        var scanQueue = [];
        var totalStepsInitial = 0;
        var maxRetries = 3;
        var retryCount = 0;
        
        // Pagination & Tabs
        var currentTab = 'grouped';
        var currentPage = 1;
        var itemsPerPage = 20;
        
        // --- Core Scanning Logic ---
        
        /**
         * Build scan queue from available post types.
         *
         * @return {Array} Queue of scan tasks.
         */
        function buildScanQueue() {
            var queue = [];
            
            // 1. Post Types
            var postTypes = toolSeoHupuna.postTypes; 
            if (!Array.isArray(postTypes)) {
                postTypes = Object.values(postTypes);
            }
            
            $.each(postTypes, function(i, type) {
                queue.push({
                    step: 'post_type',
                    sub_step: type,
                    page: 1,
                    label: toolSeoHupuna.strings.scanningPostType.replace('%s', type)
                });
            });
            
            // 2. Comments
            queue.push({ 
                step: 'comment', 
                page: 1, 
                label: toolSeoHupuna.strings.scanningComments 
            });
            
            // 3. Options
            queue.push({ 
                step: 'option', 
                page: 1, 
                label: toolSeoHupuna.strings.scanningOptions 
            });
            
            return queue;
        }
        
        /**
         * Handle scan button click.
         */
        $scanButton.on('click', function() {
            if (isScanning) {
                return;
            }
            
            isScanning = true;
            allResults = [];
            retryCount = 0;
            $scanButton.prop('disabled', true).html('<span class="dashicons dashicons-search"></span> ' + toolSeoHupuna.strings.scanning);
            $results.hide();
            $progressWrap.show();
            
            scanQueue = buildScanQueue();
            totalStepsInitial = scanQueue.length;
            
            processQueue();
        });
        
        /**
         * Process scan queue recursively with error handling.
         */
        function processQueue() {
            if (scanQueue.length === 0) {
                finishScan();
                return;
            }
            
            var currentTask = scanQueue[0];
            var progressPercent = 100 - ((scanQueue.length / totalStepsInitial) * 100);
            if (progressPercent < 2) {
                progressPercent = 2;
            }
            
            var progressText = currentTask.label + ' (' + toolSeoHupuna.strings.page + ' ' + currentTask.page + ')';
            updateProgress(progressPercent, progressText);
            
            $.ajax({
                url: toolSeoHupuna.ajaxUrl,
                type: 'POST',
                timeout: 60000, // 60 second timeout
                data: {
                    action: 'tool_seo_hupuna_scan_batch',
                    nonce: toolSeoHupuna.nonce,
                    step: currentTask.step,
                    sub_step: currentTask.sub_step || '',
                    page: currentTask.page
                },
                success: function(response) {
                    retryCount = 0; // Reset retry count on success
                    
                    if (response.success) {
                        if (response.data.results && response.data.results.length > 0) {
                            allResults = allResults.concat(response.data.results);
                        }
                        
                        if (response.data.done) {
                            scanQueue.shift(); // Task complete
                        } else {
                            scanQueue[0].page++; // Next page
                        }
                        
                        // Use setTimeout to prevent browser hang
                        setTimeout(function() {
                            processQueue();
                        }, 10);
                        
                    } else {
                        handleError(response.data.message || toolSeoHupuna.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    // Retry logic for transient errors
                    if (retryCount < maxRetries && (status === 'timeout' || xhr.status === 0)) {
                        retryCount++;
                        setTimeout(function() {
                            processQueue();
                        }, 1000 * retryCount); // Exponential backoff
                        return;
                    }
                    
                    var errorMsg = toolSeoHupuna.strings.serverError.replace('%s', error || status);
                    handleError(errorMsg);
                }
            });
        }
        
        /**
         * Handle scan errors.
         *
         * @param {string} msg Error message.
         */
        function handleError(msg) {
            console.error('Scan Error:', msg);
            alert(toolSeoHupuna.strings.error + ': ' + msg);
            isScanning = false;
            $scanButton.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + toolSeoHupuna.strings.startScan);
            $progressText.text(toolSeoHupuna.strings.errorEncountered);
        }
        
        /**
         * Update progress bar and text.
         *
         * @param {number} percent Progress percentage.
         * @param {string} text Progress text.
         */
        function updateProgress(percent, text) {
            $progressFill.css('width', percent + '%');
            $progressText.text(text);
        }
        
        /**
         * Finish scan and display results.
         */
        function finishScan() {
            isScanning = false;
            updateProgress(100, toolSeoHupuna.strings.scanCompleted);
            $scanButton.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + toolSeoHupuna.strings.startScan);
            displayResults(allResults);
        }

        // --- UI & Display Logic ---
        
        /**
         * Handle tab button clicks.
         */
        $('.tsh-tab').on('click', function() {
            $('.tsh-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            currentTab = $(this).data('tab');
            currentPage = 1;
            renderCurrentPage();
        });
        
        /**
         * Display scan results.
         *
         * @param {Array} data Results data.
         */
        function displayResults(data) {
            window.rawResults = data;
            
            // Group by URL
            window.groupedResults = {};
            $.each(data, function(i, item) {
                if (!window.groupedResults[item.url]) {
                    window.groupedResults[item.url] = { url: item.url, occurrences: [] };
                }
                window.groupedResults[item.url].occurrences.push(item);
            });
            
            $('#total-links').text(data.length);
            $('#unique-links').text(Object.keys(window.groupedResults).length);
            
            $results.show();
            renderCurrentPage();
        }
        
        /**
         * Render current page of results.
         */
        function renderCurrentPage() {
            var html = '';
            var list = [];
            
            if (currentTab === 'grouped') {
                list = Object.values(window.groupedResults);
            } else {
                list = window.rawResults;
            }
            
            if (list.length === 0) {
                $resultsContent.html('<div class="notice notice-info"><p>' + escapeHtml(toolSeoHupuna.strings.noLinksFound) + '</p></div>');
                return;
            }
            
            // Client-side Pagination
            var totalItems = list.length;
            var totalPages = Math.ceil(totalItems / itemsPerPage);
            var start = (currentPage - 1) * itemsPerPage;
            var end = start + itemsPerPage;
            var pageItems = list.slice(start, end);
            
            // Render List
            if (currentTab === 'grouped') {
                $.each(pageItems, function(i, group) {
                    html += '<div class="tsh-panel" style="margin-bottom: 20px;">';
                    html += '<h3>' + escapeHtml(group.url) + ' <span class="description">(' + group.occurrences.length + ' ' + (group.occurrences.length === 1 ? toolSeoHupuna.strings.occurrence : toolSeoHupuna.strings.occurrences) + ')</span></h3>';
                    html += '<table class="wp-list-table widefat fixed striped tsh-table">';
                    html += '<thead><tr><th>Type</th><th>Title</th><th>Location</th><th>Tag</th><th style="width: 150px;">Actions</th></tr></thead><tbody>';
                    $.each(group.occurrences, function(j, item) {
                        html += renderItemRow(item);
                    });
                    html += '</tbody></table></div>';
                });
            } else {
                html += '<table class="wp-list-table widefat fixed striped tsh-table">';
                html += '<thead><tr><th>Type</th><th>Title</th><th>Location</th><th>Tag</th><th style="width: 150px;">Actions</th></tr></thead><tbody>';
                $.each(pageItems, function(i, item) {
                    html += renderItemRow(item);
                });
                html += '</tbody></table>';
            }
            
            // Render Pagination
            if (totalPages > 1) {
                html += '<div style="margin-top: 20px; text-align: center;">';
                if (currentPage > 1) {
                    html += '<button class="button" onclick="window.changeToolSeoHupunaPage(' + (currentPage - 1) + ')">' + escapeHtml(toolSeoHupuna.strings.prev) + '</button> ';
                }
                html += '<span>' + toolSeoHupuna.strings.page + ' ' + currentPage + ' ' + toolSeoHupuna.strings.of + ' ' + totalPages + '</span>';
                if (currentPage < totalPages) {
                    html += ' <button class="button" onclick="window.changeToolSeoHupunaPage(' + (currentPage + 1) + ')">' + escapeHtml(toolSeoHupuna.strings.next) + '</button>';
                }
                html += '</div>';
            }
            
            $resultsContent.html(html);
        }
        
        /**
         * Render individual result item row.
         *
         * @param {Object} item Result item.
         * @return {string} HTML string.
         */
        function renderItemRow(item) {
            // Security warning styling
            var riskStyle = '';
            var riskBadge = '';
            
            if (item.is_safe === false) {
                riskStyle = 'style="background-color: #ffe6e6;"';
                riskBadge = '<span class="badge" style="background: #d63638; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px; margin-left: 5px; font-weight: normal;">⚠️ ' + escapeHtml(item.risk_type || toolSeoHupuna.strings.unsafe) + '</span>';
            }
            
            var actionsHtml = '';
            if (item.edit_url || item.view_url) {
                actionsHtml = '<div style="display: flex; gap: 5px; flex-wrap: wrap;">';
                if (item.edit_url) {
                    actionsHtml += '<a href="' + escapeHtml(item.edit_url) + '" target="_blank" class="button button-small">' + escapeHtml(toolSeoHupuna.strings.edit) + '</a>';
                }
                if (item.view_url) {
                    actionsHtml += '<a href="' + escapeHtml(item.view_url) + '" target="_blank" class="button button-small">' + escapeHtml(toolSeoHupuna.strings.view) + '</a>';
                }
                actionsHtml += '</div>';
            }
            
            return '<tr ' + riskStyle + '>' +
                   '<td style="padding:6px;"><code>' + escapeHtml(item.type) + '</code></td>' +
                   '<td style="padding:6px;"><strong>' + escapeHtml(item.title) + '</strong>' + riskBadge + '</td>' +
                   '<td style="padding:6px;">' + escapeHtml(item.location) + '</td>' +
                   '<td style="padding:6px;"><code>&lt;' + escapeHtml(item.tag) + '&gt;</code></td>' +
                   '<td style="padding:6px; white-space: nowrap;">' + actionsHtml + '</td>' +
                   '</tr>';
        }
        
        /**
         * Global function to change page.
         *
         * @param {number} page Page number.
         */
        window.changeToolSeoHupunaPage = function(page) {
            currentPage = page;
            renderCurrentPage();
        };
        
        /**
         * Escape HTML to prevent XSS.
         *
         * @param {string} text Text to escape.
         * @return {string} Escaped text.
         */
        function escapeHtml(text) {
            if (!text) {
                return '';
            }
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { 
                return map[m]; 
            });
        }
    });
})(jQuery);
