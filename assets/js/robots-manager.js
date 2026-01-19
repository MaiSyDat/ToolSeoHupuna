/**
 * Robots Manager JavaScript
 * Handles robots.txt editing with live preview
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        var $saveBtn = $('#tsh-save-robots-btn');
        var $message = $('#tsh-robots-message');
        var $textarea = $('#robots-content');
        var $preview = $('#tsh-robots-preview');

        // Get localized data
        var robotsData = window.hupunaRobotsManager || {};

        // Update preview on textarea change
        $textarea.on('input', function () {
            $preview.text($(this).val());
        });

        // Save button click
        $saveBtn.on('click', function () {
            var content = $textarea.val();
            var originalText = $saveBtn.text();

            $saveBtn.prop('disabled', true).text(robotsData.strings.saving || 'Saving...');
            $message.html('');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_robots_txt',
                    nonce: robotsData.nonce,
                    robots_content: content
                },
                success: function (response) {
                    if (response.success) {
                        $message.html('<span style="color: #46b450;">✓ ' + (response.data.message || robotsData.strings.success || 'Robots.txt saved successfully!') + '</span>');
                        $preview.text(content);
                        setTimeout(function () {
                            $message.html('');
                        }, 3000);
                    } else {
                        $message.html('<span style="color: #dc3232;">✗ ' + (response.data.message || robotsData.strings.error || 'Error saving robots.txt') + '</span>');
                    }
                    $saveBtn.prop('disabled', false).text(originalText);
                },
                error: function () {
                    $message.html('<span style="color: #dc3232;">✗ ' + (robotsData.strings.serverError || 'Server error. Please try again.') + '</span>');
                    $saveBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    });

})(jQuery);
