<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class The_Link_Goblin_Metabox {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'check_content_change' ), 10, 3 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_notices', array( $this, 'display_scan_notice' ) );
    }

    public function enqueue_scripts( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        wp_enqueue_style( 'the-link-goblin-metabox', THE_LINK_GOBLIN_URL . 'assets/metabox.css', array(), THE_LINK_GOBLIN_VERSION );
        wp_enqueue_script( 'the-link-goblin-metabox', THE_LINK_GOBLIN_URL . 'assets/metabox.js', array( 'jquery' ), THE_LINK_GOBLIN_VERSION, true );

        global $post;
        wp_localize_script( 'the-link-goblin-metabox', 'theLinkGoblinMeta', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'the_link_goblin_scan_nonce' ),
            'post_id'  => $post ? $post->ID : 0,
        ) );
    }

    public function add_meta_box() {
        $screens = array( 'post', 'page', 'glossary' );
        foreach ( $screens as $screen ) {
            add_meta_box(
                'the_link_goblin_suggestions_mb',
                'The Link Goblin - Suggestions',
                array( $this, 'render_meta_box' ),
                $screen,
                'side',
                'high'
            );
        }
    }

    public function render_meta_box( $post ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'the_link_goblin_suggestions';

        $suggestions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d ORDER BY created_at DESC",
            $post->ID
        ) );

        echo '<div id="tlg-metabox-container">';
        echo '<div id="tlg-suggestions-wrapper">';
        echo self::render_suggestions_html( $post->ID );
        echo '</div>'; // End wrapper

        echo '<hr>';
        echo '<p><label><input type="checkbox" id="tlg-allow-new-suggestions" checked="checked"> Include new text suggestions if no exact matches found</label></p>';
        echo '<button type="button" id="tlg-metabox-scan-btn" class="button button-primary">Scan For Links</button>';
        echo '<span id="tlg-metabox-scan-status"></span>';
        echo '</div>';
    }

    public static function render_suggestions_html( $post_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'the_link_goblin_suggestions';

        $suggestions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d ORDER BY created_at DESC",
            $post_id
        ) );

        ob_start();

        if ( ! empty( $suggestions ) ) {
            echo '<ul class="tlg-suggestions-list">';
            foreach ( $suggestions as $sugg ) {
                $target_title = get_the_title( $sugg->target_post_id );
                $target_url   = get_permalink( $sugg->target_post_id );

                $type_label = '';
                if ( isset( $sugg->is_existing_text ) ) {
                    if ( (int) $sugg->is_existing_text === 1 ) {
                        $type_label = '<span class="tlg-badge tlg-badge-existing">Existing Text</span>';
                    } else {
                        $type_label = '<span class="tlg-badge tlg-badge-new">New Suggestion</span>';
                    }
                }

                echo '<li data-suggestion-id="' . esc_attr( $sugg->id ) . '" data-target-id="' . esc_attr( $sugg->target_post_id ) . '">';
                echo $type_label . '<br>';
                echo '<strong>Anchor:</strong> <code>' . esc_html( $sugg->anchor_text ) . '</code><br>';
                echo '<strong>Link To:</strong> <a href="' . esc_url( $target_url ) . '" target="_blank">' . esc_html( $target_title ) . '</a><br>';
                echo '<em>Context:</em> "' . esc_html( $sugg->context_sentence ) . '"<br>';
                echo '<button type="button" class="button tlg-mark-added-btn" style="margin-top: 8px;">Mark as Added</button>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p id="tlg-no-suggestions">No suggestions available. Scan the post to get started.</p>';
        }

        return ob_get_clean();
    }

    public function check_content_change( $post_id, $post, $update ) {
        // We moved this functionality to class-scanner.php so it applies everywhere,
        // but we'll leave this empty or remove the hook above. Since the hook is
        // in __construct, we'll keep the function signature and just return.
        // The actual logic is now in class-scanner.php's mark_post_for_rescan method.
        return;
    }

    public function display_scan_notice() {
        global $pagenow, $post;

        if ( $pagenow !== 'post.php' || ! $post ) {
            return;
        }

        $needs_rescan = get_post_meta( $post->ID, '_the_link_goblin_needs_rescan', true );
        if ( $needs_rescan ) {
            echo '<div class="notice notice-warning is-dismissible" id="tlg-editor-notice">';
            echo '<p><strong>The Link Goblin:</strong> Content changed. Would you like to scan this page for new link suggestions? ';
            echo '<button type="button" class="button button-small" id="tlg-notice-scan-btn">Scan Now</button>';
            echo '<span id="tlg-notice-scan-status" style="margin-left: 10px;"></span></p>';
            echo '</div>';
        }
    }
}
new The_Link_Goblin_Metabox();
