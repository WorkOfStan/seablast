# TODO
All planned changes to this project are documented in this file.

## Features
- 240111, Messages from server and Ajax to user friendly closable banners; how to localize these messages - by some l10n?
- 240119, APP_MAPPING restrictToGroups 1-admin, 2-contentAdmin, 3-user, other can be added in the app; check an open source logging library https://github.com/hybridauth/hybridauth

## UX
- 231207, nice 404 (adapt redirection.latte to special operational pages layout)
- 231229, UI for db administration
- 240112, title variable to layout latte; mit.css and mit.js

## Governance
- 231128, replace `.github/linters/*.yml` and `.github/workflows/*.yml` by Seablast versions, probably `WorkOfStan/Seablast-github/.github/workflows/`
- 231206, cut Latte out of the core Seablast to be used as Seablast/render-latte
- 231206, either add PHPUnit tests or remove Test from composer.json
