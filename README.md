# WordPress Phrase Scanner

A standalone WordPress diagnostic script that scans posts, pages, custom fields, widgets, theme settings, and plugin options for a specific phrase.

## Purpose

This script helps WordPress administrators find how many times a specific phrase appears across a WordPress installation.

It scans:

- WordPress pages
- WordPress posts
- Raw `post_content`
- Rendered content after WordPress filters
- Custom fields and post meta
- Elementor data
- Theme options
- Widget data
- Plugin settings stored in `wp_options`

## Default Search Phrase

The default demo search phrase is:

```text
your text

To change it, edit this line:
$phrase = 'your text';

Installation
Upload wp-phrase-scanner.php to the WordPress root directory, where wp-load.php exists.
Example:
/public_html/wp-phrase-scanner.php

Usage
Log in to WordPress as an administrator.
Open the script in your browser:
https://your-domain.com/wp-phrase-scanner.php

Review the results.
Delete the file from the server immediately after use.
Security Notice

This script is intended for temporary diagnostic use only.

Do not leave it permanently available on a public website.

Access is restricted to logged-in WordPress administrators, but the file should still be removed after the scan is complete.

Requirements
WordPress
PHP 7.4 or newer recommended
Administrator account
Output

The script displays:

Total appearances in posts and pages
Total appearances in options, widgets, and settings
Final total appearances
A detailed table with post/page IDs, titles, statuses, edit links, view links, and match counts
A detailed table of matched WordPress options
