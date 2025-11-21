# Direktt Membership

A powerful WordPress plugin for managing memberships, tightly integrated with the [Direktt WordPress Plugin](https://direktt.com/).

With Membership extension you can:

- **Create Membership Packages** via wp-admin interface. Both **Time based** (e.g. valid for 30 days) and **Usage based** (e.g. valid for 10 uses) memberships are supported.
- **Assign Membership Packages** to users via Direktt mobile app.
- **Record usages/activate Membership Packages** directly from Direktt mobile app.
- **Display active personal Membership Packages** to users in Direktt mobile app.
- **Validate user Memberships** by scanning QR Codes in Direktt mobile app.
- **Generate reports on Memebership usage** reports on issued and used membership packages in given time period from wp-admin interface.

## Documentation

You can find the detailed plugin documentation, guides and tutorials in the Wiki section:  
https://github.com/direktt/direktt-membership/wiki

## Requirements

- WordPress 5.6 or higher
- The [Direktt Plugin](https://wordpress.org/plugins/direktt/) (must be active)

## Installation

1. Install and activate the **Direktt** core plugin.
2. Download the direktt-membership.zip from the latest [release](https://github.com/direktt/direktt-membership/releases)
3. Upload **direktt-membership.zip** either through WordPress' **Plugins > Add Plugin > Upload Plugin** or upload the contents of this direktt-membership.zip to the `/wp-content/plugins/` directory of your WordPress installation.
4. Activate **Direktt Membership** from your WordPress plugins page.
5. Create Membership Packages in wp-admin **Direktt > Membership Packages**
6. Configure the plugin under **Direktt > Settings > Membership Settings**.

## Usage

### Plugin Settings

- Find **Direktt > Settings > Membership Settings** in your WordPress admin menu.
- Configure:
    - Direktt user category/tag allowed to issue/validate membership packages.
    - Notifications for users and admin on membership package issuance/activation/usage.
- Generate reports on Membership usage over selected time period.  

### Membership Packages

- Find **Direktt > Membership Packages**
- Set up your Membership Packages:
    - Add title of the Membership package.
    - Find meta box Package Properties, inside configure:
        - Package type:
            - Time based - Validity is based on time period (e.g., membership is valid for next 30 days)
            - Usage based - Validity is based on number of usages (e.g., membership is valid for 10 usages / validations / check-ins)
        - If Time based is selected configure:
            - Validity (days) - Number of days the membership is valid after activation (0 is unlimited).
        - If Usage based is selected configure:
            - Max Usage - Number of times the membership can be used (0 is unlimited).

### Workflow

- **Assign Membership Package to user**
    - Access the Direktt user profile in Direktt mobile app.
    - Go to Membership tool subpage.
    - Choose the membership package and click "Assign Membership" button.
    - Membership is now assigned to user. The user receives the message and channel admin receives the notification.
- **Activate Time Based Membership Packages**
    - Time based Membership Packages are activated on first usage.
    - Salesperson activates the package using Direktt mobile app by scanning the QR Code
    - Once active, membership expires in number of days set in Package Properties.
- **Validate Membership and record usage**
    - Salesperson validates the membership using Direktt mobile app by scanning the QR Code
    - Upon QR Code scan, membership properties are displayed to salesperson for validation
    - If valid, salesperson taps the "Record Usage" button. Usage is now recorded.
- **Generate reports**
    - Find **Direktt > Settings > Membership Settings** in your WordPress admin menu.
    - On the bottom, you will find section **Generate Membership Reports**
    - There is a "Range" option which can be set to "Last 7 days", "Last 30 days", "Last 90 days" or "Custom date range".
    - Generate Issued Reports - CSV file is generated, containing the following information:
        - Package Name - title of the Membership Package
        - Reciever Display Name - display name of the subscriber to whom it was issued
        - Activated - true/false, for time based packages, this will be true if membership was activated, for usage based packages it will always be false
        - Time of Issue - timestamp when package was issued
        - Time of Activation - timestamp when package was activated (only for time based packages)
        - Expires on - timestamp when package expired/will expire (only for time based packages)
        - Usages left - number of usages left
        - Valid - true/false, false only if membership package was invalidated, true by default
    - Generate Usage Reports (displays only the usage based packages) - CSV file is generated, containing the following information:
        - Package Name - title of the Membership Package
        - Time of Issue - timestamp when package was issued
        - Reciever Display Name - display name of the subscriber to whom it was issued
        - Validator Display Name - display name of the user who recorded (validated) usage of the package. 

### Shortcode (Front End)

```[direktt_membership_tool]```

Using this shortcode, you can display the all currently active personal Membership Packages to users within Direktt mobile app

### Shortcode (Validation)

```[direktt_membership_validation]```

Using this shortcode, you can display the validation interface to salespersons. The interface will be automatically displayed upon Membership QR Code scan showing all relevant properties of the issued package

## Notification Templates

Direktt Message templates support following dynamic placeholders:

- `#display_name#` - display name of the subscriber
- `#subscription_id#` - subscription id of the subscriber
  
- TODO

## Usage Logs

Every Membership Package isuuance / usage is recorder in the respective log. You can query logs and generate reports (explained above and in documentation) on **Direktt > Settings > Membership Settings** in your WordPress admin menu.

---

## Updating

The plugin supports updates directly from WordPress admin console.  

You can find all plugin releases in the Releases section of this repository:  
https://github.com/direktt/direktt-membership/releases.

---

## License

GPL-2.0-or-later

---

## Support

Please use Issues section of this repository for any issue you might have:  
https://github.com/direktt/direktt-membership/issues.  

Join Direktt Community on Discord - [Direktt Discord Server](https://discord.gg/xaFWtbpkWp)  

Contact [Direktt](https://direktt.com/) for general questions, issues, or contributions.
