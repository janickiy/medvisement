/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/classCallCheck.js
function _classCallCheck(instance, Constructor) {
  if (!(instance instanceof Constructor)) {
    throw new TypeError("Cannot call a class as a function");
  }
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/typeof.js
function _typeof(o) {
  "@babel/helpers - typeof";

  return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) {
    return typeof o;
  } : function (o) {
    return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o;
  }, _typeof(o);
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/toPrimitive.js

function _toPrimitive(input, hint) {
  if (_typeof(input) !== "object" || input === null) return input;
  var prim = input[Symbol.toPrimitive];
  if (prim !== undefined) {
    var res = prim.call(input, hint || "default");
    if (_typeof(res) !== "object") return res;
    throw new TypeError("@@toPrimitive must return a primitive value.");
  }
  return (hint === "string" ? String : Number)(input);
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/toPropertyKey.js


function _toPropertyKey(arg) {
  var key = _toPrimitive(arg, "string");
  return _typeof(key) === "symbol" ? key : String(key);
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/createClass.js

function _defineProperties(target, props) {
  for (var i = 0; i < props.length; i++) {
    var descriptor = props[i];
    descriptor.enumerable = descriptor.enumerable || false;
    descriptor.configurable = true;
    if ("value" in descriptor) descriptor.writable = true;
    Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor);
  }
}
function _createClass(Constructor, protoProps, staticProps) {
  if (protoProps) _defineProperties(Constructor.prototype, protoProps);
  if (staticProps) _defineProperties(Constructor, staticProps);
  Object.defineProperty(Constructor, "prototype", {
    writable: false
  });
  return Constructor;
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/setPrototypeOf.js
function _setPrototypeOf(o, p) {
  _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function _setPrototypeOf(o, p) {
    o.__proto__ = p;
    return o;
  };
  return _setPrototypeOf(o, p);
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/inherits.js

function _inherits(subClass, superClass) {
  if (typeof superClass !== "function" && superClass !== null) {
    throw new TypeError("Super expression must either be null or a function");
  }
  subClass.prototype = Object.create(superClass && superClass.prototype, {
    constructor: {
      value: subClass,
      writable: true,
      configurable: true
    }
  });
  Object.defineProperty(subClass, "prototype", {
    writable: false
  });
  if (superClass) _setPrototypeOf(subClass, superClass);
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/assertThisInitialized.js
function _assertThisInitialized(self) {
  if (self === void 0) {
    throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
  }
  return self;
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/possibleConstructorReturn.js


function _possibleConstructorReturn(self, call) {
  if (call && (_typeof(call) === "object" || typeof call === "function")) {
    return call;
  } else if (call !== void 0) {
    throw new TypeError("Derived constructors may only return object or undefined");
  }
  return _assertThisInitialized(self);
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/getPrototypeOf.js
function _getPrototypeOf(o) {
  _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function _getPrototypeOf(o) {
    return o.__proto__ || Object.getPrototypeOf(o);
  };
  return _getPrototypeOf(o);
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/defineProperty.js

function _defineProperty(obj, key, value) {
  key = _toPropertyKey(key);
  if (key in obj) {
    Object.defineProperty(obj, key, {
      value: value,
      enumerable: true,
      configurable: true,
      writable: true
    });
  } else {
    obj[key] = value;
  }
  return obj;
}
;// CONCATENATED MODULE: external "jQuery"
const external_jQuery_namespaceObject = window["jQuery"];
var external_jQuery_default = /*#__PURE__*/__webpack_require__.n(external_jQuery_namespaceObject);
;// CONCATENATED MODULE: ./assets/js/src/globals.js


/**
 * External dependencies
 */

function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }


/* global yith_wcaf yith */

// these constants will be wrapped inside webpack closure, to prevent collisions

var $document = external_jQuery_default()(document),
  $body = external_jQuery_default()('body'),
  block = function block($el) {
    if (typeof (external_jQuery_default()).fn.block === 'undefined') {
      return false;
    }
    try {
      $el.block({
        message: null,
        overlayCSS: {
          background: '#fff',
          opacity: 0.6
        }
      });
      return $el;
    } catch (e) {
      return false;
    }
  },
  unblock = function unblock($el) {
    if (typeof (external_jQuery_default()).fn.unblock === 'undefined') {
      return false;
    }
    try {
      $el.unblock();
    } catch (e) {
      return false;
    }
  },
  globals_confirm = function confirm(title, message, args) {
    return new Promise(function (resolve, reject) {
      var _yith;
      // if can't display modal, accept by default
      if (typeof ((_yith = yith) === null || _yith === void 0 || (_yith = _yith.ui) === null || _yith === void 0 ? void 0 : _yith.confirm) === 'undefined') {
        reject(new Error('Missing yith.ui utilities'));
      }
      var options = _objectSpread({
        title: title || labels.generic_confirm_title,
        message: message || labels.generic_confirm_message
      }, args);
      options.onConfirm = function () {
        return resolve(true);
      };
      options.onCancel = reject;
      yith.ui.confirm(options);
    });
  };
var ajaxUrl, nonces, labels;
if (typeof yith_wcaf !== 'undefined') {
  var _yith_wcaf, _yith_wcaf2, _yith_wcaf3;
  ajaxUrl = (_yith_wcaf = yith_wcaf) === null || _yith_wcaf === void 0 ? void 0 : _yith_wcaf.ajax_url;
  nonces = (_yith_wcaf2 = yith_wcaf) === null || _yith_wcaf2 === void 0 ? void 0 : _yith_wcaf2.nonces;
  labels = (_yith_wcaf3 = yith_wcaf) === null || _yith_wcaf3 === void 0 ? void 0 : _yith_wcaf3.labels;
}

;// CONCATENATED MODULE: ./assets/js/admin/src/modules/confirm.js


/**
 * Internal dependencies
 */

var confirm_initConfirm = function initConfirm($container) {
  var _$container;
  // init container
  if (!((_$container = $container) !== null && _$container !== void 0 && _$container.length)) {
    $container = $document;
  }
  $container.on('click', '.delete', function () {
    var target = external_jQuery_default()(this).attr('href');

    // confirm must be done on dedicated JS.
    if (target === '#' || target === '') {
      return;
    }
    globals_confirm().then(function () {
      window.location.href = target;
    });
    return false;
  });
  $container.on('submit', 'form', function () {
    var $form = external_jQuery_default()(this),
      action = $form.find('[name="action"]').val(),
      confirmed = $form.hasClass('confirmed');
    if (action === 'delete' && !confirmed) {
      globals_confirm().then(function () {
        $form.addClass('confirmed').submit();
      });
      return false;
    }
  });
};

;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/superPropBase.js

function _superPropBase(object, property) {
  while (!Object.prototype.hasOwnProperty.call(object, property)) {
    object = _getPrototypeOf(object);
    if (object === null) break;
  }
  return object;
}
;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/get.js

function _get() {
  if (typeof Reflect !== "undefined" && Reflect.get) {
    _get = Reflect.get.bind();
  } else {
    _get = function _get(target, property, receiver) {
      var base = _superPropBase(target, property);
      if (!base) return;
      var desc = Object.getOwnPropertyDescriptor(base, property);
      if (desc.get) {
        return desc.get.call(arguments.length < 3 ? target : receiver);
      }
      return desc.value;
    };
  }
  return _get.apply(this, arguments);
}
;// CONCATENATED MODULE: ./assets/js/src/modules/dependencies.js


/**
 * Internal dependencies
 */




var YITH_WCAF_Dependencies_Handler = /*#__PURE__*/function () {
  function YITH_WCAF_Dependencies_Handler($container) {
    var _this$$container, _this$$fields;
    _classCallCheck(this, YITH_WCAF_Dependencies_Handler);
    // container
    _defineProperty(this, "$container", void 0);
    // fields;
    _defineProperty(this, "$fields", void 0);
    // dependencies tree.
    _defineProperty(this, "dependencies", {});
    this.$container = $container;
    if (!((_this$$container = this.$container) !== null && _this$$container !== void 0 && _this$$container.length)) {
      return;
    }
    this.initFields();
    if (!((_this$$fields = this.$fields) !== null && _this$$fields !== void 0 && _this$$fields.length)) {
      return;
    }
    this.initDependencies();
  }
  _createClass(YITH_WCAF_Dependencies_Handler, [{
    key: "initFields",
    value: function initFields() {
      this.$fields = this.$container.find(':input');
    }
  }, {
    key: "initDependencies",
    value: function initDependencies() {
      this.buildDependenciesTree();
      if (!Object.keys(this.dependencies).length) {
        return;
      }
      this.handleDependencies();
    }
  }, {
    key: "buildDependenciesTree",
    value: function buildDependenciesTree() {
      var self = this;
      this.$fields.closest('[data-dependencies]').each(function () {
        var $field = external_jQuery_default()(this),
          id = $field.attr('id');
        if (!id) {
          return;
        }
        var newBranch = _defineProperty({}, id, $field.data('dependencies'));
        self.dependencies = external_jQuery_default().extend(self.dependencies, newBranch);
      });

      // backward compatibility with plugin-fw
      this.$container.find('[data-dep-target]').each(function () {
        var $container = external_jQuery_default()(this),
          id = $container.data('dep-id'),
          target = $container.data('dep-target'),
          value = $container.data('dep-value');
        if (!id || !target || !value) {
          return;
        }
        var newBranch = _defineProperty({}, target, _defineProperty({}, id, value.toString().split(',')));
        self.dependencies = external_jQuery_default().extend(self.dependencies, newBranch);
      });
    }
  }, {
    key: "handleDependencies",
    value: function handleDependencies() {
      this.$fields.on('change', this.applyDependencies.bind(this));
      this.applyDependencies();
    }
  }, {
    key: "applyDependencies",
    value: function applyDependencies() {
      var _this = this;
      external_jQuery_default().each(this.dependencies, function (field, conditions) {
        var $container = _this.findFieldContainer(field);
        if (!$container.length) {
          return;
        }
        var show = _this.checkConditions(conditions);
        if (show) {
          $container === null || $container === void 0 || $container.fadeIn();
        } else {
          $container === null || $container === void 0 || $container.hide();
        }
      });
    }
  }, {
    key: "findField",
    value: function findField(field) {
      var $field = this.$container.find("#".concat(field));
      if (!$field.length) {
        $field = this.$container.find("#".concat(field, "_field"));
      }
      if (!$field.length) {
        return false;
      }
      if (!$field.is(':input')) {
        $field = $field.find(':input');
      }
      return $field;
    }
  }, {
    key: "findFieldContainer",
    value: function findFieldContainer(field) {
      var $field = this.findField(field);
      if (!($field !== null && $field !== void 0 && $field.length)) {
        return false;
      }

      // first of all search for container by id.
      var $container = $field.closest("#".concat(field, "_container"));

      // if we couldn't find item with correct id, search for a .form-row
      if (!$container.length) {
        $container = $field.closest('.form-row');
      }

      // finally, just assume closest table row is a valid container
      if (!$container.length) {
        $container = $field.closest('.yith-plugin-fw__panel__option');
      }

      // if none of the previous worked, just fail to false
      if (!$container.length) {
        return false;
      }
      return $container;
    }
  }, {
    key: "checkConditions",
    value: function checkConditions(conditions) {
      var _this2 = this;
      var result = true;
      external_jQuery_default().each(conditions, function (field, condition) {
        var $field = _this2.findField(field);
        var fieldValue;
        if (!result || !($field !== null && $field !== void 0 && $field.length)) {
          return;
        }
        if ($field.first().is('input[type="radio"]')) {
          var _$field$filter;
          fieldValue = (_$field$filter = $field.filter(':checked')) === null || _$field$filter === void 0 || (_$field$filter = _$field$filter.val()) === null || _$field$filter === void 0 ? void 0 : _$field$filter.toString();
        } else {
          var _$field$val;
          fieldValue = $field === null || $field === void 0 || (_$field$val = $field.val()) === null || _$field$val === void 0 ? void 0 : _$field$val.toString();
        }
        if (Array.isArray(condition)) {
          result = condition.includes(fieldValue);
        } else if (typeof condition === 'function') {
          result = condition(fieldValue);
        } else if (condition.indexOf(':') === 0) {
          result = $field.is(condition);
        } else if (condition.indexOf('!:') === 0) {
          result = !$field.is(condition.toString().substring(1));
        } else if (condition.indexOf('!') === 0) {
          result = condition.toString().substring(1) !== fieldValue;
        } else {
          result = condition.toString() === fieldValue;
        }
        if (typeof _this2.dependencies[field] !== 'undefined') {
          result = result && _this2.checkConditions(_this2.dependencies[field]);
        }
      });
      return result;
    }
  }]);
  return YITH_WCAF_Dependencies_Handler;
}();
function initDependencies($container) {
  var _$container;
  // init container
  if (!((_$container = $container) !== null && _$container !== void 0 && _$container.length)) {
    $container = $document;
  }
  return new YITH_WCAF_Dependencies_Handler($container);
}
;// CONCATENATED MODULE: ./assets/js/src/modules/validation.js


/**
 * Internal dependencies
 */




var YITH_WCAF_Validation_Handler = /*#__PURE__*/function () {
  function YITH_WCAF_Validation_Handler($container) {
    var _this$$container;
    _classCallCheck(this, YITH_WCAF_Validation_Handler);
    // container
    _defineProperty(this, "$container", void 0);
    // error class to add/remove to fields wrapper
    _defineProperty(this, "ERROR_CLASS", 'woocommerce-invalid');
    this.$container = $container;
    if (!((_this$$container = this.$container) !== null && _this$$container !== void 0 && _this$$container.length)) {
      return;
    }
    this.initValidation();
  }

  // init validation.
  _createClass(YITH_WCAF_Validation_Handler, [{
    key: "initValidation",
    value: function initValidation() {
      this.initForm();
      this.initFields();
    }
  }, {
    key: "initForm",
    value: function initForm() {
      var $forms = this.$container.is('form') ? this.$container : this.$container.find('form');
      if (!$forms.length) {
        return;
      }
      var self = this;
      $forms.on('submit yith_wcaf_validate_fields', function (ev) {
        var $form = external_jQuery_default()(this),
          res = self.validateForm($form);
        if (!res) {
          ev.stopImmediatePropagation();
          return false;
        }
        return true;
      });
    }
  }, {
    key: "initFields",
    value: function initFields() {
      var $fields = this.getFields(this.$container);
      if (!$fields.length) {
        return;
      }
      var self = this;
      $fields.on('keyup change', function () {
        var $field = external_jQuery_default()(this);
        self.validateField($field);
      });
    }

    // fields handling.
  }, {
    key: "getFieldWrapper",
    value: function getFieldWrapper($field) {
      return $field.closest('.form-row, .yith-plugin-fw-panel-wc-row');
    }
  }, {
    key: "getFields",
    value: function getFields($container) {
      var $fields = external_jQuery_default()('input, select, textarea', $container);
      return $fields.not('input[type="submit"]').not('input[type="hidden"]').not('.select2-search__field');
    }
  }, {
    key: "getVisibleFields",
    value: function getVisibleFields($container) {
      var _this = this;
      var $fields = this.getFields($container);
      return $fields.filter(function (index, field) {
        var $field = external_jQuery_default()(field),
          $fieldWrapper = _this.getFieldWrapper($field);
        return $fieldWrapper.is(':visible');
      });
    }
  }, {
    key: "isFieldValid",
    value: function isFieldValid($field) {
      var $wrapper = this.getFieldWrapper($field),
        fieldType = $field.attr('type'),
        value = $field.val(),
        alwaysRequiredFields = ['reg_username', 'reg_email', 'reg_password'];

      // check for required fields
      if ($field.prop('required') || $wrapper.hasClass('required') || $wrapper.hasClass('validate-required') || $wrapper.hasClass('yith-plugin-fw--required') || alwaysRequiredFields.includes($field.get(0).id)) {
        if (fieldType === 'checkbox' && !$field.is(':checked')) {
          throw 'missing';
        } else if (!value) {
          throw 'missing';
        }
      }

      // check for patterns
      var pattern = $wrapper.data('pattern');
      if (pattern) {
        var regex = new RegExp(pattern);
        if (!regex.test(value)) {
          throw 'malformed';
        }
      }

      // check for min length
      var minLength = $wrapper.data('min_length');
      if (minLength && value.length < minLength) {
        throw 'short';
      }

      // check for max length
      var maxLength = $wrapper.data('max_length');
      if (maxLength && value.length > maxLength) {
        throw 'long';
      }

      // check for number
      if (fieldType === 'number') {
        var min = parseFloat($field.attr('min')),
          max = parseFloat($field.attr('max')),
          numVal = parseFloat(value);
        if (min && min > numVal || max && max < numVal) {
          throw 'overflow';
        }
      }

      // all validation passed; we can return true.
      return true;
    }
  }, {
    key: "validateField",
    value: function validateField($field) {
      try {
        this.isFieldValid($field);
      } catch (e) {
        this.reportError($field, e);
        return false;
      }
      this.removeError($field);
      return true;
    }
  }, {
    key: "validateForm",
    value: function validateForm($form) {
      var $visibleFields = this.getVisibleFields($form);
      if (!$visibleFields.length) {
        return true;
      }
      var self = this;
      var valid = true;
      $visibleFields.each(function () {
        var $field = external_jQuery_default()(this);
        if (!self.validateField($field)) {
          valid = false;
        }
      });
      if (!valid) {
        // scroll top.
        this.scrollToFirstError($form);

        // stop form submitting.
        return false;
      }
      return true;
    }

    // error handling.
  }, {
    key: "getErrorMsg",
    value: function getErrorMsg($field, errorType) {
      var _labels$errors, _labels$errors2, _labels$errors3, _labels$errors4, _labels$errors5;
      // check if we have a field-specific error message.
      var msg = $field.data('error');
      if (msg) {
        return msg;
      }

      // check if message is added to wrapper.
      var $wrapper = this.getFieldWrapper($field);
      msg = $wrapper.data('error');
      if (msg) {
        return msg;
      }

      // check if message is added to label.
      var $label = $wrapper.find('label');
      msg = $label.data('error');
      if (msg) {
        return msg;
      }
      if (!(labels !== null && labels !== void 0 && labels.errors)) {
        return false;
      }
      switch (errorType) {
        case 'missing':
          var fieldType = $field.attr('type');
          msg = fieldType === 'checkbox' ? (_labels$errors = labels.errors) === null || _labels$errors === void 0 ? void 0 : _labels$errors.accept_check : (_labels$errors2 = labels.errors) === null || _labels$errors2 === void 0 ? void 0 : _labels$errors2.compile_field;
          if (msg) {
            return msg;
          }

        // fallthrough if we didn't find a proper message yet.
        default:
          msg = (_labels$errors3 = labels.errors) !== null && _labels$errors3 !== void 0 && _labels$errors3[errorType] ? (_labels$errors4 = labels.errors) === null || _labels$errors4 === void 0 ? void 0 : _labels$errors4[errorType] : (_labels$errors5 = labels.errors) === null || _labels$errors5 === void 0 ? void 0 : _labels$errors5.general_error;
          break;
      }
      return msg;
    }
  }, {
    key: "reportError",
    value: function reportError($field, errorType) {
      var $wrapper = this.getFieldWrapper($field),
        errorMsg = this.getErrorMsg($field, errorType);
      $wrapper.addClass(this.ERROR_CLASS);
      if (!errorMsg) {
        return;
      }

      // remove existing errors.
      $wrapper.find('.error-msg').remove();

      // generate and append new error message.
      var $errorMsg = external_jQuery_default()('<span/>', {
        "class": 'error-msg',
        text: errorMsg
      });
      $wrapper.append($errorMsg);
    }
  }, {
    key: "removeError",
    value: function removeError($field) {
      var $wrapper = this.getFieldWrapper($field),
        $errorMsg = $wrapper.find('.error-msg');
      $wrapper.removeClass(this.ERROR_CLASS);
      $errorMsg.remove();
    }
  }, {
    key: "scrollToFirstError",
    value: function scrollToFirstError($form) {
      var $firstError = $form.find(".".concat(this.ERROR_CLASS)).first();
      if (!$firstError.length) {
        return;
      }
      var $target = this.findScrollableParent($form);
      if (!$target || !$target.length) {
        $target = external_jQuery_default()('html, body');
      }
      var scrollDiff = $firstError.offset().top - $target.offset().top;
      var scrollValue = scrollDiff;
      if (!$target.is('html, body')) {
        scrollValue = $target.get(0).scrollTop + scrollDiff;
      }
      $target.animate({
        scrollTop: scrollValue
      });
    }
  }, {
    key: "findScrollableParent",
    value: function findScrollableParent($node) {
      var node = $node.get(0);
      if (!node) {
        return null;
      }
      var overflowY, isScrollable;
      do {
        if (document === node) {
          return null;
        }
        overflowY = window.getComputedStyle(node).overflowY;
        isScrollable = overflowY !== 'visible' && overflowY !== 'hidden';
      } while (!(isScrollable && node.scrollHeight > node.clientHeight) && (node = node.parentNode));
      return external_jQuery_default()(node);
    }
  }]);
  return YITH_WCAF_Validation_Handler;
}();
function initValidation($container) {
  var _$container;
  // init container
  if (!((_$container = $container) !== null && _$container !== void 0 && _$container.length)) {
    $container = $document;
  }
  return new YITH_WCAF_Validation_Handler($container);
}
;// CONCATENATED MODULE: ./assets/js/src/modules/enhanced-selects.js


/**
 * Internal dependencies
 */

var enhanceSelect = function enhanceSelect(select, args) {
    if (typeof (external_jQuery_default()).fn.selectWoo === 'undefined') {
      return;
    }
    var allowClear = !!select.data('allow_clear'),
      placeholder = select.data('placeholder'),
      minimumInputLength = select.data('minimum_input_length'),
      action = select.data('action'),
      ajax = !!action;
    var config = {
      allowClear: allowClear,
      placeholder: placeholder,
      minimumInputLength: minimumInputLength || 3
    };
    if (ajax) {
      config.ajax = {
        url: ajaxUrl,
        dataType: 'json',
        delay: 250,
        data: function data(params) {
          return {
            term: params.term,
            action: action,
            security: select.data('security'),
            exclude: select.data('exclude'),
            include: select.data('include'),
            limit: select.data('limit')
          };
        },
        processResults: function processResults(data) {
          var terms = [];
          if (data) {
            external_jQuery_default().each(data, function (id, text) {
              terms.push({
                id: id,
                text: text
              });
            });
          }
          return {
            results: terms
          };
        },
        cache: true
      };
    }
    config = external_jQuery_default().extend(config, args || {});
    try {
      select.selectWoo(config).addClass('enhanced');
    } catch (e) {
      // skip to next.
    }
  },
  enhanceSelects = function enhanceSelects($container) {
    var _$container;
    if (typeof (external_jQuery_default()).fn.selectWoo === 'undefined') {
      return;
    }

    // init container
    if (!((_$container = $container) !== null && _$container !== void 0 && _$container.length)) {
      $container = $document;
    }
    var fieldToProcess = external_jQuery_default()('.yith-wcaf-enhanced-select', $container).not('.enhanced');
    fieldToProcess.each(function () {
      var select = external_jQuery_default()(this);
      enhanceSelect(select);
    });
  };

;// CONCATENATED MODULE: ./assets/js/src/modules/enhanced-datepickers.js


/**
 * Internal dependencies
 */

var enhanceDatepicker = function enhanceDatepicker(field) {
    if (typeof (external_jQuery_default()).fn.datepicker === 'undefined') {
      return;
    }
    var format = field.data('format'),
      numberOfMonths = field.data('number-of-months'),
      maxDate = field.data('max-date'),
      minDate = field.data('min-date'),
      altField = field.data('altfield'),
      altFormat = field.data('altformat'),
      config = {
        dateFormat: format || 'yy-mm-dd',
        numberOfMonths: numberOfMonths || 1,
        maxDate: maxDate || null,
        minDate: minDate || null,
        altField: altField ? field.next(altField).get(0) : null,
        altFormat: altFormat || '',
        beforeShow: function beforeShow(input, inst) {
          var _inst$dpDiv;
          inst === null || inst === void 0 || (_inst$dpDiv = inst.dpDiv) === null || _inst$dpDiv === void 0 || (_inst$dpDiv = _inst$dpDiv.addClass('yith-wcaf-datepicker')) === null || _inst$dpDiv === void 0 || _inst$dpDiv.addClass('yith-plugin-fw-datepicker-div');
        },
        onClose: function onClose(input, inst) {
          var _inst$dpDiv2;
          inst === null || inst === void 0 || (_inst$dpDiv2 = inst.dpDiv) === null || _inst$dpDiv2 === void 0 || (_inst$dpDiv2 = _inst$dpDiv2.removeClass('yith-wcaf-datepicker')) === null || _inst$dpDiv2 === void 0 || _inst$dpDiv2.removeClass('yith-plugin-fw-datepicker-div');
        }
      };
    try {
      field.datepicker(config).addClass('enhanced');
    } catch (e) {
      // skip to next field.
    }
  },
  enhanceDatepickers = function enhanceDatepickers($container) {
    var _$container;
    if (typeof (external_jQuery_default()).fn.datepicker === 'undefined') {
      return;
    }

    // init container
    if (!((_$container = $container) !== null && _$container !== void 0 && _$container.length)) {
      $container = $document;
    }
    var fieldToProcess = external_jQuery_default()('.yith-wcaf-enhanced-date-picker', $container).add('.date-picker-field', $container).add('.date-picker', $container).not('.enhanced');
    fieldToProcess.each(function () {
      var field = external_jQuery_default()(this);
      enhanceDatepicker(field);
    });
  };

;// CONCATENATED MODULE: ./assets/js/src/modules/enhanced-accordion.js


/**
 * Internal dependencies
 */

var enhanceAccordion = function enhanceAccordion($field) {
    var $radio = external_jQuery_default()('.accordion-radio', $field);
    if (!$radio.length) {
      return;
    }
    $radio.on('change', function () {
      var $checked = $radio.filter(':checked'),
        $option = $checked.closest('.accordion-option'),
        $content = $option.find('.accordion-content');
      external_jQuery_default()('.accordion-content', $field).not($content).slideUp();
      $content.slideDown();
    }).trigger('change');
    $field.addClass('enhanced');
  },
  enhanceAccordions = function enhanceAccordions($container) {
    var _$container;
    // init container
    if (!((_$container = $container) !== null && _$container !== void 0 && _$container.length)) {
      $container = $document;
    }
    var $accordion = external_jQuery_default()('.yith-wcaf-accordion', $container).not('.enhanced');
    if (!$accordion.length) {
      return;
    }
    $accordion.each(function () {
      var $field = external_jQuery_default()(this);
      enhanceAccordion($field);
    });
  };

// export utilities

;// CONCATENATED MODULE: ./assets/js/src/modules/enhanced-uploaders.js


/**
 * Internal dependencies
 */

var enhanceUploader = function enhanceUploader($anchor) {
    var $input = $anchor.parent().find('input[type="file"]');
    if (!$input.length) {
      return;
    }
    $anchor.on('click', function (ev) {
      ev.preventDefault();
      $input.trigger('click');
    }).addClass('enhanced');
    $input.on('change', function (ev) {
      var files = ev.target.files,
        fileNames = [];
      var $fileList = $anchor.next('small.files-list');
      for (var i in files) {
        if (!files.hasOwnProperty(i)) {
          continue;
        }
        fileNames.push(files[i].name);
      }
      if (!fileNames.length) {
        if ($fileList.length) {
          $fileList.remove();
        }
      } else {
        if (!$fileList.length) {
          $fileList = external_jQuery_default()('<small/>', {
            "class": 'files-list'
          });
          $anchor.after($fileList);
        }
        $fileList.text(fileNames.join(', '));
        if ($anchor.hasClass('auto-submit')) {
          var $form = $anchor.closest('form');
          if (!$form.length) {
            return;
          }
          $form.submit();
        }
      }
    });
  },
  enhanceUploaders = function enhanceUploaders($container) {
    var _$container;
    // init container
    if (!((_$container = $container) !== null && _$container !== void 0 && _$container.length)) {
      $container = $document;
    }
    var $uploaders = external_jQuery_default()('.yith-wcaf-attach-file', $container).not('.enhanced');
    if (!$uploaders.length) {
      return;
    }
    $uploaders.each(function () {
      var $field = external_jQuery_default()(this);
      enhanceUploader($field);
    });
  };

// export utilities

;// CONCATENATED MODULE: ./assets/js/admin/src/modules/fields.js


/**
 * Internal dependencies
 */







var fields_initFields = function initFields($container) {
  var _$container;
  // init container
  if (!((_$container = $container) !== null && _$container !== void 0 && _$container.length)) {
    $container = $document;
  }

  // enhance selects
  enhanceSelects($container);

  // enhance datepicker
  enhanceDatepickers($container);

  // enhance toggles
  enhanceAccordions($container);

  // enhance uploader
  enhanceUploaders($container);

  // init dependencies
  initDependencies($container);

  // init validation
  initValidation($container);

  // enhance template fields
  (function () {
    var fieldToProcess = external_jQuery_default()('.yith-wcaf-enhanced-template', $container).not('.enhanced');
    fieldToProcess.each(function () {
      var $template = external_jQuery_default()(this),
        $editor = $template.find('.editor');
      external_jQuery_default()('a.toggle_editor', $template).text(function () {
        var $this = external_jQuery_default()(this);
        $this.text($this.data('view-label'));
      }).on('click', function () {
        var $this = external_jQuery_default()(this),
          editorVisible = $editor.is(':visible');
        $this.text($this.data("".concat(editorVisible ? 'view' : 'hide', "-label")));
        $editor.slideToggle();
        return false;
      });
      external_jQuery_default()('a.delete_template', $template).on('click', function () {
        return window.confirm(external_jQuery_default()(this).data('confirm')); // eslint-disable-line no-alert
      });

      external_jQuery_default()('textarea', $editor).on('change', function () {
        var $this = external_jQuery_default()(this),
          name = $this.attr('data-name');
        if (name) {
          $this.attr('name', name);
        }
      });
      $template.addClass('enhanced');
    });
  })();

  // editable sections
  (function () {
    var $editable = external_jQuery_default()('.editable', $container).not('.enhanced');
    $editable.one('click', function () {
      var $trigger = external_jQuery_default()(this),
        $parent = $trigger.parent(),
        $target = $parent.find('.edit');
      if (!$target) {
        return;
      }
      var $edited = $parent.find('.edited');
      $edited.slideUp();
      $target.slideDown();
      $trigger.removeClass('editable');
    }).addClass('enhanced');
  })();

  // init custom fields postfix
  (function () {
    var $fields = external_jQuery_default()('[data-postfix]', $container).not('.enhanced');
    $fields.each(function () {
      var $field = external_jQuery_default()(this),
        $postfix = external_jQuery_default()('<span/>', {
          "class": 'field-postfix',
          text: $field.data('postfix'),
          style: 'line-height: 2.5; margin-left: 5px;'
        });
      $field.after($postfix);
    });
  })();

  // remove edit prompt when not needed (double $( fn ) is required to perform code after WC's handler)
  external_jQuery_default()(function () {
    // when field is contained in one of these items, onbeforeunload will be ignored
    var exceptions = ['.yith-plugin-fw-list-table-container'];
    external_jQuery_default()('input, textarea, select', $container).on('change', function () {
      var $closest = external_jQuery_default()(this).closest(exceptions.join(', '));
      if (!$closest.length) {
        return;
      }
      window.onbeforeunload = null;
    });
  });
};

// export utilities

;// CONCATENATED MODULE: ./assets/js/admin/src/modules/yith-wcaf-admin-modal.js


/* globals yith */

/**
 * Internal dependencies
 */




function yith_wcaf_admin_modal_ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function yith_wcaf_admin_modal_objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? yith_wcaf_admin_modal_ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : yith_wcaf_admin_modal_ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }


var YITH_WCAF_Admin_Modal = /*#__PURE__*/function () {
  function YITH_WCAF_Admin_Modal($opener, args) {
    var _yith;
    _classCallCheck(this, YITH_WCAF_Admin_Modal);
    // modal opener
    _defineProperty(this, "$opener", null);
    // target of the open event
    _defineProperty(this, "$target", null);
    // modal object
    _defineProperty(this, "modal", null);
    // fields validator
    _defineProperty(this, "validator", null);
    // template variables.
    _defineProperty(this, "args", null);
    if (!($opener !== null && $opener !== void 0 && $opener.length) || typeof ((_yith = yith) === null || _yith === void 0 || (_yith = _yith.ui) === null || _yith === void 0 ? void 0 : _yith.modal) === 'undefined') {
      return;
    }
    this.$opener = $opener;
    this.args = external_jQuery_default().extend({
      title: '',
      content: false,
      footer: false,
      showClose: true,
      width: 500,
      onCreate: false,
      onClose: false,
      shouldOpen: null,
      template: false,
      titleTemplate: false,
      data: false,
      classes: {
        wrap: 'yith-wcaf-modal'
      }
    }, args || {});
    this.init();
  }

  // setters
  _createClass(YITH_WCAF_Admin_Modal, [{
    key: "addOpeners",
    value: function addOpeners($newOpeners) {
      this.$opener = this.$opener.add($newOpeners);
      this.reinit();
    }

    // init methods
  }, {
    key: "init",
    value: function init() {
      var _this = this;
      this.$opener.on('click', function (ev) {
        _this.$target = external_jQuery_default()(ev.target);
        if (!_this.shouldOpen()) {
          return;
        }
        ev.preventDefault();
        _this.onOpen();
      });
    }
  }, {
    key: "reinit",
    value: function reinit() {
      this.$opener.off('click');
      this.init();
    }
  }, {
    key: "initFields",
    value: function initFields() {
      var $fields = this.getFields();

      // init fields values.
      $fields.filter('[data-value]').each(function () {
        var $field = external_jQuery_default()(this),
          value = $field.data('value');
        if ($field.is('input[type="checkbox"]') || $field.is('input[type="radio"]')) {
          if (typeof value === 'boolean') {
            $field.prop('checked', value);
          } else if (value) {
            $field.prop('checked', value === $field.val());
          } else {
            $field.prop('checked', false);
          }
        } else if ($field.is('select') && Array.isArray(value)) {
          $field.val(value);
        } else if ($field.is('select') && _typeof(value) === 'object') {
          for (var i in value) {
            var _$field$find;
            if (!((_$field$find = $field.find("[value=\"".concat(i, "\"]"))) !== null && _$field$find !== void 0 && _$field$find.length)) {
              $field.append(external_jQuery_default()('<option/>', {
                value: i,
                text: value[i]
              }));
            }
          }
          $field.val(Object.keys(value));
        } else if (typeof value === 'boolean') {
          $field.val(value ? 1 : 0);
        } else if (value) {
          $field.val(String(value));
        }
        $field.trigger('change');
      });

      // init custom fields handling
      fields_initFields(this.modal.elements.content);
    }
  }, {
    key: "initSubmit",
    value: function initSubmit() {
      var $content = this.modal.elements.content,
        $form = $content.find('form');
      $form.on('submit', this.onSubmit.bind(this));
    }

    // events handling
  }, {
    key: "getData",
    value: function getData() {
      var _this$args;
      var data = ((_this$args = this.args) === null || _this$args === void 0 ? void 0 : _this$args.data) || {};
      if (typeof data === 'function') {
        data = data.call(this);
      }
      return data;
    }
  }, {
    key: "shouldOpen",
    value: function shouldOpen() {
      var _this$args2;
      if (typeof ((_this$args2 = this.args) === null || _this$args2 === void 0 ? void 0 : _this$args2.shouldOpen) === 'function') {
        return this.args.shouldOpen.call(this);
      }
      return true;
    }
  }, {
    key: "onOpen",
    value: function onOpen() {
      var args = yith_wcaf_admin_modal_objectSpread({}, this.args),
        template = (args === null || args === void 0 ? void 0 : args.template) || '',
        titleTemplate = (args === null || args === void 0 ? void 0 : args.titleTemplate) || '',
        data = this.getData();
      if (template) {
        args.content = wp.template(template)(data);
      } else if (typeof (args === null || args === void 0 ? void 0 : args.content) === 'function') {
        args.content = args.content.call(this, data);
      }
      if (titleTemplate) {
        args.title = wp.template(titleTemplate)(data);
      } else if (typeof (args === null || args === void 0 ? void 0 : args.title) === 'function') {
        args.title = args.title.call(this, data);
      }
      this.modal = yith.ui.modal(args);

      // init enhanced selects
      external_jQuery_default()(document.body).trigger('wc-enhanced-select-init');

      // trigger additional functionalities
      this.initFields();

      // trigger submit handling.
      this.initSubmit();
    }
  }, {
    key: "beforeSubmit",
    value: function beforeSubmit() {
      return true;
    }
  }, {
    key: "onSubmit",
    value: function onSubmit() {
      var _this$args3;
      if (!this.beforeSubmit()) {
        return false;
      }
      if (typeof ((_this$args3 = this.args) === null || _this$args3 === void 0 ? void 0 : _this$args3.onSubmitSuccess) === 'function') {
        var _this$args4;
        (_this$args4 = this.args) === null || _this$args4 === void 0 || _this$args4.onSubmitSuccess();
      }
    }

    // fields handling
  }, {
    key: "getField",
    value: function getField(fieldName) {
      return this.getFields().filter("[name=\"".concat(fieldName, "\"]")).first();
    }
  }, {
    key: "getFields",
    value: function getFields() {
      var $content = this.modal.elements.content;
      return $content.find(':input').not('button, input[type="submit"], .select2-search__field');
    }
  }, {
    key: "serialize",
    value: function serialize() {
      var $fields = this.getFields();
      var data = {};
      $fields.get().reduce(function (a, field) {
        var $field = external_jQuery_default()(field);
        var value;
        if ($field.is('input[type="checkbox"]')) {
          if (!$field.is(':checked')) {
            return a;
          }
          value = 1;
        } else {
          value = $field.val();
        }
        var name = $field.attr('name');
        if (name.indexOf('[') !== -1) {
          // if name is composite, try to recreate missing structure
          var components = name.split('[').map(function (c) {
              return c.replace(/[\[, \]]/g, '');
            }),
            firstComponent = components.shift(),
            newItem = components.reverse().reduce(function (res, key) {
              return _defineProperty({}, key, res);
            }, value);
          if (typeof data[firstComponent] === 'undefined') {
            data[firstComponent] = newItem;
          } else {
            data[firstComponent] = external_jQuery_default().extend(true, data[firstComponent], newItem);
          }
        } else {
          // else simply append value to result object
          data[name] = value;
        }
        return a;
      }, data);
      return data;
    }

    // modal level errors
  }, {
    key: "showErrorMessage",
    value: function showErrorMessage(message) {
      var $content = this.modal.elements.content,
        $error = external_jQuery_default()('<div/>', {
          "class": 'error-message form-row',
          text: message
        });
      this.hideErrorMessage();
      $content.prepend($error);
      $error.get(0).scrollIntoView();
    }
  }, {
    key: "hideErrorMessage",
    value: function hideErrorMessage() {
      var $content = this.modal.elements.content;
      $content.children('.error-message').remove();
    }
  }]);
  return YITH_WCAF_Admin_Modal;
}();

;// CONCATENATED MODULE: ./assets/js/admin/src/modules/yith-wcaf-admin-tabs.js


/**
 * Internal dependencies
 */




var YITH_WCAF_Admin_Tabs = /*#__PURE__*/function () {
  function YITH_WCAF_Admin_Tabs($container) {
    _classCallCheck(this, YITH_WCAF_Admin_Tabs);
    // container
    _defineProperty(this, "$container", null);
    // tab anchors
    _defineProperty(this, "$tabAnchors", null);
    // tabs
    _defineProperty(this, "$tabs", null);
    if (!($container !== null && $container !== void 0 && $container.length)) {
      return;
    }
    this.$container = $container;
    this.initTabs();
  }
  _createClass(YITH_WCAF_Admin_Tabs, [{
    key: "initTabs",
    value: function initTabs() {
      var self = this,
        $wrapper = this.$container;
      this.$tabs = $wrapper.find('.tabs').find('.tab');
      this.$tabAnchors = $wrapper.find('.tab-anchors').find('a.tab-anchor');
      this.$tabAnchors.on('click', function (ev) {
        var tabAnchor = external_jQuery_default()(this);
        ev.preventDefault();
        self.onTabChange(tabAnchor.data('tab'));
      });
      this.onTabChange(this.$tabAnchors.first().data('tab'));
    }
  }, {
    key: "onTabChange",
    value: function onTabChange(tab) {
      var $tab = this.$tabs.filter("#".concat(tab));
      if (!$tab.length) {
        return false;
      }
      var $tabAnchor = this.$tabAnchors.filter("[data-tab=\"".concat(tab, "\"]")),
        $modeField = this.$container.find('#mode');
      if ($tabAnchor.length) {
        this.$tabAnchors.removeClass('active');
        $tabAnchor.addClass('active');
      }
      this.$tabs.removeClass('active');
      $tab.addClass('active');
      if ($modeField !== null && $modeField !== void 0 && $modeField.length) {
        $modeField.val(tab);
      }
    }
  }]);
  return YITH_WCAF_Admin_Tabs;
}();

;// CONCATENATED MODULE: ./assets/js/admin/src/modules/yith-wcaf-admin-tabbed-modal.js


/**
 * Internal dependencies
 */








function yith_wcaf_admin_tabbed_modal_ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function yith_wcaf_admin_tabbed_modal_objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? yith_wcaf_admin_tabbed_modal_ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : yith_wcaf_admin_tabbed_modal_ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _createSuper(Derived) { var hasNativeReflectConstruct = _isNativeReflectConstruct(); return function _createSuperInternal() { var Super = _getPrototypeOf(Derived), result; if (hasNativeReflectConstruct) { var NewTarget = _getPrototypeOf(this).constructor; result = Reflect.construct(Super, arguments, NewTarget); } else { result = Super.apply(this, arguments); } return _possibleConstructorReturn(this, result); }; }
function _isNativeReflectConstruct() { if (typeof Reflect === "undefined" || !Reflect.construct) return false; if (Reflect.construct.sham) return false; if (typeof Proxy === "function") return true; try { Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); return true; } catch (e) { return false; } }


var YITH_WCAF_Admin_Tabbed_Modal = /*#__PURE__*/function (_YITH_WCAF_Admin_Moda) {
  _inherits(YITH_WCAF_Admin_Tabbed_Modal, _YITH_WCAF_Admin_Moda);
  var _super = _createSuper(YITH_WCAF_Admin_Tabbed_Modal);
  function YITH_WCAF_Admin_Tabbed_Modal($opener, args) {
    var _this;
    _classCallCheck(this, YITH_WCAF_Admin_Tabbed_Modal);
    _this = _super.call(this, $opener, yith_wcaf_admin_tabbed_modal_objectSpread({
      classes: {
        wrap: 'yith-wcaf-modal',
        main: 'tabbed-modal tabbed-content'
      }
    }, args));
    // tabs
    _defineProperty(_assertThisInitialized(_this), "$tabs", null);
    return _this;
  }
  _createClass(YITH_WCAF_Admin_Tabbed_Modal, [{
    key: "onOpen",
    value: function onOpen() {
      _get(_getPrototypeOf(YITH_WCAF_Admin_Tabbed_Modal.prototype), "onOpen", this).call(this);
      this.tab = new YITH_WCAF_Admin_Tabs(this.modal.elements.wrap);
    }
  }]);
  return YITH_WCAF_Admin_Tabbed_Modal;
}(YITH_WCAF_Admin_Modal);

;// CONCATENATED MODULE: ./assets/js/src/modules/ajax.js


/**
 * Internal dependencies
 */

function ajax_ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function ajax_objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ajax_ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ajax_ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }

var request = function request(method, action, security, params, args) {
    // retrieve wrapper as current context.
    var $wrapper = external_jQuery_default()(this);
    if (params instanceof FormData) {
      params.append('action', "yith_wcaf_".concat(action));
      params.append('security', nonces !== null && nonces !== void 0 && nonces[security] ? nonces === null || nonces === void 0 ? void 0 : nonces[security] : security);
    } else {
      params = ajax_objectSpread({
        action: "yith_wcaf_".concat(action),
        security: nonces !== null && nonces !== void 0 && nonces[security] ? nonces === null || nonces === void 0 ? void 0 : nonces[security] : security
      }, params);
    }
    var ajaxArgs = ajax_objectSpread({
      url: ajaxUrl,
      data: params,
      method: method,
      beforeSend: function beforeSend() {
        return $wrapper.length && block($wrapper);
      },
      complete: function complete() {
        return $wrapper.length && unblock($wrapper);
      }
    }, args);
    return external_jQuery_default().ajax(ajaxArgs);
  },
  get = function get() {
    for (var _len = arguments.length, params = new Array(_len), _key = 0; _key < _len; _key++) {
      params[_key] = arguments[_key];
    }
    return request.call.apply(request, [this, 'get'].concat(params));
  },
  post = function post() {
    for (var _len2 = arguments.length, params = new Array(_len2), _key2 = 0; _key2 < _len2; _key2++) {
      params[_key2] = arguments[_key2];
    }
    return request.call.apply(request, [this, 'post'].concat(params));
  };
/* harmony default export */ const ajax = ({
  request: request,
  get: get,
  post: post
});
;// CONCATENATED MODULE: ./assets/js/admin/src/modules/yith-wcaf-add-affiliate-modal.js


/**
 * Internal dependencies
 */






function yith_wcaf_add_affiliate_modal_createSuper(Derived) { var hasNativeReflectConstruct = yith_wcaf_add_affiliate_modal_isNativeReflectConstruct(); return function _createSuperInternal() { var Super = _getPrototypeOf(Derived), result; if (hasNativeReflectConstruct) { var NewTarget = _getPrototypeOf(this).constructor; result = Reflect.construct(Super, arguments, NewTarget); } else { result = Super.apply(this, arguments); } return _possibleConstructorReturn(this, result); }; }
function yith_wcaf_add_affiliate_modal_isNativeReflectConstruct() { if (typeof Reflect === "undefined" || !Reflect.construct) return false; if (Reflect.construct.sham) return false; if (typeof Proxy === "function") return true; try { Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); return true; } catch (e) { return false; } }



var YITH_WCAF_Add_Affiliate_Modal = /*#__PURE__*/function (_YITH_WCAF_Admin_Tabb) {
  _inherits(YITH_WCAF_Add_Affiliate_Modal, _YITH_WCAF_Admin_Tabb);
  var _super = yith_wcaf_add_affiliate_modal_createSuper(YITH_WCAF_Add_Affiliate_Modal);
  function YITH_WCAF_Add_Affiliate_Modal($opener) {
    _classCallCheck(this, YITH_WCAF_Add_Affiliate_Modal);
    return _super.call(this, $opener, {
      template: 'yith-wcaf-add-affiliate-modal',
      titleTemplate: 'yith-wcaf-add-affiliate-modal-title',
      data: {},
      width: 400
    });
  }
  _createClass(YITH_WCAF_Add_Affiliate_Modal, [{
    key: "initPswForm",
    value: function initPswForm() {
      var _this = this;
      var $wrapper = this.modal.elements.wrap,
        $generatePswButton = $wrapper.find('.wp-generate-pw'),
        $pswForm = $wrapper.find('.wp-pwd'),
        $pswField = $pswForm.find('#password'),
        $cancelButton = $pswForm.find('.wp-cancel-pw'),
        $customPswField = $pswForm.find('#use_custom_password');
      $generatePswButton.on('click', function () {
        if (!$pswForm.is(':visible')) {
          $pswForm.addClass('is-open');
          $customPswField.val(1);
          _this.onPswToggle(true);
        } else {
          block($generatePswButton);
          wp.ajax.post('generate-password').done(function (data) {
            $pswField.val(data);
            _this.onPswToggle(true);
            unblock($generatePswButton);
          });
        }
      });
      $cancelButton.on('click', function () {
        $pswForm.removeClass('is-open');
        $customPswField.val(0);
      });
    }
  }, {
    key: "initPswToggles",
    value: function initPswToggles() {
      var $wrapper = this.modal.elements.wrap,
        $pswForm = $wrapper.find('.wp-pwd'),
        $toggleButtons = $pswForm.find('.wp-toggle-pw');
      $toggleButtons.on('click', this.onPswToggle.bind(this));
      this.onPswToggle(true);
    }
  }, {
    key: "onOpen",
    value: function onOpen() {
      _get(_getPrototypeOf(YITH_WCAF_Add_Affiliate_Modal.prototype), "onOpen", this).call(this);
      this.initPswForm();
      this.initPswToggles();
    }
  }, {
    key: "onPswToggle",
    value: function onPswToggle(show) {
      var $wrapper = this.modal.elements.wrap,
        $pswForm = $wrapper.find('.wp-pwd'),
        $pswField = $pswForm.find('#password'),
        $toggleButtons = $pswForm.find('.wp-toggle-pw'),
        toggle = typeof show === 'boolean' ? show : $pswField.attr('type') === 'password';
      if (toggle) {
        $pswField.attr('type', 'text');
        $toggleButtons.filter('.wp-hide-pw').show();
        $toggleButtons.filter('.wp-show-pw').hide();
      } else {
        $pswField.attr('type', 'password');
        $toggleButtons.filter('.wp-show-pw').show();
        $toggleButtons.filter('.wp-hide-pw').hide();
      }
    }
  }, {
    key: "onSubmit",
    value: function onSubmit() {
      var _this2 = this;
      var $content = this.modal.elements.content;
      if (!this.beforeSubmit()) {
        return false;
      }
      this.hideErrorMessage();
      var affiliate = this.serialize();
      ajax.post.call($content, 'create_affiliate', 'create_affiliate', affiliate).done(function (data) {
        var _this2$args, _data$data;
        if (typeof ((_this2$args = _this2.args) === null || _this2$args === void 0 ? void 0 : _this2$args.onSubmitSuccess) === 'function') {
          var _this2$args2;
          (_this2$args2 = _this2.args) === null || _this2$args2 === void 0 || _this2$args2.onSubmitSuccess(data === null || data === void 0 ? void 0 : data.data);
        }
        if (data !== null && data !== void 0 && data.success) {
          window.location.reload();
        } else if (data !== null && data !== void 0 && (_data$data = data.data) !== null && _data$data !== void 0 && _data$data.message) {
          _this2.showErrorMessage(data.data.message);
        }
      });
      return false;
    }
  }]);
  return YITH_WCAF_Add_Affiliate_Modal;
}(YITH_WCAF_Admin_Tabbed_Modal);

;// CONCATENATED MODULE: ./assets/js/admin/src/modules/yith-wcaf-admin-info-modal.js


/**
 * Internal dependencies
 */





function yith_wcaf_admin_info_modal_createSuper(Derived) { var hasNativeReflectConstruct = yith_wcaf_admin_info_modal_isNativeReflectConstruct(); return function _createSuperInternal() { var Super = _getPrototypeOf(Derived), result; if (hasNativeReflectConstruct) { var NewTarget = _getPrototypeOf(this).constructor; result = Reflect.construct(Super, arguments, NewTarget); } else { result = Super.apply(this, arguments); } return _possibleConstructorReturn(this, result); }; }
function yith_wcaf_admin_info_modal_isNativeReflectConstruct() { if (typeof Reflect === "undefined" || !Reflect.construct) return false; if (Reflect.construct.sham) return false; if (typeof Proxy === "function") return true; try { Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); return true; } catch (e) { return false; } }


var YITH_WCAF_Admin_Info_Modal = /*#__PURE__*/function (_YITH_WCAF_Admin_Moda) {
  _inherits(YITH_WCAF_Admin_Info_Modal, _YITH_WCAF_Admin_Moda);
  var _super = yith_wcaf_admin_info_modal_createSuper(YITH_WCAF_Admin_Info_Modal);
  function YITH_WCAF_Admin_Info_Modal() {
    _classCallCheck(this, YITH_WCAF_Admin_Info_Modal);
    return _super.apply(this, arguments);
  }
  _createClass(YITH_WCAF_Admin_Info_Modal, [{
    key: "onSubmit",
    value: function onSubmit(ev) {
      var $target = this.$target,
        $content = this.modal.elements.content,
        $wrapper = this.modal.elements.wrap,
        $form = $wrapper.find('form');
      if (!$form.length) {
        return;
      }

      // prevent default loading
      ev.preventDefault();

      // block content
      block($content);
      var $targetForm = $target.closest('form');

      // check for supported $opener types
      if ($target.is('a')) {
        var href = this.$target.attr('href'),
          url = new URL(href),
          query = url.searchParams;

        // build new query string
        $form.find(':input').each(function () {
          var $field = external_jQuery_default()(this),
            name = $field.attr('name');
          if (!name) {
            return;
          }
          query.append(name, $field.val());
        });

        // set new query string to destination url
        url.search = query;

        // redirect to destination url
        window.location = url.href;
      } else if ($target.is('input[type="submit"]') && $targetForm.length) {
        // append current form content to target form content
        $form.find(':input').each(function () {
          var $field = external_jQuery_default()(this),
            name = $field.attr('name');
          if (!name) {
            return;
          }
          var $newField = external_jQuery_default()('<input>', {
            type: 'hidden',
            name: name,
            value: $field.val()
          });
          $targetForm.append($newField).submit();
        });
      }
    }
  }]);
  return YITH_WCAF_Admin_Info_Modal;
}(YITH_WCAF_Admin_Modal);

;// CONCATENATED MODULE: ./node_modules/@babel/runtime/helpers/esm/asyncToGenerator.js
function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) {
  try {
    var info = gen[key](arg);
    var value = info.value;
  } catch (error) {
    reject(error);
    return;
  }
  if (info.done) {
    resolve(value);
  } else {
    Promise.resolve(value).then(_next, _throw);
  }
}
function _asyncToGenerator(fn) {
  return function () {
    var self = this,
      args = arguments;
    return new Promise(function (resolve, reject) {
      var gen = fn.apply(self, args);
      function _next(value) {
        asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value);
      }
      function _throw(err) {
        asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err);
      }
      _next(undefined);
    });
  };
}
;// CONCATENATED MODULE: external "regeneratorRuntime"
const external_regeneratorRuntime_namespaceObject = window["regeneratorRuntime"];
var external_regeneratorRuntime_default = /*#__PURE__*/__webpack_require__.n(external_regeneratorRuntime_namespaceObject);
;// CONCATENATED MODULE: ./assets/js/src/modules/yith-wcaf-copy-button.js


/* global navigator */

/**
 * Internal dependencies
 */




var YITH_WCAF_Copy_Button = /*#__PURE__*/function () {
  function YITH_WCAF_Copy_Button($trigger, $target) {
    _classCallCheck(this, YITH_WCAF_Copy_Button);
    // copy button
    _defineProperty(this, "$trigger", void 0);
    // event initiator
    _defineProperty(this, "$initiator", void 0);
    // target whose content should be copied
    _defineProperty(this, "target", void 0);
    if (!$trigger.length) {
      return;
    }
    this.$trigger = $trigger;
    this.target = $target;
    this.init();
  }
  _createClass(YITH_WCAF_Copy_Button, [{
    key: "init",
    value: function init() {
      this.$trigger.off('click').on('click', this.onClick.bind(this));
    }
  }, {
    key: "onClick",
    value: function onClick(ev) {
      ev.preventDefault();
      this.$initiator = external_jQuery_default()(ev.target);
      if (this.copyContent(ev)) {
        this.outputNotification();
      }
    }
  }, {
    key: "copyContent",
    value: function copyContent(ev) {
      var target = this.target;
      var $target;
      if (typeof target === 'function') {
        $target = target(this.$trigger, ev);
      } else {
        $target = target;
      }
      if (!$target.length) {
        return false;
      }
      if ($target.is('input')) {
        this.copyInputContent($target);
      } else {
        this.copyAnyContent($target);
      }
      return true;
    }
  }, {
    key: "copyInputContent",
    value: function copyInputContent($target) {
      var _navigator;
      if (window.isSecureContext && (_navigator = navigator) !== null && _navigator !== void 0 && _navigator.clipboard) {
        return navigator.clipboard.writeText($target.val());
      }
      this.selectContent($target);
      document.execCommand('copy');
    }
  }, {
    key: "copyAnyContent",
    value: function copyAnyContent($target) {
      var _navigator2;
      if (window.isSecureContext && (_navigator2 = navigator) !== null && _navigator2 !== void 0 && _navigator2.clipboard) {
        return navigator.clipboard.writeText($target.text());
      }
      var $hidden = external_jQuery_default()('<input/>', {
        val: $target.text(),
        type: 'text'
      });
      $body.append($hidden);
      this.selectContent($hidden);
      document.execCommand('copy');
      $hidden.remove();
    }
  }, {
    key: "selectContent",
    value: function selectContent($target) {
      if (this.isIos()) {
        $target.get(0).setSelectionRange(0, 9999);
      } else {
        $target.select();
      }
    }
  }, {
    key: "outputNotification",
    value: function outputNotification() {
      if (!$document.triggerHandler('yith_wcaf_hide_link_copied_alert') && labels !== null && labels !== void 0 && labels.link_copied_message) {
        var $confirmBubble = external_jQuery_default()('<span/>', {
          "class": 'copy-confirmation',
          text: labels.link_copied_message
        });
        $confirmBubble.prependTo(this.$initiator).fadeIn(300, function () {
          setTimeout(function () {
            $confirmBubble.fadeOut(300, function () {
              $confirmBubble.remove();
            });
          }, 1000);
        });
      }
    }
  }, {
    key: "isIos",
    value: function isIos() {
      return navigator.userAgent.match(/ipad|iphone/i);
    }
  }]);
  return YITH_WCAF_Copy_Button;
}();

;// CONCATENATED MODULE: ./assets/js/src/modules/yith-wcaf-link-generator.js


/**
 * Internal dependencies
 */








var YITH_WCAF_Link_Generator = /*#__PURE__*/function () {
  function YITH_WCAF_Link_Generator($container) {
    _classCallCheck(this, YITH_WCAF_Link_Generator);
    // container
    _defineProperty(this, "$container", void 0);
    // source input
    _defineProperty(this, "$source", void 0);
    // username input, if any
    _defineProperty(this, "$username", void 0);
    // destination input
    _defineProperty(this, "$destination", void 0);
    // affiliate token
    _defineProperty(this, "token", void 0);
    // query string for token
    _defineProperty(this, "token_var", void 0);
    // affiliate id, if any
    _defineProperty(this, "affiliate", void 0);
    // timeout used for debouncing
    _defineProperty(this, "timeout", void 0);
    // timout interval for debouncing
    _defineProperty(this, "timeoutInterval", 500);
    if (!($container !== null && $container !== void 0 && $container.length)) {
      return;
    }
    this.$container = $container;
    this.token = this.$container.data('token');
    this.token_var = this.$container.data('token-var');
    this.affiliate = this.$container.data('affiliate');
    this.init();
  }
  _createClass(YITH_WCAF_Link_Generator, [{
    key: "init",
    value: function init() {
      this.$source = external_jQuery_default()('.origin-url', this.$container);
      this.$destination = external_jQuery_default()('.generated-url', this.$container);
      this.$username = external_jQuery_default()('.username', this.$container);
      if (!this.$source.length || !this.$destination.length) {
        return;
      }
      this.initActions();
      this.initCopyButton();
    }
  }, {
    key: "initActions",
    value: function initActions() {
      this.$source.on('change keyup', this.onChange.bind(this)).trigger('change');
    }
  }, {
    key: "initCopyButton",
    value: function initCopyButton() {
      this.$container.find('.copy-trigger').each(function () {
        var $trigger = external_jQuery_default()(this),
          $target = $trigger.parent().find('.copy-target');
        if (!$target.length) {
          return;
        }
        new YITH_WCAF_Copy_Button($trigger, $target);
      });
    }
  }, {
    key: "onChange",
    value: function onChange() {
      // debounce
      if (this.timeout) {
        clearTimeout(this.timeout);
      }
      if (!this.$source.val()) {
        return;
      }
      this.timeout = setTimeout(this.updateUrl.bind(this), this.timeoutInterval);
    }
  }, {
    key: "updateUrl",
    value: function () {
      var _updateUrl = _asyncToGenerator( /*#__PURE__*/external_regeneratorRuntime_default().mark(function _callee() {
        var base, url, user, affiliate;
        return external_regeneratorRuntime_default().wrap(function _callee$(_context) {
          while (1) switch (_context.prev = _context.next) {
            case 0:
              base = this.$source.val();
              if (!(this.token_var && this.token)) {
                _context.next = 5;
                break;
              }
              url = this.calculateUrl(base, this.token_var, this.token);
              _context.next = 9;
              break;
            case 5:
              user = this.$username.val(), affiliate = this.affiliate;
              _context.next = 8;
              return this.requestUrl(base, user, affiliate);
            case 8:
              url = _context.sent;
            case 9:
              this.$destination.val(url);
            case 10:
            case "end":
              return _context.stop();
          }
        }, _callee, this);
      }));
      function updateUrl() {
        return _updateUrl.apply(this, arguments);
      }
      return updateUrl;
    }()
  }, {
    key: "calculateUrl",
    value: function calculateUrl(base, token_var, token) {
      var url;
      try {
        url = new URL(base);

        // if passed url's origin is different from current one, return.
        if (url.origin !== window.location.origin) {
          return '';
        }

        // append referral param.
        url.searchParams.set(token_var, token);
      } catch (e) {
        return '';
      }
      return url.toString();
    }
  }, {
    key: "requestUrl",
    value: function requestUrl(base, user, affiliate_id) {
      var _this = this;
      return new Promise(function (resolve) {
        ajax.get.call(_this.$container, 'get_referral_url', 'get_referral_url', {
          base: base,
          user: user,
          affiliate_id: affiliate_id
        }).done(function (data) {
          var url;
          if (data !== null && data !== void 0 && data.success) {
            var _data$data;
            url = data === null || data === void 0 || (_data$data = data.data) === null || _data$data === void 0 ? void 0 : _data$data.url;
          } else {
            url = '';
          }
          resolve(url);
        }).fail(function () {
          return resolve('');
        });
      });
    }
  }]);
  return YITH_WCAF_Link_Generator;
}();

;// CONCATENATED MODULE: ./assets/js/admin/src/modules/yith-wcaf-admin-panel.js


/**
 * Internal dependencies
 */





var YITH_WCAF_Admin_Panel = /*#__PURE__*/function () {
  function YITH_WCAF_Admin_Panel($container) {
    _classCallCheck(this, YITH_WCAF_Admin_Panel);
    // container object.
    _defineProperty(this, "$container", void 0);
    this.$container = $container;
    this.initFields();
    this.initBulkAction();
  }
  _createClass(YITH_WCAF_Admin_Panel, [{
    key: "initFields",
    value: function initFields() {
      fields_initFields(this.$container);
    }
  }, {
    key: "initBulkAction",
    value: function initBulkAction() {
      var $bulkActionSelect = external_jQuery_default()('#bulk-action-selector-top', this.$container);
      if (!$bulkActionSelect.length) {
        return;
      }
      enhanceSelect($bulkActionSelect, {
        minimumResultsForSearch: Infinity,
        minimumInputLength: 0
      });
    }
  }]);
  return YITH_WCAF_Admin_Panel;
}();

;// CONCATENATED MODULE: ./assets/js/admin/src/affiliates.js


/* global yith_wcaf */

/**
 * Internal dependencies
 */





function affiliates_createSuper(Derived) { var hasNativeReflectConstruct = affiliates_isNativeReflectConstruct(); return function _createSuperInternal() { var Super = _getPrototypeOf(Derived), result; if (hasNativeReflectConstruct) { var NewTarget = _getPrototypeOf(this).constructor; result = Reflect.construct(Super, arguments, NewTarget); } else { result = Super.apply(this, arguments); } return _possibleConstructorReturn(this, result); }; }
function affiliates_isNativeReflectConstruct() { if (typeof Reflect === "undefined" || !Reflect.construct) return false; if (Reflect.construct.sham) return false; if (typeof Proxy === "function") return true; try { Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); return true; } catch (e) { return false; } }







var YITH_WCAF_Affiliates = /*#__PURE__*/function (_YITH_WCAF_Admin_Pane) {
  _inherits(YITH_WCAF_Affiliates, _YITH_WCAF_Admin_Pane);
  var _super = affiliates_createSuper(YITH_WCAF_Affiliates);
  function YITH_WCAF_Affiliates() {
    var _this;
    _classCallCheck(this, YITH_WCAF_Affiliates);
    var $containers = external_jQuery_default()('#yith_wcaf_panel_affiliates').add('#yith_wcaf_panel_affiliates-list');
    _this = _super.call(this, $containers);

    // list.
    _this.initConfirm();
    _this.initAddModal();
    _this.initMessageModals();

    // details.
    _this.initTabs();
    _this.initLinkGenerator();
    return _this;
  }
  _createClass(YITH_WCAF_Affiliates, [{
    key: "initConfirm",
    value: function initConfirm() {
      confirm_initConfirm();
    }
  }, {
    key: "initAddModal",
    value: function initAddModal() {
      var $modalOpener = external_jQuery_default()('.' + 'yith-add-button');
      if (!$modalOpener.length) {
        return;
      }
      new YITH_WCAF_Add_Affiliate_Modal($modalOpener);
    }
  }, {
    key: "initMessageModals",
    value: function initMessageModals() {
      var _yith_wcaf, _yith_wcaf2;
      var $context = this.$container;
      if (!$context.length) {
        return;
      }
      var $disableAnchor = external_jQuery_default()('.disable', $context).add('#doaction', $context),
        $banAnchor = external_jQuery_default()('.ban', $context).add('#doaction', $context),
        getModalData = function getModalData() {
          return function () {
            var $opener = this.$target,
              $row = $opener.closest('tr.affiliate-item');
            var data = {
              affiliates: []
            };
            if ($row.length) {
              // single affiliate; use full name
              data.affiliates.push({
                fullName: $row.data('full_name')
              });
            } else {
              // many affiliates; use count
              var $selected = external_jQuery_default()('.item-selector').filter(':checked');
              $selected.get().forEach(function (selected) {
                var $selecetdRow = external_jQuery_default()(selected).closest('tr');
                data.affiliates.push({
                  fullName: $selecetdRow.data('full_name')
                });
              });
            }
            return data;
          };
        },
        shouldOpenModal = function shouldOpenModal(modal) {
          return function () {
            var $opener = this.$target;
            if ($opener.hasClass(modal)) {
              return true;
            } else if ($opener.is('#doaction')) {
              var $bulkActionsSelect = $opener.siblings('[name="action"]');
              if ($bulkActionsSelect.length) {
                var bulkAction = $bulkActionsSelect.val();
                return modal === bulkAction;
              }
            }
            return false;
          };
        };
      if ($banAnchor.length && !((_yith_wcaf = yith_wcaf) !== null && _yith_wcaf !== void 0 && _yith_wcaf.ban_global_message_enabled)) {
        new YITH_WCAF_Admin_Info_Modal($banAnchor, {
          title: labels === null || labels === void 0 ? void 0 : labels.ban_modal_title,
          template: 'yith-wcaf-ban-affiliate-modal',
          data: getModalData('ban'),
          shouldOpen: shouldOpenModal('ban'),
          width: 350
        });
      }
      if ($disableAnchor.length && !((_yith_wcaf2 = yith_wcaf) !== null && _yith_wcaf2 !== void 0 && _yith_wcaf2.reject_global_message_enabled)) {
        new YITH_WCAF_Admin_Info_Modal($disableAnchor, {
          title: labels === null || labels === void 0 ? void 0 : labels.reject_modal_title,
          template: 'yith-wcaf-disable-affiliate-modal',
          data: getModalData('disable'),
          shouldOpen: shouldOpenModal('disable'),
          width: 350
        });
      }
    }
  }, {
    key: "initTabs",
    value: function initTabs() {
      var $tabWrapper = external_jQuery_default()('#affiliate_related_items');
      if (!$tabWrapper.length) {
        return;
      }
      new YITH_WCAF_Admin_Tabs($tabWrapper);
    }
  }, {
    key: "initLinkGenerator",
    value: function initLinkGenerator() {
      var $linkGenerator = external_jQuery_default()('#affiliate_url');
      if (!$linkGenerator.length) {
        return;
      }
      new YITH_WCAF_Link_Generator($linkGenerator);
    }
  }]);
  return YITH_WCAF_Affiliates;
}(YITH_WCAF_Admin_Panel);
external_jQuery_default()(function () {
  return new YITH_WCAF_Affiliates();
});
var __webpack_export_target__ = window;
for(var i in __webpack_exports__) __webpack_export_target__[i] = __webpack_exports__[i];
if(__webpack_exports__.__esModule) Object.defineProperty(__webpack_export_target__, "__esModule", { value: true });
/******/ })()
;
//# sourceMappingURL=yith-wcaf-affiliates.bundle.js.map