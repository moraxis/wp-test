jQuery(document).ready(function($) {
    function doScan(btnElement, statusElement) {
        var btn = $(btnElement);
        var status = $(statusElement);

        btn.prop('disabled', true);
        status.text('Scanning... Please wait.');

        var allowNewSuggestions = $('#tlg-allow-new-suggestions').length ? ($('#tlg-allow-new-suggestions').is(':checked') ? 1 : 0) : 1;

        $.ajax({
            url: theLinkGoblinMeta.ajax_url,
            type: 'POST',
            data: {
                action: 'the_link_goblin_scan_post',
                nonce: theLinkGoblinMeta.nonce,
                post_id: theLinkGoblinMeta.post_id,
                allow_new_suggestions: allowNewSuggestions
            },
            success: function(response) {
                if (response.success) {
                    status.text('Scan complete! Updating...');
                    // Fetch the fresh HTML instead of reloading
                    $.ajax({
                        url: theLinkGoblinMeta.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'the_link_goblin_get_suggestions',
                            nonce: theLinkGoblinMeta.nonce,
                            post_id: theLinkGoblinMeta.post_id
                        },
                        success: function(htmlResponse) {
                            if (htmlResponse.success) {
                                $('#tlg-suggestions-wrapper').html(htmlResponse.data.html);
                                status.text('Suggestions updated.');
                                btn.prop('disabled', false);
                                $('#tlg-editor-notice').fadeOut();
                            } else {
                                status.text('Failed to load suggestions.');
                                btn.prop('disabled', false);
                            }
                        },
                        error: function() {
                            status.text('Network error loading suggestions.');
                            btn.prop('disabled', false);
                        }
                    });
                } else {
                    btn.prop('disabled', false);
                    status.text('Error: ' + response.data.message);
                }
            },
            error: function() {
                btn.prop('disabled', false);
                status.text('Scan failed due to a network error.');
            }
        });
    }

    $('#tlg-metabox-scan-btn').on('click', function(e) {
        e.preventDefault();
        doScan(this, '#tlg-metabox-scan-status');
    });

    $('#tlg-notice-scan-btn').on('click', function(e) {
        e.preventDefault();
        doScan(this, '#tlg-notice-scan-status');
    });

    $(document).on('click', '.tlg-mark-added-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var listItem = btn.closest('li');
        var suggestionId = listItem.data('suggestion-id');
        var targetId = listItem.data('target-id');

        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: theLinkGoblinMeta.ajax_url,
            type: 'POST',
            data: {
                action: 'the_link_goblin_mark_added',
                nonce: theLinkGoblinMeta.nonce,
                post_id: theLinkGoblinMeta.post_id,
                suggestion_id: suggestionId,
                target_id: targetId
            },
            success: function(response) {
                if (response.success) {
                    listItem.slideUp('fast', function() {
                        $(this).remove();
                        if ($('.tlg-suggestions-list li').length === 0) {
                            $('#tlg-suggestions-wrapper').html('<p id="tlg-no-suggestions">No suggestions available. Scan the post to get started.</p>');
                        }
                    });
                } else {
                    btn.prop('disabled', false).text('Error');
                    alert(response.data.message);
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Error');
                alert('Network error marking as added.');
            }
        });
    });
});
