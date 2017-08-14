=== UpCast ===
Contributors: upcast
Tags: plugin,widget,podcast,itunes,upcast,mp3,audio,rss,shortcode
Requires at least: 4.2.2
Tested up to: 4.5.1
Stable tag: 1.2

UpCast is a plugin that allows the customised display of podcasts (RSS) with special
enhancements for podcasts from the upcast.me podcasting platform.

== Description ==

UpCast allows you to customise and filter the display of podcast lists in posts, 
pages, and a sidebar widget. It handles images and thumbnails and other standard 
podcast fields. It also allows custom templates using shortcodes and HTML. For
podcasts hosted on upcast.me it can filter by file attachments, show future items
instead of past items, and identify the source of clicks for analytics.

The UpCast website is https://upcast.me

== Installation  ==

1. Download the Zip-Archive and extract all files into your wp-content/plugins/ directory
1. Go into your WordPress administration page, click on Plugins and activate it
1. Configure your default settings under WordPress administration (Settings->UpCast)
1. Obtain your RSS link (on upcast.me under podcast->publishing) and paste into the 'feed' setting
1. Add the [upcast] shortcode on a page or post, or add the UpCast widget under Appearance->Widgets

==  Usage ==
Use WordPress administration to set UpCast defaults, or just add the UpCast widget to a sidebar.

Or on a page or post, add the following tags:

[upcast]

[upcast feed="<podcast link from upcast.me>" author="Jon G" max="6" future="on"]

Or for top-level podcast details:
 
[upcast_image feed="<podcast link>"]

[upcast_thumbnail feed="<podcast link>"]

[upcast_rss feed="<podcast link>"]<h1>[title]</h1>[/upcast_rss]

== Screenshots ==

1. Shortcode defaults allow you to configure everything from podcast column headings to styled HTML templates.
2. Widgets allow all the same customisations as shortcodes but with floating layouts for elegant resizing.
3. The plugin lets you specify equivalent settings to those on the UpCast pdocasting platform at upcast.me.
4. You can even customise how clicks on your podcast episodes from wordpress will appear in upcast.me analytics.

== Version History ==

v1.0.0
Initial release

v1.1
Fixes

v1.2
Added search bar
