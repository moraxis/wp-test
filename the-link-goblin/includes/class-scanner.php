<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class The_Link_Goblin_Scanner {

    public function __construct() {
        add_action( 'wp_ajax_the_link_goblin_scan_post', array( $this, 'ajax_scan_post' ) );
    }

    public function ajax_scan_post() {
        check_ajax_referer( 'the_link_goblin_scan_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
        }

        $result = $this->scan_post( $post_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => 'Scan complete', 'suggestions_count' => $result ) );
    }

    public function scan_post( $post_id ) {
        global $wpdb;

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'invalid_post', 'Post is invalid.' );
        }

        // Get actual text content by stripping tags. For better context we could keep some tags, but plain text is safer for tokens.
        $content = wp_strip_all_tags( $post->post_content );
        if ( empty( trim( $content ) ) ) {
            return new WP_Error( 'empty_content', 'Post content is empty.' );
        }

        $post_types = get_post_types( array( 'public' => true ) );

        // Fetch up to 100 potential target posts
        $target_posts = get_posts( array(
            'post_type'      => array_keys( $post_types ),
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => 100,
            'exclude'        => array( $post_id ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( empty( $target_posts ) ) {
            return new WP_Error( 'no_targets', 'No other posts found to link to.' );
        }

        $targets_json = array();
        foreach ( $target_posts as $tp ) {
            $targets_json[] = array(
                'id'    => $tp->ID,
                'title' => $tp->post_title,
                'url'   => get_permalink( $tp->ID ),
            );
        }

        $api_key = get_option( 'the_link_goblin_api_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'DeepSeek API key is not configured.' );
        }
        $model = get_option( 'the_link_goblin_api_model', 'deepseek-chat' );

        $prompt = "Analyze the following content and suggest 3-5 contextually relevant internal links to other existing posts from the provided target posts list.\n";
        $prompt .= "Return ONLY a valid JSON array of objects with the exact keys: 'target_id', 'anchor_text', 'context_sentence'. Do not include markdown code block formatting like ```json ... ```, just output the raw JSON array.\n\n";
        $prompt .= "Content:\n" . wp_trim_words( $content, 1500, '...' ) . "\n\n";
        $prompt .= "Target Posts (JSON):\n" . wp_json_encode( $targets_json );

        $response = wp_safe_remote_post( 'https://api.deepseek.com/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'TheLinkGoblin/1.0',
            ),
            'body'    => wp_json_encode( array(
                'model'       => $model,
                'messages'    => array(
                    array(
                        'role'    => 'user',
                        'content' => $prompt,
                    ),
                ),
                // Force a predictable JSON response structure if possible (though chat completions are freeform, deepseek-chat tends to follow instructions)
            ) ),
            'timeout' => 45, // Set a higher timeout for LLM generation
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            return new WP_Error( 'api_error', 'DeepSeek API error: HTTP ' . $status_code . ' - ' . $body );
        }

        $data = json_decode( $body, true );
        if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'invalid_api_response', 'Invalid response structure from DeepSeek.' );
        }

        $llm_reply = trim( $data['choices'][0]['message']['content'] );

        // Strip markdown formatting if present despite instructions
        $llm_reply = preg_replace('/^```json\s*/i', '', $llm_reply);
        $llm_reply = preg_replace('/```$/i', '', $llm_reply);
        $llm_reply = trim($llm_reply);

        $suggestions = json_decode( $llm_reply, true );

        if ( ! is_array( $suggestions ) ) {
            return new WP_Error( 'json_parse_error', 'Failed to parse JSON array from DeepSeek: ' . $llm_reply );
        }

        // Clear existing suggestions for this post to avoid stale/duplicate entries on re-scan
        $table_name = $wpdb->prefix . 'the_link_goblin_suggestions';
        $wpdb->delete( $table_name, array( 'post_id' => $post_id ), array( '%d' ) );

        $inserted = 0;
        foreach ( $suggestions as $sugg ) {
            if ( isset( $sugg['target_id'], $sugg['anchor_text'], $sugg['context_sentence'] ) ) {
                // Verify target post exists
                $target_status = get_post_status( $sugg['target_id'] );
                if ( $target_status ) {
                    $wpdb->insert(
                        $table_name,
                        array(
                            'post_id'          => $post_id,
                            'target_post_id'   => intval( $sugg['target_id'] ),
                            'anchor_text'      => sanitize_text_field( $sugg['anchor_text'] ),
                            'context_sentence' => sanitize_text_field( $sugg['context_sentence'] ),
                            'created_at'       => current_time('mysql')
                        ),
                        array( '%d', '%d', '%s', '%s', '%s' )
                    );
                    $inserted++;
                }
            }
        }

        // Update meta so we know it has been scanned
        update_post_meta( $post_id, '_the_link_goblin_last_scanned', current_time( 'mysql' ) );
        delete_post_meta( $post_id, '_the_link_goblin_needs_rescan' );

        return $inserted;
    }
}
new The_Link_Goblin_Scanner();
