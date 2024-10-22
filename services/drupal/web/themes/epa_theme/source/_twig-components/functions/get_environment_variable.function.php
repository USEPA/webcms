<?php

/**
 * @file
 * Add "get_environment_variable" function for Pattern Lab.
 */

/**
 *
 */
function addGetEnvironmentVariableFunction(\Twig\Environment &$env, $config) {
  $env->addFunction(new \Twig\TwigFunction('get_environment_variable', function ($varname) {
    // return $_ENV[$varname];
    $results = [];
    $results['$_ENV'] = $_ENV;
    $results['$_SERVER'] = $_SERVER;
    return $results;
  }));
}
