=== WSH WooCommerce Bulk Taxonomy Editor ===
Contributors: websolutionshub
Tags: woocommerce, bulk edit, taxonomy, categories, product tags, attributes, product taxonomy
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk edit WooCommerce product taxonomies with Preview (Dry Run). Select products, choose taxonomy, pick terms, and apply Add/Replace (PRO adds Remove + more taxonomies).

== Description ==

WSH WooCommerce Bulk Taxonomy Editor lets you quickly update WooCommerce product taxonomies for many products at once—directly from the Products list.

Choose products, select which taxonomy you want to edit, choose terms, pick the update mode, run a Preview (Dry Run), then Apply changes in batches.

FREE version supports Product Categories (product_cat).
PRO version unlocks Product Tags, Attributes (pa_*), Brands/custom taxonomies, and Remove mode.

= Key Features =
* Bulk action inside Products list (Bulk actions dropdown)
* Select taxonomy to edit (FREE: only Product Categories)
* Hierarchical taxonomies use a familiar WordPress checklist UI (with child indentation)
* Search/filter terms in hierarchical lists
* Non-hierarchical taxonomies use fast AJAX search (Select2 when available)
* Modes:
  * Add: keep existing terms and add selected terms
  * Replace: remove existing terms and set only selected terms
  * Remove (PRO): remove only selected terms from products
* Preview (Dry Run): see current vs final before saving
* Safe batch processing (chunked updates)
* WooCommerce cache cleanup after updates

= FREE vs PRO =
FREE:
* Edit Product Categories (product_cat)
* Add / Replace
* Preview + Apply (batch)

PRO:
* Edit any product taxonomy: product_tag, pa_* attributes, and custom product taxonomies (e.g., brands)
* Remove mode (remove selected terms only)
* License activation & verification via wppluginspro.io

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via Plugins > Add New.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Products > All Products.
4. Select multiple products, choose “WSH: Bulk Taxonomy Edit” in Bulk actions, and click Apply.
5. In the popup:
   - Choose taxonomy
   - Select terms
   - Choose mode (Add/Replace; Remove is PRO)
   - Preview (optional) then Apply Changes

== Frequently Asked Questions ==

= Does the FREE version support tags/brands/attributes? =
No. FREE supports only Product Categories (product_cat). PRO unlocks all product taxonomies.

= What’s the difference between Add and Replace? =
Add keeps existing terms and adds the selected ones.
Replace removes existing terms and sets only the selected ones (can clear all if no terms selected).

= What does Remove mode do? (PRO) =
Remove removes only the selected terms from each product, keeping other terms untouched.

= Does Preview change anything? =
No. Preview is a dry run and does not save data.

= Can I edit custom taxonomies like product_brand? (PRO) =
Yes, PRO supports all public/visible taxonomies assigned to the WooCommerce “product” post type.

= Will this work with variable products? =
Yes. Taxonomies are applied to the parent product (standard WooCommerce behavior).

= Where do I activate my PRO license? =
WooCommerce > WSH Taxonomy Editor License (menu name may vary depending on your setup). Enter your key and Activate.

== Screenshots ==

1. Bulk action in Products list
2. Modal: select taxonomy + mode + terms checklist/search
3. Preview (Dry Run) showing current vs final
4. PRO: select additional taxonomies + Remove mode

== Changelog ==

= 1.0.0 =
* Initial release
* Bulk taxonomy editing from Products list
* FREE: product categories support
* Preview (Dry Run) + batch Apply
* PRO license support and unlock for additional taxonomies and Remove mode

== Upgrade Notice ==

= 1.0.0 =
First stable release.
