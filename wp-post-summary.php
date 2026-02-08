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
const WPSG_OPTION_AI_PROVIDER = 'wpsg_ai_provider';
const WPSG_OPTION_OPENAI_KEY = 'wpsg_openai_api_key';
const WPSG_OPTION_OPENAI_MODEL = 'wpsg_openai_model';
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
    register_setting('wpsg_settings', WPSG_OPTION_AI_PROVIDER, [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'openai',
    ]);
    register_setting('wpsg_settings', WPSG_OPTION_OPENAI_KEY, [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);
    register_setting('wpsg_settings', WPSG_OPTION_OPENAI_MODEL, [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'gpt-4o-mini',
    ]);

    add_settings_section(
        'wpsg_main_section',
        'Summary Settings',
        '__return_false',
        'wpsg_settings'
    );

    add_settings_field(
        WPSG_OPTION_LENGTH,
        'Summary Length (max bullets)',
        'wpsg_render_length_field',
        'wpsg_settings',
        'wpsg_main_section'
    );

    add_settings_field(
        WPSG_OPTION_AI_PROVIDER,
        'AI Provider',
        'wpsg_render_provider_field',
        'wpsg_settings',
        'wpsg_main_section'
    );

    add_settings_field(
        WPSG_OPTION_OPENAI_KEY,
        'OpenAI API Key',
        'wpsg_render_openai_key_field',
        'wpsg_settings',
        'wpsg_main_section'
    );

    add_settings_field(
        WPSG_OPTION_OPENAI_MODEL,
        'OpenAI Model',
        'wpsg_render_openai_model_field',
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

function wpsg_render_provider_field(): void
{
    $value = get_option(WPSG_OPTION_AI_PROVIDER, 'openai');
    ?>
    <select name="<?php echo esc_attr(WPSG_OPTION_AI_PROVIDER); ?>">
        <option value="openai" <?php selected($value, 'openai'); ?>>OpenAI</option>
        <option value="local" <?php selected($value, 'local'); ?>>Local (no API)</option>
    </select>
    <?php
}

function wpsg_render_openai_key_field(): void
{
    $value = get_option(WPSG_OPTION_OPENAI_KEY, '');
    ?>
    <input
        type="password"
        name="<?php echo esc_attr(WPSG_OPTION_OPENAI_KEY); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="regular-text"
        autocomplete="off"
    />
    <p class="description">Stored in WordPress options; required for OpenAI summaries.</p>
    <?php
}

function wpsg_render_openai_model_field(): void
{
    $value = get_option(WPSG_OPTION_OPENAI_MODEL, 'gpt-4o-mini');
    ?>
    <input
        type="text"
        name="<?php echo esc_attr(WPSG_OPTION_OPENAI_MODEL); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="regular-text"
    />
    <?php
}

function wpsg_get_summary(int $post_id): string
{
    $length = absint(get_option(WPSG_OPTION_LENGTH, WPSG_DEFAULT_LENGTH));
    $content = get_post_field('post_content', $post_id);
    $content = wp_strip_all_tags(strip_shortcodes($content));

    $provider = get_option(WPSG_OPTION_AI_PROVIDER, 'openai');
    if ($provider === 'openai') {
        $summary = wpsg_generate_openai_summary($content, $length);
        if ($summary !== '') {
            return $summary;
        }
    }

    return wpsg_build_summary($content, $length);
}

function wpsg_build_summary(string $content, int $max_bullets): string
{
    if ($max_bullets <= 0) {
        return '';
    }

    $sentences = wpsg_split_sentences($content);
    if (empty($sentences)) {
        $trimmed = wp_trim_words($content, 40, '...');
        return wpsg_format_bullets([$trimmed]);
    }

    $bullets = [];
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if ($sentence === '') {
            continue;
        }

        $bullets[] = $sentence;
        if (count($bullets) >= $max_bullets) {
            break;
        }
    }

    if (empty($bullets)) {
        $trimmed = wp_trim_words($content, 40, '...');
        return wpsg_format_bullets([$trimmed]);
    }

    return wpsg_format_bullets($bullets);
}

function wpsg_split_sentences(string $content): array
{
    $content = trim(preg_replace('/\\s+/', ' ', $content));
    if ($content === '') {
        return [];
    }

    return preg_split('/(?<=[.!?])\\s+/', $content);
}

function wpsg_generate_openai_summary(string $content, int $max_bullets): string
{
    $api_key = trim((string) get_option(WPSG_OPTION_OPENAI_KEY, ''));
    if ($api_key === '' || $content === '') {
        return '';
    }

    $model = trim((string) get_option(WPSG_OPTION_OPENAI_MODEL, 'gpt-4o-mini'));
    $max_bullets = max(1, $max_bullets);

    $prompt = sprintf(
        "Summarize the following post into %d concise bullet points. Return only bullet points.\n\nPost:\n%s",
        $max_bullets,
        $content
    );

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'timeout' => 20,
        'body' => wp_json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant that summarizes posts into bullet points.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
        ]),
    ]);

    if (is_wp_error($response)) {
        return '';
    }

    $body = wp_remote_retrieve_body($response);
    if ($body === '') {
        return '';
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['choices'][0]['message']['content'])) {
        return '';
    }

    $summary = trim((string) $data['choices'][0]['message']['content']);
    if ($summary === '') {
        return '';
    }

    $bullets = wpsg_extract_bullets($summary);
    return wpsg_format_bullets($bullets);
}

function wpsg_extract_bullets(string $summary): array
{
    $lines = preg_split('/\\r?\\n/', $summary);
    $bullets = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $line = preg_replace('/^[-*\\d+.\\)]\\s*/', '', $line);
        $bullets[] = $line;
    }

    return $bullets;
}

function wpsg_format_bullets(array $bullets): string
{
    $filtered = array_values(array_filter($bullets, static function ($bullet): bool {
        return trim((string) $bullet) !== '';
    }));

    if (empty($filtered)) {
        return '';
    }

    $escaped = array_map('esc_html', $filtered);
    return '<ul><li>' . implode('</li><li>', $escaped) . '</li></ul>';
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
        '<section class="wpsg-summary"><h2>%s</h2>%s</section>',
        esc_html__('Summary', 'wp-post-summary'),
        wp_kses_post($summary)
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
        '<section class="wpsg-summary"><h2>%s</h2>%s</section>',
        esc_html__('Summary', 'wp-post-summary'),
        wp_kses_post($summary)
    );
}
