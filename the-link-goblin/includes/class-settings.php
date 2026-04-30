<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class The_Link_Goblin_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_settings_page() {
        // Add top-level menu
        add_menu_page(
            'The Link Goblin',
            'The Link Goblin',
            'manage_options',
            'the-link-goblin',
            array( $this, 'render_dashboard_page' ),
            'dashicons-admin-links',
            30
        );

        // Add settings submenu
        add_submenu_page(
            'the-link-goblin',
            'Settings - The Link Goblin',
            'Settings',
            'manage_options',
            'the-link-goblin-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'the_link_goblin_settings_group', 'the_link_goblin_api_key' );
        register_setting( 'the_link_goblin_settings_group', 'the_link_goblin_api_model' );

        add_settings_section(
            'the_link_goblin_main_section',
            'DeepSeek API Settings',
            null,
            'the-link-goblin-settings'
        );

        add_settings_field(
            'the_link_goblin_api_key',
            'API Key',
            array( $this, 'render_api_key_field' ),
            'the-link-goblin-settings',
            'the_link_goblin_main_section'
        );

        add_settings_field(
            'the_link_goblin_api_model',
            'Model',
            array( $this, 'render_api_model_field' ),
            'the-link-goblin-settings',
            'the_link_goblin_main_section'
        );
    }

    public function render_api_key_field() {
        $api_key = get_option( 'the_link_goblin_api_key', '' );
        echo '<input type="password" name="the_link_goblin_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
        echo '<p class="description">Enter your DeepSeek API key here.</p>';
    }

    public function render_api_model_field() {
        $model = get_option( 'the_link_goblin_api_model', 'deepseek-chat' );
        ?>
        <select name="the_link_goblin_api_model">
            <option value="deepseek-chat" <?php selected( $model, 'deepseek-chat' ); ?>>deepseek-chat</option>
            <option value="deepseek-reasoner" <?php selected( $model, 'deepseek-reasoner' ); ?>>deepseek-reasoner</option>
        </select>
        <p class="description">Select the model you want to use. 'deepseek-chat' is faster and usually sufficient.</p>
        <?php
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>The Link Goblin Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'the_link_goblin_settings_group' );
                do_settings_sections( 'the-link-goblin-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( class_exists( 'The_Link_Goblin_Dashboard' ) ) {
            The_Link_Goblin_Dashboard::render();
        }
    }
}
new The_Link_Goblin_Settings();
