  ___  _     _                         
 / _ \| |   | |                        
/ /_\ \ |__ | | _____  _ __  ___ _   _ 
|  _  | '_ \| |/ / _ \| '_ \/ __| | | |
| | | | | | |   < (_) | | | \__ \ |_| |
\_| |_/_| |_|_|\_\___/|_| |_|___/\__,_|
                                       
                                      
		
		\||/
                |  @___oo
      /\  /\   / (__,,,,|
     ) /^\) ^\/ _)
     )   /^\/   _)
     )   _ /  / _)
 /\  )/\/ ||  | )_)
<  >      |(,,) )__)
 ||      /    \)___)\
 | \____(      )___) )___
  \______(_______;;; __;;;
  
                     .

# Gravity Forms Enhanced Tools

## Description
Gravity Forms Enhanced Tools is a WordPress plugin that extends Gravity Forms with advanced email domain validation and spam filtering capabilities. It allows you to restrict or allow form submissions based on email domains and block spam entries using WordPress’s Disallowed Comment Keys.

## Features

### Email Domain Validator
- **Restrict Domains**: Limit submissions to specific domains or ban specific domains.
- **Multiple Forms**: Apply validation to one or more Gravity Forms by specifying Form IDs.
- **Custom Messages**: Set a custom validation message or hide it entirely for silent failure.
- **Flexible Configuration**: Choose between "Limit" (whitelist) or "Ban" (blacklist) modes.

### Spam Filter
- **Disallowed Keys Integration**: Uses WordPress’s Disallowed Comment Keys to flag spam entries.
- **Field Flexibility**: Optionally check all form fields or limit to specific types (email, name, phone, company, message).
- **Entry Blocking**: Add terms directly from the plugin to block spam submissions.

## Installation
1. **Upload**: Upload the `gravity-forms-enhanced-tools` folder to your `/wp-content/plugins/` directory.
2. **Activate**: Go to **Plugins** in WordPress admin and activate "Gravity Forms Enhanced Tools."
3. **Configure**: Click the "Settings" link next to the plugin on the Plugins page, or navigate to **Settings > GF Enhanced Tools**.

## Requirements
- WordPress 4.0 or higher
- Gravity Forms plugin (active)
- PHP 5.6 or higher (7.0+ recommended)

## Usage

### Accessing Settings
- On the **Plugins** page, find "Gravity Forms Enhanced Tools" and click the "Settings" link next to "Deactivate."
- Alternatively, go to **Settings > GF Enhanced Tools** in the WordPress admin menu.

### Configuring Email Domain Validator
1. **Enable**: Check "Email Domain Validator" to activate (enabled by default).
2. **Form IDs**: Enter one or more Gravity Form IDs (comma-separated, e.g., `152, 153`) to apply validation to specific forms.
3. **Field ID**: Enter the ID of the email field to validate (applies to all specified forms).
4. **Validation Mode**:
   - **Limit to these domains**: Only allows emails from domains listed in "Restricted Domains" (e.g., `gmail.com` only allows `user@gmail.com`).
   - **Ban these domains**: Blocks emails from listed domains, allows all others (e.g., bans `gmail.com`, allows `user@yahoo.com`).
5. **Restricted Domains**: Enter one domain per line (e.g., `gmail.com`, `hotmail.com`). These are the domains affected by the chosen mode.
6. **Validation Message**: Customize the message shown for invalid emails (use `%s` for the domain). Leave blank for default: "Oh no! `<strong>%s</strong>` email accounts are not eligible for this form."
7. **Hide Validation Message**: Check to suppress the message, making the form fail silently for restricted domains.
8. **Save**: Click "Save Changes" to apply settings.

### Configuring Spam Filter
1. **Enable**: Check "Spam Filter" to activate (enabled by default).
2. **Check All Fields for Spam**:
   - **Unchecked**: Only checks email, name (First Name/Last Name), phone, company, and message fields for spam terms.
   - **Checked**: Scans all form fields (e.g., text, textarea, select) for spam terms.
3. **Entry Block Terms**: Enter one term per line (e.g., `spam`, `viagra`) to block submissions containing these terms. These are added to WordPress’s Disallowed Comment Keys when saved (if spam filter is enabled).
4. **Save**: Click "Save Changes" to apply settings.

### Managing Disallowed Comment Keys
- Terms entered in "Entry Block Terms" are appended to **Settings > Discussion > Disallowed Comment Keys**.
- Existing keys are preserved, and duplicates are avoided.
- Blocked terms apply to all forms when the spam filter is active.

## Testing
- **Email Validation**: Submit a form with an email from a restricted domain (e.g., `test@gmail.com` if `gmail.com` is listed):
  - "Limit" mode: Rejected unless listed.
  - "Ban" mode: Rejected if listed.
  - Check "Hide Validation Message" to ensure silent failure.
- **Spam Filter**: Add a term (e.g., `spam`) to "Entry Block Terms," save, then submit a form with that term in a field:
  - With "Check All Fields" off, only specific fields trigger it.
  - With it on, any field triggers it.

## Debugging
- Enable debugging in `wp-config.php`:
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);

- Check `wp-content/debug.log` for errors if issues arise.

## Support
Contact support@wpproatoz.com for assistance.


## Credits
- Email Domain Validator based on GW_Email_Domain_Validator class
- Spam Filter originally developed by Nikki Stokes (https://thebizpixie.com)

## Screenshots

1. **Admin Settings Page** - description.

![screenshot1](screenshot1.png)

2. ** Output** - answer

![screenshot2](screenshot2.png)

## Demo

You can view a demo of the plugin in action at [WPProAtoZ.com](https://wpproatoz.com/plugins).

## Changelog

### 1.0.0

- Initial release of 
- Added functionality 
- Added 
- **1.2**: Fixed undefined `hide_validation_message` warning.
- **1.3**: Added option to hide validation message.
- **1.4**: Added option to check all fields for spam.
- **1.5**: Fixed undefined variable warnings in spam filter.
- **1.3**: Added Entry Block Terms to manage disallowed keys.
- **1.7**: Updated to use `disallowed_keys` instead of deprecated `blacklist_keys`.
- **1.8**: Updated corrected issued with documentation page.


## Credits

## License

This plugin is licensed under the GPL v2 or later. For more information, please see the [GNU General Public License](https://www.gnu.org/licenses/gpl-2.0.html).

## Contributing

Contributions are welcome! Feel free to fork the repository at https://github.com/Ahkonsu/wpproatoz-gf-extras, submit issues, or create pull requests.

---

**Note:** This plugin uses any other credits to other code or coders
