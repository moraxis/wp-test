jQuery(document).ready(function($) {
    function scanPost(postId, row, callback) {
        row.addClass('tlg-scanning');
        var btn = row.find('.tlg-scan-single');
        btn.prop('disabled', true).text('Scanning...');

        $.ajax({
            url: theLinkGoblin.ajax_url,
            type: 'POST',
            data: {
                action: 'the_link_goblin_scan_post',
                nonce: theLinkGoblin.nonce,
                post_id: postId
            },
            success: function(response) {
                row.removeClass('tlg-scanning');
                if (response.success) {
                    btn.text('Re-scan').prop('disabled', false);
                    row.removeClass('tlg-needs-scan').addClass('tlg-scanned');
                    row.find('.tlg-sugg-count').text(response.data.suggestions_count);
                    if (row.find('.tlg-success-icon').length === 0) {
                        btn.after(' <span class="dashicons dashicons-yes-alt tlg-success-icon"></span>');
                    }
                } else {
                    btn.text('Scan Failed').prop('disabled', false);
                    alert('Error: ' + response.data.message);
                }
                if (callback) callback();
            },
            error: function() {
                row.removeClass('tlg-scanning');
                btn.text('Error').prop('disabled', false);
                if (callback) callback();
            }
        });
    }

    $('.tlg-scan-single').on('click', function(e) {
        e.preventDefault();
        var postId = $(this).data('id');
        var row = $(this).closest('tr');
        scanPost(postId, row, null);
    });

    $('#tlg-scan-all').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var rowsToScan = $('#tlg-posts-table tr');
        var total = rowsToScan.length;

        if (total === 0) {
            alert('No posts found to scan.');
            return;
        }

        btn.prop('disabled', true);
        var current = 0;

        $('#tlg-progress-container').show();
        $('#tlg-progress-bar').css('width', '0%');
        $('#tlg-scan-status').text('0/' + total + ' pages scanned');

        function processNext() {
            if (current >= total) {
                btn.prop('disabled', false);
                $('#tlg-scan-status').text(total + '/' + total + ' pages scanned. All scans complete!');
                $('#tlg-progress-bar').css('width', '100%');
                return;
            }

            var row = $(rowsToScan[current]);
            var postId = row.data('post-id');

            scanPost(postId, row, function() {
                current++;
                var percent = Math.round((current / total) * 100);
                $('#tlg-progress-bar').css('width', percent + '%');
                $('#tlg-scan-status').text(current + '/' + total + ' pages scanned');

                setTimeout(processNext, 1000); // 1 sec delay between requests to be gentle on API
            });
        }

        processNext();
    });
});
