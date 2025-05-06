# WPProAtoZ Enhanced Tools for Gravity Forms

![Plugin Version](https://img.shields.io/badge/version-2.6-blue.svg) ![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue.svg) ![License](https://img.shields.io/badge/license-GPLv2-green.svg)

A robust WordPress plugin that extends Gravity Forms with advanced email domain validation, minimum character length enforcement, sophisticated spam filtering, and spam prediction capabilities.

---

## Overview

The **WPProAtoZ Enhanced Tools for Gravity Forms** plugin supercharges your Gravity Forms experience by adding powerful tools to manage submissions effectively. Restrict or allow entries based on email domains, enforce minimum lengths for text and textarea fields, block spam with advanced filtering techniques (including regex, whole-word matching, repetitive pattern detection, and keyboard spam detection), and predict future spam using a custom database of terms from past submissions—all from an intuitive admin interface. Customize common words to fine-tune spam detection, ensuring precision and control over form data.

Developed by [WPProAtoZ](https://wpproatoz.com), this plugin is designed for administrators seeking enhanced security and usability for Gravity Forms.

---

## Features

- **Email Domain Validator**: Whitelist or blacklist domains, apply to multiple forms/fields, customize messages, and choose silent rejection.
- **Minimum Character Length**: Enforce a configurable minimum length (default: 5) for selected text and textarea fields.
- **Spam Filter**:
  - Block spam using WordPress’s Disallowed Comment Keys.
  - Support for regex patterns (e.g., `/\bviagra\b/i`).
  - Whole-word matching to prevent partial matches (e.g., "casino" won’t match "cassino").
  - Repetitive pattern detection for repeated words/phrases (e.g., "ShirleyShirleyShirley").
  - Keyboard spam detection for random, incoherent text (e.g., "dfasfasfs,gfyhsxyhzhzdfhyztded").
  - Customizable common words list to ignore insignificant words (e.g., "the", "and") during detection.
  - Option to scan all fields or limit to specific types (email, name, phone, company, message).
- **Spam Predictor**:
  - Build a form-specific spam term database.
  - Set frequency thresholds (default: 3) for blocking terms/phrases.
  - Manual term management (add, edit, delete) and bulk actions.
  - Integrate historical spam data from Gravity Forms.
  - Auto-cleanup of terms unseen for 90 days via a daily cron job.
- **Multi-Form Support**: Target specific forms and fields via an easy-to-use mapping interface.
- **Real-Time Management**: Add, edit, delete spam terms, and customize common words directly in the admin panel.
- **User-Friendly UI**: Enhanced settings page with clear section headers and detailed descriptions.
- **Dependency**: Requires the Gravity Forms plugin.

---

## Installation

1. **Download**: Grab the latest release from the [Releases](https://github.com/Ahkonsu/wpproatoz-gf-extras/releases) page.
2. **Upload**: In WordPress admin, go to `Plugins > Add New > Upload Plugin`, and upload the `.zip` file.
3. **Activate**: Activate via the `Plugins` menu.
4. **Verify Dependency**: Ensure Gravity Forms is installed and active.
5. **Configure**: Navigate to `Settings > GF Enhanced Tools` to start customizing.

Alternatively, clone this repository into your `/wp-content/plugins/` directory and activate it:
```bash
git clone https://github.com/Ahkonsu/wpproatoz-gf-extras.git wpproatoz-gf-extras
cd wpproatoz-gf-extras
```

---

## Requirements
- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher (8.3+ recommended)
- **Gravity Forms**: Active installation

---

## Configuration

### Email Domain Validator
- **Enable**: Activate via the checkbox (enabled by default).
- **Map Forms/Fields**: Select forms and email fields under "Enable domain validation and choose fields".
- **Mode**: Choose "Limit" (whitelist) or "Ban" (blacklist).
- **Domains**: List domains (one per line, e.g., `gmail.com`).
- **Message**: Customize or hide the validation message (default: "Oh no! `<strong>%s</strong>` email accounts are not eligible for this form.").

### Minimum Character Length
- **Enable**: Activate and set a minimum length (default: 5).
- **Map Fields**: Select text or textarea fields under "Enable Minimum Character Field Mapping and choose fields".

### Spam Filter
- **Enable**: Use Disallowed Comment Keys to block spam (enabled by default).
- **Scope**: Check all fields or limit to specific types (email, name, phone, company, message).
- **Regex Matching**: Enable to use regex patterns in Disallowed Comment Keys (e.g., `/\bviagra\b/i`).
- **Whole-Word Matching**: Enable to ensure non-regex terms match whole words only.
- **Repetitive Pattern Detection**: Enable and set a threshold (default: 3) to flag repeated words/phrases.
- **Keyboard Spam Detection**: Enable and set a threshold (0.0–1.0, default: 0.8) to flag random text.
- **Common Words**: Customize the list of words to ignore (e.g., "the", "and") in the "Common Words" textarea.
- **Terms**: Add block terms in "Entry Block Terms" (supports regex if enabled).

### Spam Predictor
- **Enable**: Build a spam term database (disabled by default).
- **Threshold**: Set frequency for blocking (default: 3).
- **Manage Terms**: Add, edit, delete terms in the "Spam Terms" tab, or perform bulk deletes.

### Customizing Common Words
- **Access**: In the Settings tab, under "Spam Filter and Predictor", find the "Common Words" textarea.
- **Modify**: Add new words (e.g., `hello`), remove existing ones (e.g., `the`), or replace the list entirely.
- **Save**: Update the list to affect spam detection (ignored words won’t trigger repetitive or keyboard spam checks).

---

## Testing
- **Email Validation**: Test restricted domains in both "Limit" and "Ban" modes.
- **Minimum Length**: Submit forms with short (e.g., "abc") and valid (e.g., 42+ characters) text inputs.
- **Spam Filter**:
  - Add terms (e.g., `spam`, `/\bfree money\b/i`) and test field-specific blocking.
  - Enable regex and whole-word matching, test with "casino" (flagged) and "cassino" (not flagged).
  - Enable repetitive pattern detection, test with "ShirleyShirleyShirleyShirley" (flagged if threshold is 3).
  - Enable keyboard spam detection, test with "dfasfasfs,gfyhsxyhzhzdfhyztded" (flagged).
  - Modify common words, test with "hello hello hello" (not flagged if `hello` is common).
- **Spam Predictor**: Mark spam entries, set thresholds, add manual terms (e.g., "buy now"), and verify blocking.

Enable debugging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```
Check `wp-content/debug.log` for logs like:
- `GFET: GravityForms_Enhanced_Tools initialized`
- `GFET: Common words loaded: ...`
- `GFET Spam Detected: ...`

---

## Support
Reach out at [support@wpproatoz.com](mailto:support@wpproatoz.com).

---

## Credits
- **Developed by**: [WPProAtoZ](https://wpproatoz.com)
- **Email Validator**: Based on GW_Email_Domain_Validator.
- **Spam Filter**: Originally by Nikki Stokes ([TheBizPixie](https://thebizpixie.com)).
- **Contributors**: Web321.co for selector function assistance.

---

## Contributing
Fork the repo, submit issues, or create pull requests at [github.com/Ahkonsu/wpproatoz-gf-extras](https://github.com/Ahkonsu/wpproatoz-gf-extras).

---

## License
Licensed under [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

---

## Demo
Check out the plugin in action at [WPProAtoZ.com](https://wpproatoz.com/plugins).

---

## Changelog

### Version 2.6
- **Customizable Common Words**: Added a textarea in the Settings tab to define a custom list of common words (e.g., "the", "and") to ignore during spam detection. Supports adding, removing, or replacing the default list, stored in `gf_enhanced_tools_common_words`.
- **Improved Logging**: Enhanced debug logging for common words loading and spam detection.

### Version 2.5
- **Keyboard Spam Detection**: Added detection for random, incoherent text (e.g., "dfasfasfs,gfyhsxyhzhzdfhyztded") to catch spam padding character counts. Configurable via a threshold (0.0–1.0, default: 0.8).
- **Settings UI**: Added "Enable Keyboard Spam Detection" and "Keyboard Spam Threshold" options under Spam Filter and Predictor.
- **Enhanced Spam Filter**: Integrated keyboard spam detection with existing checks.

### Version 2.4
- **Repetitive Pattern Detection**: Added detection for repeated words or phrases (e.g., "ShirleyShirleyShirley") to catch spam bypassing minimum character requirements. Configurable via a threshold (default: 3).
- **Settings UI**: Added "Enable Repetitive Pattern Detection" and "Repetition Threshold" options under Spam Filter and Predictor.
- **Field Mapping**: Ensured repetitive pattern detection respects form-specific field mappings.

### Version 2.3
- **Fixed Field Persistence**: Ensured "Email Form & Field Mapping" selections persist when saving other settings (e.g., "Hide Validation Message").
- **Fixed Minimum Character Mapping**: Resolved issue preventing form and field selectors from rendering in "Minimum Character Field Mapping".
- **Improved UI**: Reorganized settings page with clear headers for better usability.
- **Enhanced Debugging**: Added detailed logging for reliable issue resolution.

### Version 2.2
- **Added Minimum Character Length**: Enforce minimum lengths for text fields.
- **UI Improvements**: Clearer section headers and field mapping descriptions.
- **Bug Fix**: Fixed "Update Historical Spam Terms" error with undefined form IDs.

### Version 2.1
- **Bulk Delete**: Added bulk deletion for spam terms by form ID or all terms.
- **Schema Stability**: Improved `is_phrase` column updates during upgrades.

### Version 2.0
- **Spam Predictor**: Introduced term database, form-specific tracking, manual management, auto-cleanup, and historical data integration.
- **Enhanced Settings**: Better defaults and version checking.

### Earlier Versions
- **1.9**: Documentation updates.
- **1.8**: Fixed documentation page.
- **1.7**: Updated to `disallowed_keys`.
- **1.5**: Fixed spam filter warnings.
- **1.4**: Added all-field spam check.
- **1.3**: Added block terms and silent validation.
- **1.0**: Initial release.