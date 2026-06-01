<?php

class LLMS_Txt_Generator_Test {
    public function run($runner) {
        $this->test_missing_nonce($runner);
        $this->test_invalid_nonce($runner);
        $this->test_missing_url($runner);
        $this->test_fetch_error($runner);
        $this->test_non_200_status($runner);
        $this->test_empty_body($runner);
        $this->test_successful_scrape($runner);
    }

    protected function setup_post() {
        $_POST = array();
        $_REQUEST = array();
    }

    protected function test_missing_nonce($runner) {
        $this->setup_post();

        try {
            llmstxt_generator_scrape_url();
            $runner->recordFail("Scrape URL did not fail on missing nonce.");
        } catch (Exception $e) {
            $response = json_decode($e->getMessage(), true);
            if ($response['success'] === false && isset($response['data']['message']) && $response['data']['message'] === 'Security check failed.') {
                $runner->recordPass("Scrape URL failed correctly on missing nonce.");
            } else {
                $runner->recordFail("Scrape URL missing nonce error message mismatch.");
            }
        }
    }

    protected function test_invalid_nonce($runner) {
        $this->setup_post();
        $_POST['nonce'] = 'invalid_nonce';
        $_REQUEST['nonce'] = 'invalid_nonce';

        try {
            llmstxt_generator_scrape_url();
            $runner->recordFail("Scrape URL did not fail on invalid nonce.");
        } catch (Exception $e) {
            $response = json_decode($e->getMessage(), true);
            if ($response['success'] === false && isset($response['data']['message']) && $response['data']['message'] === 'Security check failed.') {
                $runner->recordPass("Scrape URL failed correctly on invalid nonce.");
            } else {
                $runner->recordFail("Scrape URL invalid nonce error message mismatch.");
            }
        }
    }

    protected function test_missing_url($runner) {
        $this->setup_post();
        $_POST['nonce'] = 'valid_nonce';
        $_REQUEST['nonce'] = 'valid_nonce';

        try {
            llmstxt_generator_scrape_url();
            $runner->recordFail("Scrape URL did not fail on missing URL.");
        } catch (Exception $e) {
            $response = json_decode($e->getMessage(), true);
            if ($response['success'] === false && isset($response['data']['message']) && $response['data']['message'] === 'No URL provided.') {
                $runner->recordPass("Scrape URL failed correctly on missing URL.");
            } else {
                $runner->recordFail("Scrape URL missing URL error message mismatch.");
            }
        }
    }

    protected function test_fetch_error($runner) {
        $this->setup_post();
        $_POST['nonce'] = 'valid_nonce';
        $_REQUEST['nonce'] = 'valid_nonce';
        $_POST['url'] = 'http://example.com/error';

        try {
            llmstxt_generator_scrape_url();
            $runner->recordFail("Scrape URL did not fail on WP_Error fetch.");
        } catch (Exception $e) {
            $response = json_decode($e->getMessage(), true);
            if ($response['success'] === false && isset($response['data']['message']) && strpos($response['data']['message'], 'Failed to fetch URL:') !== false) {
                $runner->recordPass("Scrape URL failed correctly on WP_Error fetch.");
            } else {
                $runner->recordFail("Scrape URL WP_Error fetch message mismatch.");
            }
        }
    }

    protected function test_non_200_status($runner) {
        $this->setup_post();
        $_POST['nonce'] = 'valid_nonce';
        $_REQUEST['nonce'] = 'valid_nonce';
        $_POST['url'] = 'http://example.com/?status=404';

        try {
            llmstxt_generator_scrape_url();
            $runner->recordFail("Scrape URL did not fail on non-200 status.");
        } catch (Exception $e) {
            $response = json_decode($e->getMessage(), true);
            if ($response['success'] === false && isset($response['data']['message']) && strpos($response['data']['message'], 'HTTP Status: 404') !== false) {
                $runner->recordPass("Scrape URL failed correctly on non-200 status.");
            } else {
                $runner->recordFail("Scrape URL non-200 status message mismatch.");
            }
        }
    }

    protected function test_empty_body($runner) {
        $this->setup_post();
        $_POST['nonce'] = 'valid_nonce';
        $_REQUEST['nonce'] = 'valid_nonce';
        $_POST['url'] = 'http://example.com/?empty_body=true';

        try {
            llmstxt_generator_scrape_url();
            $runner->recordFail("Scrape URL did not fail on empty body.");
        } catch (Exception $e) {
            $response = json_decode($e->getMessage(), true);
            if ($response['success'] === false && isset($response['data']['message']) && $response['data']['message'] === 'Empty response from URL.') {
                $runner->recordPass("Scrape URL failed correctly on empty body.");
            } else {
                $runner->recordFail("Scrape URL empty body message mismatch.");
            }
        }
    }

    protected function test_successful_scrape($runner) {
        $this->setup_post();
        $_POST['nonce'] = 'valid_nonce';
        $_REQUEST['nonce'] = 'valid_nonce';
        $_POST['url'] = 'http://example.com/?test_html=true';

        try {
            llmstxt_generator_scrape_url();
            $runner->recordFail("Scrape URL did not succeed as expected.");
        } catch (Exception $e) {
            $response = json_decode($e->getMessage(), true);
            if ($response['success'] === true && $response['data']['title'] === 'Mock Title' && $response['data']['description'] === 'Mock Description') {
                $runner->recordPass("Scrape URL succeeded and parsed title/description correctly.");
            } else {
                $runner->recordFail("Scrape URL success response mismatch.");
            }
        }
    }
}
