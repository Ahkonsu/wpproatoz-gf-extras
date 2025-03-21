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
Gravity Forms Enhanced Tools is a WordPress plugin that extends Gravity Forms functionality with two powerful features:
1. Email Domain Validator - Restrict or allow form submissions based on email domains
2. Spam Filter - Block spam submissions using WordPress Disallowed Comment Keys

## Installation
1. Upload the plugin folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Settings > GF Enhanced Tools

## Features

### Email Domain Validator
- Validates email domains against a specified list
- Supports two modes:
  - 'ban': Blocks specified domains
  - 'limit': Only allows specified domains
- Configurable per form and field
- Customizable validation message

Default configuration:
- Form ID: 1
- Field ID: 3
- Domains: ['mc-2.com']
- Mode: 'limit'

### Spam Filter
- Uses WordPress Disallowed Comment Keys (Settings > Discussion > Disallowed Comment Keys)
- Checks all form fields against spam terms
- Supports:
  - Email fields
  - Name fields
  - Text fields
  - Textarea fields
  - Phone and Company fields

## Configuration
1. Go to Settings > GF Enhanced Tools in WordPress admin
2. Enable/disable features using the checkboxes
3. For Spam Filter: Add terms to Settings > Discussion > Disallowed Comment Keys
4. For Email Validator: Modify the configuration array in the plugin code

## Usage
- The plugin works automatically once activated and configured
- Email validation occurs during form submission
- Spam filtering checks entries before they're saved
- Both features can be independently enabled/disabled

## Requirements
- WordPress 4.0 or higher
- Gravity Forms plugin

## Support
Contact support@wpproatoz.com for assistance

## Credits
- Email Domain Validator based on GW_Email_Domain_Validator class
- Spam Filter originally developed by Nikki Stokes (https://thebizpixie.com)

## Screenshots

1. **Admin Settings Page** - description.

![screenshot1](screenshot-1.png)

2. ** Output** - answer

![screenshot2](screenshot-2.png)

## Demo

You can view a demo of the plugin in action at [WPProAtoZ.com](https://wpproatoz.com/plugins).

## Changelog

### 1.0.0

- Initial release of 
- Added functionality 
- Added 

## License

This plugin is licensed under the GPL v2 or later. For more information, please see the [GNU General Public License](https://www.gnu.org/licenses/gpl-2.0.html).

## Contributing

Contributions are welcome! Feel free to fork the repository, submit issues, or create pull requests.

---

**Note:** This plugin uses any other credits to other code or coders
