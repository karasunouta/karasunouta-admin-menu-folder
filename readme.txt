=== Karasunouta Admin Menu Folder ===
Contributors: karasunouta
Donate link: https://karasunouta.com
Tags: admin menu, menu organizer, menu editor, wordpress admin, dashboard
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.5.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organize and clean up your WordPress admin sidebar menu items into custom folder structures.

== Description ==

Karasunouta Admin Menu Folder is a simple and lightweight plugin that aggregates multiple WordPress admin sidebar menu items into a clean, dedicated submenu folder. 

As you install more plugins, your admin sidebar easily becomes cluttered with items you rarely touch. Karasunouta Admin Menu Folder lets you tuck away infrequently used menus into a single organized folder. This allows you to build a streamlined sidebar focused exclusively on your daily essential menus, while maintaining immediate access to all other tools whenever you need them.

= Key Features =

* **Streamlined Sidebar**: Keep your sidebar focused on daily high-frequency menus while preserving immediate access to secondary items inside the folder.
* **Easy Toggle Interface**: Drag-free intuitive checkbox interface for managing menu items in the settings page.
* **Admin Bar Shortcut**: Option to display a direct link to the settings page in the top admin bar.
* **Submenu Settings Link**: Option to place a settings link at the top or bottom of the submenu folder.
* **Clean & Lightweight**: Uses standard WordPress core hooks with no external bloat or performance impact.

== Installation ==

1. Upload the `karasunouta-admin-menu-folder` directory to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to 'Settings' > 'Admin Menu Folder' to manage and organize your sidebar menu items.

== Frequently Asked Questions ==

= How many menu items can I put in the folder? =
Up to 99 items. Items are listed inside the folder in the same order as they appear in the original admin sidebar, so the folder stays predictable no matter how many plugins you add.

= What happens if I remove every item from the folder? =
The folder disappears from the admin sidebar entirely. Nothing is left behind, and every menu item returns to its original position. Add an item again and the folder comes back.

= Will moving menu items change their functionality or permissions? =
No. Karasunouta Admin Menu Folder only changes the location where menu links are rendered in the admin sidebar. User capabilities and plugin page URLs remain unchanged.

= How do I access child menu items of a menu item placed inside a folder? =
Click on the menu item inside the folder (e.g., Submenu Folder > Target Menu). This opens the parent page and expands its submenu structure in the sidebar, allowing you to access all of its child items.

Why this design? WordPress admin menus natively support only up to 2 levels of hierarchy. Forcibly hacking or overriding core menu scripts to create deep nested dropdowns risks breaking layout compatibility and causing conflicts with other plugins or future WordPress updates. Our clean, core-compliant approach provides a safe, reliable, and practical 3-tier menu experience without stability risks.

== Screenshots ==

1. Settings page preview showing sidebar menu selection and folder preview.

== Changelog ==

= 1.5.0 =
* Enhance: Escape all generated output at the point it is printed, and drop every phpcs:ignore annotation so static analysis covers the whole codebase.
* Enhance: Submit settings as structured form fields instead of a JSON blob, fixing a case where menu titles containing "<" could truncate the saved data.
* Enhance: Prevent the Settings root menu from being selected, since it was never movable into the folder.
* Enhance: Use the WordPress settings notice API so the admin notice no longer relies on query arguments.
* Enhance: Load admin assets only on the plugin settings screen.
* Refactor: Simplify the stored option structure and expose the folder title, icon, node list and protected slugs through documented filters.

= 1.4.0 =
* Refactor: Rename plugin to Karasunouta Admin Menu Folder with official slug and prefix kamf per WordPress.org guidelines.

= 1.3.0 =
* Enhance: Introduce wp.hooks extension architecture for modular Pro add-on integration.
* Enhance: Improve folder slug and title synchronization with registered admin menu nodes.
* Refactor: Align codebase and internal naming with WordPress.org coding standards.

= 1.2.1 =
* Fix: Ensure consistent admin menu item ordering across different environments with or without custom_menu_order filters.

= 1.2.0 =
* Refine user interface and asset loading scripts.
* Update i18n compatibility and default translation files.

= 1.1.0 =
* Introduce @wordpress/scripts asset minification and build pipeline.

= 1.0.0 =
* Initial commit.

== Source Code & Development ==

The source code for this plugin is available on GitHub:
https://github.com/karasunouta/karasunouta-admin-menu-folder
