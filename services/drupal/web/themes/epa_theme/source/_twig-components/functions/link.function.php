<?php

/**
 * @file
 */

/**
 *
 */
function addLinkFunction(\Twig\Environment &$env, $config) {
  $env->addFunction(new \Twig\TwigFunction(
    'link',
    function ($title, $url, $attributes) {
      if (isset($attributes) && isset($attributes['class'])) {
        $classes = join(' ', $attributes['class']);
        return '<a href="' . $url . '" class="' . $classes . '">' . $title . '</a>';
      }
      else {
        return '<a href="' . $url . '">' . $title . '</a>';
      }
    },
    ['is_safe' => ['html']]
  ));
}
