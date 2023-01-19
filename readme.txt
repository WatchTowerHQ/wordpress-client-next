=== WatchTowerHQ ===
Tags: watchtower, client, tools, monitoring, Manage multiple sites, backup, site speed
Requires at least: 5.1
Tested up to: 6.1
Requires PHP: 7.4
Stable tag: 3.7.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
The WatchTowerHQ WordPress plugin allows us to monitor, backup, upgrade, and manage your site!

== Description ==

[WatchTowerHQ]("https://watchtowerhq.co") Website Monitoring: Done Right
Fed up with using tools that get sold to GoDaddy, lose their founding team, and slowly die?
Tired of half-assed customer service that takes 72-144 hours to get a response?
Looking for one solution to cut the costs of many tools?
Our founding team is intact and not only focused on a world-class customer service experience, we're focused on making WatchTowerHQ the best tool agencies and companies turn to when managing multiple websites.

## Automated Updates
Set it and forget it by scheduling WordPress theme and plugin updates in advance. Every Tuesday at 7am? Not a problem with WatchTowerHQ

## Performance Insights
Wondering why no one stays on your website? It's probably because it's slow...like Ford Fiesta slow. Figure out what you need to do to transform it into the Porsche it deserves to be.

## Daily Backups
Take them daily, store them for a year. Don't be at the mercy of the intern who accidentally deleted all of your blog posts from the last 6 years. Restore them quickly from one of your backups. Automate these as you would like, or take them manually.

## Historical Data Tracking
A snapshot in time is great for some. You're not some. Most are like you, they want to see how data looks over time. Screen shots from last May? Easy peasy. Site speed today relative to 2 months ago? One click.

## Notifications?
We've got you covered. You control who gets alerts, which alerts they get, and when. All for you to control within your account.

## Domain and SSL Registration Monitoring
The dreaded call from a client at 12:47am when they realize their domain has expired and a 12 year old North Korean is holding it hostage for 72 BTC. Don't let that happen to you or your clients. Always know when your domain or SSL certificate are about to expire so  you can proactively renew.

## Real-time Uptime Monitoring
Kiss false positives good-bye. Set your limits,  get updated when they're triggered.

## One-Click Access
Access your WordPress site or staging environment with one click right from the website dashboard.
WatchTowerHQ is the most robust website monitoring and management tool. Increase operational efficiency by eliminating wasted time, improving your security, and taking preventive action.

## Custom User Roles
Select from one of WatchTowerHQ's user roles with granular permissions selection or create custom roles to fit your organizational needs.

Please note that WatchTowerHQ tracks information about your site including:
*   Domain information (Registrar, DNS, SSL, Blacklist Status, Site & Proxy IP addresses)
*   WordPress plugins, themes, and core
*   Google Lighthouse & Google Analytics data
*   Screen captures
*   CSS & JavaScript code

== Installation ==

After installing the WatchTowerHQ plugin you will need to do a few things to start monitoring your site.
1. Activate the WatchTowerHQ plugin.
2. Copy the access token found in the WatchTowerHQ Settings (you will need this in a future step).
3. Go to [WatchTowerHQ](https://watchtowerhq.co) to sign up for an account.
4. Fill in information about your websites - including the access token that you'd copied earlier.
5. Save the website's information once complete. Happy monitoring!

If you've already signed up on [WatchTowerHQ](https://watchtowerhq.co), you can also follow the alternative installation:
1. Download the WatchTowerHQ client from the Settings dropdown menu
2. Go to the Plugins tab in WordPress and select "Add New Plugin".
3. Click the "Upload Plugin" button and upload the zip file you downloaded from WatchTowerHQ.
4. Click to activate the client and copy the access token from the WatchTowerHQ settings.
5. Go to the "Add new website" form found on the Websites page of your WatchTowerHQ dashboard.
6. Select WordPress as your CMS and paste the access token in the space.

== Frequently Asked Questions ==

= Can I have multiple environments on WatchTowerHQ? =
Yes, you can include your development and other environments to your WatchTowerHQ account for convenient one-click access.

= What does WatchTowerHQ do with my site's information? =
The information tracked by WatchTowerHQ is used to provide real-time analytics and alerts on your site's status including vulnerabilities, site downtime, and performance. As well as to notify you about WordPress-specific issues such as abandoned plugins.

= How much is WatchTowerHQ? =
To view our plans and get started with WatchTowerHQ check out our [website](https://watchtowerhq.co/pricing).

= Can I limit access to other users? =
We have a number of standard roles, that many users requested, along with that we've created custom user roles. With custom user roles the options are endless to the access that you can give any one user. Custom User roles are included in all plans at no additional cost.

== Changelog ==
= 3.7.7 =
* fixed installation size calculation

= 3.7.6 =
* added total users count

= 3.7.5 =
* ability to redirect user to updates page directly after login

= 3.7.4 =
* mysql backup code improvements

= 3.7.2 =
* drop old php compatibility

= 3.6.21 =
* code hardening

= 3.6.20 =
* remove unused code

= 3.6.19 =
* db backup fix

= 3.6.18 =
* WordPress 6.1.x compatibility declaration update

= 3.6.17 =
* Fix security issue

= 3.6.16 =
* Fix security issue

= 3.6.15 =
* Fix theme updates info

= 3.6.13 =
* Fix plugin updates info

= 3.6.12 =
* Fix plugin slug

= 3.6.11 =
* Fix plugin slug

= 3.6.10 =
* WP 6.x compatibility declaration

= 3.6.7 =
* WP 5.9.x compatibility declaration

= 3.6.6 =
* fix open basedir warnings

= 3.6.1 =
* Add endpoint for removing database backup file

= 3.6.0 =
* updated action scheduler

= 3.5.69 =
* WP Engine - exception

= 3.5.67 =
* code improvements

= 3.5.65 =
* code improvements
* fetch info about debug log

= 3.5.1 =
* update action scheduler

= 3.5.0 =
* new backup system

= 3.4.16 =
* WordPress 5.8 compatibility

= 3.4.15 =
* fix UI issue

= 3.4.13 =
* better settings UI

= 3.4.12 =
* allow resume downloading backup
* include backup integrity checksum to confirm error free transfer

= 3.4.10 =
* code refactoring

= 3.4.9 =
* update composer properties

= 3.4.8 =
* WordPress 5.7 compatibility

= 3.4.4 =
* new backup queue file limits

= 3.4.2 =
* increase timeout

= 3.4.0 =
* added ZipArchive fallback

= 3.3.10 =
* fix curl timeout

= 3.3.7 =
* minimum PHP version: 7.1

= 3.3.6 =
* readme improvements

= 3.3.0 =
* Wordpress.org first release.
* removed custom updates library
