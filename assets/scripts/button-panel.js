"use strict";
/* global $ */
/**
 * ButtonPanel component
 *
 * Usage:
 *     const buttonPanel = new ButtonPanel();
 *     // ... add other buttons ...
 *     // Finally, add the cancel button which will always be last
 *     buttonPanel.addCancelButton($overlayDiv);
 *     // ... append buttonPanel.element to the DOM ...
 * Note: any instantiation of the ButtonPanel class or calls to its methods that interact with the DOM should be done within a $(document).ready() handler.
 *
 * TODO: if there's just one button, show it automatically instead of the "☰" button
 *
 * @class
 * @constructor
 * @public
 * @param {string} additionalClasses separated by a space
 */
export class ButtonPanel {
  constructor(additionalClasses = "") {
    this.$element = $("<div>").addClass(
      "buttonPanel" + (additionalClasses ? " " + additionalClasses : ""),
    );
    // Toggle menu
    this.addButton("☰", "responsive button--toggle", function () {
      // setTimeout seems to be ignored by Android Chrome
      setTimeout(() => {
        // 'this' refers to the 'this' value from the surrounding scope
        // Code to execute after 700ms so that user doesn't click the button appering below
        $(this).closest(".buttonPanel").toggleClass("dont-hide");
      }, 700);
    });
  }

  /**
   *
   * @param {string} text
   * @param {string} classes
   * @param {?function} onClick
   * @param {?string} hotkey
   */
  addButton(text, classes = "", onClick = null, hotkey = null) {
    const $button = $("<button>", {
      text,
      class: classes,
      "data-hotkey": hotkey,
    });

    if (onClick && typeof onClick === "function") {
      $button.click(onClick);
    }

    this.$element.append($button);
  }

  /**
   * Remove element relevant for the ButtonPanel instance
   * @param {JQuery} $overlayDiv
   */
  addCancelButton($overlayDiv) {
    const $buttonClose = $("<button>", {
      class: "responsive button--cancel",
      text: "✖ Zrušit",
      "data-hotkey": "Escape",
    }).click(function () {
      $overlayDiv.remove();
    });

    this.$element.append($buttonClose);
  }

  get element() {
    return this.$element;
  }
}
