(function($) {
    'use strict';
    
    $(document).ready(function() {
        var $form = $('#tsh-llms-form');
        var $linksTbody = $('#llms-links-tbody');
        var $addButton = $('#add-link-row');
        var $saveButton = $('#save-llms-settings');
        var $messageDiv = $('#llms-save-message');
        
        if (!$form.length || !$linksTbody.length || !$addButton.length) {
            return;
        }
        
        var linkIndex = $linksTbody.find('tr').length;
        var localized = typeof toolSeoHupunaLlms !== 'undefined' ? toolSeoHupunaLlms : {
            ajaxUrl: (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
            nonce: '',
            strings: {
                saving: 'Saving...',
                saved: 'Settings saved successfully!',
                error: 'Error saving settings.'
            }
        };
        
        function initSortable() {
            if ($linksTbody.find('tr').length > 0) {
                if (!$linksTbody.hasClass('ui-sortable')) {
                    $linksTbody.sortable({
                        handle: '.sort-handle',
                        axis: 'y',
                        opacity: 0.6,
                        cursor: 'move',
                        update: updateRowIndices
                    });
                } else {
                    $linksTbody.sortable('refresh');
                }
            }
        }
        
        if ($linksTbody.find('tr').length > 0) {
            initSortable();
        }
        
        var $sectionOrder = $('#section-order-sortable');
        if ($sectionOrder.length && $sectionOrder.find('li').length > 0) {
            $sectionOrder.sortable({
                items: 'li.section-order-item',
                handle: '.dashicons-move',
                axis: 'y',
                opacity: 0.95,
                cursor: 'move',
                placeholder: 'ui-sortable-placeholder',
                tolerance: 'pointer',
                containment: 'parent',
                forcePlaceholderSize: true,
                start: function(event, ui) {
                    ui.placeholder.height(ui.item.height());
                    ui.item.addClass('sorting-active');
                    $sectionOrder.find('li').not(ui.item).addClass('sorting-other');
                },
                sort: function(event, ui) {
                    ui.placeholder.height(ui.item.height());
                },
                stop: function(event, ui) {
                    ui.item.removeClass('sorting-active');
                    $sectionOrder.find('li').removeClass('sorting-other');
                    ui.item.css('transition', 'all 0.3s ease');
                    setTimeout(function() {
                        ui.item.css('transition', '');
                    }, 300);
                },
                change: function(event, ui) {
                    $sectionOrder.find('li').not(ui.item).not(ui.placeholder).css({
                        'transition': 'transform 0.3s ease'
                    });
                }
            });
        }
        
        $('input[name="enabled_post_types[]"]').on('change', function() {
            var postType = $(this).val();
            var isChecked = $(this).is(':checked');
            var $item = $sectionOrder.find('li[data-post-type="' + postType + '"]');
            
            if (isChecked) {
                if ($item.length === 0) {
                    var $template = $('#section-order-templates').find('li[data-post-type="' + postType + '"]').clone();
                    if ($template.length > 0) {
                        $template.removeClass('section-order-item-template').addClass('section-order-item');
                        $sectionOrder.append($template);
                        if ($sectionOrder.hasClass('ui-sortable')) {
                            $sectionOrder.sortable('refresh');
                        }
                    }
                } else {
                    $item.show();
                }
            } else {
                $item.remove();
            }
        });
        
        $addButton.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var rowHtml = '<tr class="llms-link-row" data-index="' + linkIndex + '">' +
                '<td class="sort-handle"><span class="dashicons dashicons-move" style="cursor: move; color: #999;"></span></td>' +
                '<td><input type="text" name="links[' + linkIndex + '][title]" class="regular-text" placeholder="Link Title" required></td>' +
                '<td><input type="url" name="links[' + linkIndex + '][url]" class="regular-text" placeholder="https://example.com/page" required></td>' +
                '<td><input type="text" name="links[' + linkIndex + '][description]" class="regular-text" placeholder="Optional description"></td>' +
                '<td><button type="button" class="button button-small button-link-delete delete-link-row">Delete</button></td>' +
            '</tr>';
            
            var $row = $(rowHtml);
            $linksTbody.append($row);
            linkIndex++;
            
            if ($linksTbody.hasClass('ui-sortable')) {
                $linksTbody.sortable('refresh');
            } else {
                $linksTbody.sortable({
                    handle: '.sort-handle',
                    axis: 'y',
                    opacity: 0.6,
                    cursor: 'move',
                    update: updateRowIndices
                });
            }
            
            $row.find('input[name*="[title]"]').focus();
        });
        
        $(document).on('click', '.delete-link-row', function() {
            if (confirm('Are you sure you want to delete this link?')) {
                $(this).closest('tr').fadeOut(300, function() {
                    $(this).remove();
                    updateRowIndices();
                });
            }
        });
        
        function updateRowIndices() {
            $linksTbody.find('tr').each(function(index) {
                var $row = $(this);
                $row.attr('data-index', index);
                $row.find('input[name*="[title]"]').attr('name', 'links[' + index + '][title]');
                $row.find('input[name*="[url]"]').attr('name', 'links[' + index + '][url]');
                $row.find('input[name*="[description]"]').attr('name', 'links[' + index + '][description]');
            });
        }
        
        $form.on('submit', function(e) {
            e.preventDefault();
            
            var enabledPostTypes = [];
            $('input[name="enabled_post_types[]"]:checked').each(function() {
                enabledPostTypes.push($(this).val());
            });
            
            var sectionOrder = [];
            $('#section-order-sortable li').each(function() {
                var postType = $(this).find('input[type="hidden"]').val();
                if (postType) {
                    sectionOrder.push(postType);
                }
            });
            
            var formData = {
                action: 'save_llms_txt',
                nonce: localized.nonce,
                site_title: $('#site-title').val(),
                introduction: $('#introduction').val(),
                footer: $('#footer').val(),
                enabled_post_types: enabledPostTypes,
                section_order: sectionOrder,
                posts_limit: $('#posts-limit').val() || 50,
                links: []
            };
            
            $linksTbody.find('tr').each(function() {
                var $row = $(this);
                var title = $row.find('input[name*="[title]"]').val();
                var url = $row.find('input[name*="[url]"]').val();
                var description = $row.find('input[name*="[description]"]').val();
                
                if (title && url) {
                    formData.links.push({
                        title: title,
                        url: url,
                        description: description || ''
                    });
                }
            });
            
            $saveButton.prop('disabled', true).text(localized.strings.saving);
            
            $.ajax({
                url: localized.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showMessage(localized.strings.saved, 'success');
                    } else {
                        showMessage(response.data && response.data.message ? response.data.message : localized.strings.error, 'error');
                    }
                },
                error: function() {
                    showMessage(localized.strings.error, 'error');
                },
                complete: function() {
                    $saveButton.prop('disabled', false).text('Save Settings');
                }
            });
        });
        
        function showMessage(message, type) {
            var className = type === 'success' ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';
            $messageDiv
                .removeClass('notice notice-success notice-error is-dismissible')
                .addClass(className)
                .html('<p>' + escapeHtml(message) + '</p>')
                .fadeIn();
            
            setTimeout(function() {
                $messageDiv.fadeOut();
            }, 5000);
        }
        
        function escapeHtml(text) {
            if (!text) return '';
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

