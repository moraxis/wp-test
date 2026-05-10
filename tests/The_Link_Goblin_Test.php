<?php

class The_Link_Goblin_Test {
    public function run($runner) {
        $this->test_scanner_bulk_insert($runner);
        $this->test_dashboard_get_link_counts($runner);
    }

    protected function test_dashboard_get_link_counts($runner) {
        if (!class_exists('The_Link_Goblin_Dashboard', false)) {
            require_once __DIR__ . '/../the-link-goblin/includes/class-dashboard.php';
        }

        $dashboard = new The_Link_Goblin_Dashboard();

        $site_url = home_url();

        $scenarios = array(
            'Empty string' => array(
                'content' => '',
                'expected' => array('internal' => 0, 'external' => 0, 'duplicate' => false)
            ),
            'No links' => array(
                'content' => 'Just plain text.',
                'expected' => array('internal' => 0, 'external' => 0, 'duplicate' => false)
            ),
            'Internal absolute link' => array(
                'content' => '<a href="' . $site_url . '/about">About</a>',
                'expected' => array('internal' => 1, 'external' => 0, 'duplicate' => false)
            ),
            'Internal relative link' => array(
                'content' => '<a href="/contact">Contact</a>',
                'expected' => array('internal' => 1, 'external' => 0, 'duplicate' => false)
            ),
            'External link' => array(
                'content' => '<a href="https://example.org">External</a>',
                'expected' => array('internal' => 0, 'external' => 1, 'duplicate' => false)
            ),
            'Mixed links' => array(
                'content' => '<a href="/about">About</a> <a href="https://example.org">External</a>',
                'expected' => array('internal' => 1, 'external' => 1, 'duplicate' => false)
            ),
            'Duplicate links' => array(
                'content' => '<a href="/about">About</a> <a href="/about">About again</a>',
                'expected' => array('internal' => 2, 'external' => 0, 'duplicate' => true)
            ),
            'Ignored links' => array(
                'content' => '<a href="#">Top</a> <a href="mailto:test@example.com">Email</a> <a href="tel:12345">Phone</a> <a href="">Empty</a>',
                'expected' => array('internal' => 0, 'external' => 0, 'duplicate' => false)
            ),
            'Case insensitive and strange attributes' => array(
                'content' => '<A class="link" HREF="https://external.com">Link</A>',
                'expected' => array('internal' => 0, 'external' => 1, 'duplicate' => false)
            ),
        );

        $all_passed = true;
        foreach ($scenarios as $name => $scenario) {
            $result = $dashboard->get_link_counts($scenario['content']);
            if ($result !== $scenario['expected']) {
                $runner->recordFail("The Link Goblin Dashboard get_link_counts failed on '$name'. Expected: " . json_encode($scenario['expected']) . ", Got: " . json_encode($result));
                $all_passed = false;
            }
        }

        if ($all_passed) {
            $runner->recordPass("The Link Goblin Dashboard get_link_counts tests passed.");
        }
    }

    protected function test_scanner_bulk_insert($runner) {
        global $wpdb;

        // Ensure $wpdb is set up for counting
        $original_wpdb = $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public $insert_calls = 0;
            public $query_calls = 0;
            public function delete($table, $where, $format) {}
            public function insert($table, $data, $format) { $this->insert_calls++; }
            public function query($query) { $this->query_calls++; }
            public function prepare($query, $args) {
                if (!is_array($args)) { $args = array_slice(func_get_args(), 1); }
                $query = str_replace('%d', '%s', $query);
                $query = str_replace('%s', "'%s'", $query);
                return vsprintf($query, $args);
            }
        };

        if (!class_exists('The_Link_Goblin_Scanner', false)) {
            require_once __DIR__ . '/../the-link-goblin/includes/class-scanner.php';
        }

        $scanner = new The_Link_Goblin_Scanner();
        $inserted = $scanner->scan_post(1);

        if ($inserted === 3 && $wpdb->insert_calls === 0 && $wpdb->query_calls === 1) {
            $runner->recordPass("The Link Goblin Scanner uses bulk insert correctly.");
        } else {
            $runner->recordFail("The Link Goblin Scanner failed bulk insert test. Inserted: $inserted, Inserts: $wpdb->insert_calls, Queries: $wpdb->query_calls");
        }

        $wpdb = $original_wpdb;
    }
}
