<?php

/**
 * @file
 * Sort an array by key.
 */

/**
 *
 */
function addKeySortFilter(\Twig\Environment &$env, $config) {
  $env->addFilter(new \Twig\TwigFilter('keysort', function ($array) {
    ksort($array);
    return $array;
  }));
}
