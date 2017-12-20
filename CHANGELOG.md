# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
 - Nový plugin Cart

### Removed
 - Plugin Basket

## [2.8.0] - 2017-12-17
### Added
 - Domain specific meta robots values support

### Changed
 - Domain specific Google Analytics ID in cfg
 - Domain specific meta robots and robots.txt values in cfg

## [2.7.2] - 2017-12-09
### Fixed
 - Empty element <var> does not delete

## [2.7.1] - 2017-12-07
### Fixed
 - Decode URL characters causing ambiguity in URL handling

## [2.7.0] - 2017-10-18
### Added
 - Not found image support
 - New plugin Photoswipe for interactive image galleries
 - Auto attribute `data-target-width` and `data-target-height` for links to images
 - Auto attribute `width` and `height` for images
 - Saving autocorrected HTML+ files (if is file AUTOCORRECT)
 - Support HTML5 data-* attributes

### Changed
 - Effective HTML+ validation
 - Relativize breadcrumb padding top / bottom

### Fixed
 - LinkList contains ids instead of headings
 - Fix HTMLOutput css and js priorities
 - Register Agregator local varaibles
 - Handling broken resource reports error and outputs raw resource
 - New .html file has wrong ctime and ns attributes
 - Disabling css/js file do not clear cache

## [2.6.2] - 2017-10-06
### Fixed
 - Findex required authorization"

## [2.6.1] - 2017-09-14
### Fixed
 - Add other svg mime-type

## [2.6.0] - 2017-07-04
### Added
 - User redirect supports files
 - CMS_NAME includes system language
 - Missing lang file generates warning
 - Highlightable.js
 - Hideable Expand/Collapse switch title attribute
 - Hideable does not hide elements with nohide class

### Changed
 - Always load default *able
 - Hideable default Expand/Collapse arrows as triangles

### Fixed
 - Invalid file cache detection
 - XMLBuilder::updateDOM inline user data

## [2.5.6] - 2017-06-11
### Fixed
 - Uppercase URL does not match

## [2.5.5] - 2017-06-11
### Fixed
 - EmailBreaker removes original anchor attributes

## [2.5.4] - 2017-06-11
### Fixed
 - Catch HtmlOutput invalid xPath error

## [2.5.3] - 2017-06-11
### Fixed
 - Correct definition list detection in Convertor plugin

## [2.5.2] - 2017-06-11
### Fixed
 - Recursive file cache check

## [2.5.1] - 2017-06-08
### Fixed
 - Repair link balancing and current menu item https://trello.com/c/qRMfTh8E

## [2.5.0] - 2017-05-07
### Added
 - Set locale and system messages language using LANG file
 - Contactform `tel` for quick contact
 - Automatic repair creates missing descriptions
 - New variable cms-stage (beta, stable...)

### Changed
 - File cache check refactor to recursive to increase performance
 - Global filesystem refactor to increase overal performance
 - Improve check resources logic and performance
 - Not public system instance with file PROTECTED instead of FORBIDDEN
 - Coding style refactor, crlf to lf
 - CMS name format shows cms stage, e. g. `IGCMS 2.1.1-stable-debug`

### Fixed
 - SyntaxCodeMirror fullscreen overlay scrolltop arrow

## [2.4.11] - 2017-04-24
### Fixed
 - Set proper xmlns

## [2.4.10] - 2017-04-20
### Fixed
 - Update external links with redirect

## [2.4.9] - 2017-04-12
### Fixed
 - Check file existence before deleting chache

## [2.4.8] - 2017-04-09
### Changed
 - Status code 404 (Not Found) instead of 415 (Unsupported Request) if unknown URL

## [2.4.7] - 2017-04-09
### Fixed
 - Multiple partial docinfo support

## [2.4.6] - 2017-04-08
### Changed
 - Create missing dd instead of deleting dt

## [2.4.5] - 2017-04-08
### Fixed
 - Display scrolltop arrow onload if page loads scrolled

## [2.4.4] - 2017-04-08
### Changed
 - Scrolltop square shape

### Fixed
 - Scrolltop arrow centered

## [2.4.3] - 2017-04-08
### Fixed
 - Form input width relative to parent

## [2.4.2] - 2017-04-08
### Security
 - Inactive system version disables QSA

## [2.4.1] - 2017-04-08
### Fixed
 - Limit maximum length of form inputs

## [2.4.0] - 2016-12-21
### Added
- Recursive variable insertion.

### Changed
- Log including CMS version.
- Logs viewed in appropriate syntax highlight.
- CHANGELOG format changed to Markdown since v2.0.
- CHANGELOG language changed to English since v2.0.
- Default variable ``alt`` includes relative path within ImageList.
- Remove element ``dt`` followed by empty ``dd`` instead of creating empty ``dd``.
- Attribute for instead of element filter in DocList including content fallback.
- Insert instead of replace input variable if match or not block.
- Simplified error pages (such as 404 Not Found).

### Fixed
- Removed tabindex from Admin default page.
- Content balancing including comments.

## [2.3.4] - 2016-11-23
### Added
- HTML+ core attributes for elements ``img`` and ``source``.

## [2.3.3] - 2016-11-19
### Fixed
- Generate alert message only for status code above 500.

## [2.3.2] - 2016-11-17
### Fixed
- Image list check file extensions.

## [2.3.1] - 2016-11-17
### Fixed
- Generate default ``alt`` attributes into image lists.

## [2.3.0] - 2016-11-16
### Added
- HTML+ supports tags ``img``, ``source`` and ``picture``.
- Agregator templates support local variables from ``data-`` attributes.
- Local document variables from ``data-`` attributes.
- HTML+ ``data-`` attributes support (as in HTML5) in element ``body``.
- Configurable ``body`` attribute to disable balancing (default ``nobalance``).

### Changed
- System messages redesigned containing icons in default scheme.
- Logo tag in breadcrumb changed from ``object`` to ``img``.
- Redirection 303 when similar URL found, else 404 (Not Found).
- Default scheme CSS files split and reorganized.
- Default font-family is ``Roboto`` and ``Font-Awsome``.

## [2.2.6] - 2016-11-13
### Added
- Extension ``woff2`` among allowed extensions.

## [2.2.5] - 2016-11-03
### Fixed
- Docker compatibility.

## [2.2.4] - 2016-10-28
### Fixed
- Admin show only allowed file types.

## [2.2.3] - 2016-10-27
### Fixed
- Validate product APC cache.

## [2.2.2] - 2016-10-27
### Fixed
- Creating product from template.

## [2.2.1] - 2016-10-17
### Fixed
- System messages from previous request types.

## [2.2.0] - 2016-10-16
### Added
- Agregator lists can be filtered by keywords in attribute ``kw``.
- Content balance level configuration.

### Changed
- Secured protocol (HTTPS) configuration moved to server domain.

## [2.0.1] - 2016-10-12
### Fixed
- Plugins ContentBalancer and UrlHandler priority.

## [2.1.0] - 2016-10-10
### Added
- New agregator template attributes: ``path``, ``kw``, ``limit`` and ``skip``.
- Display last valid content if the current content is invalid.
- Global variable ``globalmenu`` generated by GlobalMenu plugin.
- Global variable ``breadcrumb`` generated by BreadCrumb plugin.
- Element ``rewrite`` to modify URL by plugin UrlHandler.
- Inserting attribute ``lang`` into local links if different.
- Default contact form message (used if user message is empty).
- Admin shortcut ``Ctrl+P`` to open file picker dialogue.
- Admin list of available variables with their values.
- EmailBreaker plugin supports inline elements inside processed link.
- Script ``editable.js`` modifies page's ``favicon`` as well as ``title``.

### Changed
- Generate variable ``linklist`` only for documents with ``linklist`` class in ``body`` element.
- Generate document info only into documents with ``docinfo`` class in ``body`` element.
- Generate URL from ``id`` attribute instead of ``link`` in element ``h``.
- Element ``include`` replaced by attribute ``src`` in element ``h``.
- Automatically balance content with two or more subheadings.
- Agregator templates split into ``doclist`` and ``imglist``.
- Group system messages by types.
- Plugin ContentLink split to ContentBalancer, GlobalMenu and BreadCrumb.

### Removed
- Removed attribute ``link`` from element ``h``.
- No active default agregator templates (only commented).
- Removed plugin ContentLink.

### Fixed
- Fixed date translation into Czech language.

## [2.0.1] - 2016-10-09
### Fixed
- CHANGELOG version number.

## [2.0.0] - 2016-10-01
### Added
- Global variable ``linklist`` generated by LinkList plugin.
- New plugin DocInfo to display document meta information.
- Default contact form class ``editable``.
- Admin option to automatically fix repairable errors.

### Changed
- Plugin LinkList creates and displays local variable ``linklist``.
- System messages from previous request are now marked via class.

[Unreleased]: https://bitbucket.org/igwr/cms/compare/dev..master
[2.8.0]: https://bitbucket.org/igwr/cms/compare/v2.8.0..v2.7.2
[2.7.2]: https://bitbucket.org/igwr/cms/compare/v2.7.2..v2.7.1
[2.7.1]: https://bitbucket.org/igwr/cms/compare/v2.7.1..v2.7.0
[2.7.0]: https://bitbucket.org/igwr/cms/compare/v2.7.0..v2.6.2
[2.6.2]: https://bitbucket.org/igwr/cms/compare/v2.6.2..v2.6.1
[2.6.1]: https://bitbucket.org/igwr/cms/compare/v2.6.1..v2.6.0
[2.6.0]: https://bitbucket.org/igwr/cms/compare/v2.6.0..v2.5.6
[2.5.6]: https://bitbucket.org/igwr/cms/compare/v2.5.6..v2.5.5
[2.5.5]: https://bitbucket.org/igwr/cms/compare/v2.5.5..v2.5.4
[2.5.4]: https://bitbucket.org/igwr/cms/compare/v2.5.4..v2.5.3
[2.5.3]: https://bitbucket.org/igwr/cms/compare/v2.5.3..v2.5.2
[2.5.2]: https://bitbucket.org/igwr/cms/compare/v2.5.2..v2.5.1
[2.5.1]: https://bitbucket.org/igwr/cms/compare/v2.5.1..v2.5.0
[2.5.0]: https://bitbucket.org/igwr/cms/compare/v2.5.0..v2.4.11
[2.4.11]: https://bitbucket.org/igwr/cms/compare/v2.4.11..v2.4.10
[2.4.10]: https://bitbucket.org/igwr/cms/compare/v2.4.10..v2.4.9
[2.4.9]: https://bitbucket.org/igwr/cms/compare/v2.4.9..v2.4.8
[2.4.8]: https://bitbucket.org/igwr/cms/compare/v2.4.8..v2.4.7
[2.4.7]: https://bitbucket.org/igwr/cms/compare/v2.4.7..v2.4.6
[2.4.6]: https://bitbucket.org/igwr/cms/compare/v2.4.6..v2.4.5
[2.4.5]: https://bitbucket.org/igwr/cms/compare/v2.4.5..v2.4.4
[2.4.4]: https://bitbucket.org/igwr/cms/compare/v2.4.4..v2.4.3
[2.4.3]: https://bitbucket.org/igwr/cms/compare/v2.4.3..v2.4.2
[2.4.2]: https://bitbucket.org/igwr/cms/compare/v2.4.2..v2.4.1
[2.4.1]: https://bitbucket.org/igwr/cms/compare/v2.4.1..v2.4.0
[2.4.0]: https://bitbucket.org/igwr/cms/compare/v2.4.0..v2.3.8
[2.3.4]: https://bitbucket.org/igwr/cms/compare/v2.3.4..v2.3.3
[2.3.3]: https://bitbucket.org/igwr/cms/compare/v2.3.3..v2.3.2
[2.3.2]: https://bitbucket.org/igwr/cms/compare/v2.3.2..v2.3.1
[2.3.1]: https://bitbucket.org/igwr/cms/compare/v2.3.1..v2.3.0
[2.3.0]: https://bitbucket.org/igwr/cms/compare/v2.3.0..v2.2.6
[2.2.6]: https://bitbucket.org/igwr/cms/compare/v2.2.6..v2.2.5
[2.2.5]: https://bitbucket.org/igwr/cms/compare/v2.2.5..v2.2.4
[2.2.4]: https://bitbucket.org/igwr/cms/compare/v2.2.4..v2.2.3
[2.2.3]: https://bitbucket.org/igwr/cms/compare/v2.2.3..v2.2.2
[2.2.2]: https://bitbucket.org/igwr/cms/compare/v2.2.2..v2.2.1
[2.2.1]: https://bitbucket.org/igwr/cms/compare/v2.2.1..v2.2.0
[2.2.0]: https://bitbucket.org/igwr/cms/compare/v2.2.0..v2.0.1
[2.0.1]: https://bitbucket.org/igwr/cms/compare/v2.0.1..v2.1.0
[2.1.0]: https://bitbucket.org/igwr/cms/compare/v2.1.0..v2.0.1
[2.0.1]: https://bitbucket.org/igwr/cms/compare/v2.0.1..v2.0.0
[2.0.0]: https://bitbucket.org/igwr/cms/compare/v2.0.0..v1.12.10
