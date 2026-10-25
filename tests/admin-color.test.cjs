const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const { execFileSync } = require("node:child_process");
const root = path.join(__dirname, "..");
const source = fs
  .readFileSync(path.join(root, "assets/scripts/overlay.js"), "utf8")
  .replace(/^import .*;$/gm, "")
  .replace("export class Overlay", "class Overlay");
const context = vm.createContext({});
vm.runInContext(
  source + "\nglobalThis.normalize = Overlay.prototype.normalizeHexColor;",
  context,
);
for (const value of [
  "",
  null,
  false,
  123,
  "#abc",
  "red",
  " #abcdef",
  "#abcdef\n",
  "#abcdef ",
]) {
  assert.equal(context.normalize(value), null);
}
assert.equal(context.normalize("#ABCDEF"), "#abcdef");
assert.equal(context.normalize("#000000"), "#000000");

vm.runInContext("globalThis.Overlay = Overlay;", context);
const overlay = new context.Overlay({}, {});
const colorElements = ["#ABCDEF", "red", "#123456; color:red"].map((value) => ({
  getAttribute: () => value,
  style: {},
}));
overlay.applyColorFields({
  querySelectorAll(selector) {
    assert.equal(selector, "[data-admin-color]");
    return colorElements;
  },
});
assert.equal(colorElements[0].style.backgroundColor, "#abcdef");
assert.equal(colorElements[1].style.backgroundColor, undefined);
assert.equal(colorElements[2].style.backgroundColor, undefined);

// Render the production template, so the test includes Latte's JavaScript serialization.
const php = String.raw`
require 'vendor/autoload.php';
$c = new Seablast\Seablast\SeablastConfiguration();
$c->setString('SB_APP_ROOT_ABSOLUTE_URL', 'https://example.test');
$c->setInt('SB_WEB_FORCE_ASSET_VERSION', 1);
echo (new Latte\Engine())->renderToString('views/admin.latte', [
 'configuration' => $c, 'csrfToken' => 'test', 'menu' => [], 'content' => [],
 'columns' => ['id', '</script><img src=x onerror=alert(1)>'],
 'editable' => [], 'conditionDetails' => [], 'table' => [],
]);`;
const html = execFileSync(process.env.PHP_BINARY || "php", ["-r", php], {
  cwd: root,
  encoding: "utf8",
  env: { ...process.env, XDEBUG_MODE: "off" },
});
const start = html.indexOf("function addCondition()");
const end = html.indexOf("// Function to remove a condition", start);
assert.ok(start > 0 && end > start);
const groups = [];
function element(tag) {
  return {
    tag,
    children: [],
    appendChild(child) {
      this.children.push(child);
    },
    append(...children) {
      this.children.push(...children);
    },
    set innerHTML(value) {
      throw new Error("Unsafe HTML sink: " + value);
    },
  };
}
const dom = vm.createContext({
  document: { createElement: element },
  conditionContainer: {
    append(group) {
      groups.push(group);
    },
  },
});
vm.runInContext(
  html.slice(start, end) + "\naddCondition(); addCondition();",
  dom,
);
assert.equal(groups.length, 2);
const select = groups[0].children[0];
assert.equal(select.children.length, 3);
assert.equal(
  select.children[2].textContent,
  "</script><img src=x onerror=alert(1)>",
);
assert.equal(select.children[2].value, "1");
assert.equal(groups[0].children[1].className, "condition-rule");
assert.equal(groups[0].children[2].className, "remove-condition");
console.log("Admin color policy and rendered filter DOM regression passed.");
