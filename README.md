# Enhanced Tools for Gravity Forms 
# WPProAtoZ Enhanced Tools for Gravity Forms

![Plugin Version](https://img.shields.io/badge/version-2.3-blue.svg) ![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue.svg) ![License](https://img.shields.io/badge/license-GPLv2-green.svg)

A robust WordPress plugin that extends Gravity Forms with advanced email domain validation, minimum character length enforcement, spam filtering, and spam prediction capabilities.

---

## Overview

The **WPProAtoZ Enhanced Tools for Gravity Forms** plugin supercharges your Gravity Forms experience by adding powerful tools to manage submissions effectively. Restrict or allow entries based on email domains, enforce minimum lengths for text and textarea fields, block spam with WordPress’s Disallowed Comment Keys, and predict future spam using a custom database of terms from past submissions—all from an intuitive admin interface.

Developed by [WPProAtoZ](https://wpproatoz.com), this plugin is designed for administrators seeking precision and control over Gravity Forms data.

---

## Features

- **Email Domain Validator**: Whitelist or blacklist domains, apply to multiple forms/fields, customize messages, and choose silent rejection.
- **Minimum Character Length**: Enforce a configurable minimum length (default: 5) for selected text and textarea fields.
- **Spam Filter**: Block spam using Disallowed Comment Keys, with options to scan all fields or specific types (email, name, phone, etc.).
- **Spam Predictor**: Build a form-specific spam term database, set frequency thresholds (default: 3), manage terms manually or in bulk, and integrate historical data.
- **Multi-Form Support**: Target specific forms and fields via an easy-to-use mapping interface.
- **Real-Time Management**: Add, edit, delete spam terms, and perform bulk actions directly in the admin panel.
- **Auto-Cleanup**: Automatically removes spam terms unseen for 90 days via a daily cron job.
- **User-Friendly UI**: Enhanced settings page with clear section headers ("Enable domain validation and choose fields", "Enable Minimum Character Field Mapping and choose fields") for better usability.
- **Dependency**: Requires the Gravity Forms plugin.

---
## Configuration
Email Domain Validator
Enable: Activate via the checkbox.

Map Forms/Fields: Select forms and email fields under "Enable domain validation and choose fields".

Mode: Choose "Limit" (whitelist) or "Ban" (blacklist).

Domains: List domains (one per line, e.g., gmail.com).

Message: Customize or hide the validation message.

Minimum Character Length
Enable: Activate and set a minimum length (default: 5).

Map Fields: Select text or textarea fields under "Enable Minimum Character Field Mapping and choose fields".

Spam Filter
Enable: Use Disallowed Comment Keys to block spam.

Scope: Check all fields or limit to specific types.

Terms: Add block terms in "Entry Block Terms".

Spam Predictor
Enable: Build a spam term database.

Threshold: Set frequency for blocking (default: 3).

Manage Terms: Add, edit, delete terms in the "Spam Terms" tab.

Requirements
WordPress: 6.0 or higher

PHP: 8.0 or higher (8.3+ recommended)

Gravity Forms: Active installation

## Changelog
Version 2.3
Fixed Field Persistence: Ensured "Email Form & Field Mapping" selections persist when saving other settings (e.g., "Hide Validation Message").

Fixed Minimum Character Mapping: Resolved issue preventing form and field selectors from rendering in "Minimum Character Field Mapping".

Improved UI: Reorganized settings page with clear headers for better usability.

Enhanced Debugging: Added detailed logging for reliable issue resolution.

Version 2.2
Added Minimum Character Length: Enforce minimum lengths for text fields.

UI Improvements: Clearer section headers and field mapping descriptions.

Bug Fix: Fixed "Update Historical Spam Terms" error with undefined form IDs.

Version 2.1
Bulk Delete: Added bulk deletion for spam terms by form ID or all terms.

Schema Stability: Improved is_phrase column updates during upgrades.

Version 2.0
Spam Predictor: Introduced term database, form-specific tracking, manual management, auto-cleanup, and historical data integration.

Enhanced Settings: Better defaults and version checking.

## Earlier Versions
1.9: Documentation updates.

1.8: Fixed documentation page.

1.7: Updated to disallowed_keys.

1.5: Fixed spam filter warnings.

1.4: Added all-field spam check.

1.3: Added block terms and silent validation.

1.0: Initial release.

## Testing
Email Validation: Test restricted domains in both modes.

Minimum Length: Submit forms with short and valid text inputs.

Spam Filter: Add terms and test field-specific blocking.

Spam Predictor: Mark spam, set thresholds, and verify term blocking.

Enable debugging in wp-config.php:


## Installation

1. **Download**: Grab the latest release from the [Releases](https://github.com/Ahkonsu/wpproatoz-gf-extras/releases) page.
2. **Upload**: In WordPress admin, go to `Plugins > Add New > Upload Plugin`, and upload the `.zip` file.
3. **Activate**: Activate via the `Plugins` menu.
4. **Verify Dependency**: Ensure Gravity Forms is installed and active.
5. **Configure**: Navigate to `Settings > GF Enhanced Tools` to start customizing.

## Support
Reach out at support@wpproatoz.com (mailto:support@wpproatoz.com).
Credits
Developed by: WPProAtoZ

Email Validator: Based on GW_Email_Domain_Validator.

Spam Filter: Originally by Nikki Stokes (TheBizPixie).

## Contributing
Fork the repo, submit issues, or create pull requests at github.com/Ahkonsu/wpproatoz-gf-extras.
License
Licensed under GPLv2 or later.
## Demo
Check out the plugin in action at WPProAtoZ.com.



Alternatively, clone this repository into your `/wp-content/plugins/` directory and activate it:
```bash
git clone https://github.com/Ahkonsu/wpproatoz-gf-extras.git wpproatoz-gf-extras
cd wpproatoz-gf-extras
