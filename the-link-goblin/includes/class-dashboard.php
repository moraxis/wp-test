<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class The_Link_Goblin_Dashboard {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_the-link-goblin' ) {
            return;
        }

        wp_enqueue_style( 'the-link-goblin-dashboard', THE_LINK_GOBLIN_URL . 'assets/dashboard.css', array(), THE_LINK_GOBLIN_VERSION );
        wp_enqueue_script( 'the-link-goblin-dashboard', THE_LINK_GOBLIN_URL . 'assets/dashboard.js', array( 'jquery' ), THE_LINK_GOBLIN_VERSION, true );

        wp_localize_script( 'the-link-goblin-dashboard', 'theLinkGoblin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'the_link_goblin_scan_nonce' ),
        ) );
    }

    public function get_link_counts( $content ) {
        $internal = 0;
        $external = 0;
        $duplicate_warning = false;
        $urls_found = array();

        if ( empty( trim( $content ) ) ) {
            return array( 'internal' => 0, 'external' => 0, 'duplicate' => false );
        }

        $site_url = home_url();
        $site_host = wp_parse_url( $site_url, PHP_URL_HOST );

        // Extract all hrefs using regex (simple approach for general anchor tags)
        preg_match_all( '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/i', $content, $matches );

        if ( ! empty( $matches[2] ) ) {
            foreach ( $matches[2] as $url ) {
                $url = trim( $url );
                if ( empty( $url ) || strpos( $url, '#' ) === 0 || strpos( $url, 'mailto:' ) === 0 || strpos( $url, 'tel:' ) === 0 ) {
                    continue; // Skip anchor links, emails, and tel
                }

                if ( in_array( $url, $urls_found ) ) {
                    $duplicate_warning = true;
                } else {
                    $urls_found[] = $url;
                }

                // Check if internal
                if ( strpos( $url, $site_url ) === 0 || strpos( $url, '/' ) === 0 ) {
                    $internal++;
                } else {
                    $parsed_url = wp_parse_url( $url, PHP_URL_HOST );
                    if ( $parsed_url === $site_host ) {
                        $internal++;
                    } else {
                        $external++;
                    }
                }
            }
        }

        return array( 'internal' => $internal, 'external' => $external, 'duplicate' => $duplicate_warning );
    }

    public static function render() {
        global $wpdb;

        $instance = new self();

        $post_types = get_post_types( array( 'public' => true ) );

        // Get all relevant posts
        $posts = get_posts( array(
            'post_type'      => array_keys( $post_types ),
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => -1,
        ) );

        $table_name = $wpdb->prefix . 'the_link_goblin_suggestions';

        echo '<div class="wrap">';
        echo '<h1>The Link Goblin Dashboard</h1>';

        echo '<div class="tlg-dashboard-controls" style="margin-bottom: 20px;">';
        echo '<p><button id="tlg-scan-all" class="button button-primary">Scan All Pages</button></p>';
        echo '<div id="tlg-progress-container" style="display: none; width: 100%; max-width: 600px; background: #e0e0e0; border-radius: 4px; overflow: hidden; margin-top: 10px;">';
        echo '<div id="tlg-progress-bar" style="width: 0%; height: 24px; background: #46b450; transition: width 0.3s;"></div>';
        echo '</div>';
        echo '<p id="tlg-scan-status" style="font-weight: bold;"></p>';
        echo '</div>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Post Title</th>';
        echo '<th>Inbound (Internal)</th>';
        echo '<th>Outbound (External)</th>';
        echo '<th>Available Suggestions</th>';
        echo '<th>Action</th>';
        echo '</tr></thead>';
        echo '<tbody id="tlg-posts-table">';

        foreach ( $posts as $post ) {
            $counts = $instance->get_link_counts( $post->post_content );
            $suggestions_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE post_id = %d", $post->ID ) );

            $needs_rescan = get_post_meta( $post->ID, '_the_link_goblin_needs_rescan', true );
            $last_scanned = get_post_meta( $post->ID, '_the_link_goblin_last_scanned', true );

            $status_class = ( ! $last_scanned || $needs_rescan ) ? 'tlg-needs-scan' : 'tlg-scanned';

            $type_obj = get_post_type_object( $post->post_type );
            $type_label = $type_obj ? $type_obj->labels->singular_name : $post->post_type;

            // Format status for display
            $status_label = $post->post_status;
            if ( $status_label === 'publish' ) {
                $status_label = 'Published';
            } else {
                $status_label = ucfirst( $status_label );
            }

            echo '<tr data-post-id="' . esc_attr( $post->ID ) . '" class="' . esc_attr( $status_class ) . '">';
            echo '<td>';
            echo '<strong><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( $post->post_title ?: '(No Title)' ) . '</a></strong><br>';
            echo '<span style="color: #666; font-size: 0.9em;">' . esc_html( $type_label ) . ' &mdash; ' . esc_html( $status_label ) . '</span>';
            echo '</td>';

            echo '<td>' . intval( $counts['internal'] );
            if ( $counts['duplicate'] ) {
                echo ' <span class="dashicons dashicons-warning tlg-duplicate-warning" title="Duplicate links found in this post!"></span>';
            }
            echo '</td>';

            echo '<td>' . intval( $counts['external'] ) . '</td>';
            echo '<td class="tlg-sugg-count">' . intval( $suggestions_count ) . '</td>';

            echo '<td>';
            if ( ! $last_scanned || $needs_rescan ) {
                echo '<button class="button tlg-scan-single" data-id="' . esc_attr( $post->ID ) . '">Scan Post</button>';
            } else {
                echo '<button class="button tlg-scan-single" data-id="' . esc_attr( $post->ID ) . '">Re-scan</button>';
                echo ' <span class="dashicons dashicons-yes-alt tlg-success-icon"></span>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
new The_Link_Goblin_Dashboard();
