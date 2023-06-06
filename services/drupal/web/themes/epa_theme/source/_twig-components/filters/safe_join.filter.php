<?php

/**
 * @file
 */

/**
 *
 */
function addSafeJoinFilter(\Twig\Environment &$env, $config) {
  // Drupal Safe Join filter.
  $env->addFilter(new \Twig\TwigFilter('safe_join', function ($string) {
    return $string;
  }));
}
