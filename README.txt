=== WPComplete ===
Contributors: ithemes, layotte
Tags: courses, teaching, read, mark, complete, lms, membership, pages, page, posts, post, widget, plugin, admin, shortcode, progress, progress bar, completion, tracking, dashboard, groups, learning
Requires at least: 4.5.3
Tested up to: 6.8.3
Stable tag: 2.9.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
A WordPress plugin that helps your students keep track of their progress through your course.


== Description ==

[WPComplete has joined the iThemes family!](https://ithemes.com/wpcomplete-joining-ithemes-family)

WPComplete is a WordPress plugin that helps your students keep track of their progress through your course or membership site.

All you have to do is pick which pages or posts can be marked as “Completed”.

There’s no programming required, it works with every WordPress theme, WordPress course plugin, and is ready to use instantly. Help your students complete the course you’ve put so much information, knowledge and heart into creating.


**Free version**

* Mark lessons as complete - students can complete lessons so they know how far they’ve progressed in your course.
* Quick toggle - set which pages or posts are completable via Quick Edit or by editing the page/post.
* Any theme, any plugin - use WPComplete with any WordPress theme or membership plugin.


**PRO version**

WPComplete is available as a PRO version with lots of extra features to help you customize and visually show students their progress.
* Supports multiple courses within a single WordPress site.
* Supports multiple buttons per lesson (if each lesson has multiple aspects, think: completed video lesson, completed workbooks, completed spreadsheet)
* Custom post types - Using something other than pages and posts? Not a problem!
* Course progression - when a student clicks complete, they’re taken to the next lesson automagically.
* Dead-easy shortcodes - without any programming, add shortcodes for buttons, graphs, and completion text.
* Complete/Incomplete custom messages - show a message on each lesson that disappears if the complete button is pressed, or, show a message only if the complete button is pressed.
* View progress - see the number of students who’ve completed each lesson or percentage complete by each student.
* Fancy graphs - use a bar or circle graph to display progress through your course via simple shortcodes.
* Completion indicators - visually show logged in students which lessons they have already completed.
* Dashboard widget - see how many buttons and users each course has, right on your admin dashboard.
* Customize everything - choose different wording for the completion buttons and/or pick colours for the buttons and graphs.
* Email support - we are available to quickly answer questions, fix bugs and take feature requests.


[https://wpcomplete.co](https://wpcomplete.co)


Although WPC is course platform agnostic, we've thoroughly tested it with: [Restrict Content Pro](https://restrictcontentpro.com/), Memberful and WOO.

**Please vote & enjoy**
If you like WPComplete, please [leave us a ★★★★★ rating](https://wordpress.org/support/view/plugin-reviews/wpcomplete). Your votes really make a difference! Thanks.


== Installation ==
1. Upload ‘wpcomplete.*.zip’ to your /wp-content/plugins/‘ directory or use the WordPress plugin uploader.
1. Activate the plugin through the ‘Plugins’ menu in WordPress.
1. Go to ‘Settings’ then ‘WPComplete’ to customize the text and colours.
1. Edit any page or post and check the box beside ‘Enable Complete button’ to set a page or post as completable.


== Frequently Asked Questions ==

= How do I enable a page so that it is completable? =
To enable a page so that it's completable:
1. Find the page from your WordPress admin page directory and click in to edit.
2. Scroll to the WPComplete meta box.
3. Check the "Enable Complete button" checkbox.
4. (Optional) Place the `[wpc_complete_button]` shortcode in the content of your post, where you want the button to exist.
5. Update or Publish the page to save the changes.
6. (Optional) On the WPComplete settings page, update your button's custom css to update the appearance of your buttons.

= How do I style the buttons? =
There are two options to add custom branding/style your WPComplete buttons:
1. On the settings page (Settings > WPComplete) you can customize the following, without any programming or HTML/CSS knowledge: the color of the button, the color of the font on the button, the words on the button (for both completed buttons and incomplete buttons), and the wording for “Saving…”.
2. You can create and add your own custom CSS for buttons to add graphics, icons, rounded corners, etc, by adding your own CSS code to the Advanced Settings section. WPC buttons use the class: a.wpc-button with two states (which can be styled differently): a.wpc-completed (for finished lessons) and a.wpc-complete (for unfinished lessons).

= Why isn't my button showing up? =
It could be one of a couple reasons:
1. You're not logged in to your wordpress site. Only logged in users can see the button.
2. That page doesn't have buttons enabled. Make sure the `Enable Complete button` checkbox is checked in the WPComplete metabox when editting that page.
3. You don't have `Automatically add complete button to enabled posts & pages for me.` checked and you haven't added the `[wpc_complete_button]` shortcode to your page content.
Still not showing up? Let us know and we can help figure out what's going on.

= How do I see how many students have completed each lesson? =
Whether you use pages, posts or custom post types for your lessons, you can see on the list page (i.e. PAGES > ALL PAGES or POSTS > ALL POSTS) beside the published date a column for user completion is shown, [which displays the number of students/total students and then the percentage](https://i0.wp.com/plugins.svn.wordpress.org/!svn/bc/1572621/wpcomplete/assets/screenshot-8.png).
If you go to Users > All Users then the final column on the user table will show the number of lessons completed vs the total lessons for each user on your site.

= Does WPComplete allow for multiple buttons per lesson? =
Heck yes it does (in the pro version)! You can add your first button by adding the `[wpc_complete_button]` shortcode to any page content with WPComplete enabled. To add additional buttons, just make sure to provide each button with a specific name, like: `[wpc_complete_button name="Button Name"]` or `[wpc_complete_button name="Video Module"]` or `[wpc_complete_button name="Workbooks"]`.

= How do I delete courses in WPComplete? =
Courses show up once you’ve added more than one lesson to them. To delete a course from WPComplete, simply remove all lessons from that course. Then the course will no longer show up on the list.

= How do I list the lessons in my course and show if they are completed or not to students? =
You’ll have to use your course software/LMS to show what lessons are available in your course, or simply create a list of pages that are lessons on any WP content.

From any list (ordered or unordered), WPComplete automatically will show each user if they’ve completed the lesson or not, using custom CSS. We add this automatically for users who don’t want to edit code (completed lessons are faded slightly and a checkmark is added beside them). For customers who want to update the CSS, it’s found in Advanced Settings:
`
li .wpc-lesson {}
li .wpc-lesson-complete {}
li .wpc-lesson-completed { opacity: .65; text-decoration: none !important; }
li .wpc-lesson-completed:after { content: "✔"; margin-left: 5px; text-decoration: none !important; font-size: 12px; }
`

= What shortcodes are available in the pro version? =
`[wpc_button]` or `[wpc_complete_button]` will add your complete button anywhere on the page or post.
`[progress_percentage]` or `[wpc_progress_percentage]` will display the current student's progress as a percentage (ex: 49%).
`[progress_ratio]` or `[wpc_progress_ratio]` will display the current student's progress as a ratio (ex: 10/35).
`[progress_graph]` or `[wpc_progress_graph]` will display a radial (donut) graph showing the current student's progress with percentage.
`[progress_bar]` or `[wpc_progress_bar]` will display a bar graph showing the current student's progress with percentage.
`[complete_button name="Button Name"]` will create another button on a lesson with an existing button. You can use any number of buttons on any page where WPC is activated.
`[wpc_completed_content]This content shows only once the WPC button is pressed.[/wpc_completed_content]`
`[wpc_incomplete_content]This content shows only until the WPC button is presesd.[/wpc_incomplete_content]`
`[wpc_completed_content name="Button Name"]This content shows only once the button Button Name is pressed.[/wpc_completed_content]`
`[wpc_incomplete_content name="Button Name"]This content shows only until the button Button Name is presesd.[/wpc_incomplete_content]`

For a complete list of available shortcodes and features, please visit: [https://wpcomplete.co/cheatsheet/](https://wpcomplete.co/cheatsheet/)

= Can WPComplete handle multiple courses within the same WordPress installation? =
Yes! Once you enable completion for a page or post, in the pro version, you will be given the option to assign it to a specific course. If you use any progress shortcodes, by default it will display the progress for the course of that post, but progress shortcodes also accept a course attribute if you want to force showing progress for a specific course. Ex:
`[wpc_progress_bar course="All"]`
`[wpc_progress_bar course="My Awesome Course"]`

= Can I use this with custom post types? =
Yes! By default, only posts and pages are enabled. But in the pro version, you have the ability to enable it for individual post types, including any custom types.

= Can I style links to posts and pages that are completable? =
Yes! In the pro version, we append the css class .wpc-lesson to ALL links to posts and pages. Links that have not been completed by the logged in student will also have the class .wpc-lesson-complete added. And links that have been completed by the logged in student will also have the class .wpc-lesson-completed added, along with some really basic styles that are easy to override manually.

= I use OptimizePress. Can I use WPComplete? =
Yes! OptimizePress is a little tricky to get working, but it does work! We automatically disable automatic insertion of the completion button, but you can easily add it where you want the button to show up!
To add the button to your page:
1. Edit the page you want to add completion to.
2. You should already be on the OptimizePress tab (not the WordPress tab).
3. Click the Live Editor "Launch Now" button.
4. Click the "Add Element" button where you want to add your completion button.
5. Select a "Custom HTML / Shortcode" element.
6. In the "Content" field, insert the shortcode: [complete_button]
7. Scroll down and click the "Insert" button.
8. The new preview will say something like:
`
!!! CUSTOM HTML CODE ELEMENT !!!
[wpc_button]
`
9. Click the Save & Close (or Save & Continue) button


== Screenshots ==
1. WPComplete Settings page.
2. Example of a "Mark as complete" button your students see.
3. Example of a WPComplete circle graph (PRO FEATURE).
4. Example of a WPComplete progress bar (PRO FEATURE).
5. Quick Edit WPComplete toggle.
6. WPComplete metabox options on a page or post.
7. Example of visual indicators of a student's lesson completion status.
8. Example of displaying a lesson completion percentage in the WordPress admin.


== Changelog ==

= 2.9.8 =
* Security Fix: Tightened security around actions.

= 2.9.7 =
* Security Fix: Tightened security around `wpc_button` shortcode.

= 2.9.6 =
* Bug Fix: Version Bump.

= 2.9.5.2 =
* Bug Fix: Version Bump.

= 2.9.5.1 =
* Security Fix: Improve security and prevent XSS vulnerabilities.

= 2.9.5 =
* Bug Fix: Using proper source for post_content
* Bug Fix: XSS Vulnerability

= 2.9.4 =
* Bug Fix: Version Bump

= 2.9.3 =
* Improvement: Removing old license key field
* Improvement: Redirecting support link
* Bug Fix: Adding missing files

= 2.9.2 =
* Bug Fix: Corrected fetching buttons for posts without a specific course name.

= 2.9.1 =
* Improvement: Removing old license key field
* Improvement: Redirecting support link
* Bug Fix: Adding missing files

= 2.6 =
* Various bug fixes.
* Added ability to set new content as default enabled.
* Added blank state copy for WPComplete page.
* Cleaned up and simplified code.
* Javascript completion triggers.

= 2.5 =
* Optimizations and various fixes.
* Fix encoding issues on buttons.
* Ability to hide the completion column on the users table.
* New shortcodes! [wpc_next_to_complete], [wpc_last_completed], and [wpc_next_page] / [wpc_previous_page] -- be sure to check the documentation for more details.
* New Wordpress filters allow you to execute WP/PHP code on button, page and course completion.

= 2.4 =
* Various bug fixes, including unlimited and lifetime license verification.
* New shortcode to insert a [wpc_reset] shortcode that users can use to clear their completion data.
* Ability to easily edit course names from your WordPress admin.
* Select multiple courses in progress indicators, i.e. [wpc_progress_ratio course=\"course1, course2, course3\"]
* Tested for WordPress 5.1.
* Removed legacy support for PHP versions < 5.3.

= 2.3 =
* Various bug fixes.
* Display all courses on user preview.
* Added non-ajax completion links for buttons, so when javascript fails, completion still works.
* PRO FEATURE ONLY: Added async loading for content blocks.
* PRO FEATURE ONLY: Add child_of to progress bars/ratios.
* PRO FEATURE ONLY: Allow admins to delete user's course/lesson data.
* PRO FEATURE ONLY: Created course specific dashboard pages.
* PRO FEATURE ONLY: Added custom WPComplete wordpress action hooks.

= 2.2 =
* Various bug fixes.
* Performance and optimization updates.
* Peer pressure shortcode: encourage users to be the Nth to complete a page!
* Simple nav builder: easily list completable pages with just a shortcode (#1 requested feature).
* Custom jQuery event triggers: when a button, page or course is completed, use our new custom jQuery events to trigger your own javascript code.

= 2.1 =
* Change the text of each individual button via shortcode attributes.
* Completed/Incomplete content blocks for entire page and entire course.
* Added setting to disable dashboard widget.

= 2.0 =
* PRO FEATURE ONLY: Support for multiple buttons within a single post, page or custom post type.
* PRO FEATURE ONLY: Support for custom messages if a button is completed or not completed.
* PRO FEATURE ONLY: Basic admin dashboard widget to show number of students, number of buttons and number of finished users.

= 1.4 =
* PRO FEATURE ONLY: Support for multiple courses within a single WordPress site.
* PRO FEATURE ONLY: Basic post page displaying all available students and their current status.
* PRO FEATURE ONLY: Basic user page displaying all available posts a user can complete and their current status.

= 1.3 =
* Added additional progress shortcodes that include a wpc_ prefix.
* Added a setting to turn off auto append.
* Disabled auto append for OptimizePress on plugin activation due to conflicts.
* Started storing a student's completion date and times.
* PRO FEATURE ONLY: Added live update of completed links when completed.
* Added a first (probably horrible) attempt at Spanish translations.

= 1.2 =
* PRO FEATURE ONLY: Upon page load, all links to pages or posts that are completable, will be tagged with a `.wpc-lesson` class along with either `.wpc-lesson-complete` or `.wpc-lesson-completed` based on the current logged in student's completion status.
* PRO FEATURE ONLY: Added advanced custom styles textarea in settings page, allowing for easier styling.

= 1.1 =
* Added support for custom post types. Default is still posts and pages, but the pro version can now select individual custom post types or all post types. [Thanks, Scott Winterroth](https://www.producthunt.com/tech/wpcomplete#comment-342255)
* Fixed a bug where the completion button would sometimes display twice if other plugins would render content before WPComplete. Thanks, Philip Morgan
* Fixed a bug relating to pro version license activation and validity checking.

= 1.0 =
* Initial working version ready for public consumption.


== Upgrade Notice ==
= 2.3 =
Tons of optimization and fixes, along with new features and improvements for PRO accounts!

= 1.4 =
Finally: Multi-course support for pro version!

= 1.3 =
More customization and new pro version features!

= 1.2 =
New features for the pro version!

= 1.1 =
Adds support for custom content types. Also fixed a couple non-critical bugs.

= 1.0 =
First release. No need to upgrade yet.

