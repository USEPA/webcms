# Gesso USWDS

Gesso is a [Sass](http://sass-lang.com/)-based and
[Pattern Lab](https://patternlab.io)
integrated starter theme that outputs accessible HTML5 markup. It uses a
mobile-first responsive approach and leverages
[SMACSS](https://smacss.com/) to
organize styles as outlined in the
[Drupal 8 CSS architecture guidelines](https://www.drupal.org/node/1887918).
This encourages a component-based approach to theming through the creation of
discrete, reusable UI elements. Gesso is heavily integrated with
[Pattern Lab](http://patternlab.io/) and the
[Component Libraries](https://www.drupal.org/project/components) module,
allowing Drupal and Pattern Lab to share the same markup.

For more information, view the
[Gesso USWDS GitHub repo](https://github.com/forumone/gesso-uswds).
To submit bug reports or feature requests, visit the
[Gesso USWDS issue queue](https://github.com/forumone/gesso-uswds/issues).

The United States Web Design System is a set of design guidelines and starting
code for creating digital government services. For more information view the
[USWDS](https://designsystem.digital.gov) project website or the
[USWDS Github repo](https://github.com/uswds/uswds).

## Global Prerequisites

The following packages need to be installed on your system in order to use
Gesso.

- Node version 18 or greater. Long-term stable recommended.
- [npm](https://www.npmjs.com/get-npm)
- [gulp](https://gulpjs.com/docs/en/getting-started/quick-start)

In addition, in order to compile Twig files, Pattern Lab requires that PHP be
available on the command line.

## Installation

1. Place the Gesso USWDS theme in your site’s theme directory. (e.g.,
   themes/guswds) Read documentation on
   [installing themes](https://drupal.org/getting-started/install-contrib/themes)
   for more information.

2. Install the
   [Component Libraries](https://www.drupal.org/project/components) module.
   Since many of the Drupal templates reference twig files inside Pattern Lab
   using Twig namespaces, this module is required for the theme to function.

3. Enable the Gesso Helpers module. This module comes packaged with the theme,
   but must be manually enabled for the theme to function.

4. Optional: Install the
   [Twig Field Value](https://www.drupal.org/project/twig_field_value) module.
   This is not required, but will make working with Twig templates easier.

5. Optional: Install the
   [Twig Tweak](https://www.drupal.org/project/twig_tweak) module.
   This is not required, but will make working with Twig templates easier.

6. Optional: Install the
   [Background Images Formatter](https://www.drupal.org/project/bg_image_formatter) module
   and its Responsive Background Images Formatter submodule. This is not required,
   but will allow you to use images uploaded to Drupal as background images,
   with different image sizes at different breakpoints.

7. Optional: Because Gesso is a starter theme, you may want to rename the
   Gesso directory or copy its contents to a new custom theme directory based
   on the name of your project.

The easiest way to accomplish this is to use
[Drush](https://github.com/drush-ops/drush).
Type `drush help gesso` for more information. If you get an error that the
`gesso` command is not defined, make sure you have enabled the Gesso Helper
module.

If you can’t use Drush, then manually replace all instances of `guswds`
within this directory with a machine-readable name of your choice, including
folder names, filenames, and all occurrences within Drupal theme files.
This custom name must start with a letter and may only contain lowercase
letters, numbers, and underscores.

Edit the .info.yml file and update the theme name and description. You can also
change the screenshot image (images/screenshot.png) shown on the Appearance
admin page.

### Compiling Pattern Lab and Sass

[LibSass](http://sass-lang.com/libsass) is required to compile the Sass into
CSS. Gesso includes Gulp tasks to compile the CSS and generate the compiled
Pattern Lab files and to watch both for changes. To use these tasks, first run
the following NPM command in the theme folder.

```
npm install
```

To initiate the Gulp build tasks that compile the Sass and Pattern Lab files
and watch for changes, run the following command in the theme directory:

```
gulp
```

To initiate the Gulp build tasks only (without watching for changes),
run the following command in the theme directory:

```
gulp build
```

## Accessing Pattern Lab

To access the Pattern Lab instance, append `/pattern-lab/index.html` to your
site URL and theme directory (e.g. http://localhost:8080/themes/gesso/pattern-lab/index.html)
or, if developing locally, just open that index.html file directly in the
browser from your file system.

## Design Tokens

Gesso USWDS uses a configuration file
`source/\_patterns/00-config/config.design-tokens.yml`
to manage the theme’s design tokens and automatically generate the global sass
map for styling and patterns to represent the theme’s design tokens. The default
gulp command will monitor changes in the config and rebuild all necessary
assets. To rebuild the theme assets a single time run `gulp build`

### Design Token Colors

In addition to using hex values for colors, you can also use Sass functions and
provide a fallback hex value to be used in Pattern Lab. This is useful when
pulling in tokens from [USWDS](https://designsystem.digital.gov/).

For example:

```
gesso:
  palette:
    brand:
      blue:
        base: '#0071bc'
        light: !sass
          sass: 'color("blue-40")'
          fallback: '#4f97d1'
```

### Design Token Functions

The following Sass functions can be used to access the tokens defined in
`config.design-tokens.yml`.

**`gesso-box-shadow($shadow)`
Output a shadow value from the box-shadow token list**

example:

```
box-shadow: gesso-box-shadow(1);
```

**`gesso-breakpoint($breakpoint)`
Output a size value from the breakpoints token list**

examples:

```
@include breakpoint(gesso-breakpoint(desktop)) {
  display: flex;
}

@include breakpoint-max(gesso-breakpoint(mobile), true) {
  display: none;
}

@include breakpoint-min-max(gesso-breakpoint(mobile), gesso-breakpoint(tablet), true) {
  display: block;
}
```

**`gesso-brand($color, $variant)`
Output a color value from the palette brand token list**

example:

```
color: gesso-brand(blue, light);
```

**`gesso-color($type, $subtype)`
Output a color value from the colors token list**

example:

```
color: gesso-color(text, primary);
```

**`gesso-constrain($constrain)`
Output a size value from the constrains token list**

example:

```
max-width: gesso-constrain(sm);
```

**`gesso-duration($duration)`
Output a timing value from the transitions duration token list**

example:

```
transition-duration: gesso-duration(short);
```

**`gesso-easing($easing)`
Output an easing value from the transitions ease token list**

example:

```
transition-timing-function: gesso-easing(ease-in-out);
```

**`gesso-font-family($family)`
Output a stack value from the font-family token list**

example:

```
font-family: gesso-font-family(primary);
```

**`gesso-font-size($size)`
Output a size value from the font-size token list**

example (combined with the rem() function to convert to rems):

```
font-size: rem(gesso-font-size(2));
```

**`gesso-font-weight($weight)`
Output a weight value from the font-weight token list**

example:

```
font-weight: gesso-font-weight(semibold);
```

**`gesso-grayscale($color)`
Output a color value from the palette grayscale token list**

example:

```
color: gesso-grayscale(gray-2);
```

**`gesso-line-height($height)`
Output a height value from the line-height token list**

example:

```
line-height: gesso-line-height(tight);
```

**`gesso-spacing($spacing)`
Output a size value from the spacing token list**

example (combined with the rem() function to convert to rems):

```
margin-bottom: rem(gesso-spacing(md));
```

**`gesso-z-index($index)`
Output an index value from the z-index token list**

example:

```
z-index: gesso-z-index(modal);
```

### Design Tokens in JavaScript

The values in Gesso's configuration file are also exported to JavaScript objects
so that the same values can be used in CSS and JS. The JS objects can be found
in `js/src/constants/_GESSO.es6.js`. This file is also rebuilt whenever `gulp`
or `gulp build` is run.

For example, to use a breakpoint in a script:
```
import { BREAKPOINTS } from '../constants/_GESSO.es6';

if (window.matchMedia(`min-width: ${BREAKPOINTS.desktop}`).matches) {
  // Some script that should only run on larger screens.
}
```
This will use the same breakpoint as `breakpoint(gesso-breakpoint(desktop))` in
your Sass.

If your token value is a Sass function, the JavaScript will use the fallback
value, if available. If there is no fallback value, the token will be omitted
from the JavaScript objects. In general, if you want to share a value between
CSS and JavaScript, you should use simple strings or numbers.


## Mixins

### Width Based Media Queries

Gesso uses custom mixins to specify viewport width based media queries:
* `breakpoint`: min-width queries
* `breakpoint-max`: max-width queries
* `breakpoint-min-max`: queries with both a min and max width

Each mixin takes one or two
width parameters, which can be a straight value (e.g., 800px, 40em) or a design
token value called using the `gesso-breakpoint` function (e.g.,
`gesso-breakpoint(tablet-lg)`).  The `breakpoint-max` and `breakpoint-min-max`
mixins can also take an optional parameter to subtract one pixel from the
max-width value, which can be useful when you want your query to go up to the
value but not to include it, such as when using Gesso breakpoint token values.

**`@include breakpoint($width) { // styles }`
Output a min-width based media query.**

examples:

```
@include breakpoint(800px) {
  display: flex;
}

@include breakpoint(gesso-breakpoint(desktop)) {
  display: none;
}
```

**`@include breakpoint-max($width, $subtract_1_from_max) { // styles }`
Output a max-width based media query. The optional $subtract_1_from_max
parameter will subtract 1px from the width value if set to `true`
(default: `false`).**

examples:

```
@include breakpoint-max(900px) {
  display: block;
}

@include breakpoint-max(gesso-breakpoint(mobile), true) {
  display: none;
}
```

**`@include breakpoint-min-max($min-width, $max-width, $subtract_1_from_max)
{ // styles }`
Output a media query with both a min-width and max-width. The optional
$subtract_1_from_max parameter will subtract 1px from the max-width value if
set to `true` (default: `false`).**

examples:

```
@include breakpoint-min-max(400px, 700px) {
  display: flex;
}

@include breakpoint-min-max(gesso-breakpoint(mobile), gesso-breakpoint(tablet), true) {
  display: block;
}
```

### Container queries

Gesso uses custom mixins to specify container queries:

- `container-query`: min-width container queries
- `container-query-max`: max-width container queries
- `container-query-min-max`: container queries with both a min and max width

Each mixin takes one or two width parameters, which can be a straight value
(e.g., 800px, 40em) or a design token value called using the `gesso-breakpoint`
function (e.g., `gesso-breakpoint(tablet-lg)`). The `container-max` and
`container-min-max` mixins can also take an optional parameter to subtract one
pixel from the max-width value, which can be useful when you want your query to
go up to the value but not to include it, such as when using Gesso breakpoint
token values.

In order for container queries to work, you need to set a containment context
on a parent element.

```scss
container-type: inline-size;
```

```scss
container: container-name / inline-size;
```

#### `@include container-query($width) { // styles }`

Output a min-width based media query.

```scss
@include container-query(800px) {
  display: flex;
}

@include container-query(gesso-breakpoint(desktop)) {
  display: none;
}
```

#### `@include container-query-max($width, $subtract_1_from_max) { // styles }`

Output a max-width based container query. The optional `$subtract_1_from_max`
parameter will subtract 1px from the width value if set to `true` (default:
`false`).

```scss
@include container-query-max(900px) {
  display: block;
}

@include container-query-max(gesso-breakpoint(mobile), true) {
  display: none;
}
```

#### `@include container-query-min-max($min-width, $max-width, $subtract_1_from_max) { // styles }`

Output a container query with both a min-width and max-width. The optional
$subtract_1_from_max parameter will subtract 1px from the max-width value if
set to `true` (default: `false`).

```scss
@include container-query-min-max(400px, 700px) {
  display: flex;
}

@include container-query-min-max(
  gesso-breakpoint(mobile),
  gesso-breakpoint(tablet),
  true
) {
  display: block;
}
```

## Creating New Components

Gesso includes a script to generate new component files. To use, run the
following command in the theme folder.

```
npm run component
```

## Build Artifacts

By default, the compiled Pattern Lab and Sass files (e.g., /pattern-lab/
and /css/\*.css) as well as some configuration artifacts (e.g.,
design-tokens.artifact.yml, \_design-tokens.artifact.scss)
are ignored by Git as these files are generated
when the Gulp tasks run. To change this, edit the included `.gitignore` file.

## Sass/Gulp dependencies

- [Sass](http://sass-lang.com): CSS on steroids. Adds nested rules, variables,
  mixins, selector inheritance, and more.

- [Autoprefixer](https://github.com/postcss/autoprefixer): Adds necessary
  browser CSS property prefixes during Sass compilation.

## Linting

### Stylelint

[Stylelint](https://stylelint.io/) is used to lint Sass files. Warnings will
break the build, so if you have a valid reason to break Stylelint rules you can
have it ignore code in two ways:

1. Add `// stylelint-disable-next-line` to the line just before where the
   Stylelint warning is triggered.

2. To ignore several lines, add `// stylelint-disable` before the code in
   question and add `// stylelint-enable` afterwards.

The Stylelint rules can be changed in the `.stylelintrc.yml` file. By default,
Gesso follows the [sass-guideline.es](https://github.com/bjankord/stylelint-config-sass-guidelines)
and [Prettier's recommended guidelines](https://github.com/prettier/stylelint-config-prettier),
with some additional customizations.

### ESLint

[ESLint](https://eslint.org/) is used to lint JavaScript files. If you have a
valid reason to break one of the rules, you can ignore a specific line using
any of the options in the [ESLint documentation](https://eslint.org/docs/user-guide/configuring#disabling-rules-with-inline-comments).

The ESLint config can be changed in the `.eslintrc.js` file. Gesso follows the
[ESLint recommended](https://eslint.org/docs/rules/) rules and [Prettier's
recommended rules](https://github.com/prettier/eslint-plugin-prettier) with
some additional customizations.

### Prettier

[Prettier](https://prettier.io/) is used by both ESLint and Stylelint to enforce
code style consistency. The Prettier config can be changed in the `.prettierrc`
file.

[Prettier plugins are available for many code editors](https://prettier.io/docs/en/editors.html). If your editor is
supported, we recommended installing the plugin to more easily reformat your
code.

## JavaScript

See the README.md file in the /js directory for details on included scripts
(e.g., mobile menu, primary menu, etc.).

## Contributing

Please use the Github issue queue (https://github.com/forumone/gesso-uswds/issues) for
discussion, bug reports, feature requests, etc.

Submitted pull requests should be against the release candidate branch, `4.x-RC`.

## Maintainers

The Gesso USWDS theme is maintained by [Dan Mouyard](https://drupal.org/u/dcmouyard)
([@dcmouyard](http://twitter.com/dcmouyard)),
[Corey Lafferty](https://drupal.org/u/clafferty)
([@coreylafferty](http://twitter.com/coreylafferty)), and
[KJ Monahan](https://drupal.org/u/kmonahan).
