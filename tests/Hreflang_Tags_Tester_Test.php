<?php
require_once __DIR__ . '/mocks/wordpress.php';
require_once __DIR__ . '/../hreflang-tags-tester/hreflang-tags-tester.php';

// Mock get_plugins()
function get_plugins() {
    return array(
        'hreflang-tags-tester/hreflang-tags-tester.php' => array(
            'Name' => 'Hreflang Tags Tester',
            'Version' => '1.0.0',
            'Author' => 'Nikola Knezhevich'
        )
    );
}

function plugin_dir_path($file) {
    return dirname($file) . '/';
}

function plugin_dir_url($file) {
    return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
}

// Ensure init is called
Hreflang_API::init();
do_action('rest_api_init');

class Hreflang_Tags_Tester_Test {

    public function run($runner) {
        $this->test_permission_callback($runner);
        $this->test_parse_page_validation($runner);
        $this->test_language_validation($runner);
    }

    private function test_permission_callback($runner) {
        global $current_user_can_return, $mock_rest_routes;

        $current_user_can_return = true;
        if (call_user_func($mock_rest_routes['hreflang-tester/v1/parse']['permission_callback'])) {
             $runner->recordPass('Hreflang permission callback allows edit_posts user.');
        } else {
             $runner->recordFail('Hreflang permission callback denied edit_posts user.');
        }

        $current_user_can_return = false;
        if (!call_user_func($mock_rest_routes['hreflang-tester/v1/parse']['permission_callback'])) {
             $runner->recordPass('Hreflang permission callback denies user without edit_posts.');
        } else {
             $runner->recordFail('Hreflang permission callback allowed user without edit_posts.');
        }
    }

    private function test_parse_page_validation($runner) {
        $validator = new Hreflang_Validator();
        // Since we mocked wp_safe_remote_get to return 'mock content' without links, it should just return the url with empty alternates
        $result = $validator->parse_page('http://example.com');

        if (!is_wp_error($result) && $result['type'] === 'page' && $result['urls'][0]['url'] === 'http://example.com') {
            $runner->recordPass('Hreflang parse_page basic structure works.');
        } else {
            $runner->recordFail('Hreflang parse_page structure failed.');
        }
    }

    private function test_language_validation($runner) {
         $validator = new Hreflang_Validator();

         // Mock wp_safe_remote_get for validation
         global $mock_remote_get_args;

         $result = $validator->validate_target('http://example.com/en', 'http://example.com/fr', 'invalid_lang');

         if (!$result['lang_valid']) {
              $runner->recordPass('Hreflang validator catches invalid language codes.');
         } else {
              $runner->recordFail('Hreflang validator failed to catch invalid language code.');
         }

         $result2 = $validator->validate_target('http://example.com/en', 'http://example.com/fr', 'fr-FR');
         if ($result2['lang_valid']) {
             $runner->recordPass('Hreflang validator accepts valid language codes (fr-FR).');
         } else {
             $runner->recordFail('Hreflang validator failed to accept valid language code (fr-FR).');
         }
    }
}
