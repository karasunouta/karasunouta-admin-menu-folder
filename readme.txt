=== KU Submenu Folder ===
Contributors: karasunouta
Donate link: https://karasunouta.com
Tags: admin menu, menu organizer, menu editor, wordpress admin, dashboard
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organize and clean up your WordPress admin sidebar menu items into custom folder structures.

== Description ==

KU Submenu Folder is a simple and lightweight plugin that aggregates multiple WordPress admin sidebar menu items into a clean, dedicated submenu folder. 

As you install more plugins, your admin sidebar easily becomes cluttered with items you rarely touch. KU Submenu Folder lets you tuck away infrequently used menus into a single organized folder. This allows you to build a streamlined sidebar focused exclusively on your daily essential menus, while maintaining immediate access to all other tools whenever you need them.

= Key Features =

* **Streamlined Sidebar**: Keep your sidebar focused on daily high-frequency menus while preserving immediate access to secondary items inside the folder.
* **Easy Toggle Interface**: Drag-free intuitive checkbox interface for managing menu items in the settings page.
* **Admin Bar Shortcut**: Option to display a direct link to the settings page in the top admin bar.
* **Submenu Settings Link**: Option to place a settings link at the top or bottom of the submenu folder.
* **Clean & Lightweight**: Uses standard WordPress core hooks with no external bloat or performance impact.

== Installation ==

1. Upload the `ku-submenu-folder` directory to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to 'Settings' > 'KU Submenu Folder' to manage and organize your sidebar menu items.

== Frequently Asked Questions ==

= Can I create multiple submenu folders? =
The standard version supports grouping menu items into 1 main submenu folder. For creating unlimited folders, custom folder icons, custom folder titles, and custom item reordering, please check out KU Submenu Folder Pro.

= Will moving menu items change their functionality or permissions? =
No. KU Submenu Folder only changes the location where menu links are rendered in the admin sidebar. User capabilities and plugin page URLs remain unchanged.

= How do I access child menu items of a menu item placed inside a folder? =
Click on the menu item inside the folder (e.g., Submenu Folder > Target Menu). This opens the parent page and expands its submenu structure in the sidebar, allowing you to access all of its child items.

Why this design? WordPress admin menus natively support only up to 2 levels of hierarchy. Forcibly hacking or overriding core menu scripts to create deep nested dropdowns risks breaking layout compatibility and causing conflicts with other plugins or future WordPress updates. Our clean, core-compliant approach provides a safe, reliable, and practical 3-tier menu experience without stability risks.

== Screenshots ==

1. Settings page preview showing sidebar menu selection and folder preview.

== Changelog ==

= 1.1.0 =
* Introduce @wordpress/scripts asset minification and build pipeline.

= 1.0.0 =
* Initial commit.

== Source Code & Development ==

The source code for this plugin is available on GitHub:
https://github.com/karasunouta/ku-submenu-folder