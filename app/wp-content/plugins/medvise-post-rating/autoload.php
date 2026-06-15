<?php

namespace MedvisementPostRating;

class Autoload
{

    private $prefix = 'MedvisementPostRating';
    private $basedir = WP_CONTENT_DIR . '/plugins/medvise-post-rating/inc';

    public function __construct()
    {
        spl_autoload_register([$this, 'autoload']);
    }

    private function autoload(string $class): void
    {
        if (0 === strpos($class, $this->prefix)) {
            $plugin_parts = explode('\\', $class);

            //exclude prefix
            array_shift($plugin_parts);

            if (empty($plugin_parts)) {
                return;
            }

            $name = array_pop($plugin_parts);
            $name = preg_match('/^(Interface|Trait)/', $name)
                ? $name . '.php'
                : 'class-' . $name . '.php';

            $path = implode('/', $plugin_parts) . '/' . $name;
            $path = str_replace(['\\', '_'], ['/', '-'], $path);
            $path = preg_replace('/([a-z])([A-Z])/', '$1-$2', $path);
            $path = strtolower($path);
            $path = $this->basedir . $path;

            require_once $path;
        }
    }

}