(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Check if required elements exist
        var $scanButton = $('#tool-seo-hupuna-images-scan-button');
        if ($scanButton.length === 0) {
            console.error('Tool SEO Hupuna Images: Scan button not found');
            return;
        }
        
        // Check if localized script data exists
        if (typeof toolSeoHupunaImages === 'undefined') {
            console.error('Tool SEO Hupuna Images: Localized script data not found');
            return;
        }
        
        // Elements
        var $progressWrap = $('#tool-seo-hupuna-images-progress-wrap');
        var $progressFill = $('#tool-seo-hupuna-images-progress-fill');
        var $progressText = $('#tool-seo-hupuna-images-progress-text');
        var $results = $('#tool-seo-hupuna-images-scan-results');
        var $resultsContent = $('#tool-seo-hupuna-images-results-content');
        var $bulkDeleteButton = $('#tool-seo-hupuna-images-bulk-delete');
        
        // State
        var allResults = [];
        var isScanning = false;
        var currentPage = 1;
        var totalImages = 0;
        var maxRetries = 3;
        var retryCount = 0;
        
        // --- Core Scanning Logic ---
        
        /**
         * Handle scan button click.
         */
        $scanButton.on('click', function(e) {
            e.preventDefault();
            if (isScanning) {
                return;
            }
            
            isScanning = true;
            allResults = [];
            currentPage = 1;
            retryCount = 0;
            totalImages = 0; // Reset total
            $scanButton.prop('disabled', true).html('<span class="dashicons dashicons-search"></span> ' + toolSeoHupunaImages.strings.scanning);
            $results.hide();
            $resultsContent.empty(); // Clear previous results
            $progressWrap.show();
            
            scanNextBatch();
        });
        
        /**
         * Scan next batch of images.
         * PROGRESSIVE RENDERING: Display results immediately after each batch.
         */
        function scanNextBatch() {
            $.ajax({
                url: toolSeoHupunaImages.ajaxUrl,
                type: 'POST',
                timeout: 60000, // 60 second timeout
                data: {
                    action: 'tool_seo_hupuna_scan_unused_images',
                    nonce: toolSeoHupunaImages.nonce,
                    page: currentPage
                },
                success: function(response) {
                    retryCount = 0; // Reset retry count on success
                    
                    if (response.success) {
                        // Update total images count from server (first batch)
                        if (response.data.stats && response.data.stats.total_in_db) {
                            totalImages = response.data.stats.total_in_db;
                        }
                        
                        // Calculate progress percentage
                        var totalPages = totalImages > 0 ? Math.ceil(totalImages / 50) : 1;
                        var progressPercent = totalPages > 0 ? Math.min((currentPage / totalPages) * 100, 95) : 0;
                        
                        // Update progress bar
                        updateProgress(progressPercent, toolSeoHupunaImages.strings.scanningImages + ' (' + toolSeoHupunaImages.strings.page + ' ' + currentPage + ' / ' + totalPages + ')');
                        
                        // Display results immediately (progressive rendering)
                        if (response.data.results && response.data.results.length > 0) {
                            allResults = allResults.concat(response.data.results);
                            appendResultsToTable(response.data.results);
                        }
                        
                        // Update total unused count
                        $('#total-unused-images').text(allResults.length);
                        
                        // Show results section if not already visible
                        if (allResults.length > 0) {
                            $results.show();
                        }
                        
                        if (response.data.done) {
                            finishScan();
                        } else {
                            currentPage++;
                            // Use setTimeout to prevent browser hang
                            setTimeout(function() {
                                scanNextBatch();
                            }, 10);
                        }
                    } else {
                        handleError(response.data.message || toolSeoHupunaImages.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    // Retry logic for transient errors
                    if (retryCount < maxRetries && (status === 'timeout' || xhr.status === 0)) {
                        retryCount++;
                        setTimeout(function() {
                            scanNextBatch();
                        }, 1000 * retryCount); // Exponential backoff
                        return;
                    }
                    
                    var errorMsg = toolSeoHupunaImages.strings.serverError.replace('%s', error || status);
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
            alert(toolSeoHupunaImages.strings.error + ': ' + msg);
            isScanning = false;
            $scanButton.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + toolSeoHupunaImages.strings.startScan);
            $progressText.text(toolSeoHupunaImages.strings.errorEncountered);
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
            updateProgress(100, toolSeoHupunaImages.strings.scanCompleted);
            $scanButton.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + toolSeoHupunaImages.strings.startScan);
            
            // Final update
            $('#total-unused-images').text(allResults.length);
            
            // Show results if not already visible
            if (allResults.length > 0) {
                $results.show();
            } else {
                // Show "no results" message if table doesn't exist
                if ($resultsContent.find('table').length === 0) {
                    var message = '<div class="notice notice-info"><p>' + escapeHtml(toolSeoHupunaImages.strings.noImagesFound) + '</p>';
                    message += '<p><strong>Note:</strong> This could mean either:</p>';
                    message += '<ul style="margin-left: 20px;">';
                    message += '<li>There are no images in your Media Library</li>';
                    message += '<li>All images are currently being used on your site</li>';
                    message += '</ul></div>';
                    $resultsContent.html(message);
                    $results.show();
                }
            }
        }
        
        // --- UI & Display Logic ---
        
        /**
         * Initialize results table (create if doesn't exist).
         */
        function initResultsTable() {
            if ($resultsContent.find('table').length === 0) {
                var html = '<table class="wp-list-table widefat fixed striped tsh-table">';
                html += '<thead><tr>';
                html += '<th style="width: 50px;"><input type="checkbox" id="select-all-checkbox"></th>';
                html += '<th style="width: 100px;">' + escapeHtml('Thumbnail') + '</th>';
                html += '<th>' + escapeHtml('Filename/Path') + '</th>';
                html += '<th style="width: 150px;">' + escapeHtml('Upload Date') + '</th>';
                html += '<th style="width: 100px;">' + escapeHtml('Actions') + '</th>';
                html += '</tr></thead><tbody></tbody></table>';
                $resultsContent.html(html);
                bindTableEvents();
            }
        }
        
        /**
         * Append new results to existing table (progressive rendering).
         *
         * @param {Array} newResults New results to append.
         */
        function appendResultsToTable(newResults) {
            // Initialize table if it doesn't exist
            initResultsTable();
            
            var $tbody = $resultsContent.find('tbody');
            
            $.each(newResults, function(i, item) {
                // Check if row already exists (prevent duplicates)
                if ($tbody.find('tr[data-image-id="' + item.id + '"]').length > 0) {
                    return;
                }
                
                var html = '<tr data-image-id="' + item.id + '">';
                html += '<td><input type="checkbox" class="image-checkbox" value="' + item.id + '"></td>';
                html += '<td>';
                if (item.thumbnail) {
                    html += '<img src="' + escapeHtml(item.thumbnail) + '" style="max-width: 60px; max-height: 60px; object-fit: cover;" alt="">';
                } else {
                    html += '<span class="dashicons dashicons-format-image"></span>';
                }
                html += '</td>';
                html += '<td>';
                html += '<strong>' + escapeHtml(item.title) + '</strong><br>';
                html += '<small style="color: #666;">' + escapeHtml(item.filename) + '</small>';
                html += '</td>';
                html += '<td>' + escapeHtml(formatDate(item.date)) + '</td>';
                html += '<td>';
                html += '<button type="button" class="button button-small button-link-delete delete-single-image" data-image-id="' + item.id + '">';
                html += escapeHtml(toolSeoHupunaImages.strings.delete);
                html += '</button>';
                html += '</td>';
                html += '</tr>';
                
                // Append with fade-in effect
                var $newRow = $(html).hide();
                $tbody.append($newRow);
                $newRow.fadeIn(200);
            });
            
            // Re-bind events for new rows
            bindTableEvents();
        }
        
        /**
         * Bind table events.
         */
        function bindTableEvents() {
            // Select all checkbox
            $('#select-all-checkbox').off('change').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.image-checkbox').prop('checked', isChecked);
                updateBulkDeleteButton();
            });
            
            // Individual checkboxes
            $('.image-checkbox').off('change').on('change', function() {
                updateSelectAllCheckbox();
                updateBulkDeleteButton();
            });
            
            // Delete single image
            $('.delete-single-image').off('click').on('click', function() {
                var imageId = $(this).data('image-id');
                if (confirm(toolSeoHupunaImages.strings.confirmDelete)) {
                    deleteImage(imageId);
                }
            });
            
            // Bulk delete
            $bulkDeleteButton.off('click').on('click', function() {
                var selectedIds = getSelectedImageIds();
                if (selectedIds.length === 0) {
                    alert('Please select at least one image.');
                    return;
                }
                
                if (confirm(toolSeoHupunaImages.strings.confirmBulkDelete)) {
                    bulkDeleteImages(selectedIds);
                }
            });
        }
        
        /**
         * Update select all checkbox state.
         */
        function updateSelectAllCheckbox() {
            var total = $('.image-checkbox').length;
            var checked = $('.image-checkbox:checked').length;
            $('#select-all-checkbox').prop('checked', total > 0 && total === checked);
        }
        
        /**
         * Update bulk delete button visibility.
         */
        function updateBulkDeleteButton() {
            var selectedCount = $('.image-checkbox:checked').length;
            if (selectedCount > 0) {
                $bulkDeleteButton.show();
            } else {
                $bulkDeleteButton.hide();
            }
        }
        
        /**
         * Get selected image IDs.
         *
         * @return {Array} Array of image IDs.
         */
        function getSelectedImageIds() {
            var ids = [];
            $('.image-checkbox:checked').each(function() {
                ids.push(parseInt($(this).val(), 10));
            });
            return ids;
        }
        
        /**
         * Delete single image.
         *
         * @param {number} imageId Image ID.
         */
        function deleteImage(imageId) {
            var $row = $('tr[data-image-id="' + imageId + '"]');
            var $button = $row.find('.delete-single-image');
            
            $button.prop('disabled', true).text(toolSeoHupunaImages.strings.deleting);
            
            $.ajax({
                url: toolSeoHupunaImages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tool_seo_hupuna_delete_image',
                    nonce: toolSeoHupunaImages.nonce,
                    image_id: imageId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            allResults = allResults.filter(function(item) {
                                return item.id !== imageId;
                            });
                            totalImages--;
                            $('#total-unused-images').text(totalImages);
                            
                            if (totalImages === 0) {
                                $resultsContent.html('<div class="notice notice-info"><p>' + escapeHtml(toolSeoHupunaImages.strings.noImagesFound) + '</p></div>');
                            }
                        });
                    } else {
                        alert(toolSeoHupunaImages.strings.deleteError + ': ' + (response.data.message || ''));
                        $button.prop('disabled', false).text(toolSeoHupunaImages.strings.delete);
                    }
                },
                error: function() {
                    alert(toolSeoHupunaImages.strings.deleteError);
                    $button.prop('disabled', false).text(toolSeoHupunaImages.strings.delete);
                }
            });
        }
        
        /**
         * Bulk delete images.
         *
         * @param {Array} imageIds Array of image IDs.
         */
        function bulkDeleteImages(imageIds) {
            $bulkDeleteButton.prop('disabled', true).text(toolSeoHupunaImages.strings.deleting);
            
            $.ajax({
                url: toolSeoHupunaImages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tool_seo_hupuna_bulk_delete_images',
                    nonce: toolSeoHupunaImages.nonce,
                    image_ids: imageIds
                },
                success: function(response) {
                    if (response.success) {
                        // Remove deleted rows
                        $.each(imageIds, function(i, imageId) {
                            var $row = $('tr[data-image-id="' + imageId + '"]');
                            $row.fadeOut(300, function() {
                                $(this).remove();
                            });
                        });
                        
                        // Update results array
                        allResults = allResults.filter(function(item) {
                            return imageIds.indexOf(item.id) === -1;
                        });
                        
                        totalImages = allResults.length;
                        $('#total-unused-images').text(totalImages);
                        
                        if (totalImages === 0) {
                            $resultsContent.html('<div class="notice notice-info"><p>' + escapeHtml(toolSeoHupunaImages.strings.noImagesFound) + '</p></div>');
                        }
                        
                        alert(response.data.message);
                    } else {
                        alert(toolSeoHupunaImages.strings.deleteError + ': ' + (response.data.message || ''));
                    }
                    
                    $bulkDeleteButton.prop('disabled', false).text(toolSeoHupunaImages.strings.deleteSelected);
                    $('.image-checkbox').prop('checked', false);
                    $('#select-all-checkbox').prop('checked', false);
                    updateBulkDeleteButton();
                },
                error: function() {
                    alert(toolSeoHupunaImages.strings.deleteError);
                    $bulkDeleteButton.prop('disabled', false).text(toolSeoHupunaImages.strings.deleteSelected);
                }
            });
        }
        
        /**
         * Format date string.
         *
         * @param {string} dateString Date string.
         * @return {string} Formatted date.
         */
        function formatDate(dateString) {
            if (!dateString) {
                return '';
            }
            var date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return dateString;
            }
            return date.toLocaleDateString();
        }
        
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

