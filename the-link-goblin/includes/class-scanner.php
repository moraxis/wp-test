<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class The_Link_Goblin_Scanner {

    public function __construct() {
        add_action( 'wp_ajax_the_link_goblin_scan_post', array( $this, 'ajax_scan_post' ) );
        add_action( 'wp_ajax_the_link_goblin_get_suggestions', array( $this, 'ajax_get_suggestions' ) );
        add_action( 'wp_ajax_the_link_goblin_mark_added', array( $this, 'ajax_mark_added' ) );
        add_action( 'save_post', array( $this, 'mark_post_for_rescan' ), 10, 3 );
    }

    public function ajax_mark_added() {
        check_ajax_referer( 'the_link_goblin_scan_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $suggestion_id = isset( $_POST['suggestion_id'] ) ? intval( $_POST['suggestion_id'] ) : 0;
        $target_id = isset( $_POST['target_id'] ) ? intval( $_POST['target_id'] ) : 0;

        if ( ! $post_id || ! $suggestion_id || ! $target_id ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
        }

        // Add to excluded targets so it isn't suggested again
        $added_targets = get_post_meta( $post_id, '_tlg_added_targets', true );
        if ( ! is_array( $added_targets ) ) {
            $added_targets = array();
        }
        if ( ! in_array( $target_id, $added_targets ) ) {
            $added_targets[] = $target_id;
            update_post_meta( $post_id, '_tlg_added_targets', $added_targets );
        }

        // Delete from the suggestions table
        global $wpdb;
        $table_name = $wpdb->prefix . 'the_link_goblin_suggestions';
        $wpdb->delete( $table_name, array( 'id' => $suggestion_id, 'post_id' => $post_id ), array( '%d', '%d' ) );

        wp_send_json_success( array( 'message' => 'Marked as added and removed suggestion.' ) );
    }

    public function ajax_get_suggestions() {
        check_ajax_referer( 'the_link_goblin_scan_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
        }

        $html = The_Link_Goblin_Metabox::render_suggestions_html( $post_id );
        wp_send_json_success( array( 'html' => $html ) );
    }

    public function mark_post_for_rescan( $post_id, $post, $update ) {
        // Only care about post, page, glossary
        if ( ! in_array( $post->post_type, array( 'post', 'page', 'glossary' ) ) ) {
            return;
        }

        // Don't run on autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Only mark if it's an update, or if we want new posts to be marked too
        update_post_meta( $post_id, '_the_link_goblin_needs_rescan', '1' );
    }

    public function ajax_scan_post() {
        check_ajax_referer( 'the_link_goblin_scan_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $allow_new = isset( $_POST['allow_new_suggestions'] ) ? intval( $_POST['allow_new_suggestions'] ) : 1;

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
        }

        $result = $this->scan_post( $post_id, $allow_new );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => 'Scan complete', 'suggestions_count' => $result ) );
    }

    public function scan_post( $post_id, $allow_new = 1 ) {
        global $wpdb;

        $post = get_post( $post_id );
        if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ) ) ) {
            return new WP_Error( 'invalid_post', 'Post is invalid or trashed.' );
        }

        // Get actual text content by stripping tags. For better context we could keep some tags, but plain text is safer for tokens.
        $content = wp_strip_all_tags( $post->post_content );
        if ( empty( trim( $content ) ) ) {
            return new WP_Error( 'empty_content', 'Post content is empty.' );
        }

        // Get already added targets to exclude them
        $added_targets = get_post_meta( $post_id, '_tlg_added_targets', true );
        if ( ! is_array( $added_targets ) ) {
            $added_targets = array();
        }

        $exclude_ids = array_merge( array( $post_id ), $added_targets );

        // Fetch up to 100 potential target posts
        $target_posts = get_posts( array(
            'post_type'      => array( 'post', 'page', 'glossary' ),
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'exclude'        => $exclude_ids,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( empty( $target_posts ) ) {
            return new WP_Error( 'no_targets', 'No other published posts found to link to.' );
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

        $prompt = "Analyze the following Content and suggest 1-5 highly relevant internal links to other existing posts from the provided Target Posts list.\n";
        $prompt .= "CRITICAL INSTRUCTIONS:\n";
        $prompt .= "1. Your primary goal is to find an EXISTING sentence exactly as it appears in the Content. Extract that exact sentence for 'context_sentence' and extract the exact phrase within it to be the 'anchor_text'. The text must match the provided Content verbatim.\n";
        $prompt .= "2. STRICT SEMANTIC MATCHING: The 'anchor_text' you select MUST be highly relevant, synonymous, or a direct match to the target post's 'title'.\n";
        $prompt .= "3. NO TANGENTIAL LINKS: You are explicitly forbidden from making loose associations. For example, do not link the phrase 'Crawl demand' to a target post titled 'Robots.txt Tester' just because they share a broad SEO context. A user clicking the anchor text must expect to land on a page primarily about that exact topic.\n";
        $prompt .= "4. If you cannot find a highly relevant match between an anchor phrase in the text and a target title, skip it. It is better to return 0 suggestions than bad suggestions.\n";

        if ( ! $allow_new ) {
            $prompt .= "5. STRICT TEXT REQUIREMENT: You are FORBIDDEN from suggesting new sentences or modifying existing ones. If you cannot find suitable existing text in the Content, return an empty array.\n";
        } else {
            $prompt .= "5. If and ONLY IF there are no good opportunities using existing text, you may suggest a new sentence or modify a sentence to better fit the link.\n";
        }

        $prompt .= "\nReturn ONLY a valid JSON array of objects with the exact keys: 'target_id', 'anchor_text', 'context_sentence'. Do not include markdown code block formatting like ```json ... ```, just output the raw JSON array.\n\n";
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

        // We will do a case-insensitive check against the stripped content
        // to see if the AI hallucinated or actually returned an exact match.
        // It's possible the LLM returns a match but with slightly different spacing.
        $clean_content = preg_replace('/\s+/', ' ', strtolower( $content ) );

        $inserted = 0;
        $values   = array();
        $placeholders = array();

        foreach ( $suggestions as $sugg ) {
            if ( isset( $sugg['target_id'], $sugg['anchor_text'], $sugg['context_sentence'] ) ) {
                // Verify target post exists
                if ( get_post_status( $sugg['target_id'] ) === 'publish' ) {
                    $is_existing = 0;

                    // Normalize spacing and case for matching
                    $clean_context = trim( preg_replace( '/\s+/', ' ', strtolower( $sugg['context_sentence'] ) ) );
                    $clean_anchor = trim( preg_replace( '/\s+/', ' ', strtolower( $sugg['anchor_text'] ) ) );

                    $anchor_in_context = strpos( $clean_context, $clean_anchor ) !== false;
                    $context_in_content = strpos( $clean_content, $clean_context ) !== false;

                    // Strictly drop if the anchor is not within the context sentence
                    if ( ! $anchor_in_context ) {
                        continue;
                    }

                    // If we strictly don't allow new suggestions and the context isn't exactly in the content, skip it
                    if ( ! $allow_new && ! $context_in_content ) {
                        continue;
                    }

                    $is_existing = $context_in_content ? 1 : 0;

                    $values[] = $post_id;
                    $values[] = intval( $sugg['target_id'] );
                    $values[] = sanitize_text_field( $sugg['anchor_text'] );
                    $values[] = sanitize_text_field( $sugg['context_sentence'] );
                    $values[] = $is_existing;
                    $values[] = current_time('mysql');

                    $placeholders[] = "(%d, %d, %s, %s, %d, %s)";
                    $inserted++;
                }
            }
        }

        if ( ! empty( $values ) ) {
            $query = "INSERT INTO $table_name (post_id, target_post_id, anchor_text, context_sentence, is_existing_text, created_at) VALUES " . implode( ', ', $placeholders );
            $wpdb->query( $wpdb->prepare( $query, $values ) );
        }

        // Update meta so we know it has been scanned
        update_post_meta( $post_id, '_the_link_goblin_last_scanned', current_time( 'mysql' ) );
        delete_post_meta( $post_id, '_the_link_goblin_needs_rescan' );

        return $inserted;
    }
}
new The_Link_Goblin_Scanner();
