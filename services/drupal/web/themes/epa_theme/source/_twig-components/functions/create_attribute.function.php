<?php

/**
 * @file
 * Add "create_attribute" function for Pattern Lab.
 */

/**
 *
 */
function addCreateAttributeFunction(\Twig_Environment &$env, $config) {
  $function = new Twig_SimpleFunction(
    'create_attribute',
    function ($attributes = []) {
      foreach ($attributes as $key => $value) {
        if (!is_array($value)) {
          $value = [$value];
        }
        print ' ' . $key . '="' . join(' ', $value) . '"';
      }
    },
    ['is_safe' => ['html']]
  );
  $env->addFunction($function);
}
