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

### Membership Packages

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

### Assign Membership Package to user

- Access the Direktt user profile.
- Go to Membership tool subpage.
- Choose the membership package and click "Assign Membership" button and then confirm.
- Membership is now assigned to user.

### Activating Time Based Membership Packages

- There are two ways for activation:
    - From Direktt Profile tool:
        - Admin accesses the Direktt Profile tool.
        - Admin finds the Membership Package in the list, and clicks the "View Details" button
        - Admin clicks the "Activate Membership" button and confirms.
        - Membership is now activated and expires in number of days set in Package Properties.
    - From shortcode
        - User accesses the page with the shortcode for membership tool.
        - User finds the Membership Package in the list, and clicks the "View Details" button
        - User shows the QR Code to Admin, Admin scans the code and validation page opens.
        - Admin clicks the "Activate Membership" button and confirms.
        - Membership is now activated and expires in number of days set in Package Properties.

### Recording Usage of Usage Based Membership Packages

- There are two ways for activation:
    - From Direktt Profile tool:
        - Admin accesses the Direktt Profile tool.
        - Admin finds the Membership Package in the list, and clicks the "View Details" button
        - Admin clicks the "Record Usage" button and confirms.
        - Usage is now recorded and number of usages left was deducted by 1.
    - From shortcode
        - User accesses the page with the shortcode for membership tool.
        - User finds the Membership Package in the list, and clicks the "View Details" button
        - User shows the QR Code to Admin, Admin scans the code and validation page opens.
        - Admin clicks the "Record Usage" button and confirms.
        - Usage is now recorded and number of usages left was deducted by 1.

### Invalidation of Membership Package

- There are two ways for activation:
    - From Direktt Profile tool:
        - Admin accesses the Direktt Profile tool.
        - Admin finds the Membership Package in the list, and clicks the "View Details" button
        - Admin clicks the "Invalidate Membership" button and confirms.
        - Membership is now invalidated and is not active/usage cannot be recorded.
    - From shortcode
        - User accesses the page with the shortcode for membership tool.
        - User finds the Membership Package in the list, and clicks the "View Details" button
        - User shows the QR Code to Admin, Admin scans the code and validation page opens.
        - Admin clicks the "Invalidate Membership" button and confirms.
        - Membership is now invalidated and is not active/usage cannot be recorded.

### Generating reports

- Find **Direktt > Settings > Membership Settings** in your WordPress admin menu.
- On the bottom, you will find section **Generate Membership Reports**
- There is a "Range" option which can be set to "Last 7 days", "Last 30 days", "Last 90 days" or "Custom date range".
- Generate Issued Reports:
    - CSV file is downloaded, containing the next informations:
        - ID - custom ID from database
        - Package Name - title of the Membership Package
        - Reciever Display Name - display name of the subscriber to whom it was issued
        - Activated - true/false, for time based packages, this will be true if membership was activated, for usage based packages it will always be false
        - Time of Issue - timestamp when package was issued
        - Time of Activation - timestamp when package was activated (only for time based packages)
        - Expires on - timestamp when package expired/will expire (only for time based packages)
        - Usages left - number of usages left
        - Valid - true/false, false only if membership package was invalidated, true by default
- Generate Used Reports (displays only the usage based packages):
    - CSV file is downloaded, containing the next informations:
        - ID - custom ID from database
        - Package Name - title of the Membership Package
        - Time of Issue - timestamp when package was issued
        - Reciever Display Name - display name of the subscriber to whom it was issued
        - Validator Display Name - display name of the user who recorded (validated) usage of the package. 

### Shortcode (Front End)

Show the all non-closed cases (only to Direktt Admin and users that are able top manage cases) and current user's non-closed cases to Direktt user:

```[direktt_membership_tool]```

### Shortcode (Validation)

Show the all non-closed cases (only to Direktt Admin and users that are able top manage cases) and current user's non-closed cases to Direktt user:

```[direktt_membership_validation]```

## Notification Templates

Direktt Message templates support following dynamic placeholders:

- TODO

---

## Updating

The plugin supports updating directly from this GitHub repository.

---

## License

GPL-2.0-or-later

---

## Support

Contact [Direktt](https://direktt.com/) for questions, issues, or contributions.