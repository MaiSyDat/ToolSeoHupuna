(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Elements
        var $scanButton = $('#hupuna-scan-button');
        var $progressWrap = $('#hupuna-progress-wrap');
        var $progressFill = $('.hupuna-progress-fill');
        var $progressText = $('#hupuna-progress-text');
        var $results = $('#hupuna-scan-results');
        var $resultsContent = $('#hupuna-results-content');
        
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
            var postTypes = hupunaEls.postTypes; 
            if (!Array.isArray(postTypes)) {
                postTypes = Object.values(postTypes);
            }
            
            $.each(postTypes, function(i, type) {
                queue.push({
                    step: 'post_type',
                    sub_step: type,
                    page: 1,
                    label: hupunaEls.strings.scanningPostType.replace('%s', type)
                });
            });
            
            // 2. Comments
            queue.push({ 
                step: 'comment', 
                page: 1, 
                label: hupunaEls.strings.scanningComments 
            });
            
            // 3. Options
            queue.push({ 
                step: 'option', 
                page: 1, 
                label: hupunaEls.strings.scanningOptions 
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
            $scanButton.prop('disabled', true).html('<span class="dashicons dashicons-search"></span> ' + hupunaEls.strings.scanning);
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
            
            var progressText = currentTask.label + ' (' + hupunaEls.strings.page + ' ' + currentTask.page + ')';
            updateProgress(progressPercent, progressText);
            
            $.ajax({
                url: hupunaEls.ajaxUrl,
                type: 'POST',
                timeout: 60000, // 60 second timeout
                data: {
                    action: 'hupuna_scan_batch',
                    nonce: hupunaEls.nonce,
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
                        handleError(response.data.message || hupunaEls.strings.error);
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
                    
                    var errorMsg = hupunaEls.strings.serverError.replace('%s', error || status);
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
            alert(hupunaEls.strings.error + ': ' + msg);
            isScanning = false;
            $scanButton.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + hupunaEls.strings.startScan);
            $progressText.text(hupunaEls.strings.errorEncountered);
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
            updateProgress(100, hupunaEls.strings.scanCompleted);
            $scanButton.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + hupunaEls.strings.startScan);
            displayResults(allResults);
        }

        // --- UI & Display Logic ---
        
        /**
         * Handle tab button clicks.
         */
        $('.tab-button').on('click', function() {
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
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
                $resultsContent.html('<div class="hupuna-no-results">' + escapeHtml(hupunaEls.strings.noLinksFound) + '</div>');
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
                    html += '<div class="hupuna-link-group">';
                    html += '<div class="hupuna-link-group-header"><strong>' + escapeHtml(group.url) + '</strong> <span class="hupuna-link-count">' + group.occurrences.length + '</span></div>';
                    $.each(group.occurrences, function(j, item) {
                        html += renderItemRow(item);
                    });
                    html += '</div>';
                });
            } else {
                $.each(pageItems, function(i, item) {
                    html += renderItemRow(item);
                });
            }
            
            // Render Pagination
            if (totalPages > 1) {
                html += '<div class="hupuna-pagination">';
                if (currentPage > 1) {
                    html += '<button class="button" onclick="window.changeHupunaPage(' + (currentPage - 1) + ')">' + escapeHtml(hupunaEls.strings.prev) + '</button>';
                }
                html += '<span>' + hupunaEls.strings.page + ' ' + currentPage + ' ' + hupunaEls.strings.of + ' ' + totalPages + '</span>';
                if (currentPage < totalPages) {
                    html += '<button class="button" onclick="window.changeHupunaPage(' + (currentPage + 1) + ')">' + escapeHtml(hupunaEls.strings.next) + '</button>';
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
            var locationText = escapeHtml(hupunaEls.strings.location) + ' ' + escapeHtml(item.location);
            var tagText = escapeHtml(hupunaEls.strings.tag) + ' &lt;' + escapeHtml(item.tag) + '&gt;';
            
            return '<div class="hupuna-link-item">' +
                   '<span class="hupuna-link-item-type ' + escapeHtml(item.type) + '">' + escapeHtml(item.type) + '</span> ' +
                   '<div class="info">' +
                       '<div class="title">' + escapeHtml(item.title) + '</div>' +
                       '<div class="meta">' + locationText + ' | ' + tagText + '</div>' +
                   '</div>' +
                   '<div class="actions">' +
                       (item.edit_url ? '<a href="' + escapeHtml(item.edit_url) + '" target="_blank" class="button button-small">' + escapeHtml(hupunaEls.strings.edit) + '</a>' : '') +
                       (item.view_url ? '<a href="' + escapeHtml(item.view_url) + '" target="_blank" class="button button-small">' + escapeHtml(hupunaEls.strings.view) + '</a>' : '') +
                   '</div>' +
                   '</div>';
        }
        
        /**
         * Global function to change page.
         *
         * @param {number} page Page number.
         */
        window.changeHupunaPage = function(page) {
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
