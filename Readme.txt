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
Description: Enhanced Tools for Gravity Forms is a WordPress plugin that extends Gravity Forms with advanced email domain validation, spam filtering, minimum character length enforcement, and spam prediction using past submissions. Restrict or allow submissions by email domain, block spam with Disallowed Comment Keys, enforce text field length, and predict spam with a custom terms database.
Version: 2.6
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

# WPProAtoZ Enhanced Tools for Gravity Forms

## Description
WPProAtoZ Enhanced Tools for Gravity Forms is a powerful WordPress plugin that extends Gravity Forms with advanced features to enhance form security and usability. It provides robust email domain validation, sophisticated spam filtering with regex and pattern detection, minimum character length enforcement, and predictive spam analysis based on historical submissions. Key capabilities include restricting or allowing submissions by email domain, blocking spam using WordPress’s Disallowed Comment Keys, enforcing text field lengths, and predicting spam with a custom database of terms and phrases.

## Features

### Email Domain Validator
- **Restrict Domains**: Limit submissions to specific domains or ban specific domains.
- **Multiple Forms & Fields**: Apply validation to one or more Gravity Forms and their email fields via a mapping interface.
- **Custom Messages**: Set a custom validation message or hide it for silent failure.
- **Flexible Configuration**: Choose between "Limit" (whitelist) or "Ban" (blacklist) modes.

### Minimum Character Length
- **Enforce Length**: Set a minimum character requirement for selected text and textarea fields.
- **Field Mapping**: Apply to specific text or textarea fields within configured forms.
- **Customizable**: Enable/disable and set the minimum length (default: 5 characters).

### Spam Filter
- **Disallowed Keys Integration**: Uses WordPress’s Disallowed Comment Keys to flag spam entries.
- **Field Flexibility**: Optionally check all form fields or limit to specific types (email, name, phone, company, message).
- **Regex Support**: Enable regular expression matching for spam terms (e.g., `/\bviagra\b/i`).
- **Whole-Word Matching**: Prevent partial matches (e.g., "casino" won’t match "cassino") when enabled.
- **Repetitive Pattern Detection**: Flags submissions with repeated words or phrases (e.g., "ShirleyShirleyShirley" or "Please contact me by email" repeated) to bypass minimum character requirements.
- **Keyboard Spam Detection**: Identifies random, incoherent text (e.g., "dfasfasfs,gfyhsxyhzhzdfhyztded") used to pad character counts.
- **Custom Common Words**: Define a list of words (e.g., "the", "and") to ignore during spam detection, customizable via the admin interface.
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
1. **Upload**: Upload the `wpproatoz-gf-extras` folder to your `/wp-content/plugins/` directory.
2. **Activate**: Go to **Plugins** in WordPress admin and activate "WPProAtoZ Enhanced Tools for Gravity Forms."
3. **Configure**: Click the "Settings" link next to the plugin on the Plugins page, or navigate to **Settings > GF Enhanced Tools**.

## Requirements
- WordPress 6.0 or higher
- Gravity Forms plugin (active)
- PHP 8.0 or higher (8.3+ recommended)

## Usage

### Accessing Settings
- On the **Plugins** page, find "WPProAtoZ Enhanced Tools for Gravity Forms" and click the "Settings" link next to "Deactivate."
- Alternatively, go to **Settings > GF Enhanced Tools** in the WordPress admin menu.

### Configuring Email Domain Validator
1. **Enable**: Check "Email Domain Validator" to activate (enabled by default).
2. **Form & Field Mapping**: Select forms and their email fields to validate. Email fields are used for domain checks.
3. **Validation Mode**:
   - **Limit to these domains**: Only allows emails from domains listed in "Restricted Domains" (e.g., `gmail.com` only allows `user@gmail.com`).
   - **Ban these domains**: Blocks emails from listed domains, allows all others (e.g., bans `gmail.com`, allows `user@yahoo.com`).
4. **Restricted Domains**: Enter one domain per line (e.g., `gmail.com`, `hotmail.com`). These are the domains affected by the chosen mode.
5. **Validation Message**: Customize the message shown for invalid emails (use `%s` for the domain). Leave blank for default: "Oh no! `<strong>%s</strong>` email accounts are not eligible for this form."
6. **Hide Validation Message**: Check to suppress the message, making the form fail silently for restricted domains.
7. **Save**: Click "Save Changes" to apply settings.

### Configuring Minimum Character Length
1. **Enable**: Check "Minimum Character Length" to activate (disabled by default).
2. **Form & Field Mapping**: Select forms and their text or textarea fields to enforce the minimum length.
3. **Set Length**: Enter the minimum number of characters required (default: 5).
4. **Save**: Click "Save Changes" to apply settings.

### Configuring Spam Filter
1. **Enable**: Check "Spam Filter" to activate (enabled by default).
2. **Check All Fields for Spam**:
   - **Unchecked**: Only checks email, name (First Name/Last Name), phone, company, and message fields for spam terms.
   - **Checked**: Scans all form fields (e.g., text, textarea, select) for spam terms.
3. **Enable Regex Matching**: Check to allow regular expression patterns in Disallowed Comment Keys (e.g., `/\bviagra\b/i`).
4. **Enable Whole-Word Matching**: Check to ensure non-regex terms match whole words only (e.g., "casino" won’t match "cassino").
5. **Enable Repetitive Pattern Detection**: Check to flag repeated words or phrases (e.g., "ShirleyShirleyShirley"). Set the repetition threshold (default: 3).
6. **Enable Keyboard Spam Detection**: Check to flag random, incoherent text (e.g., "dfasfasfs,gfyhsxyhzhzdfhyztded"). Set the threshold (0.0–1.0, default: 0.8).
7. **Common Words**: Enter one word per line to ignore in spam detection (e.g., "the", "and"). Defaults to a predefined list, customizable to add/remove words.
8. **Entry Block Terms**: Enter one term per line (e.g., `spam`, `viagra`, `/\bfree money\b/i`) to block submissions containing these terms. These are added to WordPress’s Disallowed Comment Keys when saved (if spam filter is enabled).
9. **Save**: Click "Save Changes" to apply settings.

### Configuring Spam Predictor
1. **Enable**: Check "Spam Predictor" to activate (disabled by default).
2. **Spam Predictor Threshold**: Set the minimum frequency a term or phrase must appear in spam submissions to be blocked (default: 3).
   - **Lower values (1-2)**: Catches more spam but may flag legitimate content (e.g., "contact us").
   - **Higher values (5+)**: More conservative, fewer false positives.
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

### Customizing Common Words
1. In the Settings tab, under Spam Filter and Predictor, find the **Common Words** textarea.
2. View the default list (e.g., "the", "for", "you", etc.).
3. Modify as needed:
   - **Add**: Enter new words (e.g., `hello`, `world`) on new lines.
   - **Remove**: Delete unwanted words (e.g., `the`, `and`).
   - **Replace**: Enter a new list (e.g., `a`, `an`, `to`).
4. Save changes to update the list used in spam detection.
5. Test submissions to ensure ignored words (e.g., `hello hello hello` if `hello` is common) are not flagged.

## Testing
- **Email Validation**: Submit a form with an email from a restricted domain (e.g., `test@gmail.com` if `gmail.com` is listed):
  - "Limit" mode: Rejected unless listed.
  - "Ban" mode: Rejected if listed.
  - Check "Hide Validation Message" to ensure silent failure.
- **Minimum Character Length**: Select a text or textarea field, set min length to 42, submit with "abc" (should fail) and a 42+ character valid string (should pass).
- **Spam Filter**:
  - Add a term (e.g., `spam`, `/\bfree money\b/i`) to "Entry Block Terms," save, then submit a form with that term:
    - With "Check All Fields" off, only specific fields trigger it.
    - With it on, any field triggers it.
  - Enable regex and whole-word matching, test with "casino" (flagged) and "cassino" (not flagged if whole-word is on).
  - Enable repetitive pattern detection, test with "ShirleyShirleyShirleyShirley" (flagged if threshold is 3).
  - Enable keyboard spam detection, test with "dfasfasfs,gfyhsxyhzhzdfhyztded" (flagged).
  - Modify common words, test with "hello hello hello" (not flagged if `hello` is common).
- **Spam Predictor**: Enable the predictor, mark entries as spam in Gravity Forms, add a manual phrase (e.g., "buy now" with frequency 5), and test submissions with that phrase to confirm blocking. Adjust the threshold and use bulk delete to manage terms.

## Debugging
- Enable debugging in `wp-config.php`:
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);
  ```
- Check `wp-content/debug.log` for errors, including:
  - Initialization: `GFET: GravityForms_Enhanced_Tools initialized`.
  - Spam detections: `GFET Spam Detected: ...`.
  - Settings changes: `GFET: Sanitized common words: ...`.
  - Common words: `GFET: Common words loaded: ...`.

## Support
Contact support@wpproatoz.com for assistance.

## Credits
- Email Domain Validator based on GW_Email_Domain_Validator class.
- Spam Filter originally developed by Nikki Stokes (https://thebizpixie.com).
- Web321.co for help with selector functions.

## Screenshots
1. **Admin Settings Page** - Configure email validation, spam filter, spam predictor, and common words.
 ![screenshot1](screenshot1.png)
2. **Spam Terms Management** - Add, edit, delete, and bulk delete spam terms/phrases.
 ![screenshot2](screenshot2.png)

## Demo
You can view a demo of the plugin in action at [WPProAtoZ.com](https://wpproatoz.com/plugins).

## Changelog
### 2.6
- **Customizable Common Words**: Added a textarea in the Settings tab to allow administrators to define a custom list of common words (e.g., "the", "and") to ignore during spam detection. Supports adding, removing, or replacing the default list, stored in `gf_enhanced_tools_common_words`.
- **Improved Logging**: Enhanced debug logging for common words loading and spam detection to aid troubleshooting.

### 2.5
- **Keyboard Spam Detection**: Added detection for random, incoherent text (e.g., "dfasfasfs,gfyhsxyhzhzdfhyztded") used to bypass minimum character requirements. Configurable via a threshold (0.0–1.0, default: 0.8) in the Settings tab.
- **Enhanced Spam Filter**: Integrated keyboard spam detection with existing regex, whole-word, and repetitive pattern checks.
- **Settings UI**: Added "Enable Keyboard Spam Detection" and "Keyboard Spam Threshold" options under Spam Filter and Predictor.

### 2.4
- **Repetitive Pattern Detection**: Added detection for repeated words or phrases (e.g., "ShirleyShirleyShirley" or "Please contact me by email" repeated) to catch spam padding character counts. Configurable via a threshold (default: 3) in the Settings tab.
- **Settings UI**: Added "Enable Repetitive Pattern Detection" and "Repetition Threshold" options under Spam Filter and Predictor.
- **Field Mapping**: Ensured repetitive pattern detection respects form-specific field mappings.

### 2.3
- **Field Persistence Fix**: Resolved issue where selected field IDs in "Email Form & Field Mapping" were lost when saving other settings (e.g., "Hide Validation Message").
- **Minimum Character Field Mapping Fix**: Fixed rendering of form and field selectors in "Minimum Character Field Mapping" to correctly display text and textarea fields.
- **Improved Settings UI**: Reorganized settings page with clearer section headers ("Enable domain validation and choose fields", "Enable Minimum Character Field Mapping and choose fields") for better usability.
- **Enhanced Debugging**: Added detailed logging for JavaScript and PHP to improve issue diagnosis.

### 2.2
- **Minimum Character Length**: Added option to enforce a minimum character length for text fields, configurable via the admin interface.
- **Enhanced Admin UI**: Improved settings page with clear section headers ("Domain Validator and Minimum Characters," "Spam Filter and Predictor") and updated field mapping description.
- **Bug Fix**: Resolved error in "Update Historical Spam Terms" function when spam form IDs were undefined.

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