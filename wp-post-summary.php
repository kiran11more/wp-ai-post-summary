<?php
/**
 * Plugin Name: WP Post Summary
 * Description: Generates a concise summary for posts and appends it to the content or via shortcode.
 * Version: 1.0.0
 * Author: Codex
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

const WPSG_OPTION_LENGTH = 'wpsg_summary_length';
const WPSG_DEFAULT_LENGTH = 40;

add_action('admin_init', 'wpsg_register_settings');
add_action('admin_menu', 'wpsg_register_settings_page');
add_filter('the_content', 'wpsg_append_summary_to_content');
add_shortcode('post_summary', 'wpsg_shortcode_summary');

function wpsg_register_settings(): void
{
    register_setting('wpsg_settings', WPSG_OPTION_LENGTH, [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => WPSG_DEFAULT_LENGTH,
    ]);

    add_settings_section(
        'wpsg_main_section',
        'Summary Settings',
        '__return_false',
        'wpsg_settings'
    );

    add_settings_field(
        WPSG_OPTION_LENGTH,
        'Summary Length (words)',
        'wpsg_render_length_field',
        'wpsg_settings',
        'wpsg_main_section'
    );
}

function wpsg_register_settings_page(): void
{
    add_options_page(
        'WP Post Summary',
        'WP Post Summary',
        'manage_options',
        'wpsg-settings',
        'wpsg_render_settings_page'
    );
}

function wpsg_render_settings_page(): void
{
    ?>
    <div class="wrap">
        <h1>WP Post Summary</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wpsg_settings');
            do_settings_sections('wpsg_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function wpsg_render_length_field(): void
{
    $value = get_option(WPSG_OPTION_LENGTH, WPSG_DEFAULT_LENGTH);
    ?>
    <input
        type="number"
        min="5"
        max="200"
        step="1"
        name="<?php echo esc_attr(WPSG_OPTION_LENGTH); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="small-text"
    />
    <?php
}

function wpsg_get_summary(int $post_id): string
{
    if (has_excerpt($post_id)) {
        return wp_strip_all_tags(get_the_excerpt($post_id));
    }

    $length = absint(get_option(WPSG_OPTION_LENGTH, WPSG_DEFAULT_LENGTH));
    $content = get_post_field('post_content', $post_id);
    $content = wp_strip_all_tags(strip_shortcodes($content));

    return wp_trim_words($content, $length, '...');
}

function wpsg_append_summary_to_content(string $content): string
{
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $summary = wpsg_get_summary(get_the_ID());
    if ($summary === '') {
        return $content;
    }

    $summary_html = sprintf(
        '<section class="wpsg-summary"><h2>%s</h2><p>%s</p></section>',
        esc_html__('Summary', 'wp-post-summary'),
        esc_html($summary)
    );

    return $content . $summary_html;
}

function wpsg_shortcode_summary(): string
{
    $summary = wpsg_get_summary(get_the_ID());
    if ($summary === '') {
        return '';
    }

    return sprintf(
        '<section class="wpsg-summary"><h2>%s</h2><p>%s</p></section>',
        esc_html__('Summary', 'wp-post-summary'),
        esc_html($summary)
    );
}
