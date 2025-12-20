# AI SEO Generator

A powerful WordPress plugin for automated SEO content generation using Google Gemini AI, with Google Sheets integration and scheduling capabilities.

## Features

- 🤖 **AI-Powered Content Generation** - Generate high-quality SEO articles using Google Gemini AI
- 📊 **Google Sheets Integration** - Import keywords and topics from Google Sheets
- ⏰ **Scheduling System** - Schedule content generation with customizable intervals
- 🖼️ **AI Image Generation** - Automatically generate featured images for articles
- 📧 **Email Notifications** - Get notified when articles are generated
- 🔄 **Auto-Updates** - Update the plugin directly from GitHub releases

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Google Gemini API key
- Google Cloud Service Account (for Sheets integration and image generation)

## Installation

1. Download the latest release from the [Releases page](https://github.com/codebyamos/AI-SEO-Generator/releases)
2. Upload the plugin folder to `/wp-content/plugins/`
3. Run `composer install` in the plugin directory (required for Google Sheets integration)
4. Activate the plugin through the WordPress admin
5. Go to **AI SEO Generator > Settings** to configure your API keys

## Configuration

### Google Gemini API Key
1. Go to [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Create a new API key
3. Enter the key in the plugin settings

### Google Sheets Integration
1. Create a service account in [Google Cloud Console](https://console.cloud.google.com/)
2. Download the JSON credentials file
3. Store the file securely outside your web root
4. Enter the path to the credentials file in the plugin settings
5. Share your Google Sheet with the service account email

## Usage

### Dashboard
View statistics and quick actions from the main dashboard.

### Queue Management
Add keywords manually or import from Google Sheets. Monitor the generation queue.

### Scheduler
Configure automatic content generation schedules.

### Settings
- API key configuration
- Content settings (post status, internal/external links)
- Email notification preferences
- Google Sheets connection

## Auto-Updates

This plugin supports automatic updates from GitHub. When a new release is available:
1. Go to **Plugins** in WordPress admin
2. Click "Check for updates" under AI SEO Generator
3. If an update is available, click "Update Now"

## Changelog

### 1.0.0
- Initial release
- AI content generation with Gemini
- Google Sheets integration
- Scheduling system
- Email notifications
- GitHub auto-updater

## License

GPL v2 or later

## Author

[Plixail](https://www.plixail.com)
