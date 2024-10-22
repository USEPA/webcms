<?php

/**
 * @file
 * Add "get_domain" function for Pattern Lab.
 */

/**
 *
 */
function addGetDomainFunction(\Twig\Environment &$env, $config) {
  $env->addFunction(new \Twig\TwigFunction('get_domain', function () {
    // return $_ENV['HOSTNAME'];
    $results = [];
    $results['$_ENV'] = $_ENV;
    $results['$_SERVER'] = $_SERVER;
    return $results;
  }));
}
