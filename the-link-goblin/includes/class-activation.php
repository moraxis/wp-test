<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class The_Link_Goblin_Activation {

    public static function activate() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'the_link_goblin_suggestions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            target_post_id bigint(20) unsigned NOT NULL,
            anchor_text text NOT NULL,
            context_sentence text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY target_post_id (target_post_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Setup initial options if not present
        if ( false === get_option( 'the_link_goblin_api_key' ) ) {
            add_option( 'the_link_goblin_api_key', '' );
        }
        if ( false === get_option( 'the_link_goblin_api_model' ) ) {
            add_option( 'the_link_goblin_api_model', 'deepseek-chat' );
        }
    }
}
