const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

class Element {
  constructor(tagName) {
    this.tagName = tagName;
    this.children = [];
    this.parent = null;
    this.className = "";
    this.textContent = "";
    this.rect = { top: 0, height: 50 };
    this.classList = {
      add: (name) => this.toggleClass(name, true),
      remove: (name) => this.toggleClass(name, false),
    };
  }

  set innerHTML(value) {
    assert.fail(`Unexpected HTML sink: ${value}`);
  }

  appendChild(child) {
    child.remove();
    child.parent = this;
    this.children.push(child);
    return child;
  }

  getBoundingClientRect() {
    return this.rect;
  }

  querySelector(tagName) {
    return this.children.find((child) => child.tagName === tagName) || null;
  }

  remove() {
    if (this.parent) {
      const siblings = this.parent.children;
      siblings.splice(siblings.indexOf(this), 1);
      this.parent = null;
    }
  }

  toggleClass(name, enabled) {
    const classes = new Set(this.className.split(/\s+/).filter(Boolean));
    if (enabled) {
      classes.add(name);
    } else {
      classes.delete(name);
    }
    this.className = [...classes].join(" ");
  }
}

const container = new Element("div");
const floatingContainer = new Element("div");
const timers = [];
const jquery = (element) => ({
  toggleClass: (name, enabled) => element.toggleClass(name, enabled),
});
jquery.each = (values, callback) => {
  values.forEach((value, index) => callback(index, value));
};
const context = vm.createContext({
  document: {
    createElement: (tagName) => new Element(tagName),
    getElementById: (id) => {
      assert.equal(id, "banner-container");
      return container;
    },
    querySelector: (selector) => {
      assert.equal(selector, "main > div.container > div.k-container-square");
      return floatingContainer;
    },
  },
  window: { scrollY: 0, innerHeight: 800 },
  setTimeout: (callback, delay) => timers.push({ callback, delay }),
  $: jquery,
});
const source = fs
  .readFileSync(path.join(__dirname, "../assets/scripts/seablast.js"), "utf8")
  .replace(/^export\s*\{[^}]*\};?\s*$/gm, "");
vm.runInContext(
  source + "\nglobalThis.BannerManager = BannerManager;",
  context,
);
const manager = new context.BannerManager();
const payload =
  "<img src=x onerror=\"alert(1)\"><script>alert(2)</script> & 'text'";
const updated = '<svg onload="alert(3)"></svg> &amp; "quoted"';

const banner = manager.addBanner(payload);
assert.equal(banner.parent, container);
assert.equal(banner.className, "banner info");
assert.deepEqual(
  banner.children.map((child) => child.tagName),
  ["span", "button"],
);
const span = banner.querySelector("span");
const button = banner.querySelector("button");
assert.equal(span.textContent, payload);
assert.equal(span.children.length, 0);
assert.equal(button.textContent, "✖");
assert.equal(timers.length, 0);

manager.changeBanner(banner, updated);
assert.equal(span.textContent, updated);
assert.equal(span.children.length, 0);
assert.equal(banner.className, "banner info");
assert.equal(banner.querySelector("button"), button);
manager.changeBanner(banner, payload, "warning");
assert.equal(banner.className, "banner warning");
manager.changeBanner(banner, updated, "info");
assert.equal(banner.className, "banner info");
manager.changeBanner(banner, payload, 'invalid" onclick="alert(4)');
assert.equal(banner.className, "banner warning");
assert.equal(span.textContent, payload);

const second = manager.addBanner("Second", "warning");
const invalid = manager.addBanner("Invalid type", 'invalid" onclick="alert(5)');
assert.equal(second.className, "banner warning");
assert.equal(invalid.className, "banner warning");
button.onclick();
assert.equal(banner.parent, null);
assert.deepEqual(container.children, [second, invalid]);

container.rect = { top: 900, height: 50 };
const floating = manager.addBanner(payload);
assert.equal(floating.parent, floatingContainer);
assert.equal(floating.className, "banner info floating");
assert.equal(floating.querySelector("span").textContent, payload);
assert.equal(timers.length, 1);
assert.equal(timers[0].delay, 1800);
timers[0].callback();
assert.equal(floating.parent, container);
assert.equal(floatingContainer.children.length, 0);
assert.equal(floating.className, "banner info");
floating.querySelector("button").onclick();
assert.deepEqual(container.children, [second, invalid]);

console.log(
  "BannerManager text safety, types, closing, and movement tests passed.",
);
