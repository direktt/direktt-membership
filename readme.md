# Direktt Membership

A powerful WordPress plugin for managing memberships, tightly integrated with the [Direktt WordPress Plugin](https://direktt.com/).

- **Create Membership Packages** via wp-admin interface.
- **Assign Membership Packages** to users via Direktt User profile.
- **Record usages/Activate Membership Packages** for users via Direktt User profile or shortcode.
- **Display Membership Packages** to users via a simple shortcode.
- **Customizable QR Codes** for activating/recording usage of membership package.
- **Validating Membership Packages** which happens when Admin scans the QR Code.
- **Generate Reports** for issued/used membership packages easily from wp-admin interface. 

## Requirements

- WordPress 5.0 or higher
- The [Direktt Plugin](https://wordpress.org/plugins/direktt/) (must be active)

## Installation

1. Install and activate the **Direktt** core plugin.
2. Download the direktt-membership.zip from the latest [release](https://github.com/direktt/direktt-membership/releases)
2. Upload **direktt-membership.zip** either through WordPress' **Plugins > Add Plugin > Upload Plugin** or upload the contents of this direktt-membership.zip to the `/wp-content/plugins/` directory of your WordPress installation.
3. Activate **Direktt Membership** from your WordPress plugins page.
4. Configure the plugin under **Direktt > Settings > Membership Settings**.

## Usage

### Admin Interface

- Find **Direktt > Settings > Membership Settings** in your WordPress admin menu.
- Configure:
    - Set up slug of the page containing the shortcode for validation.
    - Which user category/tag will be able to issue/validate membership packages.
    - Set up QR Code logo, color and background color, QR Code preview is available.
    - Set up notifications for users and Direktt admin on membership package issuance/activation/usage.

- Find **Direktt > Membership Packages**
- Set up your Membership Packages:
    - Add title of the Membership package.
    - Find meta box Package Properties, inside configure:
        - Package type:
            - Time based - Duration of access is based on time (e.g., 30 days)
            - Usage based - Duration of access is based on usage (e.g., 10 usages)
        - If Time based is selected configure:
            - Validity (days) - Number of days the membership is valid after activation (0 is unlimited).
        - If Usage based is selected configure:
            - Max Usage - Number of times the membership can be used (0 is unlimited).

### Case Management

- Add service case via wp-admin/Direktt User profile/shortcode.
- Set the case title and case description (optional).
- If you are adding service case via wp-admin or shortcode, you will need to enter user's Subscription ID.
- Edit service cases via wp-admin/Direktt User profile/shortcode.
- All actions are logged in the user’s **service status change log**.

### Shortcode (Front End)

Show the all non-closed cases (only to Direktt Admin and users that are able top manage cases) and current user's non-closed cases to Direktt user:

```[direktt_service_case]```

## Notification Templates

Direktt Message templates support following dynamic placeholders:

- `#case-no#` — title of the service case
- `#date-time#` — timestamp when case was opened or status was changed
- `#old-status#` for old status (only for case status change message template)
- `#new-status#` for new status (only for case status change message template)

## Case Status Change Logs

For every case status creating or change, an entry is made with admin name (not visible to user), subscription id (not visible to usr), old status, new status and timestamp.

---

## Updating

The plugin supports updating directly from this GitHub repository.

---

## License

GPL-2.0-or-later

---

## Support

Contact [Direktt](https://direktt.com/) for questions, issues, or contributions.