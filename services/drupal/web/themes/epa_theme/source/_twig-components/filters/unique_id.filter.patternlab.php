<?php

/**
 * @file
 * Twig filter to polyfill the unique_id filter for Pattern Lab.
 */

/**
 *
 */
function addUniqueIdFilter(\Twig\Environment &$env, $config) {
  $env->addFilter(new \Twig\TwigFilter('unique_id', function ($string) {
    return $string . '--' . uniqid();
  }));
}
