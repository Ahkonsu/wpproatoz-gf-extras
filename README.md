# Gravity Forms Enhanced Tools
# WPProAtoZ Enhanced Tools for Gravity Forms

![Plugin Version](https://img.shields.io/badge/version-2.2-blue.svg) ![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue.svg) ![License](https://img.shields.io/badge/license-GPLv2-green.svg)

A robust WordPress plugin that extends Gravity Forms with advanced email domain validation, minimum character length enforcement, spam filtering, and spam prediction capabilities.

---

## Overview

The **WPProAtoZ Enhanced Tools for Gravity Forms** plugin supercharges your Gravity Forms experience by adding powerful tools to manage submissions effectively. Restrict or allow entries based on email domains, enforce minimum text field lengths, block spam with WordPress’s Disallowed Comment Keys, and predict future spam using a custom database of terms from past submissions—all from an intuitive admin interface.

Developed by [WPProAtoZ](https://wpproatoz.com), this plugin is designed for administrators seeking precision and control over Gravity Forms data.

---

## Features

- **Email Domain Validator**: Whitelist or blacklist domains, apply to multiple forms/fields, customize messages, and choose silent rejection.
- **Minimum Character Length**: Enforce a configurable minimum length (default: 5) for selected text fields.
- **Spam Filter**: Block spam using Disallowed Comment Keys, with options to scan all fields or specific types (email, name, phone, etc.).
- **Spam Predictor**: Build a form-specific spam term database, set frequency thresholds (default: 3), manage terms manually or in bulk, and integrate historical data.
- **Multi-Form Support**: Target specific forms and fields via an easy-to-use mapping interface.
- **Real-Time Management**: Add, edit, delete spam terms, and perform bulk actions directly in the admin panel.
- **Auto-Cleanup**: Automatically removes spam terms unseen for 90 days via a daily cron job.
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