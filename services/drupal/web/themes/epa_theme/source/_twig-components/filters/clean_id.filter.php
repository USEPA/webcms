<?php

/**
 * @file
 */

/**
 *
 */
function addCleanIdFilter(\Twig\Environment &$env, $config) {
  $env->addFilter(new \Twig\TwigFilter('clean_id', function ($string) {
    return $string;
  }));
}
