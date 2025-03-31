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
  
    
/*
Plugin Name: WPProAtoZ Enhanced Tools for Gravity Forms
Plugin URI: https://wpproatoz.com
Description: Enhanced Tools for Gravity Forms is a WordPress plugin that extends Gravity Forms with advanced email domain validation, spam filtering, and spam prediction using past submissions. Restrict or allow submissions by email domain, block spam with Disallowed Comment Keys, and predict spam with a custom terms database.
Version: 2.1
Requires at least: 6.0
Requires PHP: 8.0
Author: WPProAtoZ.com
Author URI: https://wpproatoz.com
Text Domain: gravity-forms-enhanced-tools
Update URI: https://github.com/Ahkonsu/wpproatoz-gf-extras/releases
GitHub Plugin URI: https://github.com/Ahkonsu/wpproatoz-gf-extras/releases
GitHub Branch: main
Requires Plugins: gravityforms
*/

# Gravity Forms Enhanced Tools

## Description
Gravity Forms Enhanced Tools is a WordPress plugin that extends Gravity Forms with advanced email domain validation, spam filtering, and spam prediction capabilities. It allows you to restrict or allow form submissions based on email domains, block spam entries using WordPress’s Disallowed Comment Keys, and predict future spam based on terms and phrases from past spam submissions.

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

### Spam Predictor
- **Spam Term Database**: Collects single words and phrases (up to 5 words) from spam-marked submissions into a custom database.
- **Form-Specific Tracking**: Tracks terms per form ID for targeted spam detection.
- **Threshold Control**: Set a frequency threshold for terms/phrases to be considered spam (e.g., block terms appearing 3+ times).
- **Manual Management**: Add, edit, or delete terms/phrases manually via the admin interface.
- **Bulk Delete**: Clear all terms or terms for specific forms with a single action.
- **Automatic Cleanup**: Removes terms unseen for 90 days via a daily scheduled task.
- **Historical Data**: Integrates past spam entries from Gravity Forms into the prediction model.

## Installation
1. **Upload**: Upload the `gravity-forms-enhanced-tools` folder to your `/wp-content/plugins/` directory.
2. **Activate**: Go to **Plugins** in WordPress admin and activate "Gravity Forms Enhanced Tools."
3. **Configure**: Click the "Settings" link next to the plugin on the Plugins page, or navigate to **Settings > GF Enhanced Tools**.

## Requirements
- WordPress 6.0 or higher
- Gravity Forms plugin (active)
- PHP 8.0 or higher (8.3+ recommended)

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

### Configuring Spam Predictor
1. **Enable**: Check "Spam Predictor" to activate (disabled by default).
2. **Spam Predictor Threshold**: Set the minimum frequency a term or phrase must appear in spam submissions to be blocked (default: 3).
   - **Lower values (1-2)**: Catch more spam but may flag legitimate submissions (e.g., "contact us").
   - **Higher values (5+)**: More conservative, reducing false positives but possibly missing spam.
   - Test with your form data; start at 3 and adjust based on results.
3. **Save**: Click "Save Changes" to apply settings.

### Managing Spam Terms
1. Go to **Settings > GF Enhanced Tools > Spam Terms**.
2. **Add Term/Phrase**:
   - Enter a term or phrase (min 3 characters, max 5 words, e.g., "buy now cheap"), set an initial frequency (e.g., 5), and specify a Form ID.
   - Click "Add Term" to insert it into the database.
3. **Edit Term**:
   - Adjust the frequency in the table and click "Update."
4. **Delete Term**:
   - Click "Delete" next to a term/phrase and confirm to remove it.
5. **Update Historical Terms**:
   - Click "Update Historical Spam Terms" to process all past spam entries for configured forms into the database.
6. **Bulk Delete Terms**:
   - Enter Form IDs (comma-separated, e.g., "152, 153") or leave blank, then click "Bulk Delete Terms" and confirm.
   - Blank deletes all terms; specific IDs delete only terms for those forms.
7. **Automatic Cleanup**: Terms unseen for 90 days are removed daily.

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
- **Spam Predictor**: Enable the predictor, mark entries as spam in Gravity Forms, add a manual phrase (e.g., "buy now" with frequency 5), and test submissions with that phrase to confirm blocking. Adjust the threshold and use bulk delete to manage terms.

## Debugging
- Enable debugging in `wp-config.php`:
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);

- Check `wp-content/debug.log` for errors if issues arise.

## Support
Contact support@wpproatoz.com for assistance.

## Credits
- Email Domain Validator based on GW_Email_Domain_Validator class.
- Spam Filter originally developed by Nikki Stokes (https://thebizpixie.com).

## Screenshots
1. **Admin Settings Page** - Configure email validation, spam filter, and spam predictor settings.
 ![screenshot1](screenshot1.png)
2. **Spam Terms Management** - Add, edit, delete, and bulk delete spam terms/phrases.
 ![screenshot2](screenshot2.png)

## Demo
You can view a demo of the plugin in action at [WPProAtoZ.com](https://wpproatoz.com/plugins).

## Changelog
### 2.1
- **Bulk Delete**: Added ability to delete all spam terms or terms for specific Form IDs in one action from the "Spam Terms" tab.
- **Stabilized Schema Updates**: Improved table update logic to ensure `is_phrase` column is added correctly during upgrades.

### 2.0
- **Major Update**: Added Spam Predictor feature:
- Collects single words and phrases (up to 5 words) from spam-marked submissions.
- Form-specific tracking with `form_id`.
- Manual term/phrase management (add, edit, delete).
- Automatic cleanup of terms unseen for 90 days.
- Integration of historical spam entries from Gravity Forms.
- Enhanced settings with better defaults and version checking for upgrades.
- Consolidated previous updates (1.0-1.9) into this release.

### 1.9 and Earlier
- **1.9**: Updated documentation and minor fixes.
- **1.8**: Corrected issues with documentation page.
- **1.7**: Updated to use `disallowed_keys` instead of deprecated `blacklist_keys`.
- **1.5**: Fixed undefined variable warnings in spam filter.
- **1.4**: Added option to check all fields for spam.
- **1.3**: Added Entry Block Terms and option to hide validation message.
- **1.0**: Initial release with email domain validation and basic spam filtering.

## License
This plugin is licensed under the GPL v2 or later. For more information, please see the [GNU General Public License](https://www.gnu.org/licenses/gpl-2.0.html).

## Contributing
Contributions are welcome! Feel free to fork the repository at https://github.com/Ahkonsu/wpproatoz-gf-extras, submit issues, or create pull requests.

---

**Note:** This plugin uses credits to other code or coders as noted in the Credits section.