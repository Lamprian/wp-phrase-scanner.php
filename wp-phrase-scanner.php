<?php
/**
 * WordPress Phrase Scanner
 *
 * Standalone WordPress utility script for scanning posts, pages, custom fields,
 * widgets, theme settings, and plugin options for a specific phrase.
 *
 * Usage:
 * 1. Upload this file to the WordPress root directory, where wp-load.php exists.
 * 2. Log in as a WordPress administrator.
 * 3. Open: https://your-domain.com/wp-phrase-scanner.php
 * 4. Review the results.
 * 5. Delete this file from the server immediately after use.
 *
 * Security note:
 * This file is intended for temporary diagnostic use only.
 * Do not leave it permanently available on a public website.
 */

declare(strict_types=1);

/**
 * Load the WordPress environment.
 * This file must be placed in the WordPress root directory.
 */
require_once __DIR__ . '/wp-load.php';

/**
 * Restrict access to logged-in WordPress administrators only.
 */
if (!is_user_logged_in() || !current_user_can('administrator')) {
    wp_die('Access denied. Administrator access is required.');
}

global $wpdb;

/**
 * Search phrase.
 *
 * Replace "your text" with the phrase you want to find.
 */
$phrase = 'your text';

if ($phrase === '') {
    wp_die('Search phrase is empty.');
}

/**
 * Count how many times a phrase appears inside a text.
 * The search is case-insensitive and UTF-8 safe.
 *
 * @param string $text   Text to search inside.
 * @param string $phrase Phrase to search for.
 *
 * @return int Number of appearances.
 */
function count_phrase_occurrences(string $text, string $phrase): int
{
    if ($text === '' || $phrase === '') {
        return 0;
    }

    $textLower = mb_strtolower($text, 'UTF-8');
    $phraseLower = mb_strtolower($phrase, 'UTF-8');

    return substr_count($textLower, $phraseLower);
}

/**
 * Convert HTML content to clean searchable text.
 *
 * @param string $text Raw or rendered HTML/text.
 *
 * @return string Clean searchable text.
 */
function clean_searchable_text(string $text): string
{
    $text = wp_strip_all_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $text;
}

/**
 * Convert stored WordPress values into searchable strings.
 *
 * This is useful for serialized arrays, JSON-like data, Elementor data,
 * theme settings, widgets, and plugin options.
 *
 * @param mixed $value Raw database value.
 *
 * @return string Searchable string.
 */
function convert_value_to_searchable_string($value): string
{
    $value = maybe_unserialize($value);

    if (is_array($value) || is_object($value)) {
        return (string) wp_json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    return '';
}

/**
 * Store scan results from posts and pages.
 */
$postResults = [];
$postGrandTotal = 0;

/**
 * Scan WordPress pages and blog posts.
 *
 * Included post types:
 * - page
 * - post
 *
 * Included statuses:
 * - publish
 * - draft
 * - private
 * - pending
 * - future
 */
$posts = $wpdb->get_results("
    SELECT ID, post_title, post_type, post_status, post_content
    FROM {$wpdb->posts}
    WHERE post_type IN ('page', 'post')
      AND post_status IN ('publish', 'draft', 'private', 'pending', 'future')
    ORDER BY post_type ASC, post_title ASC
");

foreach ($posts as $post) {
    $rawContent = (string) $post->post_content;

    /**
     * Count appearances in raw post_content.
     */
    $rawContentCount = count_phrase_occurrences($rawContent, $phrase);

    /**
     * Count appearances after WordPress renders the content.
     * This may include blocks, shortcodes, and content filters.
     */
    $renderedContent = apply_filters('the_content', $rawContent);
    $renderedText = clean_searchable_text($renderedContent);
    $renderedContentCount = count_phrase_occurrences($renderedText, $phrase);

    /**
     * Scan all post meta fields.
     *
     * This is important for Elementor, custom fields, page builders,
     * SEO plugins, and other metadata-based content.
     */
    $metaRows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value
             FROM {$wpdb->postmeta}
             WHERE post_id = %d",
            (int) $post->ID
        )
    );

    $metaCount = 0;
    $matchedMetaKeys = [];

    foreach ($metaRows as $meta) {
        $metaValue = convert_value_to_searchable_string($meta->meta_value);
        $count = count_phrase_occurrences($metaValue, $phrase);

        if ($count > 0) {
            $metaCount += $count;
            $matchedMetaKeys[] = (string) $meta->meta_key . ' (' . $count . ')';
        }
    }

    /**
     * Avoid double-counting the same content from raw and rendered content.
     * Keep the highest count between the two.
     */
    $contentCount = max($rawContentCount, $renderedContentCount);
    $postTotal = $contentCount + $metaCount;

    if ($postTotal > 0) {
        $postResults[] = [
            'id' => (int) $post->ID,
            'title' => (string) $post->post_title,
            'type' => (string) $post->post_type,
            'status' => (string) $post->post_status,
            'raw_content_count' => $rawContentCount,
            'rendered_content_count' => $renderedContentCount,
            'meta_count' => $metaCount,
            'matched_meta_keys' => implode(', ', $matchedMetaKeys),
            'total' => $postTotal,
            'edit_link' => get_edit_post_link((int) $post->ID, ''),
            'view_link' => get_permalink((int) $post->ID),
        ];

        $postGrandTotal += $postTotal;
    }
}

/**
 * Store scan results from WordPress options.
 *
 * This usually includes:
 * - widgets
 * - theme settings
 * - header/footer builder data
 * - plugin settings
 * - global configuration data
 */
$optionResults = [];
$optionGrandTotal = 0;

$options = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT option_name, option_value
         FROM {$wpdb->options}
         WHERE option_value LIKE %s
         ORDER BY option_name ASC",
        '%' . $wpdb->esc_like($phrase) . '%'
    )
);

foreach ($options as $option) {
    $optionValue = convert_value_to_searchable_string($option->option_value);
    $count = count_phrase_occurrences($optionValue, $phrase);

    if ($count > 0) {
        $optionResults[] = [
            'option_name' => (string) $option->option_name,
            'count' => $count,
        ];

        $optionGrandTotal += $count;
    }
}

$finalTotal = $postGrandTotal + $optionGrandTotal;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WordPress Phrase Scanner</title>
    <style>
        body {
            margin: 30px;
            color: #222;
            background: #f7f7f7;
            font-family: Arial, sans-serif;
        }

        h1,
        h2 {
            margin-bottom: 10px;
        }

        .summary {
            padding: 15px 20px;
            margin-bottom: 25px;
            background: #fff;
            border: 1px solid #ddd;
        }

        .warning {
            padding: 12px 15px;
            margin-bottom: 25px;
            background: #fff8e5;
            border: 1px solid #e5c46a;
        }

        table {
            width: 100%;
            margin-bottom: 35px;
            border-collapse: collapse;
            background: #fff;
        }

        th,
        td {
            padding: 8px 10px;
            font-size: 14px;
            vertical-align: top;
            border: 1px solid #ddd;
        }

        th {
            text-align: left;
            background: #eee;
        }

        .number {
            text-align: center;
            font-weight: bold;
        }

        .muted {
            color: #666;
            font-size: 13px;
        }

        a {
            color: #0066cc;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        code {
            padding: 2px 4px;
            background: #eee;
        }
    </style>
</head>
<body>

<h1>WordPress Phrase Scanner</h1>

<div class="warning">
    <strong>Security warning:</strong>
    This is a temporary diagnostic script. Delete it from the server immediately after use.
</div>

<div class="summary">
    <p><strong>Search phrase:</strong> <?php echo esc_html($phrase); ?></p>
    <p><strong>Total appearances in posts/pages:</strong> <?php echo (int) $postGrandTotal; ?></p>
    <p><strong>Total appearances in options/widgets/settings:</strong> <?php echo (int) $optionGrandTotal; ?></p>
    <p><strong>Final total appearances:</strong> <?php echo (int) $finalTotal; ?></p>
</div>

<h2>Posts and Pages</h2>

<?php if (empty($postResults)): ?>

    <p>The phrase was not found in posts or pages.</p>

<?php else: ?>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Post Type</th>
            <th>Status</th>
            <th>Raw Content</th>
            <th>Rendered Content</th>
            <th>Custom Fields</th>
            <th>Matched Meta Keys</th>
            <th>Total</th>
            <th>Links</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($postResults as $row): ?>
            <tr>
                <td class="number"><?php echo (int) $row['id']; ?></td>
                <td><?php echo esc_html($row['title']); ?></td>
                <td><?php echo esc_html($row['type']); ?></td>
                <td><?php echo esc_html($row['status']); ?></td>
                <td class="number"><?php echo (int) $row['raw_content_count']; ?></td>
                <td class="number"><?php echo (int) $row['rendered_content_count']; ?></td>
                <td class="number"><?php echo (int) $row['meta_count']; ?></td>
                <td><?php echo esc_html($row['matched_meta_keys']); ?></td>
                <td class="number"><?php echo (int) $row['total']; ?></td>
                <td>
                    <?php if (!empty($row['edit_link'])): ?>
                        <a href="<?php echo esc_url($row['edit_link']); ?>" target="_blank" rel="noopener noreferrer">Edit</a>
                    <?php endif; ?>

                    <?php if (!empty($row['view_link'])): ?>
                        |
                        <a href="<?php echo esc_url($row['view_link']); ?>" target="_blank" rel="noopener noreferrer">View</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

<h2>Options, Widgets, Theme Settings and Plugin Settings</h2>

<?php if (empty($optionResults)): ?>

    <p>The phrase was not found in WordPress options.</p>

<?php else: ?>

    <table>
        <thead>
        <tr>
            <th>Option Name</th>
            <th>Appearances</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($optionResults as $optionRow): ?>
            <tr>
                <td><?php echo esc_html($optionRow['option_name']); ?></td>
                <td class="number"><?php echo (int) $optionRow['count']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

<p class="muted">
    After completing the scan, delete <code>wp-phrase-scanner.php</code> from the server.
</p>

</body>
</html>
