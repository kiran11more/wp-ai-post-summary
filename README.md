# WP AI Post Summary

A WordPress plugin that automatically generates AI-powered summaries for blog posts and displays them at the top of each post.

## Features

- Automatically generates concise summaries of blog posts using AI
- Displays summary at the top of single post pages
- Clean, professional presentation
- Lightweight and efficient

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- AI API key (OpenAI, Anthropic Claude, or other supported provider)

## Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/wp-ai-post-summary/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your AI API settings in the plugin settings page

## Configuration

After activation, go to Settings → AI Post Summary to configure:
- AI provider selection
- API key
- Summary length preferences
- Display options

## How It Works

The plugin hooks into WordPress post content and generates a summary using your configured AI provider. The summary is cached to avoid repeated API calls for the same post.

## Roadmap

- [ ] Support for multiple AI providers (OpenAI, Anthropic, Gemini)
- [ ] Customizable summary templates
- [ ] Bulk generation for existing posts
- [ ] Summary regeneration on post update

## Technical Details

Built with WordPress best practices:
- Uses WordPress hooks and filters
- Follows WordPress coding standards
- Proper sanitization and escaping
- Efficient caching mechanism

## Author

**Kiran M**
- Website: [kiranm.in](https://kiranm.in)
- GitHub: [@kiran11more](https://github.com/kiran11more)

## License

GPL v2 or later

## Support

For questions or issues, please open an issue on GitHub.
