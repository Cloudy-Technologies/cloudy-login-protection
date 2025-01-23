# Cloudy Login Protection

Extremely lightweight plugin to add some basic login protection to your WordPress site.

Made by [CloudyTechnologies](https://cloudytechnologies.mk/).

## Usage
All the features can be accessed by the "Login Protection" menu item in the "Settings" dropdown.

#### Custom Login URL
Since `wp-login` is the default URL for all WordPress installations, malicious users and bots often target that URL first to try and break into your site.

With the custom login URL feature you can:
- Change the default wp-login.php URL to any custom URL you want
- Automatically redirect users from wp-login.php to a 404 page for security
- Includes basic sanitization and security measures

Keep in mind to choose a URL that's not easily guessable and to keep your custom URL secure and share it only with authorized users.

#### Login Limiter
With bots doing dictionary and brute force attacks, it's a good idea to limit the amount of time a user can get his password wrong in a given time.

With the login limiter feature you can set the:
- Maximum number of attempts
- Lockout duration
- Attempt reset period

We create a separate table in the database to keep track of all login attempts, we utilise IP-based blocking, and we have proxy detection.

If a user enters the wrong password, he will be notified on the login page itself of how much more login attempts he has before being locked out.

#### Idle User Logout
Leaving your device unattended is usually not a secure thing to do, unfortunately we do it all the time. Security is not only about the online threats, but the physical ones too.

The idle user logout feature will:
- Track user activity through mouse, keyboard, and scroll events
- Show a warning modal 1 minute before session expiry
- Allow users to extend their session or logout when warned
- Automatically logout users after timeout period
- Work in both admin and frontend areas


#### reCAPTCHA
reCAPTCHA is a security tool that verifies users are human to prevent automated bot attacks.

To set up reCAPTCHA:

1. Go to https://www.google.com/recaptcha/admin
2. Create a new site
3. Choose reCAPTCHA v2 ("I'm not a robot" checkbox)
4. Add your domain to the allowed domains
5. Copy the Site Key and Secret Key
6. Paste them into your plugin settings

## Installation
This plugin is not on the WordPress plugins' page, 
so currently the only way to use this plugin is to zip this folder and upload it to your 
`wp-content/plugins` directory on your server.
