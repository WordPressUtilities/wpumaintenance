WPU Maintenance
==============

Adds a maintenance page for non logged-in users

## How to install :

* Put this folder to your wp-content/plugins/ folder.
* Activate the plugin in "Plugins" admin section.

## How to custom the maintenance page :

Each method is overwritten by the next one.

### Content

You can edit the page content in the admin page.

### Hooks

You can use the hooks and filters of the default page ( called before the "init" action at priority 99 ) :

* wpumaintenance_head (action) : Load content in the HEAD of this page.
* wpumaintenance_header (action) : Load content after the opening BODY of this page.
* wpumaintenance_footer (action) : Load content before the closing BODY of this page.
* wpumaintenance_pagetitle (filter) : Set a custom page title.
* wpumaintenance_content (filter) : Replace the content of the body (by default : title & admin sentence)

### File

If you place a file named "maintenance.php", "maintenance.html" or "index.html" at the root of your active theme, it will be included instead of the default maintenance page.