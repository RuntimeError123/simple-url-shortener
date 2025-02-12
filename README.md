# simple-url-shortener
Pretty much the simplest URL shortening service possible. It's simple, fast, opinionated, keeps track of click-thru stats, and does everything I need. It's all self-contained in a single PHP script (and .htaccess file). No dependencies, no frameworks to install, etc. Just upload the file to your web server and you're done. Maybe you'll find it useful, too.

## Creating a New Short Link

To create a new short link, just append the full URL you want to shorten to the end of the domain name you installed this project onto. For example, if your shortening service was hosted at `https://example.com` and you want to shorten the URL `https://some-website.com`, go to `https://example.com/http://somewebsite.com`. If all goes well, a plain-text shortened URL will be displayed. Visiting that shortened URL will redirect you to the original URL.

Possibly of interest to app developers like myself: The shortening service also supports URLs of any scheme - not just HTTP and HTTPS. This means you can shorten URLs like `app://whatever`, where `app://` is the URL scheme belonging to your mobile/desktop software. This is useful for deep-linking customers directly into your app.

**iOS Users:** If you have Apple's [Shortcuts.app](https://itunes.apple.com/app/shortcuts/id915249334) installed on your device, you can [click this link](https://www.icloud.com/shortcuts/d52505e26935491397d9c14b54beea41) to import a ready-made shortcut that will let you automatically shorten the URL on your iOS clipboard and replace it with the generated short link.

## Viewing Click-Thru Statistics

All visits to your shortened links are tracked. No personally identifiable user information is logged, however. You can view a summary of your recent link activity by going to `/stats/` on the domain hosting your link shortener.

You can click the "View Stats" link to view more detailed statistics about a specific short link.

## Password Protecting Creating Links

If you don't want to leave your shortening service wide-open for anyone to create a new link, you can optionally set a password by assigning a value to the `$pw_create` variable at the top of `index.php`. You will then need to pass in that password as part of the URL when creating a new link like so:

**Create link with no password set:** `http://example.com/http://domain.com`

**Create link with password set:** `http://example.com/your-password/http://domain.com`

## Password Protecting Stats

Your stats pages can also be password protected. Just set the `$pw_stats` variable at the top of the `index.php` file.

**Viewing stats with no password set:** `http://example.com/stats`

**Viewing stats with password set:** `http://example.com/stats/your-password`

## A Kinda-Sorta JSON API

This project aims to be as simple-to-use as possible by making all commands and interactions go through a simple URL-based API which returns plain-text or HTML. However, if you're looking to run a script against the shortening service, you can do so. Just pass along `Accept: application/json` in your `HTTP` headers and the service will return all of its output as JSON data - including the stats pages.

