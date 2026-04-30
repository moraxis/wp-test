jQuery(document).ready(function($) {
    function doScan(btnElement, statusElement) {
        var btn = $(btnElement);
        var status = $(statusElement);

        btn.prop('disabled', true);
        status.text('Scanning... Please wait.');

        $.ajax({
            url: theLinkGoblinMeta.ajax_url,
            type: 'POST',
            data: {
                action: 'the_link_goblin_scan_post',
                nonce: theLinkGoblinMeta.nonce,
                post_id: theLinkGoblinMeta.post_id
            },
            success: function(response) {
                if (response.success) {
                    status.text('Scan complete! Reloading...');
                    // Reload the page to show the new suggestions and clear the notice
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
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
});
