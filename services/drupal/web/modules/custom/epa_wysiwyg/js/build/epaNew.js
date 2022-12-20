!function(e,n){"object"==typeof exports&&"object"==typeof module?module.exports=n():"function"==typeof define&&define.amd?define([],n):"object"==typeof exports?exports.CKEditor5=n():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.epaNew=n())}(self,(()=>(()=>{var __webpack_modules__={"./js/ckeditor5_plugins/epaNew/src/epa-new-editing.js":(__unused_webpack_module,__webpack_exports__,__webpack_require__)=>{"use strict";eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (/* binding */ SimpleBoxEditing)\n/* harmony export */ });\n/* harmony import */ var ckeditor5_src_core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ckeditor5/src/core */ \"ckeditor5/src/core.js\");\n/* harmony import */ var ckeditor5_src_widget__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ckeditor5/src/widget */ \"ckeditor5/src/widget.js\");\n/* harmony import */ var _insertepanewtagcommand__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./insertepanewtagcommand */ \"./js/ckeditor5_plugins/epaNew/src/insertepanewtagcommand.js\");\n\n\n\n\n\n\n/**\n * CKEditor 5 plugins do not work directly with the DOM. They are defined as\n * plugin-specific data models that are then converted to markup that\n * is inserted in the DOM.\n *\n * CKEditor 5 internally interacts with simpleBox as this model:\n * <simpleBox>\n *    <simpleBoxTitle></simpleBoxTitle>\n *    <simpleBoxDescription></simpleBoxDescription>\n * </simpleBox>\n *\n * Which is converted for the browser/user as this markup\n * <section class=\"simple-box\">\n *   <h2 class=\"simple-box-title\"></h1>\n *   <div class=\"simple-box-description\"></div>\n * </section>\n *\n * This file has the logic for defining the simpleBox model, and for how it is\n * converted to standard DOM markup.\n */\nclass SimpleBoxEditing extends ckeditor5_src_core__WEBPACK_IMPORTED_MODULE_0__.Plugin {\n  static get requires() {\n    return [ckeditor5_src_widget__WEBPACK_IMPORTED_MODULE_1__.Widget];\n  }\n\n  init() {\n    this._defineSchema();\n    this._defineConverters();\n    this.editor.commands.add(\n      'insertEPANewTag',\n      new _insertepanewtagcommand__WEBPACK_IMPORTED_MODULE_2__[\"default\"](this.editor),\n    );\n  }\n\n  /*\n   * This registers the structure that will be seen by CKEditor 5 as\n   * <epaNew></epaNew>\n   *\n   * The logic in _defineConverters() will determine how this is converted to\n   * markup.\n   */\n  _defineSchema() {\n    // Schemas are registered via the central `editor` object.\n    const schema = this.editor.model.schema;\n\n    schema.register('epaNew', {\n      // Behaves like a self-contained object (e.g. an image).\n      isInline: true,\n      isObject: true,\n      // Allow in places where other blocks are allowed (e.g. directly in the root).\n      allowWhere: '$text',\n      allowAttributes: ['class', 'data-date']\n    });\n\n  }\n\n  /**\n   * Converters determine how CKEditor 5 models are converted into markup and\n   * vice-versa.\n   */\n  _defineConverters() {\n    const  d = new Date();\n    let month = d.getMonth() + 1;\n    let day = d.getDate();\n    let year = d.getFullYear();\n    let date = year + \",\" + month + \",\" + day;\n\n    // Converters are registered via the central editor object.\n    const { conversion } = this.editor;\n\n    // Upcast Converters: determine how existing HTML is interpreted by the\n    // editor. These trigger when an editor instance loads.\n    //\n    // If <ins class=\"new\"> is present in the existing markup\n    // processed by CKEditor, then CKEditor recognizes and loads it as a\n    // <epaNew> model.\n    conversion.for('upcast').elementToElement({\n      model: 'epaNew',\n      view: {\n        name: 'ins',\n        classes: 'new',\n      },\n    });\n\n\n    // Data Downcast Converters: converts stored model data into HTML.\n    // These trigger when content is saved.\n    //\n    // Instances of <simpleBox> are saved as\n    // <section class=\"simple-box\">{{inner content}}</section>.\n    conversion.for('dataDowncast').elementToElement({\n      model: 'epaNew',\n      view: (modelElement, { writer: viewWriter }) => {\n        return viewWriter.createContainerElement('ins', {\n          class: 'new',\n          'data-date': date,\n        });\n      },\n    });\n\n\n    // Editing Downcast Converters. These render the content to the user for\n    // editing, i.e. this determines what gets seen in the editor. These trigger\n    // after the Data Upcast Converters, and are re-triggered any time there\n    // are changes to any of the models' properties.\n    //\n    // Convert the <epaNew> model into a widget in the editor UI.\n    conversion.for('editingDowncast').elementToElement({\n      model: 'epaNew',\n      view: (modelElement, { writer: viewWriter }) => {\n        const newTag = viewWriter.createContainerElement('ins', {\n          class: 'new',\n          'data-date': date,\n        });\n\n        return (0,ckeditor5_src_widget__WEBPACK_IMPORTED_MODULE_1__.toWidget)(newTag, viewWriter);\n      },\n    });\n\n  }\n}\n\n\n//# sourceURL=webpack://CKEditor5.epaNew/./js/ckeditor5_plugins/epaNew/src/epa-new-editing.js?")},"./js/ckeditor5_plugins/epaNew/src/epa-new-ui.js":(__unused_webpack_module,__webpack_exports__,__webpack_require__)=>{"use strict";eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (/* binding */ EPANewUi)\n/* harmony export */ });\n/* harmony import */ var ckeditor5_src_core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ckeditor5/src/core */ \"ckeditor5/src/core.js\");\n/* harmony import */ var ckeditor5_src_ui__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ckeditor5/src/ui */ \"ckeditor5/src/ui.js\");\n/* harmony import */ var _icons_simpleBox_svg__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../../../icons/simpleBox.svg */ \"./icons/simpleBox.svg\");\n\n\n// @TODO: Get .gif or .png files working in webpack to then change the icon out.\n\n\nclass EPANewUi extends ckeditor5_src_core__WEBPACK_IMPORTED_MODULE_0__.Plugin {\n  init() {\n    const editor = this.editor;\n\n    // This will register the simpleBox toolbar button.\n    editor.ui.componentFactory.add('epaNew', (locale) => {\n      const command = editor.commands.get('insertEPANewTag');\n      const buttonView = new ckeditor5_src_ui__WEBPACK_IMPORTED_MODULE_1__.ButtonView(locale);\n\n      // Create the toolbar button.\n      buttonView.set({\n        label: editor.t('New! Icon'),\n        icon: _icons_simpleBox_svg__WEBPACK_IMPORTED_MODULE_2__[\"default\"],\n        tooltip: true,\n      });\n\n      // Bind the state of the button to the command.\n      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');\n\n      // Execute the command when the button is clicked (executed).\n      this.listenTo(buttonView, 'execute', () =>\n        editor.execute('insertEPANewTag'),\n      );\n\n      return buttonView;\n    });\n  }\n}\n\n\n//# sourceURL=webpack://CKEditor5.epaNew/./js/ckeditor5_plugins/epaNew/src/epa-new-ui.js?")},"./js/ckeditor5_plugins/epaNew/src/epa-new.js":(__unused_webpack_module,__webpack_exports__,__webpack_require__)=>{"use strict";eval('__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   "default": () => (/* binding */ EpaNewTag)\n/* harmony export */ });\n/* harmony import */ var _epa_new_editing__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./epa-new-editing */ "./js/ckeditor5_plugins/epaNew/src/epa-new-editing.js");\n/* harmony import */ var _epa_new_ui__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./epa-new-ui */ "./js/ckeditor5_plugins/epaNew/src/epa-new-ui.js");\n/* harmony import */ var ckeditor5_src_core__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ckeditor5/src/core */ "ckeditor5/src/core.js");\n/**\n * @file This is what CKEditor refers to as a master (glue) plugin. Its role is\n * just to load the “editing” and “UI” components of this Plugin. Those\n * components could be included in this file, but\n *\n * I.e, this file\'s purpose is to integrate all the separate parts of the plugin\n * before it\'s made discoverable via index.js.\n */\n\n// The contents of SimpleBoxUI and SimpleBox editing could be included in this\n// file, but it is recommended to separate these concerns in different files.\n\n\n\n\nclass EpaNewTag extends ckeditor5_src_core__WEBPACK_IMPORTED_MODULE_2__.Plugin {\n  // Note that SimpleBoxEditing and SimpleBoxUI also extend `Plugin`, but these\n  // are not seen as individual plugins by CKEditor 5. CKEditor 5 will only\n  // discover the plugins explicitly exported in index.js.\n  static get requires() {\n    return [_epa_new_editing__WEBPACK_IMPORTED_MODULE_0__["default"], _epa_new_ui__WEBPACK_IMPORTED_MODULE_1__["default"]];\n  }\n}\n\n\n//# sourceURL=webpack://CKEditor5.epaNew/./js/ckeditor5_plugins/epaNew/src/epa-new.js?')},"./js/ckeditor5_plugins/epaNew/src/index.js":(__unused_webpack_module,__webpack_exports__,__webpack_require__)=>{"use strict";eval('__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var _epa_new__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./epa-new */ "./js/ckeditor5_plugins/epaNew/src/epa-new.js");\n/**\n * @file The build process always expects an index.js file. Anything exported\n * here will be recognized by CKEditor 5 as an available plugin. Multiple\n * plugins can be exported in this one file.\n *\n * I.e. this file\'s purpose is to make plugin(s) discoverable.\n */\n\n\n\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({\n  EpaNewTag: _epa_new__WEBPACK_IMPORTED_MODULE_0__["default"],\n});\n\n\n//# sourceURL=webpack://CKEditor5.epaNew/./js/ckeditor5_plugins/epaNew/src/index.js?')},"./js/ckeditor5_plugins/epaNew/src/insertepanewtagcommand.js":(__unused_webpack_module,__webpack_exports__,__webpack_require__)=>{"use strict";eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (/* binding */ InsertEpaNewTagCommand)\n/* harmony export */ });\n/* harmony import */ var ckeditor5_src_core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ckeditor5/src/core */ \"ckeditor5/src/core.js\");\n/**\n * @file defines InsertSimpleBoxCommand, which is executed when the simpleBox\n * toolbar button is pressed.\n */\n// cSpell:ignore simpleboxediting\n\n\n\nclass InsertEpaNewTagCommand extends ckeditor5_src_core__WEBPACK_IMPORTED_MODULE_0__.Command {\n  execute() {\n    const { model } = this.editor;\n\n    model.change((writer) => {\n      // Insert <simpleBox>*</simpleBox> at the current selection position\n      // in a way that will result in creating a valid model structure.\n      model.insertContent(createEpaNewTag(writer));\n    });\n  }\n\n  refresh() {\n    const { model } = this.editor;\n    const { selection } = model.document;\n\n    // Determine if the cursor (selection) is in a position where adding a\n    // simpleBox is permitted. This is based on the schema of the model(s)\n    // currently containing the cursor.\n    const allowedIn = model.schema.findAllowedParent(\n      selection.getFirstPosition(),\n      'epaNew',\n    );\n\n    // If the cursor is not in a location where a simpleBox can be added, return\n    // null so the addition doesn't happen.\n    this.isEnabled = allowedIn !== null;\n  }\n}\n\nfunction createEpaNewTag(writer) {\n  // Return the element to be added to the editor.\n  return writer.createElement('epaNew');\n}\n\n\n//# sourceURL=webpack://CKEditor5.epaNew/./js/ckeditor5_plugins/epaNew/src/insertepanewtagcommand.js?")},"./icons/simpleBox.svg":(__unused_webpack_module,__webpack_exports__,__webpack_require__)=>{"use strict";eval('__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ("<svg width=\\"20\\" height=\\"20\\" viewBox=\\"0 0 20 20\\" fill=\\"none\\" xmlns=\\"http://www.w3.org/2000/svg\\"><path fill-rule=\\"evenodd\\" clip-rule=\\"evenodd\\" d=\\"M1.95154 2.84131C1.95154 2.28902 2.39925 1.84131 2.95154 1.84131H17.0484C17.6007 1.84131 18.0484 2.28902 18.0484 2.84131V17.1588C18.0484 17.7111 17.6007 18.1588 17.0484 18.1588H2.95154C2.39925 18.1588 1.95154 17.7111 1.95154 17.1588V2.84131ZM3.5116 8.10129H16.4926V15.3194C16.4926 15.8717 16.0449 16.3194 15.4926 16.3194H4.5116C3.95931 16.3194 3.5116 15.8717 3.5116 15.3194V8.10129ZM4.44415 3.81676C3.89187 3.81676 3.44415 4.26447 3.44415 4.81676V6.35087H16.4316V4.81676C16.4316 4.26447 15.9838 3.81676 15.4316 3.81676H4.44415Z\\" fill=\\"black\\"/></svg>\\n");\n\n//# sourceURL=webpack://CKEditor5.epaNew/./icons/simpleBox.svg?')},"ckeditor5/src/core.js":(module,__unused_webpack_exports,__webpack_require__)=>{eval('module.exports = (__webpack_require__(/*! dll-reference CKEditor5.dll */ "dll-reference CKEditor5.dll"))("./src/core.js");\n\n//# sourceURL=webpack://CKEditor5.epaNew/delegated_./core.js_from_dll-reference_CKEditor5.dll?')},"ckeditor5/src/ui.js":(module,__unused_webpack_exports,__webpack_require__)=>{eval('module.exports = (__webpack_require__(/*! dll-reference CKEditor5.dll */ "dll-reference CKEditor5.dll"))("./src/ui.js");\n\n//# sourceURL=webpack://CKEditor5.epaNew/delegated_./ui.js_from_dll-reference_CKEditor5.dll?')},"ckeditor5/src/widget.js":(module,__unused_webpack_exports,__webpack_require__)=>{eval('module.exports = (__webpack_require__(/*! dll-reference CKEditor5.dll */ "dll-reference CKEditor5.dll"))("./src/widget.js");\n\n//# sourceURL=webpack://CKEditor5.epaNew/delegated_./widget.js_from_dll-reference_CKEditor5.dll?')},"dll-reference CKEditor5.dll":e=>{"use strict";e.exports=CKEditor5.dll}},__webpack_module_cache__={};function __webpack_require__(e){var n=__webpack_module_cache__[e];if(void 0!==n)return n.exports;var r=__webpack_module_cache__[e]={exports:{}};return __webpack_modules__[e](r,r.exports,__webpack_require__),r.exports}__webpack_require__.d=(e,n)=>{for(var r in n)__webpack_require__.o(n,r)&&!__webpack_require__.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:n[r]})},__webpack_require__.o=(e,n)=>Object.prototype.hasOwnProperty.call(e,n),__webpack_require__.r=e=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})};var __webpack_exports__=__webpack_require__("./js/ckeditor5_plugins/epaNew/src/index.js");return __webpack_exports__=__webpack_exports__.default,__webpack_exports__})()));