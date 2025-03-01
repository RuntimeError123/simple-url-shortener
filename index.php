<?php
require 'config.php';

// Enable MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Establish database connection
    $db = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
    $db->set_charset("utf8mb4"); // Set character set to utf8mb4 for better compatibility
} catch (mysqli_sql_exception $e) {
    error('Could not connect to database: ' . $e->getMessage(), 500);
}

$accepts_json = isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/json' ? true : false;
if ($accepts_json) {
    header('Content-Type: application/json');
}

$response_code = 200;
$message = '';

$request_uri = $_SERVER['REQUEST_URI'];

if ($request_uri == '/') { 
    doNothing(); 
} elseif ($pw_stats == '' && preg_match('!^/stats(/?.*)$!', $request_uri) == 1) { 
    stats(); 
} elseif ($pw_stats != '' && preg_match('!^/stats/' . preg_quote($pw_stats, '!') . '/?$!', $request_uri) == 1) { 
    stats(); 
} elseif (preg_match("!^/[$valid_chars]+/?$!", $request_uri) == 1) { 
    processSlug(); 
} elseif ($pw_stats == '' && preg_match("!^/[$valid_chars]+/stats/?$!", $request_uri) == 1) { 
    slugStats(); 
} elseif ($pw_stats != '' && preg_match("!^/[$valid_chars]+/stats/" . preg_quote($pw_stats, '!') . "/?$!", $request_uri) == 1) { 
    slugStats(); 
} elseif ($pw_create == '' && preg_match("!^/[a-z0-9]+://.+$!", $request_uri) == 1) { 
    createLink(); 
} elseif ($pw_create != '' && preg_match("!^/" . preg_quote($pw_create, '!') . "/[a-z0-9]+://.+$!", $request_uri) == 1) { 
    createLink(); 
} else { 
    $response_code = 400;
    $message = 'Invalid request.';
}

function doNothing() {
    global $url_blank;

    if (strlen($url_blank) > 0) {
        header("Location: $url_blank");
        exit;
    } else {
        error("Nothing to do.");
    }
}

function processSlug() {
    global $db;

    $slug = trim($_SERVER['REQUEST_URI'], '/');

    $escaped_slug = mysqli_real_escape_string($db, $slug);
    $result = mysqli_query($db, "SELECT url FROM links WHERE slug = '$escaped_slug'") or error('Could not fetch slug from database.', 500);
    if ($result === false || mysqli_num_rows($result) == 0) error('slug not found', 404);

    mysqli_query($db, "UPDATE links SET visits = visits + 1, last_visited = NOW() WHERE slug = '$escaped_slug'") or error('Could not increment visit count.', 500);

    $escaped_referer = isset($_SERVER['HTTP_REFERER']) ? mysqli_real_escape_string($db, $_SERVER['HTTP_REFERER']) : '';
    $remote_address = $_SERVER['REMOTE_ADDR'];
    mysqli_query($db, "INSERT INTO visits (slug, visit_date, referer, remote_address) VALUES ('$escaped_slug', NOW(), '$escaped_referer', '$remote_address')") or error('Could not record visit.', 500);

    $row = mysqli_fetch_row($result);
    $url = $row[0];

    header('Location: ' . $url);
    exit;
}

function createLink() {
    global $base_url, $random_chars, $slug_length, $db, $pw_create, $accepts_json;

    $url = ltrim($_SERVER['REQUEST_URI'], '/');

    if ($pw_create != '') {
        $url = preg_replace('!^'. preg_quote($pw_create, '!') . '/(.+)$!', '$1', $url);
    }

    do {
        $possible_slug = '';
        for ($i = 0; $i < $slug_length; $i++) {
            $possible_slug .= $random_chars[rand(0, strlen($random_chars) - 1)];
        }
        $result = mysqli_query($db, "SELECT COUNT(*) FROM links WHERE slug = '$possible_slug'") or error('Could not generate new slug.', 500);
        $row = mysqli_fetch_row($result);
        $count = $row[0];
        if ($count == 0) {
            $slug = $possible_slug;
        }
    } while (is_null($slug));

    $escaped_url = mysqli_real_escape_string($db, $url) or error('Could not escape URL.', 500);

    $result = mysqli_query($db, "INSERT INTO links (slug, url, visits, created) VALUES ('$slug', '$escaped_url', 0, NOW())") or error('Could not insert new URL into the database.', 500);
    if ($result == false) error('Inserting into database failed.', 500);

    $short_url = $base_url . $slug;
    if ($accepts_json) {
        echo json_encode(array('short_url' => $short_url, 'slug' => $slug));
    } else {
        echo $short_url;
    }
}

function stats() {
    global $db, $base_url, $pw_stats, $accepts_json;

    $data = array();

    $result = mysqli_query($db, 'SELECT * FROM links ORDER BY created DESC LIMIT 50') or error('Could not fetch recent URLs.', 500);
    while ($link = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $referring_domains = array();
        $visits = mysqli_query($db, "SELECT * FROM visits WHERE slug = '{$link['slug']}'") or error('Could not fetch visits for slug.', 500);
        while ($visit = mysqli_fetch_array($visits, MYSQLI_ASSOC)) {
            if (strlen($visit['referer']) > 0) {
                $url_info = parse_url($visit['referer']);
                $scheme = $url_info['scheme'];
                $host = $url_info['host'];
                $domain = $scheme . '://' . $host;
                if (is_null($referring_domains[$domain])) {
                    $referring_domains[$domain] = 1;
                } else {
                    $referring_domains[$domain] = $referring_domains[$domain] + 1;
                }
            }
        }
        array_multisort($referring_domains, SORT_DESC);
        if (count($referring_domains) > 0) {
            $key = key($referring_domains);
            $link['top_referer'] = array('domain' => $key, 'visits' => $referring_domains[$key]);
        }
        $data[] = $link;
    }

    if ($accepts_json) {
        echo json_encode($data);
    } else {
        echo "<h1>Recent Clicks</h1>";
        echo "<table><thead><td><strong>Created</strong></td><td><strong>Slug</strong></td><td><strong>URL</strong></td><td><strong>Visits</strong></td><td><strong>Last Visit</strong></td><td><strong>Top Referer</strong></td><td></td></thead>";
        echo "<tbody>";
        foreach ($data as $link) {
            echo "<tr>";
            echo "<td>{$link['created']}</td>";
            echo "<td><a href='/{$link['slug']}'>{$link['slug']}</a></td>";
            echo "<td><a href='{$link['url']}'>{$link['url']}</a></td>";
            echo "<td>{$link['visits']}</td>";
            echo "<td>{$link['last_visited']}</td>";
            if (is_null($link['top_referer'])) {
                echo "<td></td>";
            } else {
                $s = $link['top_referer']['visits'] == 1 ? '' : 's';
                echo "<td><a href='" . $link['top_referer']['domain'] . "'>" . $link['top_referer']['domain'] . "</a> (" . $link['top_referer']['visits'] . " visit$s)</td>";
            }
            echo "<td><a href='/{$link['slug']}/stats/$pw_stats'>View Stats</a></td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    }
}

function slugStats() {
    global $db, $pw_stats, $valid_chars, $accepts_json;

    $slug = trim($_SERVER['REQUEST_URI'], '/');
    $slug = preg_replace("!^([$valid_chars]+)/stats.*$!", '$1', $slug);

    $escaped_slug = mysqli_real_escape_string($db, $slug);
    $result = mysqli_query($db, "SELECT * FROM links WHERE slug = '$escaped_slug'") or error('Could not fetch slug from database.', 500);
    if ($result === false || mysqli_num_rows($result) == 0) error('Slug not found.', 404);

    $data = array();
    $data['info'] = mysqli_fetch_array($result, MYSQLI_ASSOC);
    $data['visits'] = array();

    $referring_domains = array();
    $visits = mysqli_query($db, "SELECT * FROM visits WHERE slug = '$escaped_slug'") or error('Could not fetch visits for slug.', 500);
    while ($visit = mysqli_fetch_array($visits, MYSQLI_ASSOC)) {
        if (strlen($visit['referer']) > 0) {
            $url_info = parse_url($visit['referer']);
            $scheme = $url_info['scheme'];
            $host = $url_info['host'];
            $domain = $scheme . '://' . $host;
            if (is_null($referring_domains[$domain])) {
                $referring_domains[$domain] = 1;
            } else {
                $referring_domains[$domain] = $referring_domains[$domain] + 1;
            }
        }
        $data['visits'][] = $visit;
    }
    array_multisort($referring_domains, SORT_DESC);
    $data['referers'] = $referring_domains;

    array_multisort($data['visits'], SORT_DESC);

    if ($accepts_json) {
        echo json_encode($data);
    } else {
        echo "<h1>Stats for <a href='" . $data['info']['url'] . "'>" . $data['info']['url'] . "</a></h1>";
        echo "<ul>";
        echo "<li><strong>Created:</strong> " . $data['info']['created'] . "</li>";
        echo "<li><strong>Visits:</strong> " . $data['info']['visits'] . "</li>";
        echo "</ul>";

        echo "<h2>Top Referers</h2>";
        echo "<table><thead><td><strong>Domain</strong></td><td><strong>Visits</strong></td></thead><tbody>";
        foreach ($data['referers'] as $domain => $visits) {
            echo "<tr>";
            echo "<td><a href='$domain'>" . $domain . "</a></td>";
            echo "<td>" . $visits . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";

        echo "<h2>Recent Visits</h2>";
        echo "<table><thead><td><strong>Date</strong></td><td><strong>Referer</strong></td><td><strong>Remote address</strong></td></thead><tbody>";
        foreach ($data['visits'] as $visit) {
            echo "<tr>";
            echo "<td>" . $visit['visit_date'] . "</td>";
            echo "<td><a href='{$visit['referer']}'>{$visit['referer']}</a></td>";
            echo "<td>" . $visit['remote_address'] . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
}

function error($msg, $http_response_code = 200) {
    global $accepts_json, $url_not_found;

    if (($http_response_code == 404) && (strlen($url_not_found) > 0)) {
        header("Location: $url_not_found");
        exit;
    }

    http_response_code($http_response_code);

    if ($accepts_json) {
        echo json_encode(array('error' => $msg));
    } else {
        echo $msg;
    }

    exit;
}

// Close database connection
$db->close();

if ($response_code !== 200) {
    error($message, $response_code);
}

?>