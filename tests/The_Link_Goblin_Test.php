<?php

class The_Link_Goblin_Test {
    public function run($runner) {
        $this->test_scanner_bulk_insert($runner);
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
