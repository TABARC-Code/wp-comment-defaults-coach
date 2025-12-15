<p align="center">
  <img src=".branding/tabarc-icon.svg" width="180" alt="TABARC-Code Icon">
</p>

# WP Comment Defaults Coach

This plugin lets me take control of how comments and pings behave by default in WordPress. Instead of relying on the global discussion settings and whatever the theme decides to do, I can set clear rules per post type and tidy up older content with a simple bulk close tool.

## What it does

- Lets me choose **default comment status per post type**  
  For example:
  - Posts: comments open, pings closed.
  - Pages: comments closed, pings closed.
  - Custom post types: whatever makes sense per type.

- Lets me choose **default ping status per post type**

- Gives me a basic **auto close age** setting
  - Comments on new content can automatically close after N days.

- Adds a **bulk close tool**:
  - Close comments on all items of a given post type older than N days.
  - Or close comments on all existing items of that type, regardless of age.

It only ever closes comments. It does not open anything that is currently closed.

## Why it exists

WordPress has discussion settings, themes have opinions, and some sites have been through multiple redesigns. The result is often:

- Inconsistent comment behaviour across content.
- Posts where comments should never have been open.
- A long tail of old posts still accepting comments just because no one turned it off.

I built this so I can say:

- For this post type, comments should start open or closed.
- For this post type, pings should start open or closed.
- For content older than a certain age, enough is enough.

## Requirements

- WordPress 5.0 or newer.
- PHP 7.4+ recommended.
- Ability to access **Settings → Comment Defaults**.

## Installation

1. Clone or download the repository:

   ```bash
   git clone https://github.com/TABARC-Code/wp-comment-defaults-coach.git
Place it in:

text
Copy code
wp-content/plugins/wp-comment-defaults-coach
In the WordPress admin, go to Plugins → Installed Plugins and activate WP Comment Defaults Coach.

Go to Settings → Comment Defaults to configure it.

Configuration
1. Per post type defaults
On the settings screen you will see each public post type listed with two controls:

Default comments

Default pings

For each type you can choose:

Open by default

Closed by default

These settings apply when creating new items. They do not retroactively change existing content.

You can still override comment and ping status per post in the normal place. This plugin does not remove that control.

2. Automatic closing of old content
Further down you will see:

Close comments on posts older than [ N ] days. Use 0 to disable.

This influences how WordPress treats comments on content as it ages. It does not in itself update existing posts in bulk. It is a forward looking rule.

If you want to bring old content into line with this rule, use the bulk tool.

3. Bulk close comments on existing content
At the bottom is a bulk action form. It does one thing:

Closes comments on existing items that match your filters.

You choose:

A post type

An age in days

Behaviour:

If you enter 30, it will close comments on all items of that type older than 30 days that still have comments open.

If you enter 0, it will close comments on every existing item of that type that currently has comments open, regardless of age.

It does not touch pings. It does not reopen anything. It is a one way clean up.

On large sites it is worth taking a backup before you run this, or testing on a staging site first.

How it behaves
New content
When you create a new post, page or other public post type:

The comment status is set according to the rule for that post type.

The ping status is set according to the rule for that post type.

If there is no rule, WordPress falls back to its normal behaviour.

Existing content
Existing content is only affected when:

You run the bulk close action.

Or you manually change comment status per post as usual.

The plugin does not silently edit existing posts in the background.

Auto close setting
The auto close days value is stored and can be used as your policy baseline.

Right now the bulk tool simply reuses that value as a default, so it is easy to keep both views in sync:

Policy: comments close after N days.

Bulk: close comments on older content that predates that rule.

Safety notes
Only users with manage_options capability can change settings or run the bulk tool.

The bulk action only closes comments. It does not modify content, pings or other fields.

You can always reopen comments on an individual item the normal way.

If you are nervous, test your choices on a staging copy of the site first.

Roadmap
Ideas I may add later:

A dry run mode that shows how many posts would be affected before running a bulk close.

Per post type auto close rules instead of a single global days value.

A small dashboard widget summarising the current comment policy.

Integration with scheduled tasks so bulk updates can run in small batches on larger sites.
