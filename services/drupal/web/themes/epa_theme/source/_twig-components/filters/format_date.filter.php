<?php

/**
 * @file
 */

/**
 *
 */
function addFormatDateFilter(\Twig\Environment &$env, $config) {
  $env->addFilter(new \Twig\TwigFilter('format_date', function ($string) {
    return $string;
  }));
}
