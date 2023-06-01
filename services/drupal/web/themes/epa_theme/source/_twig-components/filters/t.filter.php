<?php

/**
 * @file
 */

/**
 *
 */
function addTFilter(\Twig\Environment &$env, $config) {
  // Drupal translate filter.
  $env->addFilter(new \Twig\TwigFilter('t', function ($string) {
    return $string;
  }));
}
