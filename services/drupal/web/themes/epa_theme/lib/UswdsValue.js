// @ts-check

const { parseMap } = require('yaml/util');

/**
 * Class to represent a token that represents a USWDS token. This class acts as
 * a signifier that the token should first be looked up and then passed through
 * a USWDS sass function. The internal representation uses two slots:
 * 1. `value`, which is a reference to another token (or a string or numeric
 *     value, but in that case just use the SassValue class).
 * 2. `fn`, the USWDS function used to look up the token, e.g. units.
 *
 * A value of this class can be constructed in the design tokens file by using
 * the `!uswds` tag. There are two accepted syntaxes:
 * 1. Single-value shorthand. The units() function will be used as a default.
 *
 *    ```yaml
 *    foo: !uswds uswds.column-gap-desktop
 *    ```
 *
 * 2. Object notation. This uses a map with `value` and `function` keys. The
 *    `value` key is used as a parameter in the `function` in the resulting
 *    SCSS
 *
 *    ```yaml
 *    foo: !uswds
 *      value: uswds.color-base
 *      fn: 'color'
 *    ```
 */
class UswdsValue {
  /**
   * @param value The name of a USWDS token.
   * @param [fn] The Sass function to use to look up the value.
   */
  constructor(value, fn = 'units') {
    this.value = value;
    this.fn = fn;
  }

  /**
   * Get the current value.
   * @return {mixed}
   */
  getValue() {
    return this.value;
  }

  /**
   * Set the value.
   * @param {mixed} newVal
   */
  setValue(newVal) {
    this.value = newVal;
  }
}

// YAML custom tag for the UswdsValue class
const tag = {
  identify: value => value instanceof UswdsValue,
  tag: '!uswds',
  resolve(doc, cst) {
    // This is a two-value YAML document: { value, fn }.
    // Deserialize into a full UswdsValue instance.
    if (cst.type === 'MAP') {
      const map = parseMap(doc, cst);
      return new UswdsValue(map.get('value'), map.get('fn'));
    }

    return new UswdsValue(cst.strValue);
  },
};

UswdsValue.tag = tag;

module.exports = UswdsValue;
