# TODO

All planned changes to this project are documented in this file.

## Features

- 240111, Messages from server and Ajax to user friendly closable banners; how to localize these messages - by some l10n?
- 240119, APP_MAPPING restrictToGroups (actually roles) 1-admin, 2-editor(contentAdmin), 3-user, other can be added in the app (use constants instead of magic numbers)

## UX

- 231207, nice 404 (adapt redirection.latte to special operational pages layout)
- 2402010004: Mit.js mit.css with sb const update by sbView

## CSS style

- 251022, fix assets/seablast.css to use kebab-case and then allow again CSS validation

```txt
     43:12  ✖  Expected keyframe name "floatBanner" to be kebab-case       keyframes-name-pattern
     91:21  ✖  Expected id selector "#overlayTextarea" to be kebab-case    selector-id-pattern
    103:4   ✖  Expected class selector ".buttonPanel" to be kebab-case     selector-class-pattern
    118:7   ✖  Expected class selector ".button--toggle" to be kebab-case  selector-class-pattern
    119:25  ✖  Expected class selector ".button--toggle" to be kebab-case  selector-class-pattern
    130:7   ✖  Expected class selector ".button--cancel" to be kebab-case  selector-class-pattern
    134:7   ✖  Expected class selector ".button--delete" to be kebab-case  selector-class-pattern
    138:7   ✖  Expected class selector ".button--edit" to be kebab-case    selector-class-pattern
    142:7   ✖  Expected class selector ".button--save" to be kebab-case    selector-class-pattern
```
