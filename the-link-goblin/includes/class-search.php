<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class The_Link_Goblin_Search {

    public static function render() {
        $search_query = isset( $_GET['tlg_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tlg_search'] ) ) : '';

        echo '<div class="wrap">';
        echo '<h1>Search Posts Content</h1>';

        // Search Form
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="the-link-goblin-search" />';
        echo '<p class="search-box" style="float:none; margin-bottom: 20px;">';
        echo '<label class="screen-reader-text" for="tlg-search-input">Search Content:</label>';
        echo '<input type="search" id="tlg-search-input" name="tlg_search" value="' . esc_attr( $search_query ) . '" />';
        echo '<input type="submit" id="search-submit" class="button" value="Search" style="margin-left:5px;" />';

        if ( ! empty( $search_query ) ) {
            $clear_url = admin_url( 'admin.php?page=the-link-goblin-search' );
            echo '<a href="' . esc_url( $clear_url ) . '" class="button" style="margin-left:5px;">Clear <span class="dashicons dashicons-no-alt" style="margin-top:4px;"></span></a>';
        }
        echo '</p>';
        echo '</form>';

        if ( ! empty( $search_query ) ) {
            self::render_results( $search_query );
        }

        echo '</div>';
    }

    private static function render_results( $search_query ) {
        // Fetch posts
        $posts = get_posts( array(
            'post_type'      => array( 'post', 'page', 'glossary' ),
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => -1,
        ) );

        $results = array();
        $search_lower = mb_strtolower( $search_query );

        foreach ( $posts as $post ) {
            // Clean content: remove blocks, shortcodes, and tags.
            // Gutenberg blocks often contain HTML comments like <!-- wp:paragraph -->
            $content = $post->post_content;

            // Strip block comments (e.g. <!-- wp:paragraph -->)
            $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);

            // Strip shortcodes
            $content = strip_shortcodes( $content );

            // Strip HTML tags
            $content = wp_strip_all_tags( $content );

            // Normalize whitespace
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim( $content );

            $content_lower = mb_strtolower( $content );

            // Check if keyword exists
            if ( mb_strpos( $content_lower, $search_lower ) !== false ) {
                $context_data = self::extract_context( $content, $search_query );
                if ( $context_data ) {
                    $results[] = array(
                        'post'    => $post,
                        'context' => $context_data['context'],
                        'count'   => $context_data['count'],
                    );
                }
            }
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width: 25%;">Title / Type / Status</th>';
        echo '<th>Context</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $results ) ) {
            echo '<tr><td colspan="2">No results found for "<strong>' . esc_html( $search_query ) . '</strong>".</td></tr>';
        } else {
            foreach ( $results as $res ) {
                $post = $res['post'];
                $post_type_obj = get_post_type_object( $post->post_type );
                $type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;

                $status_label = '';
                if ( $post->post_status !== 'publish' ) {
                    $status_label = ' — <span style="color:#888;">' . esc_html( ucfirst( $post->post_status ) ) . '</span>';
                }

                echo '<tr>';
                echo '<td><strong><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( $post->post_title ?: '(No Title)' ) . '</a></strong><br/><small>' . esc_html( $type_label ) . $status_label . '</small></td>';

                // Highlight the keyword in the context using case-insensitive replacement but preserving original case of the text
                $highlighted_context = preg_replace(
                    '/(' . preg_quote( $search_query, '/' ) . ')/i',
                    '<strong>$1</strong>',
                    esc_html( $res['context'] )
                );

                echo '<td>';
                echo '&ldquo;' . $highlighted_context . '&rdquo;';
                if ( $res['count'] > 1 ) {
                    $additional = $res['count'] - 1;
                    echo '<br/><small style="color: #666; font-style: italic;">...and ' . intval( $additional ) . ' more occurrence' . ( $additional > 1 ? 's' : '' ) . ' in this post.</small>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    private static function extract_context( $text, $keyword ) {
        // Count total occurrences
        $lower_text = mb_strtolower( $text );
        $lower_keyword = mb_strtolower( $keyword );
        $count = mb_substr_count( $lower_text, $lower_keyword );

        if ( $count === 0 ) {
            return false;
        }

        // Split text into sentences
        // A simple regex to split by . ! ? followed by space or end of string
        $sentences = preg_split('/(?<=[.?!])\s+(?=[a-z])/i', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ( empty( $sentences ) ) {
            $sentences = array( $text );
        }

        $keyword_sentence_index = -1;
        foreach ( $sentences as $index => $sentence ) {
            if ( mb_strpos( mb_strtolower( $sentence ), $lower_keyword ) !== false ) {
                $keyword_sentence_index = $index;
                break;
            }
        }

        if ( $keyword_sentence_index === -1 ) {
            // Fallback if sentence splitting failed to find it (should rarely happen)
            return array(
                'context' => mb_substr( $text, 0, 200 ) . '...',
                'count'   => $count
            );
        }

        // Grab 1 sentence before, the sentence itself, and 1 sentence after (total ~3 sentences)
        $start_index = max( 0, $keyword_sentence_index - 1 );
        $end_index = min( count( $sentences ) - 1, $keyword_sentence_index + 1 );

        $context_sentences = array();
        for ( $i = $start_index; $i <= $end_index; $i++ ) {
            $context_sentences[] = $sentences[$i];
        }

        $context_text = implode( ' ', $context_sentences );

        return array(
            'context' => $context_text,
            'count'   => $count
        );
    }
}
