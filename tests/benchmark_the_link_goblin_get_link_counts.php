<?php
require_once __DIR__ . '/mocks/wordpress.php';
require_once __DIR__ . '/../the-link-goblin/includes/class-dashboard.php';

$content = '';
$num_links = 20000;
for ($i = 0; $i < $num_links; $i++) {
    $content .= '<p><a href="http://example.com/page/' . $i . '">Link ' . $i . '</a></p>' . "\n";
}

$dashboard = new The_Link_Goblin_Dashboard();

echo "Starting benchmark with $num_links links...\n";
$start_time = microtime(true);

$counts = $dashboard->get_link_counts($content);

$end_time = microtime(true);
$duration = $end_time - $start_time;

echo "Duration: " . number_format($duration, 4) . " seconds\n";
echo "Internal: " . $counts['internal'] . "\n";
echo "External: " . $counts['external'] . "\n";
echo "Duplicate: " . ($counts['duplicate'] ? 'true' : 'false') . "\n";
